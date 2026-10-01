<?php
require_once __DIR__ . '/init.php';
require_login();
$user = current_user();

if (!in_array($user['role'], ['Admin', 'Super Admin', 'HR', 'HR Admin'])) {
    die("Access denied.");
}

$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'generate_drafts') {
        $periodParts = explode('|', $_POST['payroll_period']);
        $periodStart = $periodParts[0];
        $periodEnd = $periodParts[1];
        $branchFilter = get_branch_filter();
        $employees = $pdo->query('SELECT *, CONCAT(first_name, " ",   last_name) AS full_name FROM employees WHERE status IN ("Active", "Trainee")' . $branchFilter)->fetchAll();
        $generated = 0;
        
        foreach ($employees as $emp) {
            // Check if draft already exists
            $check = $pdo->prepare('SELECT id FROM payroll WHERE employee_id = ? AND period_start = ? AND period_end = ?');
            $check->execute([$emp['id'], $periodStart, $periodEnd]);
            if ($check->fetchColumn()) continue;
            
            $grossPay = 0;
            $deductions = 0;
            $absentDays = 0;
            $absentDeduction = 0;
            
            // Unified Hourly Wage Engine
            // 1. Calculate Total Hours Worked
            $attStmt = $pdo->prepare('SELECT TIMESTAMPDIFF(MINUTE, time_in, time_out) as mins FROM attendance WHERE employee_id = ? AND DATE(time_in) BETWEEN ? AND ? AND time_out IS NOT NULL');
            $attStmt->execute([$emp['id'], $periodStart, $periodEnd]);
            $totalMins = 0;
            while ($row = $attStmt->fetch()) {
                $totalMins += (int)$row['mins'];
            }
            $totalHours = $totalMins / 60;
            
            // 2. Count Approved Leave Days
            $leaveDays = 0;
            $leaveStmt = $pdo->prepare('SELECT start_date, end_date FROM leaves WHERE employee_id = ? AND status = "Approved" AND (start_date <= ? AND end_date >= ?)');
            $leaveStmt->execute([$emp['id'], $periodEnd, $periodStart]);
            $leaves = $leaveStmt->fetchAll();
            foreach ($leaves as $l) {
                $current = strtotime(max($periodStart, $l['start_date']));
                $endLeave = strtotime(min($periodEnd, $l['end_date']));
                while ($current <= $endLeave) {
                    // Only count weekdays (Mon-Fri) if needed, but for simplicity assuming all calendar days of leave count
                    $leaveDays++;
                    $current = strtotime('+1 day', $current);
                }
            }
            
            // 3. Calculate Gross Pay based strictly on hours + leaves
            $grossPay = ($totalHours + ($leaveDays * 8)) * (float)$emp['hourly_rate'];
            $absentDeduction = 0; // Deductions are implicit by not paying for unworked hours
            
            $taxableIncome = $grossPay;
            
            // Statutory Deductions (applied proportionally if gross pay exists)
            $philhealth = 0;
            $sss = 0;
            $pagibig = 0;
            $tax = 0;
            
            if ($taxableIncome > 0) {
                $philhealth = $taxableIncome * 0.025; // 2.5% PhilHealth Employee Share
                $sss = $taxableIncome * 0.045; // 4.5% SSS Employee Share
                $pagibig = 100.00; // Flat 100/cutoff
                
                // Income Tax (Simplified Bracket: Tax-free under 10,417 per cutoff)
                if ($taxableIncome > 10417) {
                    $tax = ($taxableIncome - 10417) * 0.15;
                }
            }
            
            $totalDeductions = $absentDeduction + $philhealth + $sss + $pagibig + $tax;
            
            // Fetch pending bonuses
            $bonusStmt = $pdo->prepare('SELECT id, amount FROM pending_bonuses WHERE employee_id = ? AND status = "Pending"');
            $bonusStmt->execute([$emp['id']]);
            $pendingBonuses = $bonusStmt->fetchAll();
            $bonusAmount = 0;
            $bonusIds = [];
            foreach ($pendingBonuses as $pb) {
                $bonusAmount += (float)$pb['amount'];
                $bonusIds[] = $pb['id'];
            }
            
            $netPay = max(0, $grossPay - $totalDeductions) + $bonusAmount;
            
            $stmt = $pdo->prepare('INSERT INTO payroll (employee_id, hourly_rate, absent_deduction, tax, sss, philhealth, pagibig, gross_pay, bonus_amount, deductions, net_pay, status, period_start, period_end, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "Draft", ?, ?, NOW())');
            $stmt->execute([$emp['id'], $emp['hourly_rate'], $absentDeduction, $tax, $sss, $philhealth, $pagibig, $grossPay, $bonusAmount, $totalDeductions, $netPay, $periodStart, $periodEnd]);
            
            // Mark pending bonuses as Processed
            if (!empty($bonusIds)) {
                $inQuery = implode(',', array_fill(0, count($bonusIds), '?'));
                $updateBonus = $pdo->prepare("UPDATE pending_bonuses SET status = 'Processed' WHERE id IN ($inQuery)");
                $updateBonus->execute($bonusIds);
            }
            
            $generated++;
        }
        
        $message = "Generated $generated payroll drafts for period $periodStart to $periodEnd.";
    } elseif ($action === 'clear_drafts') {
        $periodParts = explode('|', $_POST['payroll_period']);
        $periodStart = $periodParts[0];
        $periodEnd = $periodParts[1];
        
        $stmt = $pdo->prepare('DELETE FROM payroll WHERE period_start = ? AND period_end = ? AND status = "Draft"');
        $stmt->execute([$periodStart, $periodEnd]);
        $deleted = $stmt->rowCount();
        
        $message = "Cleared $deleted draft(s) for period $periodStart to $periodEnd.";
    } elseif ($action === 'approve') {
        $payrollId = $_POST['payroll_id'];
        $pdo->prepare('UPDATE payroll SET status = "Approved" WHERE id = ?')->execute([$payrollId]);
        $message = "Payroll Draft Approved.";
    }
    
    if ($action === 'release') {
        $payrollId = $_POST['payroll_id'];
        $paymentMethod = trim($_POST['payment_method']);
        $pdo->prepare('UPDATE payroll SET status = "Released", payment_method = ? WHERE id = ?')->execute([$paymentMethod, $payrollId]);
        
        // Simulate email
        $payroll = $pdo->prepare('SELECT p.*, e.email FROM payroll p JOIN employees e ON e.id = p.employee_id WHERE p.id = ?');
        $payroll->execute([$payrollId]);
        $p = $payroll->fetch();
        
        $message = "Payroll Released via {$paymentMethod}. Payslip email sent to {$p['email']}.";
    }
}

// Fetch Payrolls
$branchFilterE = get_branch_filter('e');
$drafts = $pdo->query('SELECT p.*, CONCAT(e.first_name, " ",   e.last_name) AS full_name, e.employment_category FROM payroll p JOIN employees e ON e.id = p.employee_id WHERE p.status = "Draft" ' . $branchFilterE . ' ORDER BY p.created_at DESC')->fetchAll();
$approved = $pdo->query('SELECT p.*, CONCAT(e.first_name, " ",   e.last_name) AS full_name, e.employment_category FROM payroll p JOIN employees e ON e.id = p.employee_id WHERE p.status = "Approved" ' . $branchFilterE . ' ORDER BY p.created_at DESC')->fetchAll();
$released = $pdo->query('SELECT p.*, CONCAT(e.first_name, " ",   e.last_name) AS full_name, e.employment_category FROM payroll p JOIN employees e ON e.id = p.employee_id WHERE p.status = "Released" ' . $branchFilterE . ' ORDER BY p.created_at DESC LIMIT 50')->fetchAll();

$currentTab = $_GET['tab'] ?? 'drafts';

require_once __DIR__ . '/includes/header.php';
?>

<div class="px-6 py-4 border-b border-slate-200 flex space-x-6 bg-white">
    <a href="?tab=drafts" class="font-medium px-1 py-2 border-b-2 transition <?= $currentTab === 'drafts' ? 'border-primary text-primary' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">Drafts <span class="ml-1 bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full text-xs"><?= count($drafts) ?></span></a>
    <a href="?tab=approved" class="font-medium px-1 py-2 border-b-2 transition <?= $currentTab === 'approved' ? 'border-primary text-primary' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">Approved <span class="ml-1 bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full text-xs"><?= count($approved) ?></span></a>
    <a href="?tab=released" class="font-medium px-1 py-2 border-b-2 transition <?= $currentTab === 'released' ? 'border-primary text-primary' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">Released History</a>
</div>

<div class="p-6">
    <?php if ($message): ?>
        <div class="mb-6 bg-emerald-50 text-emerald-700 p-4 rounded-lg flex items-center shadow-sm border border-emerald-100">
            <i class="fa-solid fa-check-circle mr-3 text-lg"></i>
            <?= h($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($currentTab === 'drafts'): ?>
    <!-- Generation Panel -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-8">
        <h2 class="text-lg font-bold text-slate-800 mb-4"><i class="fa-solid fa-calculator text-primary mr-2"></i> Generate Payroll Run</h2>
        <form autocomplete="off" method="POST" action="payroll" class="flex items-end gap-4">
            <input type="hidden" name="action" value="generate_drafts">
            
            <div class="flex-1">
                <label class="block text-sm font-medium text-slate-700 mb-1">Select Payroll Period</label>
                <select name="payroll_period" required class="w-full rounded border-slate-300 p-2 focus:ring-primary focus:border-primary">
                    <?php
                    $currentY = (int)date('Y');
                    $currentM = (int)date('m');
                    for ($i = 0; $i < 3; $i++) {
                        $m = $currentM - $i;
                        $y = $currentY;
                        if ($m <= 0) { $m += 12; $y--; }
                        $mStr = str_pad($m, 2, '0', STR_PAD_LEFT);
                        
                        $start1 = "$y-$mStr-01";
                        $end1 = "$y-$mStr-15";
                        $label1 = date('M 01, Y', strtotime($start1)) . " to " . date('M 15, Y', strtotime($end1));
                        
                        $start2 = "$y-$mStr-16";
                        $end2 = date('Y-m-t', strtotime($start1));
                        $label2 = date('M 16, Y', strtotime($start2)) . " to " . date('M t, Y', strtotime($end2));
                        
                        echo "<option value=\"$start2|$end2\">$label2</option>";
                        echo "<option value=\"$start1|$end1\">$label1</option>";
                    }
                    ?>
                </select>
            </div>
            <div>
                <?php if (count($drafts) > 0): ?>
                    <button type="button" disabled title="Clear existing drafts first before calculating new ones." class="bg-slate-300 text-slate-500 cursor-not-allowed px-6 py-2 rounded font-medium shadow-sm h-[42px]">
                        Calculate Drafts
                    </button>
                <?php else: ?>
                    <button type="submit" name="action" value="generate_drafts" class="bg-primary hover:bg-indigo-700 text-white px-6 py-2 rounded font-medium shadow-sm transition h-[42px]">
                        Calculate Drafts
                    </button>
                <?php endif; ?>
            </div>
            <div>
                <button type="submit" name="action" value="clear_drafts" onclick="return confirmFormButton(event, 'Are you sure you want to clear all drafts for this period?')" class="bg-red-500 hover:bg-red-600 text-white px-6 py-2 rounded font-medium shadow-sm transition h-[42px]">
                    Clear Drafts
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Payroll Lists -->
    <?php
    $displayList = [];
    if ($currentTab === 'drafts') $displayList = $drafts;
    if ($currentTab === 'approved') $displayList = $approved;
    if ($currentTab === 'released') $displayList = $released;
    ?>
    
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full text-left text-sm whitespace-nowrap">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-700">
                    <th class="p-4 font-semibold">Employee</th>
                    <th class="p-4 font-semibold">Period</th>
                    <th class="p-4 font-semibold">Base / Gross Pay</th>
                    <th class="p-4 font-semibold">Deductions</th>
                    <th class="p-4 font-semibold text-right">Net Pay</th>
                    <th class="p-4 font-semibold text-center">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($displayList)): ?>
                    <tr>
                        <td colspan="6" class="p-8 text-center text-slate-500 italic">No payroll records found in this stage.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($displayList as $row): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="p-4">
                                <div class="font-bold text-slate-800"><?= h($row['full_name']) ?></div>
                                <div class="text-xs text-slate-500"><?= h($row['employment_category']) ?></div>
                            </td>
                            <td class="p-4 text-slate-600">
                                <?= date('M d', strtotime($row['period_start'])) ?> - <?= date('M d', strtotime($row['period_end'])) ?>
                            </td>
                            <td class="p-4 text-slate-700">
                                ₱<?= number_format($row['gross_pay'], 2) ?>
                                <?php if (isset($row['bonus_amount']) && $row['bonus_amount'] > 0): ?>
                                    <br><span class="text-xs text-indigo-600 font-bold">+ ₱<?= number_format($row['bonus_amount'], 2) ?> Bonus</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-red-600 cursor-help" title="Absences: ₱<?= number_format($row['absent_deduction'], 2) ?>&#10;SSS: ₱<?= number_format($row['sss'], 2) ?>&#10;PhilHealth: ₱<?= number_format($row['philhealth'], 2) ?>&#10;Pag-IBIG: ₱<?= number_format($row['pagibig'], 2) ?>&#10;Income Tax: ₱<?= number_format($row['tax'], 2) ?>">
                                -₱<?= number_format($row['deductions'], 2) ?> <i class="fa-solid fa-circle-info text-slate-300 ml-1 text-xs"></i>
                            </td>
                            <td class="p-4 font-bold text-emerald-600 text-right text-base">₱<?= number_format($row['net_pay'], 2) ?></td>
                            <td class="p-4 text-center">
                                <?php if ($currentTab === 'drafts'): ?>
                                    <form autocomplete="off" method="POST" action="payroll?tab=drafts">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="payroll_id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="text-primary hover:text-indigo-800 font-medium px-3 py-1 rounded bg-indigo-50 hover:bg-indigo-100 transition">Approve</button>
                                    </form>
                                <?php elseif ($currentTab === 'approved'): ?>
                                    <button type="button" onclick="openReleaseModal(<?= $row['id'] ?>, '<?= h($row['full_name']) ?>')" class="text-emerald-600 hover:text-emerald-800 font-medium px-3 py-1 rounded bg-emerald-50 hover:bg-emerald-100 transition"><i class="fa-solid fa-money-bill-wave mr-1"></i> Release</button>
                                <?php elseif ($currentTab === 'released'): ?>
                                    <span class="text-xs bg-slate-100 text-slate-600 px-2 py-1 rounded font-medium"><i class="fa-solid fa-check mr-1"></i> <?= h($row['payment_method']) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Release Modal -->
<div id="releaseModal" class="fixed inset-0 z-50 bg-black/50 hidden flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="p-4 border-b flex justify-between items-center bg-slate-50">
            <h3 class="font-bold text-slate-800"><i class="fa-solid fa-paper-plane text-emerald-600 mr-2"></i> Release Payroll</h3>
            <button onclick="document.getElementById('releaseModal').classList.add('hidden')" class="text-slate-400 hover:text-red-500"><i class="fa-solid fa-times"></i></button>
        </div>
        <form autocomplete="off" method="POST" action="payroll?tab=approved" class="p-5">
            <input type="hidden" name="action" value="release">
            <input type="hidden" name="payroll_id" id="release_payroll_id">
            
            <p class="text-sm text-slate-600 mb-4">You are releasing the payslip for <strong id="release_emp_name" class="text-slate-800"></strong>. This will finalize the record and send an email notification.</p>
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-slate-700 mb-1">Payment Method</label>
                <select name="payment_method" required class="w-full rounded border-slate-300 p-2 focus:ring-emerald-500 focus:border-emerald-500 bg-slate-100 pointer-events-none">
                    <option value="Cash" selected>Cash (Physical Handover)</option>
                </select>
            </div>
            
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('releaseModal').classList.add('hidden')" class="px-4 py-2 text-slate-600 hover:text-slate-800">Cancel</button>
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded shadow transition font-medium">Confirm Release</button>
            </div>
        </form>
    </div>
</div>

<script>
function openReleaseModal(id, name) {
    document.getElementById('release_payroll_id').value = id;
    document.getElementById('release_emp_name').innerText = name;
    document.getElementById('releaseModal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

