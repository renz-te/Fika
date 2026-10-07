<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

Auth::requireLogin();
Csrf::requireValid();
Rbac::require_permission('payroll.delete');

$input = json_decode(file_get_contents('php://input'), true);
$runId = (int) ($input['payroll_run_id'] ?? 0);

if (!$runId) {
    http_response_code(400);
    exit(json_encode(["error" => "Payroll Run ID is required."]));
}

global $pdo;

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("SELECT branch_id, scope, status FROM payroll_runs WHERE id = ? FOR UPDATE");
    $stmt->execute([$runId]);
    $run = $stmt->fetch();
    
    if (!$run) {
        throw new Exception("Payroll run not found.");
    }
    
    if (!in_array($run['status'], ['DRAFT', 'GENERATED'])) {
        throw new Exception("Only DRAFT or GENERATED payroll runs can be cleared.");
    }
    
    // Scoping fallback check
    if ($run['scope'] === 'BRANCH') {
        Rbac::assert_branch_access($run['branch_id']);
    } else {
        Rbac::assert_branch_access(null);
    }
    
    // Delete items first
    $pdo->prepare("DELETE FROM payroll_items WHERE payroll_run_id = ?")->execute([$runId]);
    // Delete run
    $pdo->prepare("DELETE FROM payroll_runs WHERE id = ?")->execute([$runId]);
    
    Audit::log('PAYROLL_CLEARED', "Payroll Run ID {$runId} was cleared/deleted by User " . Auth::user()['id']);
    $pdo->commit();
    
    echo json_encode(["success" => true, "message" => "Payroll draft cleared."]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(403);
    exit(json_encode(["error" => $e->getMessage()]));
}
