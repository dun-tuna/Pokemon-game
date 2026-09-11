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
if (isset($_SESSION['game'], $_SESSION['trainer']) && ($_SESSION['game']['version'] ?? 0) !== 10) {
    $previous = $_SESSION['trainer'];
    if ($previous instanceof Trainer) {
        $_SESSION['trainer'] = new Trainer((string) $previous->name, (string) $previous->starter);
        $_SESSION['game'] = create_game_state((string) $previous->starter);
        add_log('Đã cập nhật luật mới. Lượt chơi bắt đầu lại ở Màn 1.');
    } else {
        $_SESSION = [];
    }
}

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
    $positions = random_encounter_positions(6);
    $enemies = [];
    foreach ([42, 48, 54, 60, 66, 72] as $i => $damage) {
        $e = make_encounter('meadow-' . ($i+1), 'Pikachu ' . ($i+1), 'electric', $damage,
            'pikachu.png', $positions[$i][0], $positions[$i][1]);
        $e['hp'] = $e['max_hp'] = 80 + $i * 12;
        $e['turn'] = 0;
        $e['intent'] = ['strike', 'guard', 'break'][random_int(0, 2)];
        $e['elite'] = false;
        $enemies[] = $e;
    }
    return $enemies;
}

function level_two_encounters(string $starter, int $baseDamage): array
{
    $positions = random_encounter_positions(7);
    $types = ['fire', 'water', 'grass'];
    $enemies = [];
    for ($i = 0; $i < 7; $i++) {
        $type = $types[random_int(0, 2)];
        $profile = type_profile($type);
        $elite = $i < 2;
        $e = make_encounter('gym-' . ($i+1), ($elite ? 'Hộ vệ ' : 'Đấu sĩ ') . ($i+1),
            $type, $baseDamage + ($elite ? 18 : -12), $profile['image'],
            $positions[$i][0], $positions[$i][1]);
        $e['elite'] = $elite;
        $e['hp'] = $e['max_hp'] = $elite ? 240 : 140;
        $e['turn'] = 0;
        $e['intent'] = ['strike', 'guard', 'break'][random_int(0, 2)];
        $enemies[] = $e;
    }
    return $enemies;
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
        'version' => 10,
        'potions' => 3,
        'stage' => 1,
        'pending_stage' => 0,
        'clear_message' => '',
        'defeat_message' => '',
        'current_enemy' => null,
        'level1' => [
            'wins' => 0,
            'required_wins' => 6,
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
        'log' => ['Màn 1: hạ 6 đối thủ. Đọc ý đồ, chọn thế và giữ HP; chỉ có 3 bình hồi phục.'],
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
        'potions' => (int) ($game['potions'] ?? 0),
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
            'technique' => (string) $trainer->technique,
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
