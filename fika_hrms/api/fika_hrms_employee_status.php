<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

Auth::requireLogin();
Csrf::requireValid();
Rbac::require_permission('hr.employee.edit');

$input = json_decode(file_get_contents('php://input'), true);
$employeeId = (int) ($input['employee_id'] ?? 0);
$status = trim($input['status'] ?? '');
$reason = trim($input['termination_reason'] ?? '');
$date = trim($input['termination_date'] ?? '');
$user = Auth::user();

if (!$employeeId || empty($status)) {
    http_response_code(400);
    exit(json_encode(["error" => "Employee ID and Status are required."]));
}

if (!in_array($status, ['ACTIVE', 'INACTIVE', 'SEPARATED'])) {
    http_response_code(400);
    exit(json_encode(["error" => "Invalid status."]));
}

if ($status === 'SEPARATED') {
    if (empty($reason) || empty($date)) {
        http_response_code(400);
        exit(json_encode(["error" => "Termination reason and date are absolutely required when separating an employee."]));
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        http_response_code(400);
        exit(json_encode(["error" => "Invalid termination date format."]));
    }
} else {
    $reason = null;
    $date = null;
}

global $pdo;

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("SELECT branch_id, status FROM employees WHERE id = ? FOR UPDATE");
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch();
    
    if (!$employee) {
        throw new Exception("Employee not found.");
    }
    
    Rbac::assert_branch_access($employee['branch_id']);
    
    if ($employee['status'] === $status) {
        throw new Exception("Employee is already in {$status} status.");
    }
    
    $updStmt = $pdo->prepare("UPDATE employees SET status = ?, termination_reason = ?, termination_date = ?, updated_at = NOW() WHERE id = ?");
    $updStmt->execute([$status, $reason, $date, $employeeId]);
    
    Audit::log('EMPLOYEE_STATUS_CHANGED', "Changed Employee {$employeeId} status from {$employee['status']} to {$status}.");
    
    $pdo->commit();
    echo json_encode(["success" => true, "message" => "Status updated successfully."]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    exit(json_encode(["error" => $e->getMessage()]));
}
