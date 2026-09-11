<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

$data = request_data();
$action = (string) ($data['action'] ?? 'status');

if ($action === 'status') {
    send_json(['ok' => true, 'state' => game_snapshot()]);
}

if ($action === 'start') {
    $name = clean_name($data['name'] ?? '');
    $starter = strtolower((string) ($data['starter'] ?? ''));
    $starters = ['charmander', 'bulbasaur', 'squirtle'];

    if ($name === '' || !in_array($starter, $starters, true)) {
        send_json(['ok' => false, 'message' => 'Nhập tên và chọn một Cybermon hợp lệ.'], 422);
    }

    session_regenerate_id(true);
    $_SESSION['trainer'] = new Trainer($name, $starter);
    $_SESSION['game'] = create_game_state($starter);
    send_json(['ok' => true, 'message' => 'Lượt chơi đã bắt đầu.', 'state' => game_snapshot()]);
}

if ($action === 'reset') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], '', false, true);
    }
    session_destroy();
    send_json(['ok' => true, 'message' => 'Đã tạo lại đấu trường.', 'state' => game_snapshot()]);
}

if ($action === 'dismiss_defeat') {
    require_game();
    if ((string) ($_SESSION['game']['defeat_message'] ?? '') === '') {
        send_json(['ok' => false, 'message' => 'Không có banner thất bại nào đang chờ.', 'state' => game_snapshot()], 409);
    }

    $_SESSION['game']['defeat_message'] = '';
    add_log('Bạn đã sẵn sàng thử lại từ Màn 1.');
    send_json(['ok' => true, 'message' => 'Hãy bắt đầu lại từ Màn 1.', 'state' => game_snapshot()]);
}

if ($action === 'advance_stage') {
    require_game();
    $stage = (int) $_SESSION['game']['stage'];
    $pending = (int) $_SESSION['game']['pending_stage'];
    if ($pending !== $stage + 1 || !in_array($pending, [2, 3], true)) {
        send_json(['ok' => false, 'message' => 'Chưa có màn mới để mở.', 'state' => game_snapshot()], 409);
    }

    $_SESSION['game']['stage'] = $pending;
    $_SESSION['game']['pending_stage'] = 0;
    $_SESSION['game']['clear_message'] = '';
    $_SESSION['game']['defeat_message'] = '';
    $_SESSION['game']['current_enemy'] = null;
    $_SESSION['trainer']->hp = 100;
    $_SESSION['game']['potions'] = $pending === 2 ? 4 : 0;

    if ($pending === 2) {
        $_SESSION['game']['level2']['encounters'] = level_two_encounters(
            (string) $_SESSION['trainer']->starter,
            (int) $_SESSION['trainer']->damage
        );
        add_log('Màn 2 bắt đầu: khám phá các hệ và sức mạnh trước khi chọn đối thủ.');
    } else {
        add_log('Màn 3 bắt đầu: hãy đi tới Glitch Core và tìm cách bẻ luật.');
    }

    send_json(['ok' => true, 'message' => 'Màn mới đã mở khóa.', 'state' => game_snapshot()]);
}

if ($action === 'encounter') {
    require_game();
    if ($_SESSION['game']['current_enemy'] !== null) {
        send_json(['ok' => false, 'message' => 'Bạn đang ở trong một trận battle.', 'state' => game_snapshot()], 409);
    }

    if ($_SESSION['game']['pending_stage'] || $_SESSION['game']['defeat_message'] || $_SESSION['game']['stage'] > 3) {
        send_json(['ok'=>false, 'message'=>'Hãy xác nhận chuyển cảnh trước.', 'state'=>game_snapshot()], 409);
    }
    $id = (string) ($data['id'] ?? '');
    $stage = (int) $_SESSION['game']['stage'];
    $key = $stage === 1 ? 'level1' : ($stage === 2 ? 'level2' : 'level3');
    if (!isset($_SESSION['game'][$key]['encounters']) || $id === '') {
        send_json(['ok' => false, 'message' => 'Không tìm thấy khu vực này.', 'state' => game_snapshot()], 404);
    }

    $found = null;
    foreach ($_SESSION['game'][$key]['encounters'] as $enemy) {
        if ($enemy['id'] === $id) {
            $found = $enemy;
            break;
        }
    }

    if ($found === null || $found['defeated']) {
        send_json(['ok' => false, 'message' => 'Đối thủ này đã biến mất.', 'state' => game_snapshot()], 409);
    }

    if ($found['boss']) {
        $_SESSION['trainer']->hp = 1;
        $_SESSION['trainer']->damage = 1;
        $found['turn'] = 0;
        $found['intent'] = 'null';
    }
    $_SESSION['game']['current_enemy'] = $found;
    add_log('Bạn chạm trán ' . $found['name'] . ' [' . strtoupper($found['type']) . '].');
    send_json([
        'ok' => true,
        'message' => 'Một đối thủ xuất hiện! Quan sát ý đồ trước khi chọn thế.',
        'state' => game_snapshot(),
    ]);
}

if ($action === 'battle_run') {
    require_game();
    if ($_SESSION['game']['current_enemy'] === null) {
        send_json(['ok' => false, 'message' => 'Không có trận battle đang diễn ra.', 'state' => game_snapshot()], 409);
    }

    $_SESSION['game']['current_enemy'] = null;
    add_log('Bạn đã rời khỏi trận battle.');
    send_json(['ok' => true, 'message' => 'Đã chạy khỏi trận battle.', 'state' => game_snapshot()]);
}

if ($action === 'battle_resolve') {
    require_game();
    $enemy = $_SESSION['game']['current_enemy'];
    if (!is_array($enemy)) send_json(['ok'=>false, 'message'=>'Chưa gặp đối thủ.', 'state'=>game_snapshot()], 409);
    $move = (string) ($data['move'] ?? 'strike');
    if (!in_array($move, ['strike', 'guard', 'break', 'potion'], true)) {
        send_json(['ok'=>false, 'message'=>'Thế đánh không hợp lệ.', 'state'=>game_snapshot()], 422);
    }
    $trainer = $_SESSION['trainer'];
    $stage = (int) $_SESSION['game']['stage'];
    if ($enemy['boss']) {
        // Suppression is re-applied server-side on EVERY turn, including after load.
        $trainer->hp = $trainer->damage = 1;
        if ($move === 'guard' && $trainer->technique === 'reflect') {
            $_SESSION['game']['stage'] = 4;
            $_SESSION['game']['current_enemy'] = null;
            $_SESSION['game']['clear_message'] = 'Chúc mừng! Bạn đã phá vỡ luật của NullByte và hoàn thành cả 3 màn.';
            add_log('Đòn hủy diệt bị phản ngược về NullByte.');
            send_json(['ok'=>true, 'message'=>'NullByte bị chính sức mạnh của mình hạ gục!', 'state'=>game_snapshot()]);
        }
        reset_progress_to_stage_one('NullByte triệt tiêu mọi chỉ số về 1. Đòn hủy diệt đã hạ bạn.');
        send_json(['ok'=>true, 'message'=>'Bạn đã thua trước NullByte.', 'state'=>game_snapshot()]);
    }
    $intent = $enemy['intent'];
    $beats = ['strike'=>'break', 'break'=>'guard', 'guard'=>'strike'];
    $factor = 1.0;
    if ($stage === 2) {
        $factor = $enemy['type'] === advantage_type($trainer->type) ? 1.5
            : ($enemy['type'] === counter_type($trainer->type) ? 0.65 : 1.0);
    }
    $out = 0;
    $incoming = 0;
    if ($move === 'potion') {
        if ($_SESSION['game']['potions'] <= 0) send_json(['ok'=>false, 'message'=>'Đã hết bình hồi phục.', 'state'=>game_snapshot()], 409);
        $_SESSION['game']['potions']--;
        $trainer->hp = min(100, $trainer->hp + 45);
        $incoming = $intent === 'guard' ? 0 : (int) ceil($enemy['damage'] * 0.3 / $factor);
    } else {
        $win = $beats[$move] === $intent;
        $tie = $move === $intent;
        $out = (int) max(1, floor($trainer->damage * $factor * ($win ? 0.85 : ($tie ? 0.35 : 0.12))));
        $incoming = (int) ceil($enemy['damage'] / $factor * ($win ? ($stage === 2 ? 0.14 : 0.10) : ($tie ? 0.20 : 0.42)));
        if ($move === 'guard' && $win) $incoming = 0;
    }
    $enemy['hp'] = max(0, $enemy['hp'] - $out);
    // Winning the exchange ends the fight before the enemy can retaliate.
    if ($enemy['hp'] > 0) $trainer->hp = max(0, $trainer->hp - $incoming);
    $message = 'Bạn gây ' . $out . ' damage; nhận ' . ($enemy['hp'] > 0 ? $incoming : 0) . '. HP còn ' . $trainer->hp . '.';
    add_log($message);
    if ($trainer->hp <= 0) {
        reset_progress_to_stage_one('Bạn đã cạn HP: chọn thế sai, bất lợi hệ hoặc hồi phục quá muộn.');
        send_json(['ok'=>true, 'message'=>'Bạn đã thua trận.', 'state'=>game_snapshot()]);
    }
    if ($enemy['hp'] > 0) {
        $enemy['turn']++;
        $enemy['intent'] = ['strike', 'guard', 'break'][random_int(0, 2)];
        if ($stage === 2 && $enemy['elite']) {
            $enemy['damage'] += 4;
            $enemy['type'] = ['fire'=>'water', 'water'=>'grass', 'grass'=>'fire'][$enemy['type']];
            $enemy['image'] = type_profile($enemy['type'])['image'];
        }
        $_SESSION['game']['current_enemy'] = $enemy;
        send_json(['ok'=>true, 'message'=>$message, 'state'=>game_snapshot()]);
    }
    $key = $stage === 1 ? 'level1' : 'level2';
    foreach ($_SESSION['game'][$key]['encounters'] as &$candidate) {
        if ($candidate['id'] === $enemy['id']) $candidate['defeated'] = true;
    }
    unset($candidate);
    $_SESSION['game'][$key]['wins']++;
    if ($stage === 2 && $enemy['elite']) $_SESSION['game']['level2']['advantage_wins']++;
    $trainer->damage += 3;
    // Side encounters are optional but reward resource planning.
    if ($stage === 2 && !$enemy['elite']) $_SESSION['game']['potions']++;
    $_SESSION['game']['current_enemy'] = null;
    $cleared = $stage === 1 ? $_SESSION['game']['level1']['wins'] >= 6 : $_SESSION['game']['level2']['advantage_wins'] >= 2;
    if ($cleared) {
        $_SESSION['game']['pending_stage'] = $stage + 1;
        $_SESSION['game']['clear_message'] = 'Chúc mừng! Bạn đã chinh phục Màn ' . $stage . '.';
    }
    add_log('Hạ ' . $enemy['name'] . '! +3 damage. HP được giữ sang trận tiếp theo.');
    send_json(['ok'=>true, 'message'=>$cleared ? 'Chúc mừng! Màn chơi hoàn thành.' : 'Thắng trận! Hãy chuẩn bị cho đối thủ tiếp theo.', 'state'=>game_snapshot()]);
}

send_json(['ok'=>false, 'message'=>'Action không tồn tại.', 'state'=>game_snapshot()], 404);
