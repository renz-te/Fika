<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

Auth::requireLogin();
Csrf::requireValid();
Rbac::require_permission('finance.manage');

$input = json_decode(file_get_contents('php://input'), true);
$runId = (int) ($input['payroll_run_id'] ?? 0);

if (!$runId) {
    http_response_code(400);
    exit(json_encode(["error" => "Payroll Run ID is required."]));
}

global $pdo;

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("SELECT branch_id, status FROM payroll_runs WHERE id = ? FOR UPDATE");
    $stmt->execute([$runId]);
    $run = $stmt->fetch();
    
    if (!$run) {
        throw new Exception("Payroll run not found.");
    }
    
    if ($run['status'] !== 'FINALIZED') {
        throw new Exception("Only FINALIZED payroll runs can be released/paid.");
    }
    
    Rbac::assert_branch_access($run['branch_id']);
    
    $pdo->prepare("UPDATE payroll_runs SET status = 'PAID' WHERE id = ?")->execute([$runId]);
    
    Audit::log('PAYROLL_RELEASED', "Payroll Run ID {$runId} released/paid by Finance.");
    $pdo->commit();
    
    echo json_encode(["success" => true, "message" => "Payroll released successfully."]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(403);
    exit(json_encode(["error" => $e->getMessage()]));
}
