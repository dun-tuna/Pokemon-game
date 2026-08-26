<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_game();

$action = (string) ($_GET['action'] ?? '');

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Keep the trainer object (including the intentional damage puzzle) and
    // persist the current stage/transition alongside it.
    $save = clone $_SESSION['trainer'];
    $save->save_stage = (int) $_SESSION['game']['stage'];
    $save->save_pending_stage = (int) $_SESSION['game']['pending_stage'];
    $payload = serialize($save);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="arena.sav"');
    header('Content-Length: ' . strlen($payload));
    echo $payload;
    exit;
}

if ($action === 'load' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['save']) || !is_uploaded_file($_FILES['save']['tmp_name'])) {
        send_json(['ok' => false, 'message' => 'Chưa chọn file save.', 'state' => game_snapshot()], 422);
    }

    if ((int) $_FILES['save']['error'] !== UPLOAD_ERR_OK || (int) $_FILES['save']['size'] > 4096) {
        send_json(['ok' => false, 'message' => 'File save không hợp lệ hoặc vượt quá 4 KB.', 'state' => game_snapshot()], 422);
    }

    $raw = (string) file_get_contents($_FILES['save']['tmp_name']);
    try {
        // INTENTIONAL CTF VULNERABILITY: Trainer fields are accepted from the save file.
        $candidate = @unserialize($raw, [
            'allowed_classes' => ['Trainer'],
            'max_depth' => 4,
        ]);
    } catch (Throwable $error) {
        $candidate = false;
    }

    $validName = ($candidate instanceof Trainer && isset($candidate->name) && is_string($candidate->name))
        ? clean_name($candidate->name)
        : '';
    $validStarter = $candidate instanceof Trainer
        && isset($candidate->starter)
        && is_string($candidate->starter)
        && in_array($candidate->starter, ['charmander', 'bulbasaur', 'squirtle'], true);
    $sameStarter = $validStarter
        && $candidate->starter === (string) $_SESSION['trainer']->starter;
    // Accept older Trainer-only saves by retaining the current progression.
    $savedStage = isset($candidate->save_stage) && is_numeric($candidate->save_stage)
        ? (int) $candidate->save_stage : (int) $_SESSION['game']['stage'];
    $savedPending = isset($candidate->save_pending_stage) && is_numeric($candidate->save_pending_stage)
        ? (int) $candidate->save_pending_stage : (int) $_SESSION['game']['pending_stage'];

    if (!($candidate instanceof Trainer)
        || !isset($candidate->name, $candidate->starter, $candidate->hp, $candidate->damage)
        || !is_string($candidate->name)
        || $validName === ''
        || $validName !== $candidate->name
        || !$validStarter
        || !$sameStarter
        || !is_numeric($candidate->hp)
        || !is_numeric($candidate->damage)
        || $savedStage < 1 || $savedStage > 4
        || !in_array($savedPending, [0, 2, 3], true)) {
        send_json(['ok' => false, 'message' => 'Cấu trúc save bị từ chối.', 'state' => game_snapshot()], 422);
    }

    $profile = Trainer::profile($candidate->starter);
    $stage = $savedStage;
    $wins = (int) $_SESSION['game']['level1']['wins'] + (int) $_SESSION['game']['level2']['wins'];
    $maximumDamage = $profile['damage'] + ($wins * 4);
    // Level 3 is the intended save-edit puzzle: allow a bounded damage override
    // so the player can exceed NullByte's 99,999 damage without changing class.
    $damageLimit = $stage === 3 ? 1000000 : $maximumDamage;
    $candidate->type = $profile['type'];
    $candidate->hp = max(1, min(100, (int) $candidate->hp));
    $candidate->damage = max($profile['damage'], min($damageLimit, (int) $candidate->damage));
    $_SESSION['trainer'] = $candidate;
    $_SESSION['game']['stage'] = $savedStage;
    $_SESSION['game']['pending_stage'] = $savedPending;
    $_SESSION['game']['current_enemy'] = null;
    $_SESSION['game']['clear_message'] = $savedPending ? 'Chúc mừng! Bạn đã hoàn thành Màn ' . ($savedStage) . '.' : '';
    add_log('Save được nạp trong Màn ' . (int) $_SESSION['game']['stage'] . '.');
    send_json(['ok' => true, 'message' => 'Load save thành công.', 'state' => game_snapshot()]);
}

send_json(['ok' => false, 'message' => 'Save/Load action không hợp lệ.', 'state' => game_snapshot()], 404);
