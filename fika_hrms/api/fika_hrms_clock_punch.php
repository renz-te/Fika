<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(["error" => "Method not allowed"]));
}

$mac = trim($_POST['device_mac'] ?? '');
$branchId = (int) ($_POST['branch_id'] ?? 0);
$employeeCode = trim($_POST['employee_code'] ?? '');
$pin = $_POST['pin'] ?? '';

if (empty($mac) || empty($pin) || empty($employeeCode) || $branchId <= 0) {
    http_response_code(400);
    exit(json_encode(["error" => "Missing required fields"]));
}

// 1. Verify Device
if (!Device::authenticateDevice($mac, $branchId)) {
    http_response_code(403);
    Audit::log('CLOCK_PUNCH_FAILED', "Unregistered or inactive device MAC: {$mac}");
    exit(json_encode(["error" => "Unregistered or inactive device."]));
}

global $pdo;

// Get device id for logging
$devStmt = $pdo->prepare("SELECT id FROM devices WHERE mac_address = ? AND branch_id = ?");
$devStmt->execute([$mac, $branchId]);
$deviceId = $devStmt->fetchColumn();

// 2. Verify Employee & PIN (with throttling)
try {
    $emp = Device::clockAuth($employeeCode, $pin, $branchId, $mac);
    if (!$emp) {
        http_response_code(401);
        Audit::log('CLOCK_PUNCH_FAILED', "Invalid PIN for {$employeeCode} on device: {$mac}");
        exit(json_encode(["error" => "Invalid PIN."]));
    }
} catch (Exception $e) {
    http_response_code(429);
    exit(json_encode(["error" => $e->getMessage()]));
}

$employeeId = $emp['id'];

// 3. Determine IN or OUT
try {
    $pdo->beginTransaction();
    
    // Check for an OPEN log
    $chkStmt = $pdo->prepare("SELECT id, clock_in FROM attendance_logs WHERE employee_id = ? AND status = 'OPEN' FOR UPDATE");
    $chkStmt->execute([$employeeId]);
    $openLog = $chkStmt->fetch();
    
    $utcNow = gmdate('Y-m-d H:i:s');
    $localDate = date('Y-m-d'); // Asia/Manila date for the work_date grouping
    
    if ($openLog) {
        // Clock OUT
        $updStmt = $pdo->prepare("UPDATE attendance_logs SET clock_out = ?, status = 'CLOSED', updated_at = NOW() WHERE id = ?");
        $updStmt->execute([$utcNow, $openLog['id']]);
        
        Audit::log('CLOCK_OUT', "Employee {$employeeId} clocked OUT on device {$deviceId}");
        $action = 'OUT';
    } else {
        // Clock IN
        $insStmt = $pdo->prepare("INSERT INTO attendance_logs (employee_id, branch_id, device_id, clock_in, work_date, source, status) VALUES (?, ?, ?, ?, ?, 'DEVICE', 'OPEN')");
        $insStmt->execute([$employeeId, $branchId, $deviceId, $utcNow, $localDate]);
        
        Audit::log('CLOCK_IN', "Employee {$employeeId} clocked IN on device {$deviceId}");
        $action = 'IN';
    }
    
    $pdo->commit();
    echo json_encode(["success" => true, "action" => $action, "time_utc" => $utcNow]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    exit(json_encode(["error" => "Database error: " . $e->getMessage()]));
}
