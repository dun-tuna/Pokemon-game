<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_game();

// Only progression is authenticated. The untrusted technique is the intentional puzzle.
function save_key(): string {
    $configured = getenv('SAVE_SECRET');
    if (is_string($configured) && strlen($configured) >= 32) return $configured;
    $path = sys_get_temp_dir() . '/cybermon-save-v10.key';
    $handle = fopen($path, 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) throw new RuntimeException('Save key unavailable');
    $key = stream_get_contents($handle);
    if (strlen($key) < 32) {
        $key = bin2hex(random_bytes(32));
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, $key);
        fflush($handle);
    }
    flock($handle, LOCK_UN);
    fclose($handle);
    return $key;
}

$action = $_GET['action'] ?? '';
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $save = clone $_SESSION['trainer'];
    $save->checkpoint = base64_encode(json_encode([
        'version'=>10, 'game'=>$_SESSION['game'],
        'starter'=>$save->starter, 'name'=>$save->name,
        'hp'=>$save->hp, 'damage'=>$save->damage,
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    $save->seal = hash_hmac('sha256', $save->checkpoint, save_key());
    $payload = base64_encode(serialize($save));
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="arena.sav"');
    echo $payload;
    exit;
}
if ($action === 'load' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $upload = $_FILES['save'] ?? null;
    if (!$upload || $upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 98304 || !is_uploaded_file($upload['tmp_name'])) {
        send_json(['ok'=>false, 'message'=>'Chọn file .sav hợp lệ, tối đa 96 KB.', 'state'=>game_snapshot()], 422);
    }
    try {
        // Require a Base64 envelope; accept line wrapping from common encoders.
        $encoded = preg_replace('/[\r\n\t ]+/', '', file_get_contents($upload['tmp_name']));
        if (!is_string($encoded) || $encoded === '' || !preg_match('/\A(?:[A-Za-z0-9+\/]{4})*(?:[A-Za-z0-9+\/]{2}==|[A-Za-z0-9+\/]{3}=)?\z/', $encoded)) {
            throw new RuntimeException('Invalid save envelope');
        }
        $serialized = base64_decode($encoded, true);
        if ($serialized === false || strlen($serialized) > 65536 || base64_encode($serialized) !== $encoded) {
            throw new RuntimeException('Invalid save envelope');
        }
        $candidate = @unserialize($serialized, ['allowed_classes'=>['Trainer'], 'max_depth'=>4]);
        if (!$candidate instanceof Trainer || !is_string($candidate->checkpoint) || !is_string($candidate->seal)
            || !hash_equals(hash_hmac('sha256', $candidate->checkpoint, save_key()), $candidate->seal)) {
            throw new RuntimeException('Invalid checkpoint');
        }
        $saved = json_decode(base64_decode($candidate->checkpoint, true), true, 32, JSON_THROW_ON_ERROR);
        if (($saved['version'] ?? 0) !== 10) throw new RuntimeException('Old save');
        $trainer = new Trainer($saved['name'], $saved['starter']);
        $trainer->hp = $saved['hp'];
        $trainer->damage = $saved['damage'];
        // Intentional semantic trust flaw: effect name accepted outside the signed checkpoint.
        if (!is_string($candidate->technique) || !in_array($candidate->technique, ['strike','guard','break','reflect'], true)) {
            throw new RuntimeException('Invalid technique');
        }
        $trainer->technique = $candidate->technique;
        $_SESSION['trainer'] = $trainer;
        $_SESSION['game'] = $saved['game'];
        if (!empty($_SESSION['game']['current_enemy']['boss'])) $trainer->hp = $trainer->damage = 1;
    } catch (Throwable $e) {
        send_json(['ok'=>false, 'message'=>'File save không hợp lệ hoặc dữ liệu tiến trình bị thay đổi. Hãy dùng file .sav đúng định dạng.', 'state'=>game_snapshot()], 422);
    }
    add_log('Đã khôi phục màn, HP, bình hồi phục và tiến trình trận đấu.');
    send_json(['ok'=>true, 'message'=>'Load save thành công.', 'state'=>game_snapshot()]);
}
send_json(['ok'=>false, 'message'=>'Save/Load action không hợp lệ.', 'state'=>game_snapshot()], 404);
