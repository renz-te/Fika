<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

Auth::requireLogin();
Csrf::requireValid();
Rbac::require_permission('hr.leave.approve');

$input = json_decode(file_get_contents('php://input'), true);
$leaveId = (int) ($input['leave_id'] ?? 0);
$status = trim($input['status'] ?? '');
$user = Auth::user();

if (!$leaveId || !in_array($status, ['APPROVED', 'REJECTED'])) {
    http_response_code(400);
    exit(json_encode(["error" => "Valid Leave ID and Status (APPROVED or REJECTED) are required."]));
}

global $pdo;

try {
    $pdo->beginTransaction();
    
    // Fetch leave request and employee data
    $stmt = $pdo->prepare("
        SELECT lr.*, e.branch_id 
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.id
        WHERE lr.id = ? FOR UPDATE
    ");
    $stmt->execute([$leaveId]);
    $leave = $stmt->fetch();
    
    if (!$leave) {
        throw new Exception("Leave request not found.");
    }
    
    // Check branch scope
    Rbac::assert_branch_access($leave['branch_id']);
    
    // Maker-Checker / Conflict of Interest check
    if ($user['employee_id'] && (int)$user['employee_id'] === (int)$leave['employee_id']) {
        throw new Exception("Conflict of interest: You cannot approve or reject your own leave request.");
    }
    
    if ($leave['status'] !== 'PENDING') {
        throw new Exception("This leave request is already {$leave['status']}.");
    }
    
    // Update
    $updStmt = $pdo->prepare("
        UPDATE leave_requests 
        SET status = ?, decided_by = ?, decided_at = NOW(), updated_at = NOW() 
        WHERE id = ?
    ");
    $updStmt->execute([$status, $user['id'], $leaveId]);
    
    Audit::log('LEAVE_DECIDED', "User {$user['id']} marked leave request {$leaveId} as {$status}.");
    
    $pdo->commit();
    echo json_encode(["success" => true, "message" => "Leave request {$status} successfully."]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    exit(json_encode(["error" => $e->getMessage()]));
}
