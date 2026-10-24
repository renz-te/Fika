<?php
require_once __DIR__ . '/../../app/bootstrap.php';

Auth::requireLogin();

global $pdo;

$currentUser = Auth::user();
$sessEmpId = (int)($currentUser['employee_id'] ?? 0);
$sessRoleId = (int)($currentUser['role_id'] ?? 0);

$userRoleName = '';
if ($sessRoleId > 0 && $pdo) {
    $rStmt = $pdo->prepare("SELECT name FROM roles WHERE id = ? LIMIT 1");
    $rStmt->execute([$sessRoleId]);
    $userRoleName = strtoupper(trim((string)$rStmt->fetchColumn()));
}

$empPos = '';
$empName = $currentUser['username'] ?? 'User';
if ($sessEmpId > 0 && $pdo) {
    $eStmt = $pdo->prepare("SELECT first_name, last_name, position FROM employees WHERE id = ? LIMIT 1");
    $eStmt->execute([$sessEmpId]);
    $empRow = $eStmt->fetch();
    if ($empRow) {
        $empPos = (string)($empRow['position'] ?? '');
        $empName = trim($empRow['first_name'] . ' ' . $empRow['last_name']);
    }
}

$isHB = (stripos($empPos, 'Head Barista') !== false) || ($sessRoleId === 5);
$isStaff = in_array($userRoleName, ['STAFF', 'BARISTA', 'HEAD BARISTA', 'CASHIER'], true) || in_array(strtoupper($empPos), ['HEAD BARISTA', 'BARISTA', 'CASHIER'], true);
$isStaffOrHB = $isHB || $isStaff;

$canPosDash = Rbac::can('pos.void_order') || Rbac::can('pos.menu.manage');
$canInv = Rbac::can('inventory.manage') || Rbac::can('inventory.view');
$canProc = Rbac::can('procurement.view');
$canFin = Rbac::can('finance.view');
$canHR = Rbac::can('hr.employee.view');
$canPayroll = Rbac::can('payroll.view');
$canShift = Rbac::can('schedule.view') || Rbac::can('schedule.manage');
$canAttendance = Rbac::can('hr.attendance.view');
$canUsers = Rbac::can('users.view');
$showMgmt = !$isStaffOrHB && ($canPosDash || $canInv || $canProc || $canFin || $canHR || $canPayroll || $canShift || $canAttendance || $canUsers);

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$navActive = function(string $page) use ($currentPage): string {
    return ($currentPage === $page) 
        ? 'bg-amber-600 text-white shadow-sm' 
        : 'text-slate-300 hover:text-white hover:bg-slate-800 transition';
};
?>
<!-- Sidebar Navigation -->
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
        <a href="fika_hrms_ess.php#dashboard" id="sideNav-dashboard" class="side-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 hover:text-white hover:bg-slate-800 transition">
            <i class="fa-solid fa-chart-pie w-5 text-center text-amber-500"></i>
            <span>My Dashboard</span>
        </a>
        <a href="fika_hrms_ess.php#payslips" id="sideNav-payslips" class="side-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 hover:text-white hover:bg-slate-800 transition">
            <i class="fa-solid fa-file-invoice-dollar w-5 text-center text-emerald-400"></i>
            <span>My Payslips</span>
        </a>
        <a href="fika_hrms_ess.php#leave" id="sideNav-leave" class="side-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 hover:text-white hover:bg-slate-800 transition">
            <i class="fa-solid fa-plane-departure w-5 text-center text-blue-400"></i>
            <span>My Leave</span>
        </a>
        <a href="fika_hrms_ess.php#schedule" id="sideNav-schedule" class="side-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 hover:text-white hover:bg-slate-800 transition">
            <i class="fa-solid fa-calendar-check w-5 text-center text-purple-400"></i>
            <span>My Schedule</span>
        </a>
        <a href="fika_hrms_ess.php#profile" id="sideNav-profile" class="side-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 hover:text-white hover:bg-slate-800 transition">
            <i class="fa-solid fa-user-gear w-5 text-center text-amber-500"></i>
            <span>My Profile</span>
        </a>

        <!-- Management Suite (Strictly hidden from regular STAFF and Head Baristas) -->
        <?php if ($showMgmt): ?>
        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-3 mt-6 mb-2">Management Suite</div>
        <?php if ($canHR): ?>
        <a href="fika_hrms_dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_dashboard.php') ?>">
            <i class="fa-solid fa-users w-5 text-center text-amber-400"></i>
            <span>HRMS Dashboard</span>
        </a>
        <a href="fika_hrms_employees.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_employees.php') ?>">
            <i class="fa-solid fa-address-book w-5 text-center text-amber-500"></i>
            <span>Employees</span>
        </a>
        <?php endif; ?>
        <?php if ($canPayroll): ?>
        <a href="fika_hrms_payroll.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_payroll.php') ?>">
            <i class="fa-solid fa-file-invoice-dollar w-5 text-center text-emerald-400"></i>
            <span>Payroll</span>
        </a>
        <?php endif; ?>
        <?php if ($canShift): ?>
        <a href="fika_hrms_shift_board.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_shift_board.php') ?>">
            <i class="fa-solid fa-calendar-days w-5 text-center text-purple-400"></i>
            <span>Shift Board</span>
        </a>
        <?php endif; ?>
        <?php if ($canAttendance): ?>
        <a href="fika_hrms_attendance.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_attendance.php') ?>">
            <i class="fa-solid fa-clock w-5 text-center text-emerald-400"></i>
            <span>Attendance</span>
        </a>
        <?php endif; ?>
        <?php if ($canPosDash): ?>
        <a href="pos_dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('pos_dashboard') ?>">
            <i class="fa-solid fa-cash-register w-5 text-center text-amber-500"></i>
            <span>POS Dashboard</span>
        </a>
        <?php endif; ?>
        <?php if ($canFin): ?>
        <a href="/fika_finance/fika_finance_reports.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_finance_reports.php') ?>">
            <i class="fa-solid fa-chart-line w-5 text-center text-emerald-500"></i>
            <span>Finance</span>
        </a>
        <?php endif; ?>
        <?php if ($canInv): ?>
        <a href="inventory" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('inventory') ?>">
            <i class="fa-solid fa-boxes-stacked w-5 text-center text-blue-400"></i>
            <span>Inventory</span>
        </a>
        <?php endif; ?>
        <?php if ($canProc): ?>
        <a href="procurement" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('procurement') ?>">
            <i class="fa-solid fa-truck-ramp-box w-5 text-center text-emerald-400"></i>
            <span>Procurement</span>
        </a>
        <?php endif; ?>
        <?php if ($canUsers): ?>
        <a href="fika_hrms_users.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_users.php') ?>">
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
                <?= strtoupper(substr($empName, 0, 1)) ?>
            </div>
            <div class="overflow-hidden">
                <p class="text-xs font-bold text-white truncate"><?= e($empName) ?></p>
                <p class="text-[10px] text-slate-400 truncate"><?= e($empPos ?: $userRoleName) ?></p>
            </div>
        </div>
        <a href="../login.php?logout=1" class="text-slate-400 hover:text-red-400 p-1.5 rounded transition" title="Logout">
            <i class="fa-solid fa-arrow-right-from-bracket"></i>
        </a>
    </div>
</aside>
