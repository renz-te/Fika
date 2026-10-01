<?php
require_once __DIR__ . '/init.php';
require_login();

$id = $_GET['id'] ?? null;
if ($id) {
    $stmt = $pdo->prepare('SELECT link, target_roles, target_user_id FROM global_notifications WHERE id = ?');
    $stmt->execute([$id]);
    $notif = $stmt->fetch();
    
    $isTarget = false;
    if ($notif) {
        if ($notif['target_user_id'] && $notif['target_user_id'] == current_user()['id']) {
            $isTarget = true;
        } elseif ($notif['target_roles'] && strpos($notif['target_roles'], current_user()['role']) !== false) {
            $isTarget = true;
        }
    }
    
    if ($isTarget) {
        $pdo->prepare('UPDATE global_notifications SET is_read = 1, read_by = ?, read_at = NOW() WHERE id = ? AND is_read = 0')->execute([current_user()['id'], $id]);
        $fallback = in_array(current_user()['role'], ['Employee', 'Barista', 'Head Barista']) ? 'ess.php' : 'dashboard.php';
        redirect($notif['link'] ?? $fallback);
    }
}
$fallback = in_array(current_user()['role'], ['Employee', 'Barista', 'Head Barista']) ? 'ess.php' : 'dashboard.php';
redirect($fallback);
