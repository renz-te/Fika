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
    
    $stmt = $pdo->prepare("SELECT branch_id, status, processed_by FROM payroll_runs WHERE id = ? FOR UPDATE");
    $stmt->execute([$runId]);
    $run = $stmt->fetch();
    
    if (!$run) {
        throw new Exception("Payroll run not found.");
    }
    
    if ($run['status'] !== 'DRAFT') {
        throw new Exception("Only DRAFT payroll runs can be approved.");
    }
    
    Rbac::assert_branch_access($run['branch_id']);
    
    // Strict Separation of Duties (Maker-Checker principle)
    if ((int)$run['processed_by'] === (int)$user['id']) {
        throw new Exception("Separation of Duties violation: The user who generated the payroll cannot also approve it.");
    }
    
    $pdo->prepare("UPDATE payroll_runs SET status = 'FINALIZED' WHERE id = ?")->execute([$runId]);
    
    Audit::log('PAYROLL_APPROVED', "Payroll Run ID {$runId} approved by User {$user['id']}");
    $pdo->commit();
    
    echo json_encode(["success" => true, "message" => "Payroll run finalized."]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(403);
    exit(json_encode(["error" => $e->getMessage()]));
}
