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
$isBM = ($userRoleName === 'BM') || ($sessRoleId === 6);
$isHB = (stripos($empPos, 'Head Barista') !== false);
$isStaff = in_array($userRoleName, ['STAFF', 'BARISTA', 'HEAD BARISTA', 'CASHIER'], true) || in_array(strtoupper($empPos), ['HEAD BARISTA', 'BARISTA', 'CASHIER'], true);
$isStaffOrHB = !$isAdmin && !$isBM && ($isHB || $isStaff);

$canPosDash = Rbac::can('pos.access');
$canInv = (Rbac::can('inventory.view') || Rbac::can('inventory.adjust')) && !($sessRoleId === 1);
$canProc = Rbac::can('procurement.view');
$canFin = Rbac::can('finance.view') && !($sessRoleId === 1);
$canHR = Rbac::can('hr.employee.view') && !($sessRoleId === 1) && !$isBM;
$canLeave = (Rbac::can('hr.leave.view') || Rbac::can('leave.approve')) && !($sessRoleId === 1);
$canPayroll = Rbac::can('payroll.generate') || Rbac::can('payroll.release');
$canShift = (Rbac::can('schedule.view') || Rbac::can('schedule.manage')) && !in_array($sessRoleId, [1, 2], true);
$canAttendance = Rbac::can('hr.attendance.view') && !($sessRoleId === 1);
$canUsers = Rbac::can('users.view') && !$isBM;
$canSystemAccounts = ($sessRoleId === 1) || $isAdmin || Rbac::can('user.manage');
$canDevices = Rbac::can('devices.view');
$canSettings = Rbac::can('settings.manage') && ($sessRoleId === 1);
$canBranches = Rbac::can('branches.view');
$showMgmt = !$isStaffOrHB && ($canPosDash || $canInv || $canProc || $canFin || $canHR || $canLeave || $canPayroll || $canShift || $canAttendance || $canUsers || $canSystemAccounts || $canSettings || $canBranches);

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
<aside id="appSidebar" class="w-64 bg-slate-900 text-slate-200 flex flex-col flex-shrink-0 border-r border-slate-800 z-20 h-screen sticky top-0 overflow-y-auto transition-all duration-300 ease-in-out">
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
        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-3 mb-2">PERSONAL</div>
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

        <?php if ($isBM): ?>
        <!-- Branch Manager Operational Modules -->
        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-3 mt-6 mb-2">OPERATIONS</div>
        
        <?php if (Rbac::can('pos.access')): ?>
        <a href="<?= $url('fika_pos', 'fika_pos_dashboard.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_pos_dashboard.php') ?>">
            <i class="fa-solid fa-cash-register w-5 text-center text-amber-500"></i>
            <span>POS Dashboard</span>
        </a>
        <?php endif; ?>

        <?php if (Rbac::can('inventory.view') || Rbac::can('inventory.adjust')): ?>
        <a href="<?= $url('fika_inventory', 'fika_inventory_dashboard.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_inventory_dashboard.php', ['fika_inventory_stock.php', 'fika_inventory_recipes.php', 'fika_inventory_procurement.php']) ?>">
            <i class="fa-solid fa-boxes-stacked w-5 text-center text-blue-400"></i>
            <span>Inventory</span>
        </a>
        <?php endif; ?>

        <?php if (Rbac::can('finance.view')): ?>
        <a href="<?= $url('fika_finance', 'fika_finance_dashboard.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_finance_dashboard.php', ['fika_finance_reports.php', 'fika_finance_budgets.php', 'fika_finance_reconcile.php']) ?>">
            <i class="fa-solid fa-chart-line w-5 text-center text-emerald-500"></i>
            <span>Branch Finance</span>
        </a>
        <?php endif; ?>

        <?php if (Rbac::can('payroll.generate') || Rbac::can('payroll.release')): ?>
        <a href="<?= $url('fika_hrms', 'fika_hrms_payroll.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_payroll.php') ?>">
            <i class="fa-solid fa-file-invoice-dollar w-5 text-center text-emerald-400"></i>
            <span>Payroll</span>
        </a>
        <?php endif; ?>

        <?php if (Rbac::can('schedule.manage') || Rbac::can('schedule.view')): ?>
        <a href="<?= $url('fika_hrms', 'fika_hrms_shift_board.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_shift_board.php') ?>">
            <i class="fa-solid fa-calendar-days w-5 text-center text-purple-400"></i>
            <span>Roster &amp; Scheduler</span>
        </a>
        <?php endif; ?>

        <?php if (Rbac::can('hr.attendance.view')): ?>
        <a href="<?= $url('fika_hrms', 'fika_hrms_attendance.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_attendance.php') ?>">
            <i class="fa-solid fa-clock w-5 text-center text-emerald-400"></i>
            <span>Attendance</span>
        </a>
        <?php endif; ?>

        <?php if (Rbac::can('hr.leave.view') || Rbac::can('leave.approve')): ?>
        <a href="<?= $url('fika_hrms', 'fika_hrms_leave.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_leave.php') ?>">
            <i class="fa-solid fa-plane-departure w-5 text-center text-blue-400"></i>
            <span>Leave Management</span>
        </a>
        <?php endif; ?>

        <?php if (in_array((int)($_SESSION['user']['role_id'] ?? 0), [1, 6], true)): ?>
        <a href="<?= $url('fika_hrms', 'fika_hrms_users.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_users.php') ?>">
            <i class="fa-solid fa-users w-5 text-center text-purple-400"></i>
            <span>Account Management</span>
        </a>
        <a href="<?= $url('fika_hrms', 'fika_hrms_devices.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_devices.php') ?>">
            <i class="fa-solid fa-server w-5 text-center text-cyan-400"></i>
            <span>System Accounts</span>
        </a>
        <?php endif; ?>

        <?php else: ?>

        <!-- 1. HR MODULES Group -->
        <?php if ($canHR || $canPayroll || $canShift || $canAttendance || $canLeave): ?>
        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-3 mt-6 mb-2">HR MODULES</div>
        <?php if ($canHR): ?>
        <a href="<?= $url('fika_hrms', 'fika_hrms_dashboard.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_dashboard.php') ?>">
            <i class="fa-solid fa-users w-5 text-center text-amber-400"></i>
            <span>HRMS Dashboard</span>
        </a>
        <a href="<?= $url('fika_hrms', 'fika_hrms_employees.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_employees.php', ['fika_hrms_employee_form.php']) ?>">
            <i class="fa-solid fa-address-book w-5 text-center text-amber-500"></i>
            <span>Employees</span>
        </a>
        <a href="<?= $url('fika_hrms', 'fika_hrms_applications.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_applications.php', ['fika_hrms_interview_workspace.php', 'fika_hrms_applicants.php']) ?>">
            <i class="fa-solid fa-id-card-clip w-5 text-center text-teal-400"></i>
            <span>Applicants / ATS</span>
        </a>
        <?php endif; ?>
        <?php if (Rbac::can('payroll.generate') || Rbac::can('payroll.release')): ?>
        <a href="<?= $url('fika_hrms', 'fika_hrms_payroll.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_payroll.php') ?>">
            <i class="fa-solid fa-file-invoice-dollar w-5 text-center text-emerald-400"></i>
            <span>Payroll</span>
        </a>
        <?php endif; ?>
        <?php if ((Rbac::can('schedule.manage') || Rbac::can('schedule.view')) && !in_array($sessRoleId, [1, 2], true)): ?>
        <a href="<?= $url('fika_hrms', 'fika_hrms_shift_board.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_shift_board.php') ?>">
            <i class="fa-solid fa-calendar-days w-5 text-center text-purple-400"></i>
            <span>Roster &amp; Scheduler</span>
        </a>
        <?php endif; ?>
        <?php if (Rbac::can('hr.attendance.view') && !($sessRoleId === 1)): ?>
        <a href="<?= $url('fika_hrms', 'fika_hrms_attendance.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_attendance.php') ?>">
            <i class="fa-solid fa-clock w-5 text-center text-emerald-400"></i>
            <span>Attendance</span>
        </a>
        <?php endif; ?>
        <?php if ((Rbac::can('hr.leave.view') || Rbac::can('leave.approve')) && !($sessRoleId === 1)): ?>
        <a href="<?= $url('fika_hrms', 'fika_hrms_leave.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_leave.php') ?>">
            <i class="fa-solid fa-plane-departure w-5 text-center text-blue-400"></i>
            <span>Leave Management</span>
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <!-- 2. OPERATIONS Group -->
        <?php if (Rbac::can('pos.access')): ?>
        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-3 mt-6 mb-2">OPERATIONS</div>
        <a href="<?= $url('fika_pos', 'fika_pos_dashboard.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_pos_dashboard.php') ?>">
            <i class="fa-solid fa-cash-register w-5 text-center text-amber-500"></i>
            <span>POS Dashboard</span>
        </a>
        <?php endif; ?>

        <!-- 3. FINANCE Group -->
        <?php if (Rbac::can('finance.view') && !($sessRoleId === 1)): 
            $finLabel = ($sessRoleId === 3 || $userRoleName === 'GA') ? 'Global Finance' : (($sessRoleId === 4 || $sessRoleId === 6 || in_array($userRoleName, ['BA', 'BM'], true)) ? 'Branch Finance' : 'Finance');
        ?>
        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-3 mt-6 mb-2">FINANCE</div>
        <a href="<?= $url('fika_finance', 'fika_finance_dashboard.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_finance_dashboard.php', ['fika_finance_reports.php', 'fika_finance_budgets.php', 'fika_finance_reconcile.php']) ?>">
            <i class="fa-solid fa-chart-line w-5 text-center text-emerald-500"></i>
            <span><?= $finLabel ?></span>
        </a>
        <?php endif; ?>

        <!-- 4. INVENTORY Group -->
        <?php if ($canInv): ?>
        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-3 mt-6 mb-2">INVENTORY</div>
        <a href="<?= $url('fika_inventory', 'fika_inventory_dashboard.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_inventory_dashboard.php', ['fika_inventory_stock.php', 'fika_inventory_recipes.php', 'fika_inventory_procurement.php']) ?>">
            <i class="fa-solid fa-boxes-stacked w-5 text-center text-blue-400"></i>
            <span>Inventory</span>
        </a>
        <?php endif; ?>

        <!-- 5. SYSTEM & SETTINGS Group -->
        <?php if ($canUsers || $canSystemAccounts || $canSettings || $canBranches): ?>
        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-3 mt-6 mb-2">SYSTEM &amp; SETTINGS</div>
        <?php if (in_array((int)($_SESSION['user']['role_id'] ?? 0), [1, 6], true)): ?>
        <a href="<?= $url('fika_hrms', 'fika_hrms_devices.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_devices.php') ?>">
            <i class="fa-solid fa-server w-5 text-center text-cyan-400"></i>
            <span>System Accounts</span>
        </a>
        <?php endif; ?>
        <?php if ($canUsers): ?>
        <a href="<?= $url('fika_hrms', 'fika_hrms_users.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_users.php') ?>">
            <i class="fa-solid fa-user-shield w-5 text-center text-purple-400"></i>
            <span>Account Management</span>
        </a>
        <?php endif; ?>
        <?php if ($canBranches): ?>
        <a href="<?= $url('fika_hrms', 'fika_hrms_branches.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_branches.php') ?>">
            <i class="fa-solid fa-store w-5 text-center text-amber-500"></i>
            <span>Branch Management</span>
        </a>
        <?php endif; ?>
        <?php if (Rbac::can('settings.manage') && ($sessRoleId === 1)): ?>
        <a href="<?= $url('fika_hrms', 'fika_hrms_settings.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold <?= $navActive('fika_hrms_settings.php') ?>">
            <i class="fa-solid fa-gear w-5 text-center text-slate-400"></i>
            <span>Settings</span>
        </a>
        <?php endif; ?>
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

<!-- Sidebar Floating Toggle Button -->
<button id="sidebarToggle" 
        type="button" 
        aria-label="Toggle navigation sidebar" 
        aria-expanded="true"
        title="Collapse sidebar"
        class="fixed top-3.5 z-40 w-9 h-9 rounded-xl bg-slate-900/90 hover:bg-slate-800 text-slate-300 hover:text-white shadow-md hover:shadow-lg border border-slate-700/70 backdrop-blur-md flex items-center justify-center transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-amber-500/50 cursor-pointer group">
    <i id="sidebarToggleIcon" class="fa-solid fa-xmark text-sm transition-transform duration-200 group-hover:scale-110"></i>
</button>

<style>
    /* Collapsible Sidebar Styles */
    #appSidebar {
        transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    #appSidebar.sidebar-collapsed,
    #appSidebar.-ml-64 {
        margin-left: -16rem !important;
    }
    #sidebarToggle {
        left: 16.75rem; /* When sidebar is expanded: 268px, 12px to the right of 256px sidebar */
        transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1), background-color 0.2s, color 0.2s, box-shadow 0.2s;
    }
    #sidebarToggle.collapsed {
        left: 0.875rem; /* When sidebar is collapsed: 14px from left screen edge */
    }
    @media (max-width: 640px) {
        #sidebarToggle {
            left: 16.5rem;
        }
        #sidebarToggle.collapsed {
            left: 0.75rem;
        }
    }
    /* Ensure page top headers have comfortable breathing room for the floating toggle button */
    .min-w-0 > header > div:first-child {
        padding-left: 4.5rem !important;
    }
    body {
        overflow-x: hidden;
    }
</style>

<script>
(function() {
    var storageKey = 'fika_sidebar_collapsed';
    var sidebar = document.getElementById('appSidebar');
    var toggleBtn = document.getElementById('sidebarToggle');
    var toggleIcon = document.getElementById('sidebarToggleIcon');

    function applyState(collapsed) {
        if (!sidebar || !toggleBtn) return;
        
        if (collapsed) {
            sidebar.classList.add('-ml-64', 'sidebar-collapsed');
            toggleBtn.classList.add('collapsed');
            toggleBtn.setAttribute('aria-expanded', 'false');
            toggleBtn.setAttribute('title', 'Expand sidebar');
            if (toggleIcon) {
                toggleIcon.className = 'fa-solid fa-bars text-sm transition-transform duration-200 group-hover:scale-110';
            }
        } else {
            sidebar.classList.remove('-ml-64', 'sidebar-collapsed');
            toggleBtn.classList.remove('collapsed');
            toggleBtn.setAttribute('aria-expanded', 'true');
            toggleBtn.setAttribute('title', 'Collapse sidebar');
            if (toggleIcon) {
                toggleIcon.className = 'fa-solid fa-xmark text-sm transition-transform duration-200 group-hover:scale-110';
            }
        }
    }

    // Immediate check to prevent flash of uncollapsed sidebar
    var savedState = false;
    try {
        savedState = localStorage.getItem(storageKey) === 'true';
    } catch (e) {}

    if (savedState) {
        applyState(true);
    }

    function initToggle() {
        sidebar = document.getElementById('appSidebar');
        toggleBtn = document.getElementById('sidebarToggle');
        toggleIcon = document.getElementById('sidebarToggleIcon');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                var isCurrentlyCollapsed = sidebar && (sidebar.classList.contains('-ml-64') || sidebar.classList.contains('sidebar-collapsed'));
                var newState = !isCurrentlyCollapsed;
                applyState(newState);
                try {
                    localStorage.setItem(storageKey, newState ? 'true' : 'false');
                } catch (err) {}
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initToggle);
    } else {
        initToggle();
    }
})();
</script>
