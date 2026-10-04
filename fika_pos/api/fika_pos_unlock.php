<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(["error" => "Method not allowed"]));
}

$mac = $_POST['device_mac'] ?? '';
$branchId = (int) ($_POST['branch_id'] ?? 0);
$pin = $_POST['pin'] ?? '';

if (empty($mac) || empty($pin) || $branchId <= 0) {
    http_response_code(400);
    exit(json_encode(["error" => "Missing required fields"]));
}

// 1. Verify Device
if (!Device::authenticateDevice($mac, $branchId)) {
    http_response_code(403);
    Audit::log('POS_UNLOCK_FAILED', 'Unregistered or inactive device MAC: ' . $mac);
    exit(json_encode(["error" => "Unregistered or inactive device."]));
}

// 2. Verify PIN
$user = Device::pinAuth($pin, $branchId);
if (!$user) {
    http_response_code(401);
    Audit::log('POS_UNLOCK_FAILED', 'Invalid PIN entered on device: ' . $mac);
    exit(json_encode(["error" => "Invalid PIN."]));
}

// 3. Login
session_regenerate_id(true);
$_SESSION['user'] = [
    'id' => $user['id'],
    'role_id' => $user['role_id'],
    'employee_id' => $user['employee_id'],
    'branch_id' => $user['branch_id']
];
$_SESSION['last_activity'] = time();

Audit::log('POS_UNLOCKED', 'POS Unlocked on device: ' . $mac, $user['id']);

echo json_encode([
    "success" => true,
    "user_id" => $user['id'],
    "csrf_token" => Csrf::getToken()
]);
