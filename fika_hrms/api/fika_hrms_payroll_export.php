<?php
require_once __DIR__ . '/../../app/bootstrap.php';

Auth::requireLogin();
Rbac::require_permission('payroll.export');

$runId = (int)($_GET['run_id'] ?? 0);
if (!$runId) {
    http_response_code(400);
    die("Run ID is required");
}

global $pdo;

// Fetch run to check scoping and status
$stmt = $pdo->prepare("SELECT * FROM payroll_runs WHERE id = ?");
$stmt->execute([$runId]);
$run = $stmt->fetch();

if (!$run) {
    http_response_code(404);
    die("Payroll run not found.");
}

if ($run['status'] !== 'RELEASED') {
    http_response_code(403);
    die("Only RELEASED payroll runs can be exported.");
}

// Scoping
if ($run['scope'] === 'BRANCH') {
    Rbac::assert_branch_access($run['branch_id']);
} else {
    Rbac::assert_branch_access(null);
}

// Fetch Items
$itemsStmt = $pdo->prepare("
    SELECT pi.*, e.employee_code, e.first_name, e.last_name, e.employment_type, e.bank_no_enc
    FROM payroll_items pi
    JOIN employees e ON pi.employee_id = e.id
    WHERE pi.payroll_run_id = ?
    ORDER BY e.last_name, e.first_name
");
$itemsStmt->execute([$runId]);
$items = $itemsStmt->fetchAll();

Audit::log('PAYROLL_EXPORTED', "User " . Auth::user()['id'] . " exported payroll run $runId as CSV.");

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="payroll_run_' . $runId . '.csv"');

$output = fopen('php://output', 'w');

// Headers
fputcsv($output, [
    'Employee Code',
    'Last Name',
    'First Name',
    'Employment Type',
    'Bank Account',
    'Basic Pay',
    'Overtime Pay',
    'Bonus Pay',
    'Gross Pay',
    'SSS Deduction',
    'PhilHealth Deduction',
    'Pag-IBIG Deduction',
    'Tax Deduction',
    'Total Deductions',
    'Net Pay'
]);

// Decrypt bank account if needed
$canViewSensitive = Rbac::can('hr.employee.view_sensitive');

foreach ($items as $it) {
    $bank = $it['bank_no_enc'];
    if ($bank) {
        try {
            $bank = Crypto::decrypt($bank);
        } catch (Exception $e) {
            // fallback
        }
        if (!$canViewSensitive) {
            $bank = (strlen($bank) > 4) ? str_repeat('*', strlen($bank) - 4) . substr($bank, -4) : '****';
        }
    } else {
        $bank = 'N/A';
    }

    $gross = (float)$it['basic_pay'] + (float)$it['overtime_pay'] + (float)$it['bonus_pay'];
    $deductions = (float)$it['sss_deduction'] + (float)$it['philhealth_deduction'] + (float)$it['pagibig_deduction'] + (float)$it['tax_deduction'];

    fputcsv($output, [
        $it['employee_code'],
        $it['last_name'],
        $it['first_name'],
        $it['employment_type'],
        $bank,
        number_format($it['basic_pay'], 2, '.', ''),
        number_format($it['overtime_pay'], 2, '.', ''),
        number_format($it['bonus_pay'], 2, '.', ''),
        number_format($gross, 2, '.', ''),
        number_format($it['sss_deduction'], 2, '.', ''),
        number_format($it['philhealth_deduction'], 2, '.', ''),
        number_format($it['pagibig_deduction'], 2, '.', ''),
        number_format($it['tax_deduction'], 2, '.', ''),
        number_format($deductions, 2, '.', ''),
        number_format($it['net_pay'], 2, '.', '')
    ]);
}

fclose($output);
if (php_sapi_name() !== 'cli') {
    exit;
}
