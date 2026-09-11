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

    $_SESSION['game']['current_enemy'] = $found;
    add_log('Bạn chạm trán ' . $found['name'] . ' [' . strtoupper($found['type']) . '].');
    send_json([
        'ok' => true,
        'message' => 'Một đối thủ xuất hiện! Hãy so sánh damage.',
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
    if (!is_array($enemy)) {
        send_json(['ok' => false, 'message' => 'Chưa gặp đối thủ nào.', 'state' => game_snapshot()], 409);
    }

    $trainer = $_SESSION['trainer'];
    $stage = (int) $_SESSION['game']['stage'];

    if ($enemy['boss']) {
        $damage = (int) $trainer->damage;

        if ($damage > (int) $enemy['damage']) {
            $_SESSION['game']['current_enemy'] = null;
            $_SESSION['game']['stage'] = 4;
            $_SESSION['game']['clear_message'] = 'Chúc mừng! Bạn đã hoàn thành cả 3 màn.';
            add_log('DAMAGE OVERRIDE: ' . $damage . '. NullByte đã bị hạ!');
            send_json([
                'ok' => true,
                'message' => 'Bạn đã vượt qua sức mạnh tuyệt đối của NullByte!',
                'state' => game_snapshot(),
            ]);
        }

        $defeatMessage = 'Bạn đã thua vì sức mạnh chưa đủ để hạ Boss.';
        reset_progress_to_stage_one($defeatMessage);
        add_log($defeatMessage);
        send_json([
            'ok' => true,
            'message' => $defeatMessage,
            'state' => game_snapshot(),
        ]);
    }

    $relation = 'normal';
    $debuff = 0;
    if ($stage === 2) {
        $playerType = (string) $trainer->type;
        $enemyType = (string) ($enemy['type'] ?? '');
        $relation = $enemyType === advantage_type($playerType)
            ? 'advantage'
            : ($enemyType === $playerType ? 'same' : 'counter');

        if ($relation === 'counter') {
            $counterDebuff = (int) ($enemy['debuff'] ?? 0);
            $defeatMessage = 'Bạn đã thua vì gặp đối thủ counter (debuff -' . $counterDebuff . ' damage).';
            reset_progress_to_stage_one($defeatMessage);
            add_log($defeatMessage);
            send_json([
                'ok' => true,
                'message' => $defeatMessage,
                'state' => game_snapshot(),
            ]);
        }

        // An advantageous type still carries a visible debuff, but the
        // player's adjusted damage should remain high enough to win.
        $debuff = $relation === 'advantage' ? (int) ($enemy['debuff'] ?? 0) : 0;
    }

    $trainerDamage = max(0, (int) $trainer->damage - $debuff);
    $enemyDamage = (int) $enemy['damage'];
    if ($trainerDamage <= $enemyDamage) {
        $defeatMessage = $relation === 'same'
            ? 'Bạn đã thua vì sức mạnh yếu hơn.'
            : ($stage === 2
                ? 'Bạn đã thua vì debuff làm damage của mình yếu hơn.'
                : 'Bạn đã thua vì sức mạnh yếu hơn.');
        reset_progress_to_stage_one($defeatMessage);
        add_log($defeatMessage);
        send_json([
            'ok' => true,
            'message' => $defeatMessage,
            'state' => game_snapshot(),
        ]);
    }

    $key = $stage === 1 ? 'level1' : 'level2';
    foreach ($_SESSION['game'][$key]['encounters'] as &$candidate) {
        if ($candidate['id'] === $enemy['id']) {
            $candidate['defeated'] = true;
            break;
        }
    }
    unset($candidate);

    $_SESSION['game'][$key]['wins']++;
    if ($stage === 2 && $relation === 'advantage') {
        $_SESSION['game']['level2']['advantage_wins']++;
    }
    $trainer->damage += 4;
    $trainer->hp = 100;
    $_SESSION['game']['current_enemy'] = null;
    add_log('Bạn thắng trong một đòn! Debuff -' . $debuff . '; damage trainer hiện tại ' . $trainer->damage . '.');

    $cleared = $stage === 1
        ? $_SESSION['game']['level1']['wins'] >= $_SESSION['game']['level1']['required_wins']
        : $_SESSION['game']['level2']['advantage_wins'] >= $_SESSION['game']['level2']['required_wins'];
    if ($cleared) {
        $nextStage = $stage + 1;
        $_SESSION['game']['pending_stage'] = $nextStage;
        $_SESSION['game']['clear_message'] = 'Chúc mừng! Bạn đã hoàn thành Màn ' . $stage . '.';
        add_log('Màn ' . $stage . ' hoàn thành.');
        send_json([
            'ok' => true,
            'message' => 'Chúc mừng! Màn ' . $stage . ' đã hoàn thành.',
            'state' => game_snapshot(),
        ]);
    }

    $message = $stage === 2 && $relation === 'same'
        ? 'Bạn thắng trận này, nhưng Màn 2 vẫn chưa hoàn thành.'
        : 'Thắng! Hãy tiếp tục đi tìm đối thủ tiếp theo.';
    send_json(['ok' => true, 'message' => $message, 'state' => game_snapshot()]);
}

send_json(['ok' => false, 'message' => 'Action không tồn tại.', 'state' => game_snapshot()], 404);
