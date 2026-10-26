<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

Auth::requireLogin();
Rbac::require_permission('devices.manage');
Csrf::requireValid();

global $pdo;

$currentUser = Auth::user();
$currentRoleId = (int)($currentUser['role_id'] ?? 0);
$bmBranchId = (int)($currentUser['branch_id'] ?? 0);

$userRoleName = '';
if ($currentRoleId > 0 && $pdo) {
    $rStmt = $pdo->prepare("SELECT name FROM roles WHERE id = ? LIMIT 1");
    $rStmt->execute([$currentRoleId]);
    $userRoleName = strtoupper(trim((string)$rStmt->fetchColumn()));
}
$isBM = ($currentRoleId === 6) || ($userRoleName === 'BM');

$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true) ?? [];
$inputData = array_merge($_POST, $jsonInput);

$deviceName = trim($inputData['device_name'] ?? '');
$macAddress = trim($inputData['mac_address'] ?? '');
$deviceToken = trim($inputData['device_token'] ?? '');
$systemRole = trim($inputData['system_role'] ?? '');
$status = 'Active';

// Auto-infer system role if missing
if ($systemRole === '') {
    if (stripos($deviceName, 'clock') !== false) {
        $systemRole = 'Clock';
    } elseif (stripos($deviceName, 'kiosk') !== false) {
        $systemRole = 'Kiosk';
    } else {
        $systemRole = 'Cashier Terminal';
    }
}

$submittedBranchId = isset($inputData['branch_id']) && $inputData['branch_id'] !== '' ? (int)$inputData['branch_id'] : null;

if ($isBM) {
    if ($submittedBranchId !== null && $submittedBranchId !== $bmBranchId) {
        Audit::log('SECURITY_CROSS_BRANCH_DEVICE_BLOCKED', "BM user {$currentUser['id']} attempted to enroll device for branch {$submittedBranchId}");
        http_response_code(403);
        echo json_encode(["error" => "Forbidden. Branch scope mismatch. Cannot enroll device for another branch."]);
        exit;
    }
    $effectiveBranchId = $bmBranchId;
} else {
    $effectiveBranchId = $submittedBranchId ?: 1;
}

if ($deviceName === '') {
    http_response_code(400);
    echo json_encode(["error" => "Device Name is required."]);
    exit;
}

// 1. Resolve branch prefix
$brStmt = $pdo->prepare("SELECT name FROM branches WHERE id = ? LIMIT 1");
$brStmt->execute([$effectiveBranchId]);
$branchRowName = (string)$brStmt->fetchColumn();

if (stripos($branchRowName, 'General Trias') !== false || stripos($branchRowName, 'gentri') !== false) {
    $branchPrefix = 'gentri';
} elseif (stripos($branchRowName, 'Trece') !== false) {
    $branchPrefix = 'trece';
} elseif (stripos($branchRowName, 'Imus') !== false) {
    $branchPrefix = 'imus';
} else {
    $words = explode(' ', trim($branchRowName));
    $branchPrefix = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $words[0] ?: 'branch' . $effectiveBranchId));
}

// 2. Resolve role suffix & system role ID
switch ($systemRole) {
    case 'Clock':
        $roleSuffix = 'clock';
        $systemRoleId = 12;
        break;
    case 'Kiosk':
        $roleSuffix = 'kiosk';
        $systemRoleId = 13;
        break;
    case 'Cashier Terminal':
    default:
        $systemRole = 'Cashier Terminal';
        $roleSuffix = 'cashier';
        $systemRoleId = 9;
        break;
}

// 3. Auto-generate unique username
$baseUsername = "{$branchPrefix}.{$roleSuffix}";
$generatedUsername = $baseUsername;
$suffixCounter = 2;
$checkUserStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND deleted_at IS NULL LIMIT 1");
while (true) {
    $checkUserStmt->execute([$generatedUsername]);
    if (!$checkUserStmt->fetch()) {
        break;
    }
    $generatedUsername = "{$baseUsername}{$suffixCounter}";
    $suffixCounter++;
}

// 4. Generate random device_token strictly following tok_{branchPrefix}_{roleSuffix}_ + bin2hex(random_bytes(4))
if (empty($deviceToken)) {
    $deviceToken = "tok_{$branchPrefix}_{$roleSuffix}_" . bin2hex(random_bytes(4));
}

$checkStmt = $pdo->prepare("SELECT id FROM devices WHERE device_token = ? AND deleted_at IS NULL LIMIT 1");
$checkStmt->execute([$deviceToken]);
if ($checkStmt->fetch()) {
    http_response_code(409);
    echo json_encode(["error" => "Device token is already in use by another terminal."]);
    exit;
}

try {
    $pdo->beginTransaction();

    // 5. Create System User Account
    $defaultPasswordHash = password_hash('password', PASSWORD_DEFAULT);
    $systemEmail = "{$generatedUsername}@fika.local";
    $insUser = $pdo->prepare("
        INSERT INTO users (username, password, email, role_id, employee_id, branch_id, created_at, updated_at) 
        VALUES (?, ?, ?, ?, NULL, ?, NOW(), NOW())
    ");
    $insUser->execute([$generatedUsername, $defaultPasswordHash, $systemEmail, $systemRoleId, $effectiveBranchId]);
    $newUserId = (int)$pdo->lastInsertId();

    // 6. Create Device linked to user_id
    $insDevice = $pdo->prepare("
        INSERT INTO devices (branch_id, user_id, device_name, mac_address, device_token, status, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, 'Active', NOW(), NOW())
    ");
    $insDevice->execute([$effectiveBranchId, $newUserId, $deviceName, $macAddress ?: null, $deviceToken]);
    $newDeviceId = (int)$pdo->lastInsertId();

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "device_id" => $newDeviceId,
        "user_id" => $newUserId,
        "username" => $generatedUsername,
        "device_token" => $deviceToken,
        "message" => "Hardware device enrolled successfully. System account '{$generatedUsername}' created."
    ]);
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(["error" => "Failed to enroll device: " . $e->getMessage()]);
}
