<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/core/Output.php';

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

$isAdmin = ($userRoleName === 'ADMIN') || ($sessRoleId === 1);
$isHB = (stripos($empPos, 'Head Barista') !== false) || ($sessRoleId === 5);
$isStaff = in_array($userRoleName, ['STAFF', 'BARISTA', 'HEAD BARISTA', 'CASHIER'], true) || in_array(strtoupper($empPos), ['HEAD BARISTA', 'BARISTA', 'CASHIER'], true);
$isStaffOrHB = !$isAdmin && ($isHB || $isStaff);

$canPosDash = Rbac::can('pos.access') || Rbac::can('pos.void_order') || Rbac::can('pos.menu.manage');
$canInv = Rbac::can('inventory.manage') || Rbac::can('inventory.view');
$canProc = Rbac::can('procurement.view');
$canFin = Rbac::can('finance.view');
$canHR = Rbac::can('hr.employee.view');
$canPayroll = Rbac::can('payroll.view');
$canShift = Rbac::can('schedule.view') || Rbac::can('schedule.manage');
$canAttendance = Rbac::can('hr.attendance.view');
$canUsers = Rbac::can('users.view');
$canDevices = Rbac::can('devices.view');
$showMgmt = !$isStaffOrHB && ($canPosDash || $canInv || $canProc || $canFin || $canHR || $canPayroll || $canShift || $canAttendance || $canUsers || $canDevices);

$currentDir = basename(dirname($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''));
$url = function(string $targetModule, string $file) use ($currentDir): string {
    if ($currentDir === $targetModule) {
        return $file;
    }
    return "../{$targetModule}/{$file}";
};

$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
$navActive = function(string $file, array $aliases = []) use ($currentPage): string {
    $isActive = ($currentPage === $file) || in_array($currentPage, $aliases, true);
    return $isActive 
        ? 'bg-amber-600 text-white shadow-sm' 
        : 'text-slate-300 hover:text-white hover:bg-slate-800 transition';
};
?>
<!-- Sidebar Navigation -->
<aside class="w-64 bg-slate-900 text-slate-200 flex flex-col flex-shrink-0 border-r border-slate-800 z-20 h-screen sticky top-0 overflow-y-auto">
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
        <a href="<?= $url('fika_hrms', 'fika_hrms_ess.php') ?>#dashboard" id="sideNav-dashboard" class="side-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 hover:text-white hover:bg-slate-800 transition">
            <i class="fa-solid fa-chart-pie w-5 text-center text-amber-500"></i>
            <span>My Dashboard</span>
        </a>
        <a href="<?= $url('fika_hrms', 'fika_hrms_ess.php') ?>#payslips" id="sideNav-payslips" class="side-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 hover:text-white hover:bg-slate-800 transition">
            <i class="fa-solid fa-file-invoice-dollar w-5 text-center text-emerald-400"></i>
            <span>My Payslips</span>
        </a>
        <a href="<?= $url('fika_hrms', 'fika_hrms_ess.php') ?>#leave" id="sideNav-leave" class="side-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 hover:text-white hover:bg-slate-800 transition">
            <i class="fa-solid fa-plane-departure w-5 text-center text-blue-400"></i>
            <span>My Leave</span>
        </a>
        <a href="<?= $url('fika_hrms', 'fika_hrms_ess.php') ?>#schedule" id="sideNav-schedule" class="side-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 hover:text-white hover:bg-slate-800 transition">
            <i class="fa-solid fa-calendar-check w-5 text-center text-purple-400"></i>
            <span>My Schedule</span>
        </a>
        <a href="<?= $url('fika_hrms', 'fika_hrms_ess.php') ?>#profile" id="sideNav-profile" class="side-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 hover:text-white hover:bg-slate-800 transition">
            <i class="fa-solid fa-user-gear w-5 text-center text-amber-500"></i>
            <span>My Profile</span>
        </a>

        <!-- Management Sections (Hidden from regular STAFF and Head Baristas) -->
        <?php if ($showMgmt): ?>

        <!-- 1. HRMS Group -->
        <?php if ($canHR || $canPayroll || $canShift || $canAttendance): ?>
        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-3 mt-6 mb-2">HRMS</div>
        <?php if ($canHR): ?>
        <a href="<?= $url('fika_hrms', 'fika_hrms_dashboard.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_dashboard.php') ?>">
            <i class="fa-solid fa-users w-5 text-center text-amber-400"></i>
            <span>HRMS Dashboard</span>
        </a>
        <a href="<?= $url('fika_hrms', 'fika_hrms_employees.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_employees.php', ['fika_hrms_employee_form.php']) ?>">
            <i class="fa-solid fa-address-book w-5 text-center text-amber-500"></i>
            <span>Employees</span>
        </a>
        <?php endif; ?>
        <?php if ($canPayroll): ?>
        <a href="<?= $url('fika_hrms', 'fika_hrms_payroll.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_payroll.php') ?>">
            <i class="fa-solid fa-file-invoice-dollar w-5 text-center text-emerald-400"></i>
            <span>Payroll</span>
        </a>
        <?php endif; ?>
        <?php if ($canShift): ?>
        <a href="<?= $url('fika_hrms', 'fika_hrms_shift_board.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_shift_board.php') ?>">
            <i class="fa-solid fa-calendar-days w-5 text-center text-purple-400"></i>
            <span>Roster &amp; Scheduler</span>
        </a>
        <?php endif; ?>
        <?php if ($canAttendance): ?>
        <a href="<?= $url('fika_hrms', 'fika_hrms_attendance.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_attendance.php') ?>">
            <i class="fa-solid fa-clock w-5 text-center text-emerald-400"></i>
            <span>Attendance</span>
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <!-- 2. OPERATIONS Group -->
        <?php if ($canPosDash): ?>
        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-3 mt-6 mb-2">Operations</div>
        <a href="<?= $url('fika_pos', 'fika_pos_dashboard.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_pos_dashboard.php') ?>">
            <i class="fa-solid fa-cash-register w-5 text-center text-amber-500"></i>
            <span>POS Dashboard</span>
        </a>
        <?php endif; ?>

        <!-- 3. FINANCE Group -->
        <?php if ($canFin): ?>
        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-3 mt-6 mb-2">Finance</div>
        <a href="<?= $url('fika_finance', 'fika_finance_dashboard.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_finance_dashboard.php', ['fika_finance_reports.php', 'fika_finance_budgets.php', 'fika_finance_reconcile.php']) ?>">
            <i class="fa-solid fa-chart-line w-5 text-center text-emerald-500"></i>
            <span>Finance</span>
        </a>
        <?php endif; ?>

        <!-- 4. INVENTORY Group -->
        <?php if ($canInv || $canProc): ?>
        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-3 mt-6 mb-2">Inventory</div>
        <a href="<?= $url('fika_inventory', 'fika_inventory_dashboard.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_inventory_dashboard.php', ['fika_inventory_stock.php', 'fika_inventory_recipes.php', 'fika_inventory_procurement.php']) ?>">
            <i class="fa-solid fa-boxes-stacked w-5 text-center text-blue-400"></i>
            <span>Inventory</span>
        </a>
        <?php endif; ?>

        <!-- 5. SYSTEM Group -->
        <?php if ($canUsers || $canDevices): ?>
        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-3 mt-6 mb-2">System</div>
        <?php if ($canDevices): ?>
        <a href="<?= $url('fika_hrms', 'fika_hrms_devices.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_devices.php') ?>">
            <i class="fa-solid fa-tablet-screen-button w-5 text-center text-cyan-400"></i>
            <span>System Accounts</span>
        </a>
        <?php endif; ?>
        <?php if ($canUsers): ?>
        <a href="<?= $url('fika_hrms', 'fika_hrms_users.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_users.php') ?>">
            <i class="fa-solid fa-user-shield w-5 text-center text-purple-400"></i>
            <span>Account Management</span>
        </a>
        <?php endif; ?>
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
