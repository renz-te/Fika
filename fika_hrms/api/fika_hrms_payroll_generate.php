<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/payroll/payroll_engine.php';
header('Content-Type: application/json');

Auth::requireLogin();
Csrf::requireValid();
Rbac::require_permission('payroll.manage');

$input = json_decode(file_get_contents('php://input'), true);
$branchId = (int) ($input['branch_id'] ?? 0);
$periodStart = $input['period_start'] ?? '';
$periodEnd = $input['period_end'] ?? '';

if (!$branchId || empty($periodStart) || empty($periodEnd)) {
    http_response_code(400);
    exit(json_encode(["error" => "Missing required fields."]));
}

Rbac::assert_branch_access($branchId);

global $pdo;

try {
    $pdo->beginTransaction();
    
    // Check if payroll already exists for this branch & period
    $chkStmt = $pdo->prepare("SELECT id FROM payroll_runs WHERE branch_id = ? AND period_start = ? AND period_end = ?");
    $chkStmt->execute([$branchId, $periodStart, $periodEnd]);
    if ($chkStmt->fetch()) {
        throw new Exception("A payroll run already exists for this branch and period.");
    }
    
    // Check if attendance is certified for this BRANCH
    $certStmt = $pdo->prepare("
        SELECT id FROM attendance_certifications 
        WHERE scope = 'BRANCH' AND branch_id = ? AND period_start = ? AND period_end = ? AND status = 'CERTIFIED'
    ");
    $certStmt->execute([$branchId, $periodStart, $periodEnd]);
    if (!$certStmt->fetch()) {
        throw new Exception("Attendance for this period must be certified before payroll can be generated.");
    }
    
    $user = Auth::user();
    
    // Create Run
    $runStmt = $pdo->prepare("INSERT INTO payroll_runs (branch_id, period_start, period_end, status, processed_by, created_at) VALUES (?, ?, ?, 'DRAFT', ?, NOW())");
    $runStmt->execute([$branchId, $periodStart, $periodEnd, $user['id']]);
    $runId = $pdo->lastInsertId();
    
    // Get Employees
    $empStmt = $pdo->prepare("SELECT id, basic_salary FROM employees WHERE branch_id = ? AND status = 'ACTIVE' AND deleted_at IS NULL");
    $empStmt->execute([$branchId]);
    $employees = $empStmt->fetchAll();
    
    if (empty($employees)) {
        throw new Exception("No active employees found for this branch.");
    }
    
    $itemStmt = $pdo->prepare("
        INSERT INTO payroll_items 
        (payroll_run_id, employee_id, basic_pay, overtime_pay, bonus_pay, sss_deduction, philhealth_deduction, pagibig_deduction, tax_deduction, net_pay, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $attStmt = $pdo->prepare("
        SELECT SUM(total_hours) as hours_worked 
        FROM attendance 
        WHERE employee_id = ? AND date BETWEEN ? AND ? AND status = 'Present'
    ");
    
    // Assume 104 standard hours for a typical semi-monthly period (13 days * 8 hours)
    $standardHours = 104; 
    
    foreach ($employees as $emp) {
        $attStmt->execute([$emp['id'], $periodStart, $periodEnd]);
        $hoursWorked = (float) $attStmt->fetchColumn();
        
        $slip = PayrollEngine::calculate_payslip(
            monthlyBasePayCentavos: Money::toCentavos($emp['basic_salary']),
            hoursWorked: $hoursWorked,
            overtimeHours: 0, // In real scenario, fetch OT from attendance/timesheets
            standardHoursPerPeriod: $standardHours,
            bonusCentavos: 0,
            deductContributions: true
        );
        
        $itemStmt->execute([
            $runId,
            $emp['id'],
            Money::toDecimal($slip['basic_pay']),
            Money::toDecimal($slip['overtime_pay']),
            Money::toDecimal($slip['bonus_pay']),
            Money::toDecimal($slip['deductions']['sss']),
            Money::toDecimal($slip['deductions']['philhealth']),
            Money::toDecimal($slip['deductions']['pagibig']),
            Money::toDecimal($slip['deductions']['withholding_tax']),
            Money::toDecimal($slip['net_pay'])
        ]);
    }
    
    Audit::log('PAYROLL_GENERATED', "Payroll Run ID {$runId} generated for Branch {$branchId}");
    $pdo->commit();
    
    echo json_encode(["success" => true, "payroll_run_id" => $runId]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    exit(json_encode(["error" => $e->getMessage()]));
}
