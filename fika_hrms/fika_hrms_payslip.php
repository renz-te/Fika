<?php
require __DIR__ . '/../app/bootstrap.php';
class_exists('Output'); // Force load the class to get e() helper
Auth::requireLogin();
Rbac::require_permission('payroll.view');

$itemId = (int)($_GET['item'] ?? 0);
if (!$itemId) {
    die("Invalid item ID");
}

global $pdo;

// Branch Scoping: Apply to the employee's branch to ensure a branch manager can't view an item for another branch
list($scopeSql, $params) = Rbac::branch_scope('e');
$params[] = $itemId;

$stmt = $pdo->prepare("
    SELECT pi.*, 
           pr.period_start, pr.period_end, pr.status as run_status, pr.id as run_id, pr.scope as run_scope,
           e.first_name, e.last_name, e.employee_code, e.employment_type, e.basic_salary, e.position,
           e.sss_no_enc as sss, e.philhealth_no_enc as philhealth, e.pagibig_no_enc as pagibig, e.tin_enc as tin, e.bank_no_enc as bank_account,
           b.name as branch_name
    FROM payroll_items pi
    JOIN payroll_runs pr ON pi.payroll_run_id = pr.id
    JOIN employees e ON pi.employee_id = e.id
    LEFT JOIN branches b ON e.branch_id = b.id
    WHERE 1=1 $scopeSql AND pi.id = ?
");
$stmt->execute($params);
$slip = $stmt->fetch();

if (!$slip) {
    http_response_code(403);
    die("Payslip not found or access denied.");
}

$canViewSensitive = Rbac::can('hr.employee.view_sensitive');

function format_sensitive($encryptedValue, $canViewSensitive) {
    if (!$encryptedValue) return 'N/A';
    try {
        $decrypted = Crypto::decrypt($encryptedValue);
    } catch (Exception $e) {
        $decrypted = $encryptedValue; // Fallback if plain text in tests
    }
    
    if (!$decrypted) return 'N/A';
    
    if ($canViewSensitive) {
        return e($decrypted);
    }
    
    // Mask all but last 4
    if (strlen($decrypted) <= 4) return '****';
    return str_repeat('*', strlen($decrypted) - 4) . substr($decrypted, -4);
}

$sss = format_sensitive($slip['sss'], $canViewSensitive);
$ph = format_sensitive($slip['philhealth'], $canViewSensitive);
$pgb = format_sensitive($slip['pagibig'], $canViewSensitive);
$tin = format_sensitive($slip['tin'], $canViewSensitive);
$bank = format_sensitive($slip['bank_account'], $canViewSensitive);

$gross = (float)$slip['basic_pay'] + (float)$slip['overtime_pay'] + (float)$slip['bonus_pay'];
$deductions = (float)$slip['sss_deduction'] + (float)$slip['philhealth_deduction'] + (float)$slip['pagibig_deduction'] + (float)$slip['tax_deduction'];
$net = (float)$slip['net_pay'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payslip - <?= e($slip['first_name'] . ' ' . $slip['last_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @page { size: A5 landscape; margin: 10mm; }
        @media print {
            body { font-size: 12pt; background-color: white; }
            .no-print { display: none !important; }
            .print-border { border: 2px solid #000; padding: 15px; }
            .bg-gray-100 { background-color: #f3f4f6 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .text-red-700 { color: #b91c1c !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .text-green-800 { color: #166534 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
        .a5-container {
            width: 210mm;
            min-height: 148mm;
            margin: auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        @media print { .a5-container { box-shadow: none; width: 100%; min-height: auto; margin: 0; } }
    </style>
</head>
<body class="bg-gray-200 p-8 font-sans text-gray-900">

    <div class="mb-4 text-center no-print">
        <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded shadow font-bold hover:bg-blue-700">Print Payslip</button>
        <a href="fika_hrms_payroll_run.php?id=<?= $slip['run_id'] ?>" class="ml-4 text-gray-600 hover:underline">Back to Run</a>
    </div>

    <div class="a5-container p-6 print-border">
        <div class="flex justify-between items-center mb-6 border-b-2 border-gray-800 pb-4">
            <div>
                <h1 class="text-2xl font-black uppercase tracking-wider text-gray-900">Fika Cafe</h1>
                <p class="text-xs text-gray-500 uppercase tracking-widest mt-1">Payslip Document</p>
            </div>
            <div class="text-right text-sm">
                <p class="font-bold">Period: <?= e($slip['period_start']) ?> to <?= e($slip['period_end']) ?></p>
                <p class="text-gray-600">Run Ref: PR-<?= str_pad($slip['run_id'], 5, '0', STR_PAD_LEFT) ?> (<?= e($slip['run_status']) ?>)</p>
                <p class="text-gray-600">Generated on: <?= date('Y-m-d') ?></p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-6">
            <div>
                <p class="text-xs text-gray-500 uppercase">Employee Details</p>
                <p class="font-bold text-lg"><?= e($slip['first_name'] . ' ' . $slip['last_name']) ?></p>
                <p class="text-sm">ID: <?= e($slip['employee_code']) ?> | <?= e($slip['employment_type']) ?></p>
                <p class="text-sm">Pos: <?= e($slip['position'] ?? 'Staff') ?> | Branch: <?= e($slip['branch_name'] ?? 'HQ') ?></p>
                <p class="text-sm mt-1">Bank Acc: <span class="font-mono"><?= $bank ?></span></p>
            </div>
            <div class="text-sm bg-gray-50 p-3 rounded border border-gray-200">
                <p class="text-xs text-gray-500 uppercase mb-1">Statutory IDs</p>
                <div class="grid grid-cols-2 gap-2">
                    <div><span class="text-gray-500">TIN:</span> <span class="font-mono"><?= $tin ?></span></div>
                    <div><span class="text-gray-500">SSS:</span> <span class="font-mono"><?= $sss ?></span></div>
                    <div><span class="text-gray-500">PH:</span> <span class="font-mono"><?= $ph ?></span></div>
                    <div><span class="text-gray-500">PI:</span> <span class="font-mono"><?= $pgb ?></span></div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-8 mb-6">
            <!-- Earnings -->
            <div>
                <p class="font-bold border-b border-gray-400 pb-1 mb-2">Earnings</p>
                <div class="flex justify-between text-sm mb-1">
                    <span>Basic Pay</span>
                    <span>₱ <?= number_format($slip['basic_pay'], 2) ?></span>
                </div>
                <div class="flex justify-between text-sm mb-1">
                    <span>Overtime</span>
                    <span>₱ <?= number_format($slip['overtime_pay'], 2) ?></span>
                </div>
                <div class="flex justify-between text-sm mb-1">
                    <span>Bonus / Adjustments</span>
                    <span>₱ <?= number_format($slip['bonus_pay'], 2) ?></span>
                </div>
                <div class="flex justify-between font-bold mt-2 pt-2 border-t border-gray-300">
                    <span>Gross Earnings</span>
                    <span>₱ <?= number_format($gross, 2) ?></span>
                </div>
            </div>

            <!-- Deductions -->
            <div>
                <p class="font-bold border-b border-gray-400 pb-1 mb-2">Deductions</p>
                <div class="flex justify-between text-sm mb-1">
                    <span>SSS Contribution</span>
                    <span class="text-red-700">₱ <?= number_format($slip['sss_deduction'], 2) ?></span>
                </div>
                <div class="flex justify-between text-sm mb-1">
                    <span>PhilHealth</span>
                    <span class="text-red-700">₱ <?= number_format($slip['philhealth_deduction'], 2) ?></span>
                </div>
                <div class="flex justify-between text-sm mb-1">
                    <span>Pag-IBIG</span>
                    <span class="text-red-700">₱ <?= number_format($slip['pagibig_deduction'], 2) ?></span>
                </div>
                <div class="flex justify-between text-sm mb-1">
                    <span>Withholding Tax</span>
                    <span class="text-red-700">₱ <?= number_format($slip['tax_deduction'], 2) ?></span>
                </div>
                <div class="flex justify-between font-bold mt-2 pt-2 border-t border-gray-300">
                    <span>Total Deductions</span>
                    <span class="text-red-700">₱ <?= number_format($deductions, 2) ?></span>
                </div>
            </div>
        </div>

        <!-- Net Pay -->
        <div class="bg-gray-100 p-4 rounded flex justify-between items-center border border-gray-300">
            <span class="text-lg font-bold uppercase tracking-widest text-gray-700">Net Take Home Pay</span>
            <span class="text-2xl font-black text-green-800">₱ <?= number_format($net, 2) ?></span>
        </div>
        
        <div class="mt-8 pt-4 border-t border-dashed border-gray-400 text-xs text-center text-gray-500">
            This is a computer-generated document. No signature is required.
        </div>
    </div>

</body>
</html>
