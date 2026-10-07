<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/payroll_conflict.php';
header('Content-Type: application/json');

Auth::requireLogin();
Csrf::requireValid();
Rbac::require_permission('payroll.approve');

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
    
    // Payroll conflict and chain validation
    payroll_conflict($user, $run, 'APPROVE');
    
    // Additional check: Does it sum up correctly?
    $itemStmt = $pdo->prepare("
        SELECT 
            SUM(basic_pay) as sum_basic,
            SUM(overtime_pay) as sum_ot,
            SUM(bonus_pay) as sum_bonus,
            SUM(sss_deduction + philhealth_deduction + pagibig_deduction + tax_deduction) as sum_deductions,
            SUM(net_pay) as sum_net
        FROM payroll_items 
        WHERE payroll_run_id = ?
    ");
    $itemStmt->execute([$runId]);
    $totals = $itemStmt->fetch(PDO::FETCH_ASSOC);
    
    $expectedNet = round($totals['sum_basic'] + $totals['sum_ot'] + $totals['sum_bonus'] - $totals['sum_deductions'], 2);
    $actualNet = round($totals['sum_net'], 2);
    
    if (abs($expectedNet - $actualNet) > 0.01) {
        throw new Exception("Payroll totals mismatch: Expected {$expectedNet}, but got {$actualNet}.");
    }
    
    $pdo->prepare("UPDATE payroll_runs SET status = 'APPROVED' WHERE id = ?")->execute([$runId]);
    
    Audit::log('PAYROLL_APPROVED', "Payroll Run ID {$runId} approved by User {$user['id']}");
    $pdo->commit();
    
    echo json_encode(["success" => true, "message" => "Payroll run finalized."]);
} catch (Exception $e) {
    if (isset($runId) && $runId) {
        Audit::log('PAYROLL_APPROVAL_REJECTED', "Payroll Run ID {$runId} approval rejected for User {$user['id']}: " . $e->getMessage());
    }
    $pdo->rollBack();
    http_response_code(403);
    exit(json_encode(["error" => $e->getMessage()]));
}
