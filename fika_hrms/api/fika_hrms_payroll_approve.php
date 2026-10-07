<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

Auth::requireLogin();
Csrf::requireValid();
Rbac::require_permission('payroll.manage');

$input = json_decode(file_get_contents('php://input'), true);
$runId = (int) ($input['payroll_run_id'] ?? 0);
$user = Auth::user();

if (!$runId) {
    http_response_code(400);
    exit(json_encode(["error" => "Payroll Run ID is required."]));
}

global $pdo;

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("SELECT branch_id, scope, status, processed_by FROM payroll_runs WHERE id = ? FOR UPDATE");
    $stmt->execute([$runId]);
    $run = $stmt->fetch();
    
    if (!$run) {
        throw new Exception("Payroll run not found.");
    }
    
    if ($run['status'] !== 'GENERATED') {
        throw new Exception("Only GENERATED payroll runs can be approved.");
    }
    
    // Scoping fallback check
    if ($run['scope'] === 'BRANCH') {
        Rbac::assert_branch_access($run['branch_id']);
    } else {
        Rbac::assert_branch_access(null);
    }
    
    // Strict Separation of Duties (Maker-Checker principle)
    if ((int)$run['processed_by'] === (int)$user['id']) {
        throw new Exception("Separation of Duties violation: The user who generated the payroll cannot also approve it.");
    }
    
    // Cannot contain own pay
    if ($user['employee_id']) {
        $check = $pdo->prepare("SELECT id FROM payroll_items WHERE payroll_run_id = ? AND employee_id = ?");
        $check->execute([$runId, $user['employee_id']]);
        if ($check->fetch()) {
            throw new Exception("Maker-Checker violation: You cannot approve a payroll run that contains your own pay.");
        }
    }
    
    // Additional check: Does it sum up correctly?
    $itemStmt = $pdo->query("SELECT SUM(net_pay) FROM payroll_items WHERE payroll_run_id = $runId");
    $totalNet = (float)$itemStmt->fetchColumn();
    // In a full system, you would sum basic_pay+ot+bonus - deductions and assert it equals total_net here again.
    
    $pdo->prepare("UPDATE payroll_runs SET status = 'APPROVED' WHERE id = ?")->execute([$runId]);
    
    Audit::log('PAYROLL_APPROVED', "Payroll Run ID {$runId} approved by User {$user['id']}");
    $pdo->commit();
    
    echo json_encode(["success" => true, "message" => "Payroll run finalized."]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(403);
    exit(json_encode(["error" => $e->getMessage()]));
}
