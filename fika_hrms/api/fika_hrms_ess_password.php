<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

Auth::requireLogin();

$rawInput = file_get_contents('php://input');
if (empty($rawInput) && !empty($GLOBALS['RAW_INPUT'])) {
    $rawInput = $GLOBALS['RAW_INPUT'];
}
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = $_POST;
}

if (empty($_SERVER['HTTP_X_CSRF_TOKEN']) && empty($_POST['csrf_token']) && !empty($input['csrf_token'])) {
    $_POST['csrf_token'] = $input['csrf_token'];
}
Csrf::requireValid();

$userId = (int)($_SESSION['user']['id'] ?? 0);
if (!$userId) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized.']));
}

$currentPassword = (string)($input['current_password'] ?? '');
$newPassword = (string)($input['new_password'] ?? '');
$confirmPassword = (string)($input['confirm_password'] ?? $newPassword);

if (empty($currentPassword) || empty($newPassword)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Current password and new password are required.']));
}

if (strlen($newPassword) < 8) {
    http_response_code(400);
    exit(json_encode(['error' => 'New password must be at least 8 characters long.']));
}

if ($confirmPassword !== $newPassword) {
    http_response_code(400);
    exit(json_encode(['error' => 'New password confirmation does not match.']));
}

global $pdo;

$stmt = $pdo->prepare("SELECT id, password FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user || !password_verify($currentPassword, $user['password'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'Current password is incorrect.']));
}

$newHash = password_hash($newPassword, PASSWORD_DEFAULT);
$updateStmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
$updateStmt->execute([$newHash, $userId]);

Audit::log('PASSWORD_CHANGED', 'Password changed via ESS portal', $userId);

echo json_encode([
    'success' => true,
    'message' => 'Password changed successfully.'
]);
