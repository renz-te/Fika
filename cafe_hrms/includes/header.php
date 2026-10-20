<?php
$role = $_SESSION['user']['role'] ?? '';
$companyName = get_setting($pdo, 'company_name', 'HRMS');
$showSidebar = $showSidebar ?? true;

$userNotifs = [];
$unreadCount = 0;
if (isset($_SESSION['user']['id'])) {
    $userId = $_SESSION['user']['id'];
    
    // For HR and Admins, use target_roles. For Employees, Baristas, and Head Baristas, use target_user_id.
    if (in_array($role, ['Employee', 'Barista', 'Head Barista'])) {
        $notifStmt = $pdo->prepare("
            SELECT g.*, u.name as resolver_name 
            FROM global_notifications g
            LEFT JOIN users u ON u.id = g.read_by
            WHERE g.target_user_id = ?
            AND (g.is_read = 0 OR (g.is_read = 1 AND g.read_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)))
            ORDER BY g.created_at DESC 
            LIMIT 15
        ");
        $notifStmt->execute([$userId]);
    } else {
        $notifStmt = $pdo->prepare("
            SELECT g.*, u.name as resolver_name 
            FROM global_notifications g
            LEFT JOIN users u ON u.id = g.read_by
            WHERE FIND_IN_SET(?, g.target_roles) > 0 AND g.target_user_id IS NULL
            AND (g.is_read = 0 OR (g.is_read = 1 AND g.read_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)))
            ORDER BY g.created_at DESC 
            LIMIT 15
        ");
        $notifStmt->execute([$role]);
    }
    
    $userNotifs = $notifStmt->fetchAll();
    foreach($userNotifs as $n) { if (!$n['is_read']) $unreadCount++; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fika · Slow Hours | HRMS & Operations</title>
    <link rel="icon" type="image/svg+xml" href="/Fika/cafe_hrms/favicon.svg">
    
    <!-- Tailwind CSS (via CDN for rapid prototyping) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,500;0,600;0,700;0,900;1,400;1,600&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        serif: ['"Playfair Display"', 'serif'],
                    },
                    colors: {
                        primary: '#f59e0b',
                        secondary: '#1a0d07',
                        espresso: {
                            50: '#fbf8f5',
                            100: '#f5efe9',
                            200: '#ebdcd0',
                            300: '#dbc0ad',
                            400: '#c59d83',
                            700: '#5c3821',
                            800: '#422415',
                            900: '#2c180e',
                            950: '#1a0d07'
                        },
                        amber: {
                            50: '#fffbeb',
                            100: '#fef3c7',
                            200: '#fde68a',
                            300: '#fcd34d',
                            400: '#fbbf24',
                            500: '#f59e0b',
                            600: '#d97706',
                            700: '#b45309',
                            800: '#92400e',
                            900: '#78350f'
                        },
                        cream: {
                            50: '#fdfbf7',
                            100: '#fbf7f0',
                            200: '#f5eee1',
                            300: '#ece1ce',
                            400: '#decbb0'
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        body { font-family: '"Plus Jakarta Sans"', sans-serif; background-color: #f8fafc; }
        .glass-panel {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        /* Custom Scrollbar for Sidebar */
        aside nav::-webkit-scrollbar {
            width: 6px;
        }
        aside nav::-webkit-scrollbar-track {
            background: #1a0d07; /* Matches espresso-950 */
        }
        aside nav::-webkit-scrollbar-thumb {
            background-color: #5c3821; /* Matches espresso-700 */
            border-radius: 10px;
        }
        aside nav::-webkit-scrollbar-thumb:hover {
            background-color: #f59e0b; /* Amber */
        }
    </style>
</head>
<body class="text-slate-800 antialiased flex h-screen overflow-hidden">

<?php if ($showSidebar): ?>
    <!-- Sidebar -->
    <aside id="main-sidebar" class="w-64 bg-espresso-950 text-cream-100 flex flex-col transition-all duration-300 flex-shrink-0 z-20 border-r border-espresso-900">
        <!-- Fika Brand Header -->
        <div class="flex items-center h-16 px-4 border-b border-espresso-900">
            <!-- Fika Brand Logo -->
            <img src="../public/assets/logo.svg" alt="Fika Logo" class="w-10 h-10 object-contain drop-shadow flex-shrink-0" onerror="this.style.display='none'; document.getElementById('hrms-logo-fallback').classList.remove('hidden');">
            <div id="hrms-logo-fallback" class="hidden w-10 h-10 rounded-full bg-espresso-900 text-amber-400 flex items-center justify-center font-serif font-black text-xl shadow-inner border border-amber-600/40 flex-shrink-0">
                <span>F</span>
            </div>
            
            <!-- Collapsible Sidebar Text -->
            <div class="sidebar-text ml-3 flex flex-col justify-center overflow-hidden whitespace-nowrap">
                <span class="text-xl font-black text-cream-50 leading-none tracking-wide font-serif">FIKA</span>
                <span class="text-[10px] text-amber-400/90 leading-tight uppercase tracking-widest mt-0.5 font-bold">Slow Hours</span>
            </div>
        </div>
        
        <?php
        $currentScript = basename($_SERVER['PHP_SELF']);
        if (!function_exists('nav_active')) {
            function nav_active($page, $currentScript) {
                $pClean = str_replace('.php', '', $page);
                if ($currentScript === $page || $currentScript === $pClean || $currentScript === $pClean . '.php') {
                    return 'bg-amber-500 text-espresso-950 font-bold rounded-lg shadow-sm';
                }
                return 'text-cream-100/70 hover:text-amber-300 hover:bg-espresso-900 rounded-lg transition-colors';
            }
        }
        ?>
        <nav class="flex-1 px-4 py-6 space-y-6 overflow-y-auto">
            <!-- Group 1: PERSONAL (Always visible to ALL logged-in users) -->
            <div>
                <div class="text-[11px] font-bold text-amber-500/80 uppercase tracking-wider mb-2 px-4"><span class="sidebar-text">PERSONAL</span></div>
                <div class="space-y-1">
                    <a href="ess.php" class="flex items-center px-4 py-2 <?= nav_active('ess.php', $currentScript) ?>" title="My Dashboard">
                        <i class="fa-solid fa-gauge-high w-5 text-center"></i> <span class="sidebar-text ml-2">My Dashboard</span>
                    </a>
                    <a href="ess.php#payslips" class="flex items-center px-4 py-2 text-cream-100/70 hover:text-amber-300 hover:bg-espresso-900 rounded-lg transition-colors" title="My Payslips">
                        <i class="fa-solid fa-receipt w-5 text-center"></i> <span class="sidebar-text ml-2">My Payslips</span>
                    </a>
                    <a href="ess.php#leave" class="flex items-center px-4 py-2 text-cream-100/70 hover:text-amber-300 hover:bg-espresso-900 rounded-lg transition-colors" title="My Leave">
                        <i class="fa-solid fa-calendar-check w-5 text-center"></i> <span class="sidebar-text ml-2">My Leave</span>
                    </a>
                    <a href="ess.php#schedule" class="flex items-center px-4 py-2 text-cream-100/70 hover:text-amber-300 hover:bg-espresso-900 rounded-lg transition-colors" title="My Schedule">
                        <i class="fa-solid fa-calendar-days w-5 text-center"></i> <span class="sidebar-text ml-2">My Schedule</span>
                    </a>
                </div>
            </div>

            <!-- Group 2: HR & PERSONNEL (Show if $role is 'Central HR', 'Branch HR', 'HR', 'Branch Manager', 'Admin', 'Super Admin') -->
            <?php if (in_array($role, ['Central HR', 'Branch HR', 'HR', 'Branch Manager', 'Admin', 'Super Admin'])): ?>
            <div>
                <div class="text-[11px] font-bold text-amber-500/80 uppercase tracking-wider mb-2 px-4"><span class="sidebar-text">HR & PERSONNEL</span></div>
                <div class="space-y-1">
                    <a href="dashboard.php" class="flex items-center px-4 py-2 <?= nav_active('dashboard.php', $currentScript) ?>" title="HR Dashboard">
                        <i class="fa-solid fa-chart-pie w-5 text-center"></i> <span class="sidebar-text ml-2">HR Dashboard</span>
                    </a>
                    <a href="employees.php" class="flex items-center px-4 py-2 <?= nav_active('employees.php', $currentScript) ?>" title="Employees">
                        <i class="fa-solid fa-users w-5 text-center"></i> <span class="sidebar-text ml-2">Employees</span>
                    </a>
                    <a href="attendance.php" class="flex items-center px-4 py-2 <?= nav_active('attendance.php', $currentScript) ?>" title="Attendance">
                        <i class="fa-solid fa-clock w-5 text-center"></i> <span class="sidebar-text ml-2">Attendance</span>
                    </a>
                    <a href="leave.php" class="flex items-center px-4 py-2 <?= nav_active('leave.php', $currentScript) ?>" title="Leave Approvals">
                        <i class="fa-solid fa-calendar-alt w-5 text-center"></i> <span class="sidebar-text ml-2">Leave Approvals</span>
                    </a>
                    <a href="payroll.php" class="flex items-center px-4 py-2 <?= nav_active('payroll.php', $currentScript) ?>" title="Payroll">
                        <i class="fa-solid fa-file-invoice-dollar w-5 text-center"></i> <span class="sidebar-text ml-2">Payroll</span>
                    </a>
                    <a href="shift_board.php" class="flex items-center px-4 py-2 <?= nav_active('shift_board.php', $currentScript) ?>" title="Shift Board">
                        <i class="fa-solid fa-clipboard-list w-5 text-center"></i> <span class="sidebar-text ml-2">Shift Board</span>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Group 3: FINANCE & ACCOUNTING (Show if $role is 'Global Accountant', 'Branch Accountant', 'Branch Manager', 'Admin', 'Super Admin') -->
            <?php if (in_array($role, ['Global Accountant', 'Branch Accountant', 'Branch Manager', 'Admin', 'Super Admin'])): ?>
            <div>
                <div class="text-[11px] font-bold text-amber-500/80 uppercase tracking-wider mb-2 px-4"><span class="sidebar-text">FINANCE & ACCOUNTING</span></div>
                <div class="space-y-1">
                    <a href="finance_budgeting.php" class="flex items-center px-4 py-2 <?= nav_active('finance_budgeting.php', $currentScript) ?>" title="Financial Dashboard">
                        <i class="fa-solid fa-chart-line w-5 text-center"></i> <span class="sidebar-text ml-2">Financial Dashboard</span>
                    </a>
                    <a href="finance_reports.php" class="flex items-center px-4 py-2 <?= nav_active('finance_reports.php', $currentScript) ?>" title="Financial Reports">
                        <i class="fa-solid fa-file-contract w-5 text-center"></i> <span class="sidebar-text ml-2">Financial Reports</span>
                    </a>
                    <a href="settlement_reports.php" class="flex items-center px-4 py-2 <?= nav_active('settlement_reports.php', $currentScript) ?>" title="Settlement Reports">
                        <i class="fa-solid fa-scale-balanced w-5 text-center"></i> <span class="sidebar-text ml-2">Settlement Reports</span>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Group 4: STORE OPERATIONS (Show if $role is 'Branch Manager', 'Admin', 'Super Admin', 'Cashier', 'Head Barista', 'Barista') -->
            <?php if (in_array($role, ['Branch Manager', 'Admin', 'Super Admin', 'Cashier', 'Head Barista', 'Barista'])): ?>
            <div>
                <div class="text-[11px] font-bold text-amber-500/80 uppercase tracking-wider mb-2 px-4"><span class="sidebar-text">STORE OPERATIONS</span></div>
                <div class="space-y-1">
                    <a href="pos_dashboard.php" class="flex items-center px-4 py-2 <?= nav_active('pos_dashboard.php', $currentScript) ?>" title="POS Dashboard">
                        <i class="fa-solid fa-cash-register w-5 text-center"></i> <span class="sidebar-text ml-2">POS Dashboard</span>
                    </a>
                    <a href="inventory.php" class="flex items-center px-4 py-2 <?= nav_active('inventory.php', $currentScript) ?>" title="Inventory">
                        <i class="fa-solid fa-box-open w-5 text-center"></i> <span class="sidebar-text ml-2">Inventory</span>
                    </a>
                    <a href="procurement.php" class="flex items-center px-4 py-2 <?= nav_active('procurement.php', $currentScript) ?>" title="Procurement">
                        <i class="fa-solid fa-truck-loading w-5 text-center"></i> <span class="sidebar-text ml-2">Procurement</span>
                    </a>
                    <a href="../cafe_pos/cashier.php" target="_blank" class="flex items-center px-4 py-2 text-cream-100/70 hover:text-amber-300 hover:bg-espresso-900 rounded-lg transition-colors mt-2 border-t border-espresso-900 pt-2" title="Cashier Terminal">
                        <i class="fa-solid fa-desktop w-5 text-center"></i> <span class="sidebar-text ml-2">Cashier Terminal <i class="fa-solid fa-external-link-alt text-[10px] ml-1"></i></span>
                    </a>
                    <a href="../cafe_pos/kiosk.php" target="_blank" class="flex items-center px-4 py-2 text-cream-100/70 hover:text-amber-300 hover:bg-espresso-900 rounded-lg transition-colors" title="Kiosk Mode">
                        <i class="fa-solid fa-tablet-screen-button w-5 text-center"></i> <span class="sidebar-text ml-2">Kiosk Mode <i class="fa-solid fa-external-link-alt text-[10px] ml-1"></i></span>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- SYSTEM ADMIN (Super Admin & Admin only) -->
            <?php if (in_array($role, ['Super Admin', 'Admin'])): ?>
            <div>
                <div class="text-[11px] font-bold text-amber-500/80 uppercase tracking-wider mb-2 px-4"><span class="sidebar-text">SYSTEM ADMIN</span></div>
                <div class="space-y-1">
                    <a href="system_accounts.php" class="flex items-center px-4 py-2 <?= nav_active('system_accounts.php', $currentScript) ?>" title="System Accounts">
                        <i class="fa-solid fa-server w-5 text-center"></i> <span class="sidebar-text ml-2">System Accounts</span>
                    </a>
                    <a href="account_management.php" class="flex items-center px-4 py-2 <?= nav_active('account_management.php', $currentScript) ?>" title="Account Management">
                        <i class="fa-solid fa-user-shield w-5 text-center"></i> <span class="sidebar-text ml-2">Account Management</span>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </nav>
        
        <div class="p-4 border-t border-espresso-900">
            <a href="settings" class="flex items-center px-4 py-2 text-sm text-cream-100/70 hover:text-amber-300 hover:bg-espresso-900 rounded-lg transition-colors" title="Settings">
                <i class="fa-solid fa-cog w-6 text-center text-amber-500/70"></i> <span class="sidebar-text ml-3">Settings</span>
            </a>
            <a href="#" id="logout-trigger" class="flex items-center px-4 py-2 text-sm text-red-400 hover:bg-espresso-900 hover:text-red-300 rounded-lg transition-colors mt-1" title="Logout">
                <i class="fa-solid fa-sign-out-alt w-6 text-center"></i> <span class="sidebar-text ml-3">Logout</span>
            </a>
        </div>
    </aside>
    
    <!-- Logout Confirmation Modal -->
    <div id="logout-modal" class="fixed inset-0 z-[999] hidden bg-black/50 backdrop-blur-sm flex items-center justify-center transition-opacity">
        <div class="bg-white rounded-xl shadow-lg p-6 w-[400px] max-w-[90vw]">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center text-red-600 flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900">End Session?</h3>
                    <p class="text-sm text-slate-500">You are about to securely log out.</p>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button id="cancel-logout" class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200 transition-colors">Cancel</button>
                <a href="logout" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors">Secure Logout</a>
            </div>
        </div>
    </div>

    <!-- Main Content Wrapper -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden bg-slate-50">
        <!-- Top Navbar -->
        <header class="h-16 bg-espresso-900 border-b border-espresso-800 flex items-center justify-between px-6 z-10 flex-shrink-0 text-cream-100">
            <div class="flex items-center gap-4">
                <button id="sidebar-toggle" class="text-amber-400 hover:text-amber-300 transition-colors focus:outline-none p-1.5 -ml-2 rounded-lg hover:bg-espresso-800">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <h2 class="text-lg font-bold font-serif text-cream-50 tracking-tight"><?= h($pageTitle ?? 'Dashboard') ?></h2>
            </div>
            <div class="flex items-center space-x-4">

                <!-- Notification Dropdown -->
                <div class="relative">
                    <button onclick="document.getElementById('notifDropdown').classList.toggle('hidden')" class="relative text-cream-100/80 hover:text-amber-300 transition-colors focus:outline-none pt-1">
                        <i class="fa-regular fa-bell text-xl"></i>
                        <?php if ($unreadCount > 0): ?>
                        <span class="absolute top-0 right-0 -mt-1 -mr-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] text-white font-bold border-2 border-white">
                            <?= $unreadCount > 9 ? '9+' : $unreadCount ?>
                        </span>
                        <?php endif; ?>
                    </button>
                    
                    <div id="notifDropdown" class="hidden absolute right-0 mt-3 w-80 bg-white rounded-xl shadow-lg border border-slate-100 overflow-hidden z-50">
                        <div class="p-3 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                            <h3 class="font-bold text-slate-800 text-sm">Notifications</h3>
                            <?php if ($unreadCount > 0): ?>
                                <span class="text-xs bg-primary/10 text-primary px-2 py-1 rounded-md font-medium"><?= $unreadCount ?> New</span>
                            <?php endif; ?>
                        </div>
                        <div class="max-h-80 overflow-y-auto">
                            <?php if (empty($userNotifs)): ?>
                                <div class="p-6 text-center text-slate-500 text-sm">
                                    <i class="fa-regular fa-bell-slash text-2xl mb-2 text-slate-300"></i><br>
                                    No notifications yet.
                                </div>
                            <?php else: ?>
                                <ul class="divide-y divide-slate-50">
                                    <?php foreach ($userNotifs as $notif): ?>
                                    <li>
                                        <a href="read_notif?id=<?= $notif['id'] ?>" class="flex p-3 hover:bg-slate-50 transition-colors <?= $notif['is_read'] ? 'opacity-60 bg-slate-50' : 'bg-indigo-50/30' ?>">
                                            <div class="flex-shrink-0 mr-3 mt-1">
                                                <div class="h-8 w-8 rounded-full <?= $notif['is_read'] ? 'bg-slate-200 text-slate-500' : 'bg-'.h($notif['color'] ?? 'primary').'/10 text-'.h($notif['color'] ?? 'primary').'-600' ?> flex items-center justify-center text-sm">
                                                    <i class="fa-solid <?= h($notif['icon'] ?? 'fa-bell') ?>"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <p class="text-sm text-slate-800 <?= $notif['is_read'] ? '' : 'font-semibold' ?>">
                                                    <?= h($notif['message']) ?>
                                                </p>
                                                <div class="flex items-center text-xs text-slate-400 mt-1 space-x-2">
                                                    <span><?= date('M d, h:i A', strtotime($notif['created_at'])) ?></span>
                                                    <?php if ($notif['is_read'] && $notif['resolver_name']): ?>
                                                        <span class="bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded text-[10px] font-medium">Solved by <?= h($notif['resolver_name']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php
                // Fetch employee ID and photo if applicable
                $empIdDisp = 'N/A';
                $empPhoto = $_SESSION['user']['photo'] ?? null;
                if (isset($_SESSION['user']['email'])) {
                    $empStmt = $pdo->prepare('SELECT employee_id, photo FROM employees WHERE email = ? LIMIT 1');
                    $empStmt->execute([$_SESSION['user']['email']]);
                    $empRow = $empStmt->fetch();
                    if ($empRow) {
                        $empIdDisp = $empRow['employee_id'];
                        if (!$empPhoto && $empRow['photo']) {
                            $empPhoto = $empRow['photo'];
                            $_SESSION['user']['photo'] = $empPhoto; // cache in session
                        }
                    }
                }
                ?>
                <div class="relative group">
                    <div class="h-8 w-8 rounded-full bg-amber-500 text-espresso-950 font-bold flex items-center justify-center cursor-pointer overflow-hidden border-2 border-amber-400/40 shadow-sm">
                        <?php if ($empPhoto && file_exists(__DIR__ . '/../' . $empPhoto)): ?>
                            <img src="<?= h($empPhoto) ?>" alt="Avatar" class="w-full h-full object-cover">
                        <?php else: ?>
                            <?= h(strtoupper(substr(current_user()['name'] ?? 'U', 0, 1))) ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Profile Tooltip / Dropdown on Hover -->
                    <div class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-slate-100 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                        <div class="p-4 flex flex-col items-center border-b border-slate-50">
                            <div class="h-12 w-12 rounded-full bg-primary/10 text-primary flex items-center justify-center text-xl font-bold mb-2 overflow-hidden border-2 border-white shadow-sm">
                                <?php if ($empPhoto && file_exists(__DIR__ . '/../' . $empPhoto)): ?>
                                    <img src="<?= h($empPhoto) ?>" alt="Avatar" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <?= h(strtoupper(substr(current_user()['name'] ?? 'U', 0, 1))) ?>
                                <?php endif; ?>
                            </div>
                            <p class="text-sm font-bold text-slate-800 text-center w-full truncate"><?= h(current_user()['name'] ?? 'User') ?></p>
                            <p class="text-xs text-slate-500 text-center w-full truncate"><?= h(current_user()['username'] ?? current_user()['email'] ?? '') ?></p>
                        </div>
                        <div class="p-3 bg-slate-50 rounded-b-xl">
                            <div class="flex justify-between items-center text-xs">
                                <span class="text-slate-500 font-medium">Employee ID:</span>
                                <span class="font-bold text-slate-700 font-mono"><?= h($empIdDisp) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Main Scrollable Area -->
        <main class="flex-1 w-full max-w-screen-2xl mx-auto p-4 md:p-6 overflow-y-auto">
            <?php if ($msg = flash('success')): ?>
                <div class="mb-6 bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded shadow-sm" role="alert">
                    <p class="font-medium"><?= h($msg) ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($msg = flash('error')): ?>
                <div class="mb-6 bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-sm" role="alert">
                    <p class="font-medium"><?= h($msg) ?></p>
                </div>
            <?php endif; ?>
<?php else: ?>
    <!-- Full width for auth pages / kiosk -->
    <div class="w-full min-h-screen bg-slate-50 flex items-center justify-center p-4">
<?php endif; ?>

<!-- Global Confirm Modal -->
<div id="globalConfirmModal" class="fixed inset-0 z-[100] bg-black/60 hidden flex items-center justify-center backdrop-blur-sm transition-all duration-300">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm flex flex-col relative overflow-hidden transform scale-95 transition-transform duration-300" id="globalConfirmModalContent">
        <div class="p-4 border-b flex justify-between items-center bg-slate-50">
            <h3 class="font-bold text-slate-800 text-lg flex items-center"><i class="fa-solid fa-triangle-exclamation text-amber-500 mr-2"></i> Confirmation</h3>
        </div>
        <div class="p-6 text-slate-600 text-center font-medium" id="globalConfirmMsg">
            Are you sure?
        </div>
        <div class="p-4 border-t bg-slate-50 flex justify-end gap-3">
            <button onclick="closeGlobalConfirm()" class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-4 py-2 rounded-lg font-medium transition">Cancel</button>
            <button id="globalConfirmBtn" class="bg-primary hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-medium shadow-sm transition">Confirm</button>
        </div>
    </div>
</div>

<script>
function showGlobalConfirm(msg, onConfirm) {
    document.getElementById('globalConfirmMsg').innerText = msg;
    const modal = document.getElementById('globalConfirmModal');
    const content = document.getElementById('globalConfirmModalContent');
    const btn = document.getElementById('globalConfirmBtn');
    
    modal.classList.remove('hidden');
    setTimeout(() => content.classList.replace('scale-95', 'scale-100'), 10);
    
    btn.onclick = function() {
        onConfirm();
    };
}
function confirmSubmit(event, msg) {
    event.preventDefault();
    const form = event.target.closest('form');
    showGlobalConfirm(msg, () => form.submit());
    return false;
}
function confirmFormButton(event, msg) {
    const btn = event.target.closest('button');
    const form = btn.closest('form');
    if (form && !form.checkValidity()) {
        form.reportValidity();
        return false;
    }
    event.preventDefault();
    showGlobalConfirm(msg, () => {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = btn.name;
        hidden.value = btn.value;
        form.appendChild(hidden);
        form.submit();
    });
    return false;
}
function confirmLink(event, msg, url) {
    event.preventDefault();
    showGlobalConfirm(msg, () => { window.location.href = url; });
    return false;
}
function closeGlobalConfirm() {
    const modal = document.getElementById('globalConfirmModal');
    const content = document.getElementById('globalConfirmModalContent');
    content.classList.replace('scale-100', 'scale-95');
    setTimeout(() => modal.classList.add('hidden'), 300);
}

document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('main-sidebar');
    const toggleBtn = document.getElementById('sidebar-toggle');
    const sidebarTexts = document.querySelectorAll('.sidebar-text');

    if (sidebar && toggleBtn) {
        // 1. FIX ANIMATION FLASH: Remove transition temporarily
        sidebar.classList.remove('transition-all', 'duration-300');

        // Apply saved width state
        if (localStorage.getItem('sidebarState') === 'collapsed') {
            sidebar.classList.replace('w-64', 'w-16');
            sidebarTexts.forEach(text => text.classList.add('hidden'));
        }

        // Force browser reflow to apply the width instantly, then re-enable transitions
        void sidebar.offsetWidth; 
        sidebar.classList.add('transition-all', 'duration-300');

        // 2. FIX SCROLL RESET: Auto-scroll to the current active module
        setTimeout(() => {
            const sidebarLinks = sidebar.querySelectorAll('a');
            
            // Get the current page URL, ignoring tab/search parameters for base matching
            const currentPath = window.location.pathname; 

            sidebarLinks.forEach(link => {
                if (link.href.includes(currentPath)) {
                    // 1. If this link is inside a collapsed accordion, force it open
                    const parentGroup = link.closest('.group-content'); // Assumes you wrapped child links in a div with this class
                    if (parentGroup) {
                        parentGroup.classList.remove('hidden');
                    }

                    // 2. Scroll the sidebar to center this specific link
                    link.scrollIntoView({ behavior: 'instant', block: 'center' });
                }
            });
        }, 50);

        // 3. TOGGLE LOGIC
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('w-64');
            sidebar.classList.toggle('w-16');
            sidebarTexts.forEach(text => text.classList.toggle('hidden'));
            
            const isCollapsed = sidebar.classList.contains('w-16');
            localStorage.setItem('sidebarState', isCollapsed ? 'collapsed' : 'expanded');
        });
    }

    // 4. LOGOUT MODAL LOGIC
    const logoutTrigger = document.getElementById('logout-trigger');
    const logoutModal = document.getElementById('logout-modal');
    const cancelLogout = document.getElementById('cancel-logout');

    if (logoutTrigger && logoutModal) {
        logoutTrigger.addEventListener('click', (e) => {
            e.preventDefault();
            logoutModal.classList.remove('hidden');
        });
        
        cancelLogout.addEventListener('click', () => {
            logoutModal.classList.add('hidden');
        });
        
        logoutModal.addEventListener('click', (e) => {
            if (e.target === logoutModal) logoutModal.classList.add('hidden');
        });
    }
});
</script>
