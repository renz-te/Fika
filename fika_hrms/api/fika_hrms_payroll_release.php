<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/payroll_conflict.php';
header('Content-Type: application/json');

Auth::requireLogin();
Csrf::requireValid();
Rbac::require_permission('payroll.release');

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
    
    if ($run['status'] !== 'APPROVED') {
        throw new Exception("Only APPROVED payroll runs can be released.");
    }
    
    // Scoping fallback check
    if ($run['scope'] === 'BRANCH') {
        Rbac::assert_branch_access($run['branch_id']);
    } else {
        Rbac::assert_branch_access(null);
    }
    
    $user = Auth::user();
    
    // Payroll conflict and chain validation
    payroll_conflict($user, $run, 'RELEASE');
    
    $pdo->prepare("UPDATE payroll_runs SET status = 'RELEASED' WHERE id = ?")->execute([$runId]);
    
    Audit::log('PAYROLL_RELEASED', "Payroll Run ID {$runId} released/paid by Finance.");
    $pdo->commit();
    
    echo json_encode(["success" => true, "message" => "Payroll released successfully."]);
} catch (Exception $e) {
    if (isset($runId) && $runId) {
        Audit::log('PAYROLL_RELEASE_REJECTED', "Payroll Run ID {$runId} release rejected for User {$user['id']}: " . $e->getMessage());
    }
    $pdo->rollBack();
    http_response_code(403);
    exit(json_encode(["error" => $e->getMessage()]));
}
