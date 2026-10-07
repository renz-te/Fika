<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

Auth::requireLogin();
Csrf::requireValid();
Rbac::require_permission('hr.attendance.adjust');

$input = json_decode(file_get_contents('php://input'), true);
$logId = (int) ($input['log_id'] ?? 0);
$newClockIn = trim($input['clock_in'] ?? '');
$newClockOut = trim($input['clock_out'] ?? '');
$reason = trim($input['reason'] ?? '');
$user = Auth::user();

if (!$logId || empty($newClockIn) || empty($newClockOut) || empty($reason)) {
    http_response_code(400);
    exit(json_encode(["error" => "Log ID, valid dates, and an Adjustment Reason are strictly required."]));
}

global $pdo;

try {
    $pdo->beginTransaction();
    
    // Fetch original
    $stmt = $pdo->prepare("SELECT a.*, e.branch_id FROM attendance_logs a JOIN employees e ON a.employee_id = e.id WHERE a.id = ? FOR UPDATE");
    $stmt->execute([$logId]);
    $log = $stmt->fetch();
    
    if (!$log) {
        throw new Exception("Attendance log not found.");
    }
    
    Rbac::assert_branch_access($log['branch_id']);
    
    // Ensure inputs are valid UTC times (validate structure simply or just trust parsing)
    if (!strtotime($newClockIn) || !strtotime($newClockOut)) {
        throw new Exception("Invalid date format provided.");
    }

    // Determine employee scope to check if period is certified
    $userStmt = $pdo->prepare("SELECT branch_id FROM users WHERE employee_id = ?");
    $userStmt->execute([$log['employee_id']]);
    $u = $userStmt->fetch();
    $scope = 'BRANCH';
    $empIdForCert = null;
    $branchIdForCert = $log['branch_id'];
    
    if ($u !== false) {
        if ($u['branch_id'] === null) {
            $scope = 'HQ';
            $branchIdForCert = null;
            $empIdForCert = $log['employee_id'];
        } else {
            $scope = 'OFFICIALS';
            $branchIdForCert = null; // officials cert is global
        }
    }

    // 1. Check if locked by a RELEASED payroll run
    $releaseCheck = $pdo->prepare("
        SELECT pr.id FROM payroll_runs pr
        JOIN payroll_items pi ON pr.id = pi.payroll_run_id
        WHERE pi.employee_id = ? AND pr.status = 'RELEASED'
        AND ? BETWEEN pr.period_start AND pr.period_end
    ");
    $releaseCheck->execute([$log['employee_id'], $log['work_date']]);
    if ($releaseCheck->fetch()) {
        throw new Exception("Cannot adjust attendance. A payroll run covering this date has already been released (locked). Reversal flow required.");
    }
    
    // 2. Check certification
    $certStmt = $pdo->prepare("
        SELECT id FROM attendance_certifications 
        WHERE scope = ? AND IFNULL(branch_id, 0) = ? AND IFNULL(employee_id, 0) = ?
        AND status = 'CERTIFIED'
        AND ? BETWEEN period_start AND period_end
    ");
    $certStmt->execute([$scope, $branchIdForCert ?? 0, $empIdForCert ?? 0, $log['work_date']]);
    if ($certStmt->fetch()) {
        throw new Exception("Cannot adjust attendance. The period containing this date is already certified. Please reopen certification first.");
    }
    
    // Update
    $updStmt = $pdo->prepare("
        UPDATE attendance_logs 
        SET clock_in = ?, clock_out = ?, status = 'ADJUSTED', adjusted_by = ?, adjust_reason = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $updStmt->execute([$newClockIn, $newClockOut, $user['id'], $reason, $logId]);
    
    // Audit must keep original values
    $oldIn = $log['clock_in'] ?? 'NULL';
    $oldOut = $log['clock_out'] ?? 'NULL';
    Audit::log('ATTENDANCE_ADJUSTED', "User {$user['id']} adjusted log {$logId}. Original: In={$oldIn}, Out={$oldOut}. New: In={$newClockIn}, Out={$newClockOut}. Reason: {$reason}");
    
    $pdo->commit();
    echo json_encode(["success" => true, "message" => "Attendance adjusted successfully."]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    exit(json_encode(["error" => $e->getMessage()]));
}
