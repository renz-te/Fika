<?php
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/lib/attendance_summary.php';
Auth::requireLogin();
Rbac::require_permission('payroll.view');

$runId = (int)($_GET['id'] ?? 0);
if (!$runId) {
    die("Invalid ID");
}

global $pdo;

// Branch Scoping for the RUN
list($scopeSql, $params) = Rbac::branch_scope('pr');
$params[] = $runId;
$stmt = $pdo->prepare("
    SELECT pr.*, b.name as branch_name, u.username as processed_by_username
    FROM payroll_runs pr
    LEFT JOIN branches b ON pr.branch_id = b.id
    LEFT JOIN users u ON pr.processed_by = u.id
    WHERE 1=1 $scopeSql AND pr.id = ?
");
$stmt->execute($params);
$run = $stmt->fetch();

if (!$run) {
    die("Run not found or access denied.");
}

// Fetch items
$itemStmt = $pdo->prepare("
    SELECT pi.*, e.employee_code, e.first_name, e.last_name, e.employment_type, e.basic_salary
    FROM payroll_items pi
    JOIN employees e ON pi.employee_id = e.id
    WHERE pi.payroll_run_id = ?
    ORDER BY e.last_name, e.first_name
");
$itemStmt->execute([$runId]);
$items = $itemStmt->fetchAll();

$hasMismatch = false;
$mismatchLogs = [];

// Totals
$totals = [
    'basic_pay' => 0,
    'overtime_pay' => 0,
    'bonus_pay' => 0,
    'sss' => 0,
    'philhealth' => 0,
    'pagibig' => 0,
    'tax' => 0,
    'net_pay' => 0
];

foreach ($items as $it) {
    $gross = (float)$it['basic_pay'] + (float)$it['overtime_pay'] + (float)$it['bonus_pay'];
    $deductions = (float)$it['sss_deduction'] + (float)$it['philhealth_deduction'] + (float)$it['pagibig_deduction'] + (float)$it['tax_deduction'];
    $computedNet = round($gross - $deductions, 2);
    $actualNet = round((float)$it['net_pay'], 2);
    
    if (abs($computedNet - $actualNet) > 0.01) {
        $hasMismatch = true;
        $mismatchLogs[] = "Mismatch for Emp ID {$it['employee_id']}: Computed Net ($computedNet) != Stored Net ($actualNet)";
        Audit::log('PAYROLL_MISMATCH', "Run ID $runId, Emp ID {$it['employee_id']}: Computed $computedNet vs Stored $actualNet");
    }
    
    $totals['basic_pay'] += (float)$it['basic_pay'];
    $totals['overtime_pay'] += (float)$it['overtime_pay'];
    $totals['bonus_pay'] += (float)$it['bonus_pay'];
    $totals['sss'] += (float)$it['sss_deduction'];
    $totals['philhealth'] += (float)$it['philhealth_deduction'];
    $totals['pagibig'] += (float)$it['pagibig_deduction'];
    $totals['tax'] += (float)$it['tax_deduction'];
    $totals['net_pay'] += (float)$it['net_pay'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Review Payroll Run - Fika HRMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800 font-sans">
    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        
        <div class="mb-6 flex items-center justify-between">
            <div>
                <div class="mb-2 flex items-center space-x-4">
                    <a href="fika_hrms_payroll.php" class="text-blue-600 hover:underline">&larr; Back to Payroll Runs</a>
                    <?php if ($run['status'] === 'RELEASED' && Rbac::can('payroll.export')): ?>
                        <a href="api/fika_hrms_payroll_export.php?run_id=<?= $run['id'] ?>" target="_blank" class="text-gray-600 hover:underline text-sm font-bold">Export CSV</a>
                    <?php endif; ?>
                </div>
                <h1 class="text-3xl font-bold text-gray-900">Review Payroll Run #<?= $runId ?></h1>
                <p class="mt-2 text-sm text-gray-600">
                    Scope: <span class="font-bold"><?= e($run['scope']) ?></span> | 
                    Branch: <span class="font-bold"><?= $run['branch_name'] ? e($run['branch_name']) : 'N/A' ?></span> | 
                    Period: <span class="font-bold"><?= e($run['period_start']) ?> to <?= e($run['period_end']) ?></span> |
                    Status: <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800"><?= e($run['status']) ?></span>
                </p>
            </div>
        </div>
        
        <?php if ($hasMismatch): ?>
            <div class="mb-4 p-4 bg-red-100 text-red-800 rounded-lg shadow font-bold border border-red-300">
                ⚠️ MATH MISMATCH DETECTED: The sum of items does not equal the net pay. This run must not be approved!
                <ul class="list-disc pl-5 mt-2 font-normal text-sm">
                    <?php foreach ($mismatchLogs as $log): ?>
                        <li><?= e($log) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Basic Pay</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">OT / Bonus</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Gross</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">SSS / PH / PI</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">W. Tax</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Net Pay</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($items as $it): 
                            $gross = (float)$it['basic_pay'] + (float)$it['overtime_pay'] + (float)$it['bonus_pay'];
                            $isNegative = (float)$it['net_pay'] < 0;
                            $isZeroBasic = (float)$it['basic_pay'] == 0;
                            
                            $rowClass = '';
                            if ($isNegative) $rowClass = 'bg-red-50';
                            else if ($isZeroBasic) $rowClass = 'bg-yellow-50';
                            
                            $summary = attendance_summary($it['employee_id'], $run['period_start'], $run['period_end']);
                            $absentDays = $summary['absences'] + $summary['unpaid_leave_days'];
                            $lateMins = $summary['late_minutes'];
                            $otMins = $summary['ot_minutes'];
                        ?>
                        <tr class="hover:bg-gray-50 <?= $rowClass ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= e($it['first_name'] . ' ' . $it['last_name']) ?></div>
                                <div class="text-sm text-gray-500"><?= e($it['employee_code']) ?> (<?= e($it['employment_type']) ?>)</div>
                                <div class="text-xs text-gray-400 mt-1">Base: ₱ <?= number_format($it['basic_salary'], 2) ?></div>
                                <?php if ($isNegative): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 mt-1">Negative Net</span>
                                <?php endif; ?>
                                <?php if ($isZeroBasic): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 mt-1">Zero Basic Pay</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <div class="text-sm text-gray-900">₱ <?= number_format($it['basic_pay'], 2) ?></div>
                                <div class="text-xs text-red-500" title="Absent Days">Abs: <?= $absentDays ?>d</div>
                                <div class="text-xs text-red-500" title="Late Minutes">Late: <?= $lateMins ?>m</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <div class="text-sm text-gray-900" title="OT">₱ <?= number_format($it['overtime_pay'], 2) ?></div>
                                <div class="text-xs text-blue-500" title="OT Minutes">OT: <?= $otMins ?>m</div>
                                <div class="text-sm text-gray-500" title="Bonus">₱ <?= number_format($it['bonus_pay'], 2) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-bold text-gray-900">
                                ₱ <?= number_format($gross, 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <div title="SSS">₱ <?= number_format($it['sss_deduction'], 2) ?></div>
                                <div title="PhilHealth">₱ <?= number_format($it['philhealth_deduction'], 2) ?></div>
                                <div title="Pag-IBIG">₱ <?= number_format($it['pagibig_deduction'], 2) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-red-600">
                                ₱ <?= number_format($it['tax_deduction'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-bold <?= $isNegative ? 'text-red-700' : 'text-green-700' ?>">
                                <div>₱ <?= number_format($it['net_pay'], 2) ?></div>
                                <?php if ($run['status'] !== 'DRAFT'): ?>
                                    <div class="mt-1"><a href="fika_hrms_payslip.php?item=<?= $it['id'] ?>" target="_blank" class="text-xs text-blue-600 hover:underline">View Payslip</a></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if(empty($items)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-4 text-center text-gray-500">No items found for this run.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="bg-gray-100 font-bold text-gray-900 border-t-2 border-gray-300">
                        <tr>
                            <td class="px-6 py-4 text-right uppercase text-sm">Totals</td>
                            <td class="px-6 py-4 text-right text-sm">₱ <?= number_format($totals['basic_pay'], 2) ?></td>
                            <td class="px-6 py-4 text-right text-sm text-gray-600">
                                <div>₱ <?= number_format($totals['overtime_pay'], 2) ?></div>
                                <div>₱ <?= number_format($totals['bonus_pay'], 2) ?></div>
                            </td>
                            <td class="px-6 py-4 text-right text-sm text-blue-700">
                                ₱ <?= number_format($totals['basic_pay'] + $totals['overtime_pay'] + $totals['bonus_pay'], 2) ?>
                            </td>
                            <td class="px-6 py-4 text-right text-sm text-gray-600">
                                <div>₱ <?= number_format($totals['sss'], 2) ?></div>
                                <div>₱ <?= number_format($totals['philhealth'], 2) ?></div>
                                <div>₱ <?= number_format($totals['pagibig'], 2) ?></div>
                            </td>
                            <td class="px-6 py-4 text-right text-sm text-red-700">₱ <?= number_format($totals['tax'], 2) ?></td>
                            <td class="px-6 py-4 text-right text-sm text-green-800 text-lg">₱ <?= number_format($totals['net_pay'], 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

    </div>
</body>
</html>
