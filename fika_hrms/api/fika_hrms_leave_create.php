<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

Auth::requireLogin();
Csrf::requireValid();
// We'll require a basic leave manage permission to file a leave
Rbac::require_permission('hr.leave.manage');

$input = json_decode(file_get_contents('php://input'), true);
$employeeId = (int) ($input['employee_id'] ?? 0);
$type = trim($input['type'] ?? '');
$dateFrom = trim($input['date_from'] ?? '');
$dateTo = trim($input['date_to'] ?? '');
$days = (float) ($input['days'] ?? 0);
$reason = trim($input['reason'] ?? '');

if (!$employeeId || empty($type) || empty($dateFrom) || empty($dateTo) || $days <= 0 || empty($reason)) {
    http_response_code(400);
    exit(json_encode(["error" => "All fields including Days and Reason are strictly required."]));
}

global $pdo;

try {
    $pdo->beginTransaction();
    
    // Validate employee and scope
    $empStmt = $pdo->prepare("SELECT branch_id FROM employees WHERE id = ? AND status = 'ACTIVE'");
    $empStmt->execute([$employeeId]);
    $emp = $empStmt->fetch();
    
    if (!$emp) {
        throw new Exception("Active employee not found.");
    }
    
    Rbac::assert_branch_access($emp['branch_id']);
    
    // Validate leave type exists
    $typeStmt = $pdo->prepare("SELECT code FROM leave_types WHERE code = ?");
    $typeStmt->execute([$type]);
    if (!$typeStmt->fetch()) {
        throw new Exception("Invalid leave type.");
    }
    
    // Prevent overlapping leaves
    $overlap = $pdo->prepare("
        SELECT id FROM leave_requests 
        WHERE employee_id = ? AND status IN ('PENDING', 'APPROVED')
        AND (date_from <= ? AND date_to >= ?)
    ");
    $overlap->execute([$employeeId, $dateTo, $dateFrom]);
    if ($overlap->fetch()) {
        throw new Exception("This employee already has a pending or approved leave overlapping these dates.");
    }
    
    // Insert
    $stmt = $pdo->prepare("
        INSERT INTO leave_requests (employee_id, type, date_from, date_to, days, reason, status) 
        VALUES (?, ?, ?, ?, ?, ?, 'PENDING')
    ");
    $stmt->execute([$employeeId, $type, $dateFrom, $dateTo, $days, $reason]);
    
    $pdo->commit();
    echo json_encode(["success" => true, "message" => "Leave request filed successfully."]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    exit(json_encode(["error" => $e->getMessage()]));
}
