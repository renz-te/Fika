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

$empPos = (string)($employee['position'] ?? '');
$isHB = (stripos($empPos, 'Head Barista') !== false);
$userRoleName = '';
if (!empty($_SESSION['user']['role_id'])) {
    $rStmt = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
    $rStmt->execute([$_SESSION['user']['role_id']]);
    $userRoleName = strtoupper(trim((string)$rStmt->fetchColumn()));
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

$empBranchId = (int)($employee['branch_id'] ?? 0);

// 5. Shift Schedule (Only PUBLISHED shifts appear in ESS portal)
$shiftStmt = $pdo->prepare("
    SELECT s.*, 
           os.id as open_shift_id, os.status as open_status, os.claimed_by
    FROM shifts s
    LEFT JOIN open_shifts os ON os.shift_id = s.id AND os.status IN ('OPEN', 'PENDING_APPROVAL')
    WHERE s.employee_id = ? AND s.status = 'PUBLISHED'
    ORDER BY s.shift_date ASC, s.start_time ASC
");
$shiftStmt->execute([$sessEmpId]);
$myShifts = $shiftStmt->fetchAll();

// Open shifts available in employee's branch
$openShiftsStmt = $pdo->prepare("
    SELECT os.*, s.shift_date, s.start_time, s.end_time, s.shift_type,
           orig.first_name as orig_first, orig.last_name as orig_last, orig.employee_code as orig_code
    FROM open_shifts os
    JOIN shifts s ON os.shift_id = s.id
    JOIN employees orig ON os.original_employee_id = orig.id
    WHERE os.branch_id = ? AND os.status = 'OPEN' AND os.original_employee_id != ?
    ORDER BY s.shift_date ASC, s.start_time ASC
");
$openShiftsStmt->execute([$empBranchId, $sessEmpId]);
$availableOpenShifts = $openShiftsStmt->fetchAll();

// 6. Today's Attendance for Clock In/Out KPI widget
$todayDate = date('Y-m-d');
$todayAttStmt = $pdo->prepare("
    SELECT * FROM attendance_logs 
    WHERE employee_id = ? AND work_date = ? 
    ORDER BY id DESC LIMIT 1
");
$todayAttStmt->execute([$sessEmpId, $todayDate]);
$todayLog = $todayAttStmt->fetch();

$isCurrentlyClockedIn = ($todayLog && empty($todayLog['clock_out']));
$isClockedOutToday = ($todayLog && !empty($todayLog['clock_out']));

// 7. Next upcoming published shift for KPI card
$nextShiftStmt = $pdo->prepare("
    SELECT s.*, b.name as branch_name 
    FROM shifts s
    LEFT JOIN branches b ON s.branch_id = b.id
    WHERE s.employee_id = ? 
      AND s.status = 'PUBLISHED'
      AND s.shift_date >= CURDATE()
    ORDER BY s.shift_date ASC, s.start_time ASC 
    LIMIT 1
");
$nextShiftStmt->execute([$sessEmpId]);
$nextShift = $nextShiftStmt->fetch();

// 8. Leave balance & pending counts for KPI card
$pendingLeaveCount = 0;
foreach ($myLeaves as $lv) {
    if (strtoupper($lv['status'] ?? '') === 'PENDING') {
        $pendingLeaveCount++;
    }
}
$silDays = $sickLeaveBalance > 0 ? $sickLeaveBalance : 14;

// 9. Latest released payslip for KPI card
$latestPayslip = !empty($myPayslips) ? $myPayslips[0] : null;

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
<body class="bg-slate-50 text-slate-800 antialiased h-screen overflow-hidden flex">

    <!-- Role-Based Sidebar Navigation -->
    <aside class="w-64 bg-slate-900 text-slate-200 flex flex-col flex-shrink-0 border-r border-slate-800 z-20">
        <!-- Brand Header -->
        <div class="h-16 flex items-center px-6 border-b border-slate-800 gap-3">
            <div class="w-9 h-9 rounded-xl bg-amber-600 text-white flex items-center justify-center font-black text-lg shadow-sm">
                F
            </div>
            <div class="flex flex-col justify-center overflow-hidden">
                <span class="text-base font-black text-white leading-none tracking-wide">FIKA</span>
                <span class="text-[10px] text-amber-500 font-bold uppercase tracking-widest mt-0.5">Slow Hours</span>
            </div>
        </div>

        <!-- Navigation Links -->
        <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
            <!-- Personal Section (Always visible) -->
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-3 mb-2">Personal</div>
            <a href="#dashboard" id="sideNav-dashboard" class="side-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 hover:text-white hover:bg-slate-800 transition">
                <i class="fa-solid fa-chart-pie w-5 text-center text-amber-500"></i>
                <span>My Dashboard</span>
            </a>
            <a href="#payslips" id="sideNav-payslips" class="side-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 hover:text-white hover:bg-slate-800 transition">
                <i class="fa-solid fa-file-invoice-dollar w-5 text-center text-emerald-400"></i>
                <span>My Payslips</span>
                <?php if (count($myPayslips) > 0): ?>
                    <span class="ml-auto bg-slate-800 text-slate-300 text-xs px-2 py-0.5 rounded-full"><?= count($myPayslips) ?></span>
                <?php endif; ?>
            </a>
            <a href="#leave" id="sideNav-leave" class="side-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 hover:text-white hover:bg-slate-800 transition">
                <i class="fa-solid fa-plane-departure w-5 text-center text-blue-400"></i>
                <span>My Leave</span>
            </a>
            <a href="#schedule" id="sideNav-schedule" class="side-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 hover:text-white hover:bg-slate-800 transition">
                <i class="fa-solid fa-calendar-check w-5 text-center text-purple-400"></i>
                <span>My Schedule</span>
                <?php if (count($myShifts) > 0): ?>
                    <span class="ml-auto bg-slate-800 text-slate-300 text-xs px-2 py-0.5 rounded-full"><?= count($myShifts) ?></span>
                <?php endif; ?>
            </a>
            <a href="#profile" id="sideNav-profile" class="side-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 hover:text-white hover:bg-slate-800 transition">
                <i class="fa-solid fa-user-gear w-5 text-center text-amber-500"></i>
                <span>My Profile</span>
            </a>

            <!-- Management Suite (Strictly hidden from regular STAFF and Head Baristas) -->
            <?php 
            $isStaffOrHB = $isHB || in_array($userRoleName, ['STAFF', 'BARISTA', 'HEAD BARISTA', 'CASHIER'], true);
            $canPosDash = Rbac::can('pos.void_order') || Rbac::can('pos.menu.manage');
            $canInv = Rbac::can('inventory.manage');
            $canProc = Rbac::can('procurement.view');
            $canFin = Rbac::can('finance.view');
            $canHR = Rbac::can('hr.employee.view');
            $canUsers = Rbac::can('users.view');
            $showMgmt = !$isStaffOrHB && ($canPosDash || $canInv || $canProc || $canFin || $canHR || $canUsers);
            ?>
            <?php if ($showMgmt): ?>
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-3 mt-6 mb-2">Management Suite</div>
            <?php if ($canHR): ?>
            <a href="fika_hrms_dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 hover:text-white hover:bg-slate-800 transition">
                <i class="fa-solid fa-users w-5 text-center text-amber-400"></i>
                <span>HR Dashboard</span>
            </a>
            <a href="fika_hrms_employees.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 hover:text-white hover:bg-slate-800 transition">
                <i class="fa-solid fa-address-book w-5 text-center text-amber-500"></i>
                <span>Employees</span>
            </a>
            <a href="fika_hrms_attendance.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 hover:text-white hover:bg-slate-800 transition">
                <i class="fa-solid fa-clock w-5 text-center text-emerald-400"></i>
                <span>Attendance</span>
            </a>
            <?php endif; ?>
            <?php if ($canPosDash): ?>
            <a href="pos_dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 hover:text-white hover:bg-slate-800 transition">
                <i class="fa-solid fa-cash-register w-5 text-center text-amber-500"></i>
                <span>POS Dashboard</span>
            </a>
            <?php endif; ?>
            <?php if ($canInv): ?>
            <a href="inventory" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 hover:text-white hover:bg-slate-800 transition">
                <i class="fa-solid fa-boxes-stacked w-5 text-center text-blue-400"></i>
                <span>Inventory</span>
            </a>
            <?php endif; ?>
            <?php if ($canProc): ?>
            <a href="procurement" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 hover:text-white hover:bg-slate-800 transition">
                <i class="fa-solid fa-truck-ramp-box w-5 text-center text-emerald-400"></i>
                <span>Procurement</span>
            </a>
            <?php endif; ?>
            <?php if ($canFin): ?>
            <a href="/fika_finance/fika_finance_reports.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 hover:text-white hover:bg-slate-800 transition">
                <i class="fa-solid fa-chart-line w-5 text-center text-emerald-500"></i>
                <span>Financial Reports</span>
            </a>
            <?php endif; ?>
            <?php if ($canUsers): ?>
            <a href="fika_hrms_users.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 hover:text-white hover:bg-slate-800 transition">
                <i class="fa-solid fa-user-shield w-5 text-center text-purple-400"></i>
                <span>Account Management</span>
            </a>
            <?php endif; ?>
            <?php endif; ?>
        </nav>

        <!-- User Profile Footer -->
        <div class="p-4 border-t border-slate-800 flex items-center justify-between">
            <div class="flex items-center gap-2.5 overflow-hidden">
                <div class="w-8 h-8 rounded-lg bg-amber-600/30 text-amber-400 font-bold flex items-center justify-center text-xs flex-shrink-0 border border-amber-500/20">
                    <?= strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)) ?>
                </div>
                <div class="overflow-hidden">
                    <p class="text-xs font-bold text-white truncate"><?= e($employee['first_name'] . ' ' . $employee['last_name']) ?></p>
                    <p class="text-[10px] text-slate-400 truncate"><?= e($employee['position'] ?? 'Staff') ?></p>
                </div>
            </div>
            <a href="../login.php?logout=1" class="text-slate-400 hover:text-red-400 p-1.5 rounded transition" title="Logout">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden">
        <!-- Top Navbar -->
        <header class="bg-white border-b border-slate-200 h-16 flex items-center justify-between px-6 flex-shrink-0 z-10 shadow-sm">
            <div class="flex items-center gap-3">
                <h1 class="font-extrabold text-lg text-slate-900" id="pageSectionTitle">My Dashboard</h1>
                <span class="text-xs text-slate-400 hidden sm:inline">&bull;</span>
                <span class="text-xs text-slate-500 hidden sm:inline"><?= e($employee['branch_name'] ?? 'Main Branch') ?></span>
            </div>
            <div class="flex items-center space-x-3">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <i class="fa-solid fa-circle text-[6px] mr-1.5 animate-pulse"></i> <?= e($employee['status']) ?>
                </span>
                <span class="text-xs font-mono font-semibold text-slate-600 bg-slate-100 border border-slate-200 px-2 py-0.5 rounded-md">
                    <?= e($employee['employee_code']) ?>
                </span>
            </div>
        </header>

        <!-- Main Scrolling Pane -->
        <main class="flex-1 overflow-y-auto p-6 md:p-8">
            <div class="max-w-7xl mx-auto">
                <!-- Global Notifications -->
                <div id="toastAlert" class="hidden mb-6 p-4 rounded-xl shadow-sm text-sm font-medium transition-all"></div>

        <!-- 1. DASHBOARD TAB (KPI OVERVIEW) -->
        <div id="tabContent-dashboard" class="tab-content">
            <!-- Welcome Banner -->
            <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-amber-950 rounded-2xl p-6 md:p-8 text-white shadow-sm border border-slate-800 mb-8 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 rounded-2xl bg-amber-500/20 text-amber-400 border border-amber-500/30 flex items-center justify-center text-2xl font-black shadow-inner flex-shrink-0">
                        <?= strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-amber-400">Employee Self-Service</span>
                            <span class="text-slate-500">&bull;</span>
                            <span class="text-xs text-slate-300"><?= e($employee['branch_name'] ?? 'Main Branch') ?></span>
                        </div>
                        <h2 class="text-2xl font-black text-white mt-0.5">Welcome back, <?= e($employee['first_name']) ?>!</h2>
                        <p class="text-xs text-slate-300 mt-1"><?= e($employee['position'] ?? 'Staff') ?> &bull; <?= e($employee['department'] ?? 'Operations') ?> (<?= e($employee['employee_code']) ?>)</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <a href="#profile" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold transition border border-white/10">
                        <i class="fa-solid fa-user-pen"></i> Edit Profile
                    </a>
                    <a href="#schedule" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 text-xs font-bold transition shadow-sm">
                        <i class="fa-solid fa-calendar-day"></i> View Schedule
                    </a>
                </div>
            </div>

            <!-- KPI Cards Grid (4 Visually Distinct Cards) -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                
                <!-- CARD 1: Clock In/Out / Today's Attendance Widget (Emerald / Teal Theme) -->
                <div class="relative overflow-hidden rounded-2xl border border-emerald-200/80 bg-gradient-to-br from-emerald-50 via-white to-teal-50/50 p-6 shadow-sm hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-[11px] font-extrabold uppercase tracking-wider text-emerald-800 bg-emerald-100/80 px-2.5 py-1 rounded-full border border-emerald-200">
                            Today's Shift
                        </span>
                        <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-base shadow-sm">
                            <i class="fa-solid fa-fingerprint"></i>
                        </div>
                    </div>
                    
                    <div>
                        <?php if ($isCurrentlyClockedIn): ?>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="relative flex h-3 w-3">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                                </span>
                                <span class="text-xs font-bold uppercase tracking-wider text-emerald-700">Currently On Shift</span>
                            </div>
                            <p class="text-2xl font-black text-slate-900 tracking-tight">
                                <?= date('h:i A', strtotime($todayLog['clock_in'])) ?>
                            </p>
                            <p class="text-xs text-slate-600 mt-1">Clocked in today. Remember to clock out at the terminal!</p>
                        <?php elseif ($isClockedOutToday): ?>
                            <span class="inline-block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Shift Completed</span>
                            <p class="text-2xl font-black text-slate-900 tracking-tight">Clocked Out</p>
                            <p class="text-xs text-slate-600 mt-1">
                                <?= date('h:i A', strtotime($todayLog['clock_in'])) ?> &ndash; <?= date('h:i A', strtotime($todayLog['clock_out'])) ?>
                            </p>
                        <?php else: ?>
                            <span class="inline-block text-xs font-bold uppercase tracking-wider text-amber-700 mb-1">Ready for Duty</span>
                            <p class="text-2xl font-black text-slate-900 tracking-tight">Not Clocked In</p>
                            <p class="text-xs text-slate-600 mt-1">No attendance punch recorded for today yet.</p>
                        <?php endif; ?>
                    </div>

                    <div class="mt-4 pt-3 border-t border-emerald-100 flex items-center justify-between text-xs">
                        <span class="text-slate-500"><?= date('D, M j, Y') ?></span>
                        <a href="#attendance" class="font-bold text-emerald-700 hover:text-emerald-900 transition flex items-center gap-1">
                            Logs <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>
                </div>

                <!-- CARD 2: Next Shift Card (Indigo / Purple Theme) -->
                <div class="relative overflow-hidden rounded-2xl border border-indigo-200/80 bg-gradient-to-br from-indigo-50 via-white to-purple-50/50 p-6 shadow-sm hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-[11px] font-extrabold uppercase tracking-wider text-indigo-800 bg-indigo-100/80 px-2.5 py-1 rounded-full border border-indigo-200">
                            Upcoming Shift
                        </span>
                        <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-base shadow-sm">
                            <i class="fa-solid fa-calendar-check"></i>
                        </div>
                    </div>

                    <div>
                        <?php if ($nextShift): ?>
                            <span class="inline-block text-xs font-bold uppercase tracking-wider text-indigo-700 mb-1">
                                <?= date('D, M j', strtotime($nextShift['shift_date'])) ?>
                            </span>
                            <p class="text-2xl font-black text-slate-900 tracking-tight">
                                <?= date('g:i A', strtotime($nextShift['start_time'])) ?>
                            </p>
                            <p class="text-xs text-slate-600 mt-1">
                                Until <?= date('g:i A', strtotime($nextShift['end_time'])) ?> &bull; <?= e($nextShift['shift_type'] ?? 'Regular') ?>
                            </p>
                        <?php else: ?>
                            <span class="inline-block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Roster Cleared</span>
                            <p class="text-2xl font-black text-slate-900 tracking-tight">No Shift</p>
                            <p class="text-xs text-slate-600 mt-1">No upcoming published shifts on your schedule.</p>
                        <?php endif; ?>
                    </div>

                    <div class="mt-4 pt-3 border-t border-indigo-100 flex items-center justify-between text-xs">
                        <span class="text-slate-500"><?= e($nextShift['branch_name'] ?? ($employee['branch_name'] ?? 'Main Branch')) ?></span>
                        <a href="#schedule" class="font-bold text-indigo-700 hover:text-indigo-900 transition flex items-center gap-1">
                            Schedule <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>
                </div>

                <!-- CARD 3: Leave Balance Card (Sky / Cyan Theme) -->
                <div class="relative overflow-hidden rounded-2xl border border-sky-200/80 bg-gradient-to-br from-sky-50 via-white to-cyan-50/50 p-6 shadow-sm hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-[11px] font-extrabold uppercase tracking-wider text-sky-800 bg-sky-100/80 px-2.5 py-1 rounded-full border border-sky-200">
                            Leave Balance
                        </span>
                        <div class="w-10 h-10 rounded-xl bg-sky-600 text-white flex items-center justify-center text-base shadow-sm">
                            <i class="fa-solid fa-plane-departure"></i>
                        </div>
                    </div>

                    <div>
                        <span class="inline-block text-xs font-bold uppercase tracking-wider text-sky-700 mb-1">Service Incentive (SIL)</span>
                        <p class="text-2xl font-black text-slate-900 tracking-tight">
                            <?= $silDays ?> <span class="text-sm font-bold text-slate-500">Days</span>
                        </p>
                        <p class="text-xs text-slate-600 mt-1">
                            <?= $pendingLeaveCount > 0 ? $pendingLeaveCount . ' pending request(s) awaiting review' : 'Annual paid leave balance available' ?>
                        </p>
                    </div>

                    <div class="mt-4 pt-3 border-t border-sky-100 flex items-center justify-between text-xs">
                        <span class="text-slate-500"><?= $pendingLeaveCount > 0 ? 'Pending: ' . $pendingLeaveCount : 'Active balance' ?></span>
                        <a href="#leave" class="font-bold text-sky-700 hover:text-sky-900 transition flex items-center gap-1">
                            Request <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>
                </div>

                <!-- CARD 4: Recent Payslip Card (Amber / Warm Gold Theme) -->
                <div class="relative overflow-hidden rounded-2xl border border-amber-200/80 bg-gradient-to-br from-amber-50 via-white to-orange-50/50 p-6 shadow-sm hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-[11px] font-extrabold uppercase tracking-wider text-amber-800 bg-amber-100/80 px-2.5 py-1 rounded-full border border-amber-200">
                            Recent Payslip
                        </span>
                        <div class="w-10 h-10 rounded-xl bg-amber-600 text-white flex items-center justify-center text-base shadow-sm">
                            <i class="fa-solid fa-file-invoice-dollar"></i>
                        </div>
                    </div>

                    <div>
                        <?php if ($latestPayslip): ?>
                            <span class="inline-block text-xs font-bold uppercase tracking-wider text-amber-700 mb-1">
                                Net Take-Home Pay
                            </span>
                            <p class="text-2xl font-black text-slate-900 tracking-tight">
                                ₱ <?= number_format($latestPayslip['net_pay'], 2) ?>
                            </p>
                            <p class="text-xs text-slate-600 mt-1">
                                Period: <?= date('M d', strtotime($latestPayslip['period_start'])) ?> &ndash; <?= date('M d, Y', strtotime($latestPayslip['period_end'])) ?>
                            </p>
                        <?php else: ?>
                            <span class="inline-block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Payroll Status</span>
                            <p class="text-2xl font-black text-slate-900 tracking-tight">₱ 0.00</p>
                            <p class="text-xs text-slate-600 mt-1">No released payslip records on file yet.</p>
                        <?php endif; ?>
                    </div>

                    <div class="mt-4 pt-3 border-t border-amber-100 flex items-center justify-between text-xs">
                        <span class="text-slate-500"><?= $latestPayslip ? 'Released' : 'Pending' ?></span>
                        <a href="#payslips" class="font-bold text-amber-700 hover:text-amber-900 transition flex items-center gap-1">
                            View Slip <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>
                </div>

            </div>

            <!-- Quick Overview & Actions Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left: Employment Summary Snapshot -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-600 mb-4 flex items-center gap-2">
                        <i class="fa-solid fa-id-badge text-amber-500"></i> Employment Snapshot
                    </h3>
                    <div class="space-y-3.5 text-xs">
                        <div class="flex justify-between items-center py-1.5 border-b border-slate-100">
                            <span class="text-slate-500">Employee ID</span>
                            <span class="font-mono font-bold text-slate-800"><?= e($employee['employee_code']) ?></span>
                        </div>
                        <div class="flex justify-between items-center py-1.5 border-b border-slate-100">
                            <span class="text-slate-500">Assigned Branch</span>
                            <span class="font-semibold text-slate-800"><?= e($employee['branch_name'] ?? 'HQ') ?></span>
                        </div>
                        <div class="flex justify-between items-center py-1.5 border-b border-slate-100">
                            <span class="text-slate-500">Department</span>
                            <span class="font-semibold text-slate-800"><?= e($employee['department'] ?? 'Operations') ?></span>
                        </div>
                        <div class="flex justify-between items-center py-1.5 border-b border-slate-100">
                            <span class="text-slate-500">Position / Title</span>
                            <span class="font-semibold text-slate-800"><?= e($employee['position'] ?? 'Staff') ?></span>
                        </div>
                        <div class="flex justify-between items-center py-1.5 border-b border-slate-100">
                            <span class="text-slate-500">Date Hired</span>
                            <span class="font-semibold text-slate-800"><?= e($employee['date_hired'] ?? 'N/A') ?></span>
                        </div>
                        <div class="flex justify-between items-center py-1.5">
                            <span class="text-slate-500">Current Status</span>
                            <span class="font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                                <?= e($employee['status']) ?>
                            </span>
                        </div>
                    </div>
                    <div class="mt-5 pt-4 border-t border-slate-100">
                        <a href="#profile" class="w-full inline-flex items-center justify-center gap-2 py-2 px-3 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-700 font-semibold text-xs border border-slate-200 transition">
                            <i class="fa-solid fa-user-gear text-slate-500"></i> Manage Personal Profile
                        </a>
                    </div>
                </div>

                <!-- Right: Recent Attendance & Quick Shortcuts (2 cols) -->
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-600 flex items-center gap-2">
                                <i class="fa-solid fa-clock-rotate-left text-indigo-500"></i> Recent Attendance Punches
                            </h3>
                            <a href="#attendance" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 transition">
                                View Full Month (<?= $daysPresent ?> days) &rarr;
                            </a>
                        </div>

                        <?php if (empty($attendanceLogs)): ?>
                            <div class="py-8 text-center text-slate-400">
                                <i class="fa-regular fa-clock text-3xl mb-2 text-slate-300"></i>
                                <p class="text-xs font-medium text-slate-600">No attendance punches recorded this month yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-2">
                                <?php 
                                $previewLogs = array_slice($computedLogs, 0, 4);
                                foreach ($previewLogs as $item):
                                    $l = $item['log'];
                                    $c = $item['calc'];
                                    $hrs = floor($c['worked_minutes'] / 60);
                                    $mins = $c['worked_minutes'] % 60;
                                ?>
                                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 hover:bg-slate-100/80 transition text-xs">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-[11px]">
                                                <?= date('d', strtotime($l['work_date'])) ?>
                                            </div>
                                            <div>
                                                <p class="font-bold text-slate-800"><?= date('l, M d', strtotime($l['work_date'])) ?></p>
                                                <p class="text-slate-500">
                                                    In: <span class="font-medium text-emerald-700"><?= date('h:i A', strtotime($l['clock_in'])) ?></span>
                                                    <?php if ($l['clock_out']): ?>
                                                        &bull; Out: <span class="font-medium text-rose-700"><?= date('h:i A', strtotime($l['clock_out'])) ?></span>
                                                    <?php else: ?>
                                                        &bull; <span class="font-semibold text-emerald-600">Active Shift</span>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <span class="font-mono font-bold text-slate-700"><?= "{$hrs}h {$mins}m" ?></span>
                                            <?php if ($c['late_minutes'] > 0): ?>
                                                <p class="text-[10px] text-rose-600 font-semibold"><?= $c['late_minutes'] ?>m late</p>
                                            <?php else: ?>
                                                <p class="text-[10px] text-emerald-600 font-medium">On time</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Quick Action Buttons -->
                    <div class="mt-6 pt-4 border-t border-slate-100 grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <button onclick="switchTab('payslips')" class="p-2.5 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-semibold flex flex-col items-center gap-1 transition">
                            <i class="fa-solid fa-file-invoice text-emerald-600"></i>
                            <span>Payslips</span>
                        </button>
                        <button onclick="switchTab('schedule')" class="p-2.5 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-semibold flex flex-col items-center gap-1 transition">
                            <i class="fa-solid fa-calendar-alt text-purple-600"></i>
                            <span>My Shifts</span>
                        </button>
                        <button onclick="openLeaveModal()" class="p-2.5 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-semibold flex flex-col items-center gap-1 transition">
                            <i class="fa-solid fa-plane-departure text-blue-600"></i>
                            <span>File Leave</span>
                        </button>
                        <button onclick="openPasswordModal()" class="p-2.5 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-semibold flex flex-col items-center gap-1 transition">
                            <i class="fa-solid fa-key text-amber-600"></i>
                            <span>Password</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. PROFILE TAB -->
        <div id="tabContent-profile" class="tab-content hidden">
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

        <!-- 5. MY SCHEDULE TAB -->
        <div id="tabContent-schedule" class="tab-content hidden space-y-8">
            
            <!-- Published Shifts Section -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">My Assigned Shifts</h2>
                        <p class="text-xs text-slate-500 mt-1">Only official published shifts are shown here.</p>
                    </div>
                    <span class="text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200 px-3 py-1 rounded-full">
                        <?= count($myShifts) ?> Published Shift(s)
                    </span>
                </div>

                <?php if (empty($myShifts)): ?>
                    <div class="p-12 text-center text-slate-400">
                        <i class="fa-solid fa-calendar-xmark text-4xl mb-3 text-slate-300"></i>
                        <p class="font-medium text-base text-slate-700">No published shifts assigned to you yet</p>
                        <p class="text-xs text-slate-500 mt-1">When your branch manager publishes the weekly schedule, your shifts will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-slate-500 font-semibold text-xs uppercase tracking-wider">
                                <tr>
                                    <th class="px-6 py-3.5 text-left">Shift Date</th>
                                    <th class="px-6 py-3.5 text-left">Working Hours</th>
                                    <th class="px-6 py-3.5 text-left">Shift Type</th>
                                    <th class="px-6 py-3.5 text-center">Status</th>
                                    <th class="px-6 py-3.5 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($myShifts as $sh): 
                                    $hasOpen = !empty($sh['open_status']);
                                ?>
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="px-6 py-4 font-bold text-slate-900">
                                            <?= date('l, M d, Y', strtotime($sh['shift_date'])) ?>
                                        </td>
                                        <td class="px-6 py-4 font-medium text-slate-700">
                                            <?= date('h:i A', strtotime($sh['start_time'])) ?> &mdash; <?= date('h:i A', strtotime($sh['end_time'])) ?>
                                        </td>
                                        <td class="px-6 py-4 text-slate-600 text-xs">
                                            <?= e($sh['shift_type']) ?>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <?php if ($hasOpen && $sh['open_status'] === 'OPEN'): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                                    <i class="fa-solid fa-clock text-[9px] mr-1"></i> Dropped (Awaiting Claim)
                                                </span>
                                            <?php elseif ($hasOpen && $sh['open_status'] === 'PENDING_APPROVAL'): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-50 text-purple-700 border border-purple-200">
                                                    <i class="fa-solid fa-user-clock text-[9px] mr-1"></i> Claim Pending Approval
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    <i class="fa-solid fa-circle-check text-[9px] mr-1"></i> Confirmed
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <?php if (!$hasOpen): ?>
                                                <button onclick="dropShift(<?= (int)$sh['id'] ?>)" class="inline-flex items-center gap-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 font-semibold px-3 py-1.5 rounded-lg text-xs transition border border-rose-200">
                                                    <i class="fa-solid fa-handshake-slash text-[10px]"></i> Drop Shift
                                                </button>
                                            <?php else: ?>
                                                <span class="text-xs text-slate-400 italic">Posted to Board</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Open Shifts Section (Available to Claim) -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Available Shifts to Claim</h2>
                        <p class="text-xs text-slate-500 mt-1">Shifts dropped by peers in your branch. Claim extra hours!</p>
                    </div>
                    <span class="text-xs font-semibold bg-purple-50 text-purple-700 border border-purple-200 px-3 py-1 rounded-full">
                        <?= count($availableOpenShifts) ?> Shift(s) Available
                    </span>
                </div>

                <?php if (empty($availableOpenShifts)): ?>
                    <div class="p-12 text-center text-slate-400">
                        <i class="fa-solid fa-mug-hot text-4xl mb-3 text-slate-300"></i>
                        <p class="font-medium text-base text-slate-700">No shifts need coverage right now</p>
                        <p class="text-xs text-slate-500 mt-1">When team members drop shifts, they will appear here for you to claim.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 p-6">
                        <?php foreach ($availableOpenShifts as $os): ?>
                            <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200 flex flex-col justify-between hover:border-indigo-300 transition">
                                <div>
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <div class="text-xs uppercase font-bold text-slate-400"><?= date('l', strtotime($os['shift_date'])) ?></div>
                                            <div class="font-bold text-slate-900 text-base"><?= date('M d, Y', strtotime($os['shift_date'])) ?></div>
                                        </div>
                                        <span class="bg-emerald-100 text-emerald-800 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase">OPEN</span>
                                    </div>
                                    <div class="space-y-1.5 text-xs text-slate-600 my-3">
                                        <div><i class="fa-solid fa-clock text-slate-400 mr-1.5"></i> <?= date('h:i A', strtotime($os['start_time'])) ?> &mdash; <?= date('h:i A', strtotime($os['end_time'])) ?></div>
                                        <div><i class="fa-solid fa-tag text-slate-400 mr-1.5"></i> <?= e($os['shift_type']) ?></div>
                                        <div><i class="fa-solid fa-user-minus text-slate-400 mr-1.5"></i> Dropped by: <strong class="text-slate-800"><?= e($os['orig_first'] . ' ' . $os['orig_last']) ?></strong></div>
                                        <?php if ($os['drop_reason']): ?>
                                            <div class="italic text-[11px] bg-white p-2 rounded-lg border border-slate-200 mt-2 text-slate-500">"<?= e($os['drop_reason']) ?>"</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="mt-4 pt-3 border-t border-slate-200/60">
                                    <button onclick="claimOpenShift(<?= (int)$os['id'] ?>)" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-3 rounded-xl text-xs shadow-sm transition flex items-center justify-center gap-1.5">
                                        <i class="fa-solid fa-hand-holding-hand"></i> Claim This Shift
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
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
            window.location.hash = '#' + tab;
        }

        function applyHashRoute() {
            let hash = window.location.hash.replace('#', '').toLowerCase();
            if (!hash) {
                hash = 'dashboard';
            }

            const validTabs = ['dashboard', 'profile', 'payslips', 'attendance', 'leave', 'schedule'];
            if (!validTabs.includes(hash)) {
                hash = 'dashboard';
            }

            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));

            // Show active tab content
            const activeContent = document.getElementById('tabContent-' + hash);
            if (activeContent) {
                activeContent.classList.remove('hidden');
            }

            // Update page header title
            const titles = {
                'dashboard': 'My Dashboard',
                'profile': 'My Profile',
                'payslips': 'My Payslips',
                'attendance': 'Attendance Record',
                'leave': 'Leave & Time Off',
                'schedule': 'My Schedule'
            };
            const titleEl = document.getElementById('pageSectionTitle');
            if (titleEl && titles[hash]) {
                titleEl.textContent = titles[hash];
            }

            // Update active state in sidebar
            document.querySelectorAll('.side-nav-item').forEach(el => {
                el.classList.remove('bg-amber-600', 'text-white', 'shadow-sm');
                el.classList.add('text-slate-300');
            });
            const activeNav = document.getElementById('sideNav-' + hash);
            if (activeNav) {
                activeNav.classList.remove('text-slate-300');
                activeNav.classList.add('bg-amber-600', 'text-white', 'shadow-sm');
            }
        }

        window.addEventListener('hashchange', applyHashRoute);
        window.addEventListener('DOMContentLoaded', applyHashRoute);

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

        // Shift Board Drop & Claim
        async function dropShift(shiftId) {
            const reason = prompt('Optional reason for dropping this shift:');
            if (reason === null) return;

            try {
                const res = await fetch('api/fika_hrms_shift_claim.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({
                        action: 'drop',
                        shift_id: shiftId,
                        reason: reason,
                        csrf_token: csrfToken
                    })
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    showAlert(data.message || 'Shift dropped successfully.', true);
                    setTimeout(() => location.reload(), 1200);
                } else {
                    alert(data.error || 'Failed to drop shift.');
                }
            } catch (err) {
                alert('Network error: ' + err.message);
            }
        }

        async function claimOpenShift(openShiftId) {
            if (!confirm('Are you sure you want to claim this open shift? Your request will be sent to your manager for approval.')) return;

            try {
                const res = await fetch('api/fika_hrms_shift_claim.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({
                        action: 'claim',
                        open_shift_id: openShiftId,
                        csrf_token: csrfToken
                    })
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    showAlert(data.message || 'Shift claimed successfully.', true);
                    setTimeout(() => location.reload(), 1200);
                } else {
                    alert(data.error || 'Failed to claim shift.');
                }
            } catch (err) {
                alert('Network error: ' + err.message);
            }
        }
    </script>
</body>
</html>
