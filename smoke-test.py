"""Integration test against a running deployment. Uses a separate cookie session."""
import json, re, sys, urllib.request, urllib.error, http.cookiejar, base64
base = (sys.argv[1] if len(sys.argv) > 1 else 'http://localhost:15001').rstrip('/')
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
state = None

def request(path, body=None, content_type='application/json'):
    if isinstance(body, dict): body = json.dumps(body).encode()
    if isinstance(body, str): body = body.encode()
    req = urllib.request.Request(base+path, data=body, headers={'Content-Type':content_type})
    try:
        with client.open(req, timeout=20) as response: return response.status, response.read()
    except urllib.error.HTTPError as e: return e.code, e.read()

def action(action_name, **kwargs):
    global state
    code, raw = request('/api/game.php', dict(action=action_name, **kwargs))
    result = json.loads(raw)
    state = result.get('state', state)
    return code, result

def save():
    code, raw = request('/api/save-load.php?action=save')
    assert code == 200
    decoded = base64.b64decode(raw, validate=True)
    assert decoded.startswith(b'O:7:"Trainer":')
    assert base64.b64encode(decoded) == raw
    return raw

def load(raw):
    global state
    boundary = 'arena-smoke-test'
    body = (f'--{boundary}\r\nContent-Disposition: form-data; name="save"; filename="arena.sav"\r\nContent-Type: application/octet-stream\r\n\r\n'.encode()
            + raw + f'\r\n--{boundary}--\r\n'.encode())
    code, raw = request('/api/save-load.php?action=load', body, 'multipart/form-data; boundary='+boundary)
    result = json.loads(raw)
    state = result.get('state',state)
    return code

def fight(enemy):
    assert action('encounter', id=enemy['id'])[0] == 200
    for _ in range(50):
        if not state['current_enemy']: break
        current = state['current_enemy']
        move = {'strike':'guard', 'guard':'break', 'break':'strike'}[current['intent']]
        if state['trainer']['hp'] < 60 and state['potions'] and (current['intent']=='guard' or state['trainer']['hp'] < 30): move='potion'
        assert action('battle_resolve', move=move)[0] == 200
        assert not state['defeat_message'], state['log']
    assert state['current_enemy'] is None

for starter in ['charmander','bulbasaur','squirtle']:
    action('start',name='SmokeTest',starter=starter)
    assert action('advance_stage')[0] == 409
    assert len(state['level1']['encounters']) == 6
    action('encounter',id=state['level1']['encounters'][0]['id'])
    before = json.loads(json.dumps(state))
    checkpoint = save()
    action('battle_resolve',move='strike')
    assert load(checkpoint) == 200
    assert state['current_enemy'] == before['current_enemy']
    assert state['trainer'] == before['trainer']
    assert state['potions'] == before['potions']
    action('battle_run')
    for enemy in state['level1']['encounters']: fight(enemy)
    assert state['pending_stage'] == 2
    pending = save()
    action('advance_stage')
    assert load(pending) == 200 and state['pending_stage'] == 2
    action('advance_stage')
    assert state['potions'] == 4
    enemies = state['level2']['encounters']
    assert len(enemies) == 7 and sum(e['elite'] for e in enemies) == 2
    fight(next(e for e in enemies if not e['elite']))
    assert state['level2']['advantage_wins'] == 0 and state['pending_stage'] == 0
    for enemy in enemies:
        if enemy['elite']: fight(enemy)
    assert state['pending_stage'] == 3
    action('advance_stage')
    checkpoint = save()
    serialized = base64.b64decode(checkpoint, validate=True)
    inflated = re.sub(rb's:6:"damage";i:\d+;', b's:6:"damage";i:1000000;', serialized, count=1)
    assert inflated != serialized
    assert load(base64.b64encode(inflated)) == 200 and state['trainer']['damage'] < 1000000
    action('encounter',id='nullbyte-core')
    assert state['trainer']['hp'] == state['trainer']['damage'] == 1
    action('battle_resolve',move='guard')
    assert state['defeat_message'] and state['stage'] == 1
    assert load(checkpoint) == 200 and state['stage'] == 3 and not state['defeat_message']
    hacked = serialized.replace(b's:9:"technique";s:6:"strike"', b's:9:"technique";s:7:"reflect"', 1)
    assert hacked != serialized and load(base64.b64encode(hacked)) == 200
    action('encounter',id='nullbyte-core')
    battle_save = save()
    assert load(battle_save) == 200
    assert state['trainer']['hp'] == state['trainer']['damage'] == 1
    action('battle_resolve',move='guard')
    assert state['stage'] == 4 and state.get('victory_code')
    # A forged stage must not pass the checkpoint MAC.
    m = re.search(rb's:10:"checkpoint";s:\d+:"([A-Za-z0-9+/=]+)"',serialized)
    decoded = json.loads(base64.b64decode(m[1])); decoded['game']['stage'] = 4
    encoded = base64.b64encode(json.dumps(decoded).encode())
    forged = serialized.replace(m[0], b's:10:"checkpoint";s:'+str(len(encoded)).encode()+b':"'+encoded+b'"')
    assert load(base64.b64encode(forged)) == 422
    valid_state = json.loads(json.dumps(state))
    for invalid in [serialized, b'', b'@@@@', checkpoint[:-1], checkpoint+b'!', base64.b64encode(b'not an object'), b'A'*98308]:
        assert load(invalid) == 422
        assert state['stage'] == valid_state['stage'] and state['trainer'] == valid_state['trainer']
    wrapped = b'\n'.join(checkpoint[i:i+76] for i in range(0, len(checkpoint), 76)) + b'\n'
    assert load(wrapped) == 200 and state['stage'] == 3
    assert load(b'O:7:"Trainer":0:{}') == 422
    print('PASS:', starter, '— battles, checkpoint restore, suppression, puzzle and stage integrity')
print('All checks passed. Test did not modify other players’ sessions.')
