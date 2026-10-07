<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/payroll/payroll_engine.php';
header('Content-Type: application/json');

Auth::requireLogin();
Csrf::requireValid();
Rbac::require_permission('payroll.generate');

require_once __DIR__ . '/../../app/lib/attendance_summary.php';

$input = json_decode(file_get_contents('php://input'), true);
$scope = $input['scope'] ?? '';
$branchId = (int) ($input['branch_id'] ?? 0);
$periodStart = $input['period_start'] ?? '';
$periodEnd = $input['period_end'] ?? '';
$cutoffNumber = (int) ($input['cutoff_number'] ?? 1);
$payeeId = (int) ($input['payee_employee_id'] ?? 0);

if (empty($scope) || empty($periodStart) || empty($periodEnd) || !in_array($cutoffNumber, [1, 2])) {
    http_response_code(400);
    exit(json_encode(["error" => "Missing or invalid required fields."]));
}

if ($scope === 'BRANCH' && !$branchId) {
    http_response_code(400);
    exit(json_encode(["error" => "Branch is required for BRANCH scope."]));
}

if ($scope === 'HQ' && !$payeeId) {
    http_response_code(400);
    exit(json_encode(["error" => "Payee Employee ID is required for HQ scope."]));
}

if ($scope === 'BRANCH') {
    Rbac::assert_branch_access($branchId);
} else {
    // OFFICIALS and HQ scopes require global view
    Rbac::assert_branch_access(null); 
}

global $pdo;

try {
    $pdo->beginTransaction();
    
    // Check if payroll already exists for this scope & period
    $chkSql = "SELECT id FROM payroll_runs WHERE scope = ? AND period_start = ? AND period_end = ?";
    $chkParams = [$scope, $periodStart, $periodEnd];
    if ($scope === 'BRANCH') {
        $chkSql .= " AND branch_id = ?";
        $chkParams[] = $branchId;
    } else if ($scope === 'HQ') {
        $chkSql .= " AND payee_employee_id = ?";
        $chkParams[] = $payeeId;
    }
    
    $chkStmt = $pdo->prepare($chkSql);
    $chkStmt->execute($chkParams);
    if ($chkStmt->fetch()) {
        throw new Exception("A payroll run already exists for this scope and period.");
    }
    
    if ($scope === 'HQ') {
        // Validate payee exists and is HQ
        $payeeStmt = $pdo->prepare("SELECT id FROM employees WHERE id = ? AND staff_class = 'HQ' AND status = 'ACTIVE'");
        $payeeStmt->execute([$payeeId]);
        if (!$payeeStmt->fetch()) {
            throw new Exception("Invalid or inactive HQ employee.");
        }
    }
    
    // Check if attendance is certified
    $certSql = "SELECT id FROM attendance_certifications WHERE scope = ? AND period_start = ? AND period_end = ? AND status = 'CERTIFIED'";
    $certParams = [$scope, $periodStart, $periodEnd];
    if ($scope === 'BRANCH') {
        $certSql .= " AND branch_id = ?";
        $certParams[] = $branchId;
    }
    
    $certStmt = $pdo->prepare($certSql);
    $certStmt->execute($certParams);
    if (!$certStmt->fetch()) {
        throw new Exception("Attendance for this period must be certified before payroll can be generated.");
    }
    
    $user = Auth::user();
    
    // Create Run
    $runStmt = $pdo->prepare("INSERT INTO payroll_runs (scope, branch_id, payee_employee_id, period_start, period_end, status, processed_by, created_at) VALUES (?, ?, ?, ?, ?, 'GENERATED', ?, NOW())");
    $runStmt->execute([$scope, $scope === 'BRANCH' ? $branchId : null, $scope === 'HQ' ? $payeeId : null, $periodStart, $periodEnd, $user['id']]);
    $runId = $pdo->lastInsertId();
    
    // Get Employees
    $empSql = "SELECT id, basic_salary, employment_type FROM employees WHERE status = 'ACTIVE' AND deleted_at IS NULL";
    $empParams = [];
    if ($scope === 'BRANCH') {
        $empSql .= " AND branch_id = ? AND staff_class = 'CREW'";
        $empParams[] = $branchId;
    } else if ($scope === 'OFFICIALS') {
        $empSql .= " AND staff_class = 'OFFICIAL'"; 
    } else {
        $empSql .= " AND id = ? AND staff_class = 'HQ'";
        $empParams[] = $payeeId;
    }
    
    $empStmt = $pdo->prepare($empSql);
    $empStmt->execute($empParams);
    $employees = $empStmt->fetchAll();
    
    if (empty($employees)) {
        throw new Exception("No active employees found for this scope.");
    }
    
    // Maker-Checker validation
    $employeeIds = array_column($employees, 'id');
    if ($user['employee_id'] && in_array($user['employee_id'], $employeeIds)) {
        throw new Exception("Maker-Checker violation: You cannot generate a payroll run that contains your own pay.");
    }
    
    // Check overlaps
    $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
    $overlapSql = "
        SELECT pi.employee_id 
        FROM payroll_items pi
        JOIN payroll_runs pr ON pi.payroll_run_id = pr.id
        WHERE pi.employee_id IN ($placeholders)
        AND pr.status != 'CLEARED'
        AND pr.period_start <= ? AND pr.period_end >= ?
    ";
    $overlapParams = array_merge($employeeIds, [$periodEnd, $periodStart]);
    $overlapStmt = $pdo->prepare($overlapSql);
    $overlapStmt->execute($overlapParams);
    if ($overlap = $overlapStmt->fetch()) {
        throw new Exception("Overlap detected: Employee #{$overlap['employee_id']} is already in a non-cleared run that overlaps this period.");
    }
    
    $itemStmt = $pdo->prepare("
        INSERT INTO payroll_items 
        (payroll_run_id, employee_id, basic_pay, overtime_pay, bonus_pay, sss_deduction, philhealth_deduction, pagibig_deduction, tax_deduction, net_pay, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    foreach ($employees as $emp) {
        $summary = attendance_summary($emp['id'], $periodStart, $periodEnd);
        
        $payType = strtoupper($emp['employment_type']) === 'PART_TIME' ? 'HOURLY' : 'MONTHLY';
        
        $absentDays = $summary['absences'] + $summary['unpaid_leave_days'];
        $totalHours = $summary['days_worked'] * 8; // Standard 8-hour days
        $otHours = $summary['ot_minutes'] / 60.0;
        
        $slip = PayrollEngine::calculate_payslip(
            monthlyBasePayCentavos: Money::toCentavos($emp['basic_salary']),
            hoursWorked: $totalHours,
            overtimeHours: $otHours,
            standardHoursPerPeriod: 104,
            bonusCentavos: 0,
            deductContributions: $payType === 'MONTHLY',
            payType: $payType,
            absentDays: $absentDays,
            lateMinutes: $summary['late_minutes'],
            cutoffNumber: $cutoffNumber
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
