<?php

declare(strict_types=1);

require_once __DIR__ . '/Trainer.php';

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

session_name('cyber_arena');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; media-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
header('Referrer-Policy: no-referrer');

function send_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_data(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : [];
    }

    return $_POST;
}

function clean_name($value): string
{
    $name = trim((string) $value);
    $name = preg_replace('/[^\p{L}\p{N} _.-]/u', '', $name) ?? '';
    $characters = preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY);
    return is_array($characters) ? implode('', array_slice($characters, 0, 20)) : '';
}

function final_flag(): string
{
    $value = trim((string) getenv('FINAL_FLAG'));
    return $value !== '' ? $value : 'CLB_ATTT_DEMO_WINNER';
}

function counter_type(string $playerType): string
{
    return [
        'fire' => 'water',
        'water' => 'grass',
        'grass' => 'fire',
    ][$playerType] ?? 'water';
}

function advantage_type(string $playerType): string
{
    return [
        'fire' => 'grass',
        'water' => 'fire',
        'grass' => 'water',
    ][$playerType] ?? 'grass';
}

function type_profile(string $type): array
{
    $profiles = [
        'fire' => ['name' => 'Charmander', 'image' => 'charmander.png'],
        'water' => ['name' => 'Squirtle', 'image' => 'squirtle.png'],
        'grass' => ['name' => 'Bulbasaur', 'image' => 'bulbasaur.png'],
        'electric' => ['name' => 'Pikachu', 'image' => 'pikachu.png'],
        'glitch' => ['name' => 'NullByte Ω', 'image' => '../materials/bosses/boss2.png'],
    ];

    return $profiles[$type] ?? $profiles['electric'];
}

function make_encounter(
    string $id,
    string $name,
    string $type,
    int $damage,
    string $image,
    float $x,
    float $y,
    bool $boss = false,
    int $debuff = 0
): array {
    return [
        'id' => $id,
        'name' => $name,
        'type' => $type,
        'damage' => $damage,
        'image' => $image,
        'x' => $x,
        'y' => $y,
        'boss' => $boss,
        'debuff' => $debuff,
        'defeated' => false,
    ];
}

function random_encounter_positions(int $count): array
{
    $pool = [
        [0.14, 0.22], [0.28, 0.35], [0.44, 0.22], [0.61, 0.29],
        [0.78, 0.21], [0.88, 0.39], [0.18, 0.55], [0.34, 0.76],
        [0.53, 0.56], [0.70, 0.78], [0.86, 0.70], [0.58, 0.40],
    ];

    shuffle($pool);
    return array_slice($pool, 0, $count);
}

function level_one_encounters(): array
{
    $positions = random_encounter_positions(4);

    return [
        make_encounter('meadow-1', 'Pikachu', 'electric', 36, 'pikachu.png', $positions[0][0], $positions[0][1]),
        make_encounter('meadow-2', 'Pikachu', 'electric', 42, 'pikachu.png', $positions[1][0], $positions[1][1]),
        make_encounter('meadow-3', 'Pikachu', 'electric', 48, 'pikachu.png', $positions[2][0], $positions[2][1]),
        make_encounter('meadow-4', 'Pikachu', 'electric', 54, 'pikachu.png', $positions[3][0], $positions[3][1]),
    ];
}

function level_two_encounters(string $starter, int $baseDamage): array
{
    $playerType = Trainer::profile($starter)['type'];
    $safeType = advantage_type($playerType);
    $badType = counter_type($playerType);
    $safe = type_profile($safeType);
    $same = type_profile($playerType);
    $bad = type_profile($badType);
    $definitions = [
        ['type' => $safeType, 'profile' => $safe, 'damage' => max(15, $baseDamage - 14), 'debuff' => 6],
        // Keep every advantageous encounter winnable after its debuff. The
        // adjusted trainer damage remains strictly greater than the enemy.
        ['type' => $safeType, 'profile' => $safe, 'damage' => max(15, $baseDamage - 13), 'debuff' => 10],
        ['type' => $playerType, 'profile' => $same, 'damage' => max(15, $baseDamage - 16), 'debuff' => 0],
        ['type' => $playerType, 'profile' => $same, 'damage' => max(15, $baseDamage - 10), 'debuff' => 0],
        ['type' => $playerType, 'profile' => $same, 'damage' => $baseDamage + 10, 'debuff' => 0],
        ['type' => $badType, 'profile' => $bad, 'damage' => $baseDamage + 12, 'debuff' => 25],
        ['type' => $badType, 'profile' => $bad, 'damage' => $baseDamage + 16, 'debuff' => 30],
    ];

    shuffle($definitions);
    $positions = random_encounter_positions(count($definitions));
    $encounters = [];
    foreach ($definitions as $index => $definition) {
        $encounters[] = make_encounter(
            'gym-' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
            $definition['profile']['name'],
            $definition['type'],
            $definition['damage'],
            $definition['profile']['image'],
            $positions[$index][0],
            $positions[$index][1],
            false,
            $definition['debuff']
        );
    }

    return $encounters;
}

function level_three_encounters(): array
{
    return [
        make_encounter('nullbyte-core', 'NullByte Ω', 'glitch', 99999, 'boss2.png', 0.80, 0.25, true),
    ];
}

function create_game_state(string $starter): array
{
    $profile = Trainer::profile($starter);

    return [
        'stage' => 1,
        'pending_stage' => 0,
        'clear_message' => '',
        'defeat_message' => '',
        'current_enemy' => null,
        'level1' => [
            'wins' => 0,
            'required_wins' => 4,
            'encounters' => level_one_encounters(),
        ],
        'level2' => [
            'wins' => 0,
            'required_wins' => 2,
            'advantage_wins' => 0,
            'encounters' => level_two_encounters($starter, $profile['damage']),
        ],
        'level3' => [
            'encounters' => level_three_encounters(),
        ],
        'log' => ['Màn 1 bắt đầu: dùng phím mũi tên để đi tìm 4 Wild Pikachu.'],
    ];
}

function reset_progress_to_stage_one(string $defeatMessage = ''): void
{
    $oldTrainer = $_SESSION['trainer'];
    $_SESSION['trainer'] = new Trainer((string) $oldTrainer->name, (string) $oldTrainer->starter);
    $_SESSION['game'] = create_game_state((string) $_SESSION['trainer']->starter);
    $_SESSION['game']['defeat_message'] = $defeatMessage;
}

function add_log(string $message): void
{
    if (!isset($_SESSION['game'])) {
        return;
    }

    $_SESSION['game']['log'][] = $message;
    $_SESSION['game']['log'] = array_slice($_SESSION['game']['log'], -8);
}

function public_encounter(?array $enemy): ?array
{
    if ($enemy === null) {
        return null;
    }

    return $enemy;
}

function game_snapshot(): array
{
    if (!isset($_SESSION['game'], $_SESSION['trainer']) || !($_SESSION['trainer'] instanceof Trainer)) {
        return ['started' => false, 'stage' => 0, 'pending_stage' => 0, 'log' => []];
    }

    $game = $_SESSION['game'];
    $trainer = $_SESSION['trainer'];
    $stage = (int) $game['stage'];

    $snapshot = [
        'started' => true,
        'stage' => $stage,
        'pending_stage' => (int) $game['pending_stage'],
        'clear_message' => (string) $game['clear_message'],
        'defeat_message' => (string) ($game['defeat_message'] ?? ''),
        'trainer' => [
            'name' => (string) $trainer->name,
            'starter' => (string) $trainer->starter,
            'type' => (string) $trainer->type,
            'hp' => max(0, (int) $trainer->hp),
            'damage' => max(0, (int) $trainer->damage),
        ],
        'current_enemy' => public_encounter($game['current_enemy']),
        'level1' => [
            'wins' => (int) $game['level1']['wins'],
            'required_wins' => (int) $game['level1']['required_wins'],
            'encounters' => $game['level1']['encounters'],
        ],
        'level2' => [
            'wins' => (int) $game['level2']['wins'],
            'required_wins' => (int) $game['level2']['required_wins'],
            'advantage_wins' => (int) ($game['level2']['advantage_wins'] ?? 0),
            'encounters' => $game['level2']['encounters'],
        ],
        'level3' => [
            'encounters' => $game['level3']['encounters'],
        ],
        'log' => $game['log'],
    ];

    if ($stage === 4) {
        $snapshot['victory_code'] = final_flag();
    }

    return $snapshot;
}

function require_game(): void
{
    if (!isset($_SESSION['game'], $_SESSION['trainer']) || !($_SESSION['trainer'] instanceof Trainer)) {
        send_json([
            'ok' => false,
            'message' => 'Hãy bắt đầu một lượt chơi mới.',
            'state' => game_snapshot(),
        ], 409);
    }
}

function require_stage(int $stage): void
{
    require_game();
    if ((int) $_SESSION['game']['stage'] !== $stage || (int) $_SESSION['game']['pending_stage'] !== 0) {
        send_json([
            'ok' => false,
            'message' => 'Màn này chưa được mở khóa hoặc đang chờ bạn xác nhận qua màn.',
            'state' => game_snapshot(),
        ], 409);
    }
}
