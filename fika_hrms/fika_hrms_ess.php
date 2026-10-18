<?php
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/lib/attendance_calc.php';
class_exists('Output');

Auth::requireLogin();

global $pdo;

$sessEmpId = (int)($_SESSION['user']['employee_id'] ?? 0);
$csrfToken = Csrf::getToken();

if (!$sessEmpId) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Employee Self-Service - Fika</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
        <div class="bg-white p-8 rounded-xl shadow-md max-w-md w-full text-center">
            <div class="w-16 h-16 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <h2 class="text-xl font-bold text-gray-800 mb-2">Account Not Linked</h2>
            <p class="text-gray-600 text-sm mb-6">Your user account is not currently linked to an employee profile. Please contact your manager or HR administrator.</p>
            <a href="fika_hrms_dashboard.php" class="inline-block bg-blue-600 text-white px-5 py-2.5 rounded-lg font-medium hover:bg-blue-700 transition">Return to Dashboard</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 1. Fetch Employee Profile
$stmt = $pdo->prepare("
    SELECT e.*, b.name as branch_name, r.name as role_name
    FROM employees e
    LEFT JOIN branches b ON e.branch_id = b.id
    LEFT JOIN users u ON u.employee_id = e.id
    LEFT JOIN roles r ON u.role_id = r.id
    WHERE e.id = ?
");
$stmt->execute([$sessEmpId]);
$employee = $stmt->fetch();

if (!$employee) {
    http_response_code(404);
    die("Employee profile record not found.");
}

// Constraint 5: Decrypt bank account and mask all but last 4 digits
$rawBankEnc = $employee['bank_no_enc'] ?? '';
$bankDecrypted = $rawBankEnc ? Crypto::decrypt($rawBankEnc) : '';
if (!empty($bankDecrypted)) {
    $maskedBank = '****' . substr($bankDecrypted, -4);
} else {
    $maskedBank = 'Not Set';
}

// 2. Fetch Released Payslips Only
$slipStmt = $pdo->prepare("
    SELECT pi.id, pi.basic_pay, pi.overtime_pay, pi.bonus_pay, pi.gross_pay, pi.net_pay,
           (pi.sss_deduction + pi.philhealth_deduction + pi.pagibig_deduction + pi.tax_deduction) as total_deductions,
           pr.period_start, pr.period_end, pr.id as run_id, pr.status as run_status
    FROM payroll_items pi
    JOIN payroll_runs pr ON pi.payroll_run_id = pr.id
    WHERE pi.employee_id = ? AND pr.status = 'RELEASED'
    ORDER BY pr.period_start DESC
");
$slipStmt->execute([$sessEmpId]);
$myPayslips = $slipStmt->fetchAll();

// 3. Fetch Attendance Logs for current month
$firstDay = date('Y-m-01');
$lastDay = date('Y-m-t');

$graceMins = 15;
$setStmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'late_grace_minutes'");
if ($val = $setStmt->fetchColumn()) {
    $graceMins = (int)$val;
}

$attStmt = $pdo->prepare("
    SELECT * FROM attendance_logs 
    WHERE employee_id = ? 
      AND work_date BETWEEN ? AND ?
    ORDER BY work_date DESC, clock_in DESC
");
$attStmt->execute([$sessEmpId, $firstDay, $lastDay]);
$attendanceLogs = $attStmt->fetchAll();

$monthWorkedMins = 0;
$monthLateMins = 0;
$daysPresent = count($attendanceLogs);

$computedLogs = [];
foreach ($attendanceLogs as $log) {
    $calc = AttendanceCalc::calculate($log['clock_in'], $log['clock_out'], $log['clock_in'], $graceMins);
    $monthWorkedMins += $calc['worked_minutes'];
    $monthLateMins += $calc['late_minutes'];
    $computedLogs[] = [
        'log' => $log,
        'calc' => $calc
    ];
}
$monthTotalHours = round($monthWorkedMins / 60, 1);

// 4. Leave Balances and Requests
$leaveTypes = $pdo->query("SELECT * FROM leave_types ORDER BY name ASC")->fetchAll();
$leaveStmt = $pdo->prepare("
    SELECT lr.*, lt.name as type_name
    FROM leave_requests lr
    LEFT JOIN leave_types lt ON lr.type = lt.code
    WHERE lr.employee_id = ?
    ORDER BY lr.created_at DESC
");
$leaveStmt->execute([$sessEmpId]);
$myLeaves = $leaveStmt->fetchAll();

$sickLeaveBalance = (int)($employee['sick_leave_balance'] ?? 0);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Self-Service (ESS) - Fika</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">

    <!-- Top Navigation -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-black text-xl shadow-sm">
                    F
                </div>
                <div>
                    <h1 class="font-bold text-lg text-slate-900 leading-tight">Employee Self-Service</h1>
                    <p class="text-xs text-slate-500">Welcome, <?= e($employee['first_name'] . ' ' . $employee['last_name']) ?> (<?= e($employee['employee_code']) ?>)</p>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <a href="fika_hrms_dashboard.php" class="text-sm font-medium text-slate-600 hover:text-indigo-600 transition flex items-center gap-1.5">
                    <i class="fa-solid fa-arrow-left text-xs"></i> Back to Dashboard
                </a>
                <span class="text-slate-300">|</span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <i class="fa-solid fa-circle text-[6px] mr-1.5 animate-pulse"></i> <?= e($employee['status']) ?>
                </span>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Tab Navigation Bar -->
        <div class="flex border-b border-slate-200 mb-6 space-x-1 sm:space-x-4">
            <button onclick="switchTab('profile')" id="tabBtn-profile" class="tab-btn px-4 py-3 text-sm font-semibold border-b-2 border-indigo-600 text-indigo-600 flex items-center gap-2">
                <i class="fa-solid fa-user"></i> My Profile
            </button>
            <button onclick="switchTab('payslips')" id="tabBtn-payslips" class="tab-btn px-4 py-3 text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-file-invoice-dollar"></i> My Payslips
                <?php if (count($myPayslips) > 0): ?>
                    <span class="ml-1.5 bg-slate-200 text-slate-700 text-xs px-2 py-0.5 rounded-full"><?= count($myPayslips) ?></span>
                <?php endif; ?>
            </button>
            <button onclick="switchTab('attendance')" id="tabBtn-attendance" class="tab-btn px-4 py-3 text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-clock"></i> Attendance
            </button>
            <button onclick="switchTab('leave')" id="tabBtn-leave" class="tab-btn px-4 py-3 text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-plane-departure"></i> Leave & Time Off
            </button>
        </div>

        <!-- Global Notifications -->
        <div id="toastAlert" class="hidden mb-6 p-4 rounded-xl shadow-sm text-sm font-medium transition-all"></div>

        <!-- 1. PROFILE TAB -->
        <div id="tabContent-profile" class="tab-content">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Left: Read-Only Info Card -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center space-x-4 mb-6 pb-6 border-b border-slate-100">
                            <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-indigo-500 to-purple-600 flex items-center justify-center text-white text-2xl font-black shadow-inner">
                                <?= strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <h3 class="font-bold text-lg text-slate-900"><?= e($employee['first_name'] . ' ' . $employee['last_name']) ?></h3>
                                <p class="text-xs text-slate-500"><?= e($employee['position'] ?? 'Staff') ?> &bull; <?= e($employee['department'] ?? 'Operations') ?></p>
                                <p class="text-xs font-mono text-indigo-600 font-semibold mt-0.5"><?= e($employee['employee_code']) ?></p>
                            </div>
                        </div>

                        <div class="space-y-4 text-sm">
                            <div class="flex justify-between items-center py-1">
                                <span class="text-slate-500">Branch</span>
                                <span class="font-medium text-slate-800"><?= e($employee['branch_name'] ?? 'HQ') ?></span>
                            </div>
                            <div class="flex justify-between items-center py-1">
                                <span class="text-slate-500">Employment Type</span>
                                <span class="font-medium text-slate-800"><?= e($employee['employment_type']) ?></span>
                            </div>
                            <div class="flex justify-between items-center py-1">
                                <span class="text-slate-500">Official Role</span>
                                <span class="font-medium text-slate-800"><?= e($employee['role_name'] ?? 'Employee') ?></span>
                            </div>
                            <div class="flex justify-between items-center py-1">
                                <span class="text-slate-500">Basic Salary</span>
                                <span class="font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-100">
                                    ₱ <?= number_format($employee['basic_salary'], 2) ?>
                                </span>
                            </div>
                            <div class="flex justify-between items-center py-1">
                                <span class="text-slate-500">Date Hired</span>
                                <span class="font-medium text-slate-800"><?= e($employee['date_hired'] ?? 'N/A') ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 pt-4 border-t border-slate-100 space-y-3">
                        <button onclick="openPasswordModal()" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-800 font-semibold py-2.5 px-4 rounded-xl text-sm transition flex items-center justify-center gap-2">
                            <i class="fa-solid fa-key text-slate-500"></i> Change Password
                        </button>
                        <p class="text-[11px] text-slate-400 text-center">Salary, Role, and Branch can only be modified by HR & Central Admin.</p>
                    </div>
                </div>

                <!-- Right: Whitelisted Profile Update Form -->
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <div class="border-b border-slate-100 pb-4 mb-6">
                        <h2 class="text-lg font-bold text-slate-900">Personal & Contact Details</h2>
                        <p class="text-xs text-slate-500 mt-1">Keep your contact details up to date for official notices and payroll disbursements.</p>
                    </div>

                    <form id="profileForm" onsubmit="handleProfileSubmit(event)" class="space-y-5">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-600 mb-1.5">Phone Number</label>
                                <input type="text" id="profPhone" name="phone" value="<?= e($employee['phone'] ?? '') ?>" placeholder="+63 912 345 6789" class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-600 mb-1.5">Emergency Contact</label>
                                <input type="text" id="profEmergency" name="emergency_contact" value="<?= e($employee['emergency_contact'] ?? '') ?>" placeholder="Name & Contact (e.g. Maria 09187654321)" class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-600 mb-1.5">Home Address</label>
                            <textarea id="profAddress" name="address" rows="3" placeholder="Enter complete residential address" class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"><?= e($employee['address'] ?? '') ?></textarea>
                        </div>

                        <div class="bg-indigo-50/50 p-4 rounded-xl border border-indigo-100 space-y-3">
                            <div class="flex justify-between items-center">
                                <div>
                                    <label class="block text-xs font-bold uppercase text-indigo-950">Disbursement Bank Account</label>
                                    <p class="text-xs text-indigo-700/80">Encrypted with AES-256-GCM. Currently: <strong class="font-mono text-indigo-900"><?= e($maskedBank) ?></strong></p>
                                </div>
                                <span class="text-xs text-indigo-600 bg-white px-2 py-1 rounded shadow-sm border border-indigo-200">
                                    <i class="fa-solid fa-lock text-[10px] mr-1"></i> Encrypted
                                </span>
                            </div>
                            <div>
                                <input type="text" id="profBankAccount" name="bank_account" placeholder="Enter new bank account number to update" class="w-full border border-indigo-200 bg-white rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                                <p class="text-[11px] text-slate-500 mt-1">Leave blank if you do not want to change your existing bank account.</p>
                            </div>
                        </div>

                        <div class="flex justify-end pt-3">
                            <button type="submit" id="btnSaveProfile" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-2.5 rounded-xl shadow-sm text-sm transition flex items-center gap-2">
                                <i class="fa-solid fa-floppy-disk"></i> Save Profile
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>

        <!-- 2. PAYSLIPS TAB -->
        <div id="tabContent-payslips" class="tab-content hidden">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Released Payslips</h2>
                        <p class="text-xs text-slate-500 mt-1">Only official released payroll runs are accessible for your review and printing.</p>
                    </div>
                    <span class="text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1 rounded-full">
                        <?= count($myPayslips) ?> Record(s) Available
                    </span>
                </div>

                <?php if (empty($myPayslips)): ?>
                    <div class="p-12 text-center text-slate-400">
                        <i class="fa-solid fa-receipt text-4xl mb-3 text-slate-300"></i>
                        <p class="font-medium text-base text-slate-700">No released payslips found</p>
                        <p class="text-xs text-slate-500 mt-1">When your branch manager or accountant releases a payroll run, your payslip will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-slate-500 font-semibold text-xs uppercase tracking-wider">
                                <tr>
                                    <th class="px-6 py-3.5 text-left">Period Covered</th>
                                    <th class="px-6 py-3.5 text-right">Basic Pay</th>
                                    <th class="px-6 py-3.5 text-right">Gross Pay</th>
                                    <th class="px-6 py-3.5 text-right">Total Deductions</th>
                                    <th class="px-6 py-3.5 text-right">Net Take-Home</th>
                                    <th class="px-6 py-3.5 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($myPayslips as $slip): ?>
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="px-6 py-4 font-medium text-slate-900">
                                            <?= date('M d, Y', strtotime($slip['period_start'])) ?> &mdash; <?= date('M d, Y', strtotime($slip['period_end'])) ?>
                                            <div class="text-[11px] text-slate-400">Run Ref: PR-<?= str_pad($slip['run_id'], 5, '0', STR_PAD_LEFT) ?></div>
                                        </td>
                                        <td class="px-6 py-4 text-right font-medium text-slate-700">
                                            ₱ <?= number_format($slip['basic_pay'], 2) ?>
                                        </td>
                                        <td class="px-6 py-4 text-right font-medium text-slate-700">
                                            ₱ <?= number_format($slip['gross_pay'], 2) ?>
                                        </td>
                                        <td class="px-6 py-4 text-right font-medium text-red-600">
                                            - ₱ <?= number_format($slip['total_deductions'], 2) ?>
                                        </td>
                                        <td class="px-6 py-4 text-right font-bold text-emerald-700">
                                            ₱ <?= number_format($slip['net_pay'], 2) ?>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <a href="fika_hrms_payslip.php?item=<?= (int)$slip['id'] ?>" target="_blank" class="inline-flex items-center gap-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-semibold px-3 py-1.5 rounded-lg text-xs transition border border-indigo-200">
                                                <i class="fa-solid fa-eye"></i> View Payslip
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 3. ATTENDANCE TAB -->
        <div id="tabContent-attendance" class="tab-content hidden">
            <!-- Stat Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-6">
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase font-bold text-slate-400">Total Hours Worked</p>
                        <h3 class="text-2xl font-black text-slate-900 mt-1"><?= $monthTotalHours ?> <span class="text-xs font-normal text-slate-500">hrs</span></h3>
                        <p class="text-[11px] text-slate-400 mt-1"><?= date('F Y') ?></p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl">
                        <i class="fa-solid fa-business-time"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase font-bold text-slate-400">Late Minutes</p>
                        <h3 class="text-2xl font-black <?= $monthLateMins > 0 ? 'text-amber-600' : 'text-emerald-600' ?> mt-1">
                            <?= $monthLateMins ?> <span class="text-xs font-normal text-slate-500">mins</span>
                        </h3>
                        <p class="text-[11px] text-slate-400 mt-1">Grace Period: <?= $graceMins ?> mins</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl <?= $monthLateMins > 0 ? 'bg-amber-50 text-amber-600' : 'bg-emerald-50 text-emerald-600' ?> flex items-center justify-center text-xl">
                        <i class="fa-solid fa-stopwatch"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase font-bold text-slate-400">Days Logged</p>
                        <h3 class="text-2xl font-black text-slate-900 mt-1"><?= $daysPresent ?> <span class="text-xs font-normal text-slate-500">days</span></h3>
                        <p class="text-[11px] text-slate-400 mt-1">Current Billing Cycle</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>
                </div>
            </div>

            <!-- Attendance Logs Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Monthly Attendance History</h2>
                        <p class="text-xs text-slate-500 mt-1"><?= date('F 1, Y') ?> &mdash; <?= date('F t, Y') ?></p>
                    </div>
                </div>

                <?php if (empty($computedLogs)): ?>
                    <div class="p-12 text-center text-slate-400">
                        <i class="fa-regular fa-calendar-xmark text-4xl mb-3 text-slate-300"></i>
                        <p class="font-medium text-base text-slate-700">No attendance records for this month</p>
                        <p class="text-xs text-slate-500 mt-1">Logs recorded via kiosk terminal will automatically appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-slate-500 font-semibold text-xs uppercase tracking-wider">
                                <tr>
                                    <th class="px-6 py-3.5 text-left">Work Date</th>
                                    <th class="px-6 py-3.5 text-left">Clock In</th>
                                    <th class="px-6 py-3.5 text-left">Clock Out</th>
                                    <th class="px-6 py-3.5 text-right">Late (m)</th>
                                    <th class="px-6 py-3.5 text-right">Total Hours</th>
                                    <th class="px-6 py-3.5 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($computedLogs as $item): 
                                    $l = $item['log'];
                                    $c = $item['calc'];
                                    $hrs = floor($c['worked_minutes'] / 60);
                                    $mins = $c['worked_minutes'] % 60;
                                    $workedDisplay = "{$hrs}h {$mins}m";
                                ?>
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="px-6 py-4 font-semibold text-slate-900">
                                            <?= date('D, M d, Y', strtotime($l['work_date'])) ?>
                                        </td>
                                        <td class="px-6 py-4 text-emerald-700 font-medium">
                                            <i class="fa-solid fa-arrow-right-to-bracket text-xs mr-1 text-emerald-500"></i>
                                            <?= date('h:i A', strtotime($l['clock_in'])) ?>
                                        </td>
                                        <td class="px-6 py-4 font-medium <?= $l['clock_out'] ? 'text-rose-700' : 'text-slate-400' ?>">
                                            <?php if ($l['clock_out']): ?>
                                                <i class="fa-solid fa-arrow-right-from-bracket text-xs mr-1 text-rose-500"></i>
                                                <?= date('h:i A', strtotime($l['clock_out'])) ?>
                                            <?php else: ?>
                                                <span class="italic text-xs">Active Shift</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-right font-medium <?= $c['late_minutes'] > 0 ? 'text-amber-600 font-bold' : 'text-slate-400' ?>">
                                            <?= $c['late_minutes'] > 0 ? $c['late_minutes'] . ' min' : '-' ?>
                                        </td>
                                        <td class="px-6 py-4 text-right font-bold text-slate-800">
                                            <?= $l['clock_out'] ? $workedDisplay : '-' ?>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                                                <?= $l['status'] === 'OPEN' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                                                   ($l['status'] === 'ADJUSTED' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-slate-100 text-slate-700') ?>">
                                                <?= e($l['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 4. LEAVE TAB -->
        <div id="tabContent-leave" class="tab-content hidden">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                
                <!-- Balances Card -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-slate-900">Leave Balance</h3>
                        <span class="text-xs bg-indigo-50 text-indigo-700 font-bold px-2.5 py-1 rounded-full border border-indigo-200">Annual</span>
                    </div>

                    <div class="p-4 bg-emerald-50 rounded-xl border border-emerald-100 mb-4">
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-bold text-emerald-800 uppercase">Sick Leave Balance</span>
                            <span class="text-xl font-black text-emerald-700"><?= $sickLeaveBalance ?> days</span>
                        </div>
                    </div>

                    <div class="space-y-2 text-xs">
                        <p class="font-bold text-slate-500 uppercase tracking-wider mb-2">Available Statutory Types:</p>
                        <?php foreach ($leaveTypes as $lt): ?>
                            <div class="flex justify-between items-center py-1 border-b border-slate-50">
                                <span class="text-slate-600 font-medium"><?= e($lt['name']) ?></span>
                                <span class="text-slate-400 font-mono"><?= $lt['annual_days'] > 0 ? $lt['annual_days'] . 'd' : 'Custom' ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <button onclick="openLeaveModal()" class="w-full mt-6 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-4 rounded-xl text-sm transition flex items-center justify-center gap-2 shadow-sm">
                        <i class="fa-solid fa-plus"></i> File Leave Request
                    </button>
                </div>

                <!-- Past Requests List -->
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                        <div>
                            <h3 class="font-bold text-slate-900">My Leave Requests</h3>
                            <p class="text-xs text-slate-500 mt-1">Track pending and approved requests.</p>
                        </div>
                        <span class="text-xs bg-slate-100 text-slate-600 font-semibold px-2.5 py-1 rounded-full">
                            <?= count($myLeaves) ?> Record(s)
                        </span>
                    </div>

                    <?php if (empty($myLeaves)): ?>
                        <div class="p-12 text-center text-slate-400">
                            <i class="fa-solid fa-umbrella-beach text-4xl mb-3 text-slate-300"></i>
                            <p class="font-medium text-base text-slate-700">No leave requests filed</p>
                            <p class="text-xs text-slate-500 mt-1">Use the "File Leave Request" button to submit a time-off application.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-slate-500 font-semibold text-xs uppercase tracking-wider">
                                    <tr>
                                        <th class="px-6 py-3.5 text-left">Type</th>
                                        <th class="px-6 py-3.5 text-left">Date Range</th>
                                        <th class="px-6 py-3.5 text-right">Days</th>
                                        <th class="px-6 py-3.5 text-left">Reason</th>
                                        <th class="px-6 py-3.5 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($myLeaves as $l): ?>
                                        <tr class="hover:bg-slate-50/80 transition">
                                            <td class="px-6 py-4 font-bold text-slate-900">
                                                <?= e($l['type_name'] ?? $l['type']) ?>
                                            </td>
                                            <td class="px-6 py-4 text-slate-600 text-xs">
                                                <?= date('M d, Y', strtotime($l['date_from'])) ?> &mdash; <?= date('M d, Y', strtotime($l['date_to'])) ?>
                                            </td>
                                            <td class="px-6 py-4 text-right font-medium text-slate-800">
                                                <?= $l['days'] ?>
                                            </td>
                                            <td class="px-6 py-4 text-slate-600 text-xs max-w-xs truncate" title="<?= e($l['reason']) ?>">
                                                <?= e($l['reason']) ?>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                                                    <?= $l['status'] === 'APPROVED' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                                                       ($l['status'] === 'PENDING' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-rose-50 text-rose-700 border border-rose-200') ?>">
                                                    <?= e($l['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

    </div>

    <!-- CHANGE PASSWORD MODAL -->
    <div id="passwordModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 max-w-md w-full overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                <h3 class="font-bold text-slate-900 text-lg">Change Your Password</h3>
                <button onclick="closePasswordModal()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form id="passwordForm" onsubmit="handlePasswordSubmit(event)" class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Current Password</label>
                    <input type="password" id="currentPassword" required class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-600 mb-1">New Password (Min 8 characters)</label>
                    <input type="password" id="newPassword" minlength="8" required class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Confirm New Password</label>
                    <input type="password" id="confirmPassword" minlength="8" required class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div class="flex justify-end gap-3 pt-3">
                    <button type="button" onclick="closePasswordModal()" class="px-4 py-2 text-slate-600 hover:text-slate-800 text-sm font-medium">Cancel</button>
                    <button type="submit" id="btnSavePassword" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-5 py-2 rounded-xl text-sm transition">Update Password</button>
                </div>
            </form>
        </div>
    </div>

    <!-- FILE LEAVE MODAL -->
    <div id="leaveModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 max-w-lg w-full overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                <h3 class="font-bold text-slate-900 text-lg">File Leave Request</h3>
                <button onclick="closeLeaveModal()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form id="leaveForm" onsubmit="handleLeaveSubmit(event)" enctype="multipart/form-data" class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Leave Type</label>
                    <select id="leaveType" name="type" required class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <?php foreach ($leaveTypes as $lt): ?>
                            <option value="<?= e($lt['code']) ?>"><?= e($lt['name']) ?> (<?= $lt['code'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Start Date</label>
                        <input type="date" id="leaveDateFrom" name="date_from" required class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1">End Date</label>
                        <input type="date" id="leaveDateTo" name="date_to" required class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Medical Certificate / Supporting File (Optional)</label>
                    <input type="file" id="leaveMedCert" name="med_cert" accept=".pdf,image/jpeg,image/png" class="w-full border border-slate-200 rounded-xl p-2 text-xs text-slate-600 file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    <p class="text-[11px] text-slate-400 mt-1">Accepted: PDF, JPG, PNG up to 5MB.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Reason for Leave</label>
                    <textarea id="leaveReason" name="reason" rows="3" required placeholder="Describe the reason for your time-off request..." class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-3">
                    <button type="button" onclick="closeLeaveModal()" class="px-4 py-2 text-slate-600 hover:text-slate-800 text-sm font-medium">Cancel</button>
                    <button type="submit" id="btnSubmitLeave" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-5 py-2 rounded-xl text-sm transition">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('border-indigo-600', 'text-indigo-600');
                btn.classList.add('border-transparent', 'text-slate-500');
            });
            document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));

            const activeBtn = document.getElementById('tabBtn-' + tab);
            const activeContent = document.getElementById('tabContent-' + tab);
            if (activeBtn && activeContent) {
                activeBtn.classList.remove('border-transparent', 'text-slate-500');
                activeBtn.classList.add('border-indigo-600', 'text-indigo-600');
                activeContent.classList.remove('hidden');
            }
        }

        function showAlert(msg, isSuccess = true) {
            const el = document.getElementById('toastAlert');
            el.className = 'mb-6 p-4 rounded-xl shadow-sm text-sm font-medium border ' + 
                (isSuccess ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200');
            el.innerHTML = '<i class="fa-solid ' + (isSuccess ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600') + ' mr-2"></i>' + msg;
            el.classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
            setTimeout(() => { el.classList.add('hidden'); }, 6000);
        }

        // Profile Form
        async function handleProfileSubmit(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSaveProfile');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Saving...';

            const payload = {
                action: 'update_profile',
                csrf_token: csrfToken,
                phone: document.getElementById('profPhone').value,
                emergency_contact: document.getElementById('profEmergency').value,
                address: document.getElementById('profAddress').value
            };
            const bank = document.getElementById('profBankAccount').value.trim();
            if (bank !== '') {
                payload.bank_account = bank;
            }

            try {
                const res = await fetch('api/fika_hrms_ess_save.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    showAlert(data.message || 'Profile saved successfully.', true);
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showAlert(data.error || 'Failed to update profile.', false);
                }
            } catch (err) {
                showAlert('Network error: ' + err.message, false);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Profile';
            }
        }

        // Change Password Modal
        function openPasswordModal() {
            document.getElementById('passwordModal').classList.remove('hidden');
        }
        function closePasswordModal() {
            document.getElementById('passwordModal').classList.add('hidden');
        }

        async function handlePasswordSubmit(e) {
            e.preventDefault();
            const cur = document.getElementById('currentPassword').value;
            const np = document.getElementById('newPassword').value;
            const cp = document.getElementById('confirmPassword').value;

            if (np !== cp) {
                alert('New password and confirmation do not match.');
                return;
            }

            const btn = document.getElementById('btnSavePassword');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Updating...';

            try {
                const res = await fetch('api/fika_hrms_ess_password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        csrf_token: csrfToken,
                        current_password: cur,
                        new_password: np,
                        confirm_password: cp
                    })
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    closePasswordModal();
                    document.getElementById('passwordForm').reset();
                    showAlert(data.message || 'Password changed successfully.', true);
                } else {
                    alert(data.error || 'Failed to change password.');
                }
            } catch (err) {
                alert('Network error: ' + err.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'Update Password';
            }
        }

        // Leave Modal
        function openLeaveModal() {
            document.getElementById('leaveModal').classList.remove('hidden');
        }
        function closeLeaveModal() {
            document.getElementById('leaveModal').classList.add('hidden');
        }

        async function handleLeaveSubmit(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSubmitLeave');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Submitting...';

            const form = document.getElementById('leaveForm');
            const formData = new FormData(form);
            formData.append('action', 'request_leave');
            formData.append('csrf_token', csrfToken);

            try {
                const res = await fetch('api/fika_hrms_ess_save.php', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: formData
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    closeLeaveModal();
                    form.reset();
                    showAlert(data.message || 'Leave request filed successfully.', true);
                    setTimeout(() => location.reload(), 1200);
                } else {
                    alert(data.error || 'Failed to file leave request.');
                }
            } catch (err) {
                alert('Network error: ' + err.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'Submit Request';
            }
        }
    </script>
</body>
</html>
