<?php
$companyName = get_setting($pdo, 'company_name', 'HRMS');
$showSidebar = $showSidebar ?? true;

$userNotifs = [];
$unreadCount = 0;
if (isset($_SESSION['user']['id'])) {
    $role = $_SESSION['user']['role'] ?? '';
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
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4f46e5',
                        secondary: '#1e293b',
                    }
                }
            }
        }
    </script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
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
            background: #1e293b; /* Matches secondary bg */
        }
        aside nav::-webkit-scrollbar-thumb {
            background-color: #4f46e5; /* Matches primary theme */
            border-radius: 10px;
        }
    </style>
</head>
<body class="text-slate-800 antialiased flex h-screen overflow-hidden">

<?php if ($showSidebar): ?>
    <!-- Sidebar -->
    <aside id="main-sidebar" class="w-64 bg-secondary text-white flex flex-col transition-all duration-300 flex-shrink-0 z-20">
        <!-- Fika Brand Header -->
        <div class="flex items-center h-16 px-4 border-b border-slate-700">
            <!-- SVG Logo: Bean, Clock, and Circular Text -->
            <svg class="w-10 h-10 text-primary flex-shrink-0" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                <!-- Circular Typography Path -->
                <path id="brand-arc" d="M 12 50 A 38 38 0 1 1 88 50 A 38 38 0 1 1 12 50" fill="none" />
                <text fill="currentColor" font-size="9.5" font-weight="bold" letter-spacing="1.5">
                    <textPath href="#brand-arc" startOffset="50%" text-anchor="middle">FIKA · SLOW HOURS ·</textPath>
                </text>
                
                <!-- Coffee Bean Center -->
                <ellipse cx="50" cy="50" rx="16" ry="22" stroke="currentColor" stroke-width="2" fill="none"/>
                <path d="M 50 28 C 40 40 60 60 50 72" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/>
                
                <!-- Clock Hands intersecting the bean -->
                <circle cx="50" cy="50" r="2.5" fill="currentColor"/>
                <line x1="50" y1="50" x2="50" y2="35" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                <line x1="50" y1="50" x2="60" y2="58" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
            
            <!-- Collapsible Sidebar Text -->
            <div class="sidebar-text ml-3 flex flex-col justify-center overflow-hidden whitespace-nowrap">
                <span class="text-xl font-black text-white leading-none tracking-wide">FIKA</span>
                <span class="text-xs text-slate-400 leading-tight uppercase tracking-widest mt-0.5">Slow Hours</span>
            </div>
        </div>
        
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <?php 
            if (isset(current_user()['role']) && in_array(current_user()['role'], ['Employee', 'Barista', 'Head Barista'])): 
                $isHB = false;
                if (isset($pdo)) {
                    $hbStmt = $pdo->prepare("SELECT position FROM employees WHERE id = (SELECT employee_id FROM users WHERE id = ?)");
                    $hbStmt->execute([current_user()['id']]);
                    $empPos = $hbStmt->fetchColumn();
                    if ($empPos && stripos($empPos, 'Head Barista') !== false) {
                        $isHB = true;
                    }
                }
            ?>
                <a href="ess" class="flex items-center px-4 py-3 <?= basename($_SERVER['PHP_SELF']) == 'ess.php' ? 'bg-primary rounded-lg text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white rounded-lg transition-colors' ?>" title="My Dashboard">
                    <i class="fa-solid fa-chart-pie w-6 text-center"></i> <span class="sidebar-text ml-3">My Dashboard</span>
                </a>
                <a href="shift_board" class="flex items-center px-4 py-3 <?= basename($_SERVER['PHP_SELF']) == 'shift_board.php' ? 'bg-primary rounded-lg text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white rounded-lg transition-colors' ?>" title="Shift Board">
                    <i class="fa-solid fa-clipboard-list w-6 text-center"></i> <span class="sidebar-text ml-3">Shift Board</span>
                </a>
                <?php if ($isHB): ?>
                <a href="performance" class="flex items-center px-4 py-3 <?= basename($_SERVER['PHP_SELF']) == 'performance.php' ? 'bg-primary rounded-lg text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white rounded-lg transition-colors' ?>" title="Performance">
                    <i class="fa-solid fa-star-half-stroke w-6 text-center"></i> <span class="sidebar-text ml-3">Performance</span>
                </a>
                <?php endif; ?>

            <?php else: ?>
                <!-- Sidebar Sections -->
                <div class="mt-4 space-y-6">
                    
                    <!-- HRMS & OPERATIONS -->
                    <div>
                        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2 px-4"><span class="sidebar-text">HRMS & OPERATIONS</span></div>
                        <div class="space-y-1">
                            <a href="dashboard" class="flex items-center px-4 py-2 <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'text-white font-medium bg-slate-800 rounded' : 'text-slate-400 hover:text-white transition-colors' ?>" title="Dashboard">
                                <i class="fa-solid fa-chart-pie w-5 text-center"></i> <span class="sidebar-text ml-2">Dashboard</span>
                            </a>
                            <a href="shift_board" class="flex items-center px-4 py-2 <?= basename($_SERVER['PHP_SELF']) == 'shift_board.php' ? 'text-white font-medium bg-slate-800 rounded' : 'text-slate-400 hover:text-white transition-colors' ?>" title="Shift Board">
                                <i class="fa-solid fa-clipboard-list w-5 text-center"></i> <span class="sidebar-text ml-2">Shift Board</span>
                            </a>
                            <a href="applications" class="flex items-center px-4 py-2 <?= basename($_SERVER['PHP_SELF']) == 'applications.php' ? 'text-white font-medium bg-slate-800 rounded' : 'text-slate-400 hover:text-white transition-colors' ?>" title="Applications">
                                <i class="fa-solid fa-user-plus w-5 text-center"></i> <span class="sidebar-text ml-2">Applications</span>
                            </a>
                            <a href="employees" class="flex items-center px-4 py-2 <?= basename($_SERVER['PHP_SELF']) == 'employees.php' ? 'text-white font-medium bg-slate-800 rounded' : 'text-slate-400 hover:text-white transition-colors' ?>" title="Employees">
                                <i class="fa-solid fa-users w-5 text-center"></i> <span class="sidebar-text ml-2">Employees</span>
                            </a>
                            <a href="attendance" class="flex items-center px-4 py-2 <?= basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'text-white font-medium bg-slate-800 rounded' : 'text-slate-400 hover:text-white transition-colors' ?>" title="Attendance">
                                <i class="fa-solid fa-clock w-5 text-center"></i> <span class="sidebar-text ml-2">Attendance</span>
                            </a>
                            <a href="leave" class="flex items-center px-4 py-2 <?= basename($_SERVER['PHP_SELF']) == 'leave.php' ? 'text-white font-medium bg-slate-800 rounded' : 'text-slate-400 hover:text-white transition-colors' ?>" title="Leave & Shifts">
                                <i class="fa-solid fa-calendar-alt w-5 text-center"></i> <span class="sidebar-text ml-2">Leave & Shifts</span>
                            </a>
                            <a href="payroll" class="flex items-center px-4 py-2 <?= basename($_SERVER['PHP_SELF']) == 'payroll.php' ? 'text-white font-medium bg-slate-800 rounded' : 'text-slate-400 hover:text-white transition-colors' ?>" title="Payroll">
                                <i class="fa-solid fa-file-invoice-dollar w-5 text-center"></i> <span class="sidebar-text ml-2">Payroll</span>
                            </a>
                            <a href="performance" class="flex items-center px-4 py-2 <?= basename($_SERVER['PHP_SELF']) == 'performance.php' ? 'text-white font-medium bg-slate-800 rounded' : 'text-slate-400 hover:text-white transition-colors' ?>" title="Performance">
                                <i class="fa-solid fa-star w-5 text-center"></i> <span class="sidebar-text ml-2">Performance</span>
                            </a>
                        </div>
                    </div>

                    <?php if (in_array($role, [ROLE_SUPER_ADMIN, ROLE_BRANCH_MANAGER])): ?>
                    <!-- POS & INVENTORY -->
                    <div>
                        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2 px-4"><span class="sidebar-text">POS & INVENTORY</span></div>
                        <div class="space-y-1">
                            <a href="pos_dashboard" class="flex items-center px-4 py-2 <?= basename($_SERVER['PHP_SELF']) == 'pos_dashboard.php' ? 'text-white font-medium bg-slate-800 rounded' : 'text-slate-400 hover:text-white transition-colors' ?>" title="POS Dashboard">
                                <i class="fa-solid fa-cash-register w-5 text-center"></i> <span class="sidebar-text ml-2">POS Dashboard</span>
                            </a>
                            <a href="menu_list" class="flex items-center px-4 py-2 <?= basename($_SERVER['PHP_SELF']) == 'menu_list.php' ? 'text-white font-medium bg-slate-800 rounded' : 'text-slate-400 hover:text-white transition-colors' ?>" title="Menu List">
                                <i class="fa-solid fa-list-ul w-5 text-center"></i> <span class="sidebar-text ml-2">Menu List</span>
                            </a>
                            <a href="inventory" class="flex items-center px-4 py-2 <?= basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'text-white font-medium bg-slate-800 rounded' : 'text-slate-400 hover:text-white transition-colors' ?>" title="Inventory">
                                <i class="fa-solid fa-box-open w-5 text-center"></i> <span class="sidebar-text ml-2">Inventory</span>
                            </a>
                            <?php
                                $term_branch_id = $_SESSION['user']['branch_id'] ?? 1;
                                $term_params = in_array($_SESSION['user']['role'], [ROLE_SUPER_ADMIN]) ? "?mode=test&branch_id={$term_branch_id}" : "?branch_id={$term_branch_id}";
                            ?>
                            <a href="../cafe_pos/cashier<?= $term_params ?>" target="_blank" class="flex items-center px-4 py-2 text-slate-400 hover:text-white transition-colors mt-2 border-t border-slate-700/50 pt-2" title="Cashier Terminal">
                                <i class="fa-solid fa-desktop w-5 text-center"></i> <span class="sidebar-text ml-2">Cashier Terminal <i class="fa-solid fa-external-link-alt text-[10px] ml-1"></i></span>
                            </a>
                            <a href="../cafe_pos/kiosk<?= $term_params ?>" target="_blank" class="flex items-center px-4 py-2 text-slate-400 hover:text-white transition-colors" title="Kiosk Mode">
                                <i class="fa-solid fa-tablet-screen-button w-5 text-center"></i> <span class="sidebar-text ml-2">Kiosk Mode <i class="fa-solid fa-external-link-alt text-[10px] ml-1"></i></span>
                            </a>
                            <a href="procurement" class="flex items-center px-4 py-2 <?= basename($_SERVER['PHP_SELF']) == 'procurement.php' ? 'text-white font-medium bg-slate-800 rounded' : 'text-slate-400 hover:text-white transition-colors' ?>" title="Procurement">
                                <i class="fa-solid fa-truck-loading w-5 text-center"></i> <span class="sidebar-text ml-2">Procurement</span>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- FINANCE -->
                    <div>
                        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2 px-4"><span class="sidebar-text">FINANCE</span></div>
                        <div class="space-y-1">
                            <a href="finance_budgeting" class="flex items-center px-4 py-2 <?= basename($_SERVER['PHP_SELF']) == 'finance_budgeting.php' ? 'text-white font-medium bg-slate-800 rounded' : 'text-slate-400 hover:text-white transition-colors' ?>" title="P&L Dashboard">
                                <i class="fa-solid fa-chart-line w-5 text-center"></i> <span class="sidebar-text ml-2">P&L Dashboard</span>
                            </a>
                            <a href="finance_reports" class="flex items-center px-4 py-2 <?= basename($_SERVER['PHP_SELF']) == 'finance_reports.php' ? 'text-white font-medium bg-slate-800 rounded' : 'text-slate-400 hover:text-white transition-colors' ?>" title="Inventory Write-Offs">
                                <i class="fa-solid fa-file-invoice-dollar w-5 text-center"></i> <span class="sidebar-text ml-2">Inventory Write-Offs</span>
                            </a>
                        </div>
                    </div>

                    <?php if (in_array($role, ['Super Admin', 'Admin'])): ?>
                    <!-- ADMINISTRATION -->
                    <div>
                        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2 px-4"><span class="sidebar-text">ADMINISTRATION</span></div>
                        <div class="space-y-1">
                            <a href="system_accounts" class="flex items-center px-4 py-2 <?= basename($_SERVER['PHP_SELF']) == 'system_accounts.php' ? 'text-white font-medium bg-slate-800 rounded' : 'text-slate-400 hover:text-white transition-colors' ?>" title="System Accounts">
                                <i class="fa-solid fa-server w-5 text-center"></i> <span class="sidebar-text ml-2">System Accounts</span>
                            </a>
                            <a href="account_management" class="flex items-center px-4 py-2 <?= basename($_SERVER['PHP_SELF']) == 'account_management.php' ? 'text-white font-medium bg-slate-800 rounded' : 'text-slate-400 hover:text-white transition-colors' ?>" title="Account Management">
                                <i class="fa-solid fa-user-shield w-5 text-center"></i> <span class="sidebar-text ml-2">Account Management</span>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </nav>
        
        <div class="p-4 border-t border-slate-700">
            <a href="settings" class="flex items-center px-4 py-2 text-sm text-slate-400 hover:text-white transition-colors" title="Settings">
                <i class="fa-solid fa-cog w-6 text-center"></i> <span class="sidebar-text ml-3">Settings</span>
            </a>
            <a href="#" id="logout-trigger" class="flex items-center px-4 py-2 text-sm text-red-400 hover:bg-slate-800 hover:text-red-300 rounded transition-colors mt-1" title="Logout">
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
        <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-6 z-10 flex-shrink-0">
            <div class="flex items-center gap-4">
                <button id="sidebar-toggle" class="text-slate-500 hover:text-primary transition-colors focus:outline-none p-1 -ml-2 rounded hover:bg-slate-100">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
                <h2 class="text-xl font-semibold text-slate-800"><?= h($pageTitle ?? 'Dashboard') ?></h2>
            </div>
            <div class="flex items-center space-x-4">

                <!-- Notification Dropdown -->
                <div class="relative">
                    <button onclick="document.getElementById('notifDropdown').classList.toggle('hidden')" class="relative text-slate-400 hover:text-primary transition-colors focus:outline-none pt-1">
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
                    <div class="h-8 w-8 rounded-full bg-primary flex items-center justify-center text-white font-bold cursor-pointer overflow-hidden border-2 border-white shadow-sm">
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
