<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// CLI Support: If executed directly from command line, supply mock session before init.php auth check
if (php_sapi_name() === 'cli' && empty($_SESSION['user'])) {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $_SESSION['user'] = [
        'id' => 141, // Juan Dela Cruz
        'role' => 'Employee',
        'branch_id' => 1,
        'name' => 'Juan Dela Cruz',
        'employee_id' => 1
    ];
}

require_once __DIR__ . '/init.php';
require_login();

$user = current_user();

// Resolve employee_id for the logged-in user
$empId = null;
if (!empty($user['employee_id'])) {
    $empId = (int)$user['employee_id'];
} else {
    $stmt = $pdo->prepare('SELECT employee_id FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $empId = $stmt->fetchColumn() ?: null;
}

$employee = null;
if ($empId) {
    $empStmt = $pdo->prepare('SELECT *, CONCAT(first_name, " ", last_name) AS full_name FROM employees WHERE id = ?');
    $empStmt->execute([$empId]);
    $employee = $empStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// CRITICAL CONSTRAINT 1: Admin ESS Fallback: If not linked to employee profile, do not crash. Display clean message.
if (!$empId || !$employee) {
    $pageTitle = 'Employee Self Service';
    require_once __DIR__ . '/includes/header.php';
    ?>
    <!-- Tailwind CDN & Brand Configuration Guarantee -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        if (typeof tailwind !== 'undefined') {
            tailwind.config = tailwind.config || {};
            tailwind.config.theme = tailwind.config.theme || {};
            tailwind.config.theme.extend = tailwind.config.theme.extend || {};
            tailwind.config.theme.extend.colors = Object.assign(tailwind.config.theme.extend.colors || {}, {
                espresso: {
                    50: '#fbf8f5', 100: '#f5efe9', 200: '#ebdcd0', 300: '#dbc0ad', 400: '#c59d83',
                    700: '#5c3821', 800: '#422415', 900: '#2c180e', 950: '#1a0d07'
                },
                amber: {
                    50: '#fffbeb', 100: '#fef3c7', 200: '#fde68a', 300: '#fcd34d', 400: '#fbbf24',
                    500: '#f59e0b', 600: '#d97706', 700: '#b45309', 800: '#92400e', 900: '#78350f'
                },
                cream: {
                    50: '#fdfbf7', 100: '#fbf7f0', 200: '#f5eee1', 300: '#ece1ce', 400: '#decbb0'
                }
            });
        }
    </script>
    <div class="p-6 max-w-4xl mx-auto space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-amber-200/80 p-8 text-center space-y-4">
            <div class="w-16 h-16 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-500 flex items-center justify-center text-3xl mx-auto">
                <i class="fa-solid fa-user-slash"></i>
            </div>
            <div class="space-y-1">
                <h2 class="text-xl font-bold font-serif text-slate-800">No employee profile linked to this account.</h2>
                <p class="text-sm text-slate-600 max-w-md mx-auto">
                    Employee Self-Service (ESS) is designed for active staff members. Your account (<strong><?= h($user['name'] ?? $user['username'] ?? 'User') ?></strong> &mdash; <em><?= h($user['role'] ?? 'Admin') ?></em>) is not currently associated with an active employee record.
                </p>
            </div>
            <div class="pt-2 flex flex-wrap justify-center gap-3">
                <a href="dashboard" class="inline-flex items-center px-4 py-2 bg-amber-500 hover:bg-amber-400 text-espresso-950 font-bold text-sm rounded-xl transition-all shadow-sm">
                    <i class="fa-solid fa-gauge-high mr-2"></i> Go to HR Dashboard
                </a>
                <a href="employees" class="inline-flex items-center px-4 py-2 border border-slate-300 text-slate-700 hover:bg-slate-50 font-medium text-sm rounded-xl transition-all">
                    <i class="fa-solid fa-users mr-2"></i> View Employees
                </a>
            </div>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$message = null;
$messageType = 'success';

// Handle Leave Request Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_leave') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $message = "Security token mismatch. Please try again.";
        $messageType = 'error';
    } else {
        $type = trim($_POST['leave_type'] ?? 'Vacation');
        $start = trim($_POST['start_date'] ?? '');
        $end = trim($_POST['end_date'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        
        if (empty($start) || empty($end)) {
            $message = "Please select valid start and end dates.";
            $messageType = 'error';
        } else {
            $status = 'Pending';
            if ($type === 'Sick Leave') {
                $status = 'Approved';
            }
            
            // Maternal Leave Logic (105 Days, Female Only)
            if ($type === 'Maternal Leave') {
                if (($employee['sex'] ?? '') !== 'Female') {
                    $message = "Maternal Leave is only applicable to female employees.";
                    $messageType = 'error';
                } else {
                    $startDateObj = new DateTime($start);
                    $startDateObj->modify('+105 days');
                    $end = $startDateObj->format('Y-m-d');
                    $status = 'Approved';
                }
            }
            
            if ($messageType !== 'error') {
                $medCertPath = null;
                if ($type === 'Sick Leave' && !empty($_FILES['med_cert']['tmp_name'])) {
                    $uploadDir = __DIR__ . '/uploads/leaves/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $ext = pathinfo($_FILES['med_cert']['name'], PATHINFO_EXTENSION);
                    $filename = uniqid('cert_', true) . '.' . $ext;
                    if (move_uploaded_file($_FILES['med_cert']['tmp_name'], $uploadDir . $filename)) {
                        $medCertPath = 'uploads/leaves/' . $filename;
                    }
                }
                
                $stmt = $pdo->prepare('INSERT INTO leaves (employee_id, leave_type, start_date, end_date, reason, status, med_cert, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
                $stmt->execute([$empId, $type, $start, $end, $reason, $status, $medCertPath]);
                
                // Check if any shifts fall in this range
                $countShifts = $pdo->prepare('SELECT COUNT(*) FROM schedules WHERE employee_id = ? AND shift_date BETWEEN ? AND ?');
                $countShifts->execute([$empId, $start, $end]);
                $clearedCount = (int)$countShifts->fetchColumn();
                
                if ($clearedCount > 0) {
                    $updateShifts = $pdo->prepare("UPDATE schedules SET employee_id = NULL, status = 'Open' WHERE employee_id = ? AND shift_date BETWEEN ? AND ?");
                    $updateShifts->execute([$empId, $start, $end]);
                }
                
                $message = "Leave requested successfully. " . ($clearedCount > 0 ? "($clearedCount scheduled shift(s) were posted to the Shift Board.)" : "");
                $messageType = 'success';
            }
        }
    }
}

// -------------------------------------------------------------------------
// DATA QUERIES
// -------------------------------------------------------------------------

// 1. Released Payslips Query
$payslipsStmt = $pdo->prepare("
    SELECT * 
    FROM payroll 
    WHERE employee_id = ? AND status = 'Released' 
    ORDER BY period_end DESC, id DESC
");
$payslipsStmt->execute([$empId]);
$myPayslips = $payslipsStmt->fetchAll(PDO::FETCH_ASSOC);
$lastPayslip = !empty($myPayslips) ? $myPayslips[0] : null;

// 2. Leave History Query
$leavesStmt = $pdo->prepare("
    SELECT * 
    FROM leaves 
    WHERE employee_id = ? 
    ORDER BY start_date DESC, id DESC
");
$leavesStmt->execute([$empId]);
$myLeaves = $leavesStmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Schedules Query (upcoming and recent)
$schedStmt = $pdo->prepare("
    SELECT * 
    FROM schedules 
    WHERE employee_id = ? 
      AND shift_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
    ORDER BY shift_date ASC, start_time ASC
");
$schedStmt->execute([$empId]);
$mySchedules = $schedStmt->fetchAll(PDO::FETCH_ASSOC);

// Find next upcoming shift
$nextShift = null;
$today = date('Y-m-d');
foreach ($mySchedules as $s) {
    if ($s['shift_date'] >= $today) {
        $nextShift = $s;
        break;
    }
}

// Summary Metrics for Dashboard KPI Cards
$leaveCredits = (float)($employee['sick_leave_balance'] ?? 5);

// Tenure Calculation
$tenure = 'N/A';
if (!empty($employee['date_hired'])) {
    $d1 = new DateTime($employee['date_hired']);
    $d2 = new DateTime();
    $diff = $d1->diff($d2);
    if ($diff->y > 0) $tenure = $diff->y . ' yrs, ' . $diff->m . ' mos';
    elseif ($diff->m > 0) $tenure = $diff->m . ' mos, ' . $diff->d . ' days';
    else $tenure = $diff->d . ' days';
}

$pageTitle = 'Employee Self Service';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Tailwind CDN & Brand Configuration Guarantee -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
    if (typeof tailwind !== 'undefined') {
        tailwind.config = tailwind.config || {};
        tailwind.config.theme = tailwind.config.theme || {};
        tailwind.config.theme.extend = tailwind.config.theme.extend || {};
        tailwind.config.theme.extend.colors = Object.assign(tailwind.config.theme.extend.colors || {}, {
            espresso: {
                50: '#fbf8f5', 100: '#f5efe9', 200: '#ebdcd0', 300: '#dbc0ad', 400: '#c59d83',
                700: '#5c3821', 800: '#422415', 900: '#2c180e', 950: '#1a0d07'
            },
            amber: {
                50: '#fffbeb', 100: '#fef3c7', 200: '#fde68a', 300: '#fcd34d', 400: '#fbbf24',
                500: '#f59e0b', 600: '#d97706', 700: '#b45309', 800: '#92400e', 900: '#78350f'
            },
            cream: {
                50: '#fdfbf7', 100: '#fbf7f0', 200: '#f5eee1', 300: '#ece1ce', 400: '#decbb0'
            }
        });
    }
</script>

<!-- Main Content Wrapper -->
<div class="p-6 max-w-7xl mx-auto space-y-6">

    <!-- Header Banner -->
    <div class="bg-espresso-950 text-cream-100 rounded-2xl p-6 sm:p-8 shadow-md border border-amber-500/20 relative overflow-hidden flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="absolute -right-8 -bottom-8 w-48 h-48 bg-amber-500/5 rounded-full pointer-events-none"></div>
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-400 text-xs font-bold mb-3 uppercase tracking-wider">
                <i class="fa-solid fa-mug-saucer"></i> Fika Employee Portal
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold font-serif tracking-tight text-cream-50">
                Welcome back, <?= h($employee['full_name']) ?>
            </h1>
            <p class="text-sm text-cream-100/70 mt-1 flex flex-wrap items-center gap-3">
                <span class="inline-flex items-center gap-1.5"><i class="fa-solid fa-id-badge text-amber-400"></i> Emp ID: #<?= h($employee['id']) ?></span>
                <span class="text-cream-100/40">•</span>
                <span class="inline-flex items-center gap-1.5"><i class="fa-solid fa-briefcase text-amber-400"></i> <?= h($employee['position'] ?? 'Barista') ?></span>
                <span class="text-cream-100/40">•</span>
                <span class="inline-flex items-center gap-1.5"><i class="fa-solid fa-calendar-check text-amber-400"></i> Tenure: <?= h($tenure) ?></span>
            </p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="openModal('leaveModal')" class="inline-flex items-center px-4 py-2.5 bg-amber-500 hover:bg-amber-400 text-espresso-950 font-bold text-sm rounded-xl transition-all shadow-sm">
                <i class="fa-solid fa-plane-departure mr-2"></i> Request Leave
            </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="p-4 rounded-xl flex items-center shadow-sm border <?= $messageType === 'success' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200' ?>">
            <i class="fa-solid <?= $messageType === 'success' ? 'fa-circle-check text-emerald-500' : 'fa-circle-exclamation text-rose-500' ?> mr-3 text-lg"></i>
            <span class="text-sm font-medium"><?= h($message) ?></span>
        </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-2 flex flex-wrap gap-2">
        <button type="button" onclick="switchTab('dashboard')" id="btn-tab-dashboard" class="tab-btn px-5 py-2.5 rounded-xl font-bold text-sm transition-all shadow-sm bg-amber-500 text-espresso-950">
            <i class="fa-solid fa-gauge-high mr-2"></i> Dashboard
        </button>
        <button type="button" onclick="switchTab('payslips')" id="btn-tab-payslips" class="tab-btn px-5 py-2.5 rounded-xl font-semibold text-sm transition-all text-slate-600 hover:text-amber-800 hover:bg-amber-50">
            <i class="fa-solid fa-receipt mr-2"></i> My Payslips
        </button>
        <button type="button" onclick="switchTab('leave')" id="btn-tab-leave" class="tab-btn px-5 py-2.5 rounded-xl font-semibold text-sm transition-all text-slate-600 hover:text-amber-800 hover:bg-amber-50">
            <i class="fa-solid fa-calendar-check mr-2"></i> My Leave
        </button>
        <button type="button" onclick="switchTab('schedule')" id="btn-tab-schedule" class="tab-btn px-5 py-2.5 rounded-xl font-semibold text-sm transition-all text-slate-600 hover:text-amber-800 hover:bg-amber-50">
            <i class="fa-solid fa-calendar-days mr-2"></i> My Schedule
        </button>
    </div>

    <!-- =================================================================== -->
    <!-- TAB 1: DASHBOARD -->
    <!-- =================================================================== -->
    <div id="tab-dashboard" class="tab-pane space-y-6">

        <!-- 3 KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            <!-- KPI 1: Leave Credits -->
            <div class="bg-espresso-950 text-cream-100 border border-amber-500/20 rounded-2xl p-6 shadow-md relative overflow-hidden transition-all hover:border-amber-400/40">
                <div class="absolute -right-4 -bottom-4 w-28 h-28 bg-amber-500/5 rounded-full pointer-events-none"></div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-bold text-amber-400/80 uppercase tracking-wider">Leave Credits</span>
                    <div class="w-10 h-10 rounded-xl bg-amber-400/10 border border-amber-400/20 flex items-center justify-center text-amber-400">
                        <i class="fa-solid fa-calendar-check text-base"></i>
                    </div>
                </div>
                <div class="text-3xl lg:text-4xl font-extrabold text-amber-400 tracking-tight">
                    <?= number_format($leaveCredits, 1) ?> <span class="text-sm font-normal text-cream-100/70">Days</span>
                </div>
                <div class="mt-3 text-xs text-cream-100/60 flex items-center justify-between border-t border-espresso-900 pt-3">
                    <span>Available Balance</span>
                    <button type="button" onclick="switchTab('leave')" class="text-amber-400 font-semibold hover:underline">
                        Apply Leave <i class="fa-solid fa-arrow-right ml-1 text-[10px]"></i>
                    </button>
                </div>
            </div>

            <!-- KPI 2: Last Payslip -->
            <div class="bg-espresso-950 text-cream-100 border border-amber-500/20 rounded-2xl p-6 shadow-md relative overflow-hidden transition-all hover:border-amber-400/40">
                <div class="absolute -right-4 -bottom-4 w-28 h-28 bg-emerald-500/5 rounded-full pointer-events-none"></div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-bold text-amber-400/80 uppercase tracking-wider">Last Payslip</span>
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                        <i class="fa-solid fa-receipt text-base"></i>
                    </div>
                </div>
                <div class="text-3xl lg:text-4xl font-extrabold text-emerald-400 tracking-tight">
                    <?= $lastPayslip ? '₱' . number_format($lastPayslip['net_pay'], 2) : '₱0.00' ?>
                </div>
                <div class="mt-3 text-xs text-cream-100/60 flex items-center justify-between border-t border-espresso-900 pt-3">
                    <?php if ($lastPayslip): ?>
                        <span><?= date('M d', strtotime($lastPayslip['period_start'])) ?> - <?= date('M d, Y', strtotime($lastPayslip['period_end'])) ?></span>
                        <button type="button" onclick="switchTab('payslips')" class="text-emerald-400 font-semibold hover:underline">
                            View Details <i class="fa-solid fa-arrow-right ml-1 text-[10px]"></i>
                        </button>
                    <?php else: ?>
                        <span>No released payslip</span>
                        <span class="text-slate-400">Pending</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- KPI 3: Next Shift -->
            <div class="bg-espresso-950 text-cream-100 border border-amber-500/20 rounded-2xl p-6 shadow-md relative overflow-hidden transition-all hover:border-amber-400/40">
                <div class="absolute -right-4 -bottom-4 w-28 h-28 bg-blue-500/5 rounded-full pointer-events-none"></div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-bold text-amber-400/80 uppercase tracking-wider">Next Shift</span>
                    <div class="w-10 h-10 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-400">
                        <i class="fa-solid fa-clock text-base"></i>
                    </div>
                </div>
                <div class="text-2xl lg:text-3xl font-extrabold text-cream-100 tracking-tight">
                    <?= $nextShift ? date('D, M d', strtotime($nextShift['shift_date'])) : 'No Shift' ?>
                </div>
                <div class="mt-3 text-xs text-cream-100/60 flex items-center justify-between border-t border-espresso-900 pt-3">
                    <?php if ($nextShift): ?>
                        <span class="text-amber-400 font-medium"><?= h($nextShift['shift_type']) ?> Shift (<?= date('h:i A', strtotime($nextShift['start_time'])) ?>)</span>
                        <button type="button" onclick="switchTab('schedule')" class="text-blue-400 font-semibold hover:underline">
                            Full Roster <i class="fa-solid fa-arrow-right ml-1 text-[10px]"></i>
                        </button>
                    <?php else: ?>
                        <span>No upcoming shift assigned</span>
                        <button type="button" onclick="switchTab('schedule')" class="text-slate-400 hover:underline">View Roster</button>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Dashboard Split Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Upcoming Shifts Preview -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <div>
                        <h2 class="text-base font-bold text-slate-800 flex items-center">
                            <i class="fa-solid fa-calendar-days text-amber-500 mr-2"></i> Upcoming Shift Schedule
                        </h2>
                        <p class="text-xs text-slate-500">Your next scheduled shifts</p>
                    </div>
                    <button type="button" onclick="switchTab('schedule')" class="text-xs font-semibold text-amber-600 hover:text-amber-700 bg-amber-50 hover:bg-amber-100 px-3 py-1.5 rounded-lg transition-colors border border-amber-200">
                        View All
                    </button>
                </div>
                <div class="p-0">
                    <?php 
                    $upcomingList = array_filter($mySchedules, function($s) use ($today) { return $s['shift_date'] >= $today; });
                    $upcomingList = array_slice($upcomingList, 0, 4);
                    ?>
                    <?php if (empty($upcomingList)): ?>
                        <div class="p-8 text-center text-slate-400">
                            <i class="fa-regular fa-calendar-xmark text-3xl mb-2 text-slate-300 block"></i>
                            No upcoming shifts assigned.
                        </div>
                    <?php else: ?>
                        <ul class="divide-y divide-slate-100">
                            <?php foreach ($upcomingList as $s): ?>
                                <li class="p-4 hover:bg-slate-50/70 flex justify-between items-center transition-colors">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-800 flex flex-col items-center justify-center font-bold text-xs">
                                            <span><?= date('M', strtotime($s['shift_date'])) ?></span>
                                            <span class="text-sm leading-none"><?= date('d', strtotime($s['shift_date'])) ?></span>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-800 text-sm"><?= date('l', strtotime($s['shift_date'])) ?></div>
                                            <div class="text-xs text-slate-500"><?= date('h:i A', strtotime($s['start_time'])) ?> - <?= date('h:i A', strtotime($s['end_time'])) ?></div>
                                        </div>
                                    </div>
                                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-espresso-950 text-amber-400 border border-amber-500/20">
                                        <?= h($s['shift_type']) ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Payslip Preview -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <div>
                        <h2 class="text-base font-bold text-slate-800 flex items-center">
                            <i class="fa-solid fa-receipt text-emerald-600 mr-2"></i> Recent Payslips
                        </h2>
                        <p class="text-xs text-slate-500">Released by Branch & Management</p>
                    </div>
                    <button type="button" onclick="switchTab('payslips')" class="text-xs font-semibold text-emerald-700 hover:text-emerald-800 bg-emerald-50 hover:bg-emerald-100 px-3 py-1.5 rounded-lg transition-colors border border-emerald-200">
                        View All
                    </button>
                </div>
                <div class="p-0">
                    <?php $recentPayslipsList = array_slice($myPayslips, 0, 4); ?>
                    <?php if (empty($recentPayslipsList)): ?>
                        <div class="p-8 text-center text-slate-400">
                            <i class="fa-solid fa-file-invoice-dollar text-3xl mb-2 text-slate-300 block"></i>
                            No released payslips yet.
                        </div>
                    <?php else: ?>
                        <ul class="divide-y divide-slate-100">
                            <?php foreach ($recentPayslipsList as $p): ?>
                                <li class="p-4 hover:bg-slate-50/70 flex justify-between items-center transition-colors">
                                    <div>
                                        <div class="font-bold text-slate-800 text-sm">
                                            <?= date('M d', strtotime($p['period_start'])) ?> - <?= date('M d, Y', strtotime($p['period_end'])) ?>
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            Method: <?= h($p['payment_method'] ?? 'Bank Transfer') ?>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-extrabold text-slate-900 text-base">₱<?= number_format($p['net_pay'], 2) ?></div>
                                        <span class="inline-block px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-800">
                                            Released
                                        </span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>

    <!-- =================================================================== -->
    <!-- TAB 2: MY PAYSLIPS -->
    <!-- =================================================================== -->
    <div id="tab-payslips" class="tab-pane hidden space-y-6">

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 bg-slate-50/50">
                <div>
                    <h2 class="text-lg font-bold text-slate-800 flex items-center">
                        <i class="fa-solid fa-receipt text-amber-500 mr-2"></i> Official Released Payslips
                    </h2>
                    <p class="text-xs text-slate-500">History of salary statements released to your profile</p>
                </div>
                <div class="text-xs text-slate-500 bg-white border border-slate-200 px-3 py-1.5 rounded-lg shadow-2xs">
                    Total Released: <span class="font-bold text-slate-800"><?= count($myPayslips) ?></span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-[11px] font-bold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                            <th class="py-3 px-6">Pay Period</th>
                            <th class="py-3 px-6 text-right">Gross Pay</th>
                            <th class="py-3 px-6 text-right">Deductions</th>
                            <th class="py-3 px-6 text-right">Net Pay</th>
                            <th class="py-3 px-6 text-center">Payment Method</th>
                            <th class="py-3 px-6 text-center">Status</th>
                            <th class="py-3 px-6 text-right">Date Released</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <?php if (empty($myPayslips)): ?>
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    <i class="fa-solid fa-file-invoice text-4xl mb-3 text-slate-300 block"></i>
                                    No released payslips found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($myPayslips as $p): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-3.5 px-6 font-semibold text-slate-800">
                                        <?= date('M d, Y', strtotime($p['period_start'])) ?> &mdash; <?= date('M d, Y', strtotime($p['period_end'])) ?>
                                    </td>
                                    <td class="py-3.5 px-6 text-right text-slate-600 font-mono">
                                        ₱<?= number_format($p['gross_pay'], 2) ?>
                                    </td>
                                    <td class="py-3.5 px-6 text-right text-rose-600 font-mono">
                                        -₱<?= number_format($p['deductions'], 2) ?>
                                    </td>
                                    <td class="py-3.5 px-6 text-right font-extrabold text-emerald-700 font-mono text-base">
                                        ₱<?= number_format($p['net_pay'], 2) ?>
                                    </td>
                                    <td class="py-3.5 px-6 text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                            <?= h($p['payment_method'] ?? 'Bank Transfer') ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-6 text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                            <i class="fa-solid fa-check-circle mr-1 text-[10px]"></i> Released
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-6 text-right text-xs text-slate-500">
                                        <?= !empty($p['released_at']) ? date('M d, Y h:i A', strtotime($p['released_at'])) : date('M d, Y', strtotime($p['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- =================================================================== -->
    <!-- TAB 3: MY LEAVE -->
    <!-- =================================================================== -->
    <div id="tab-leave" class="tab-pane hidden space-y-6">

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-slate-50/50">
                <div>
                    <h2 class="text-lg font-bold text-slate-800 flex items-center">
                        <i class="fa-solid fa-calendar-check text-amber-500 mr-2"></i> Leave Requests & History
                    </h2>
                    <p class="text-xs text-slate-500">Current Balance: <strong class="text-amber-700 font-bold"><?= number_format($leaveCredits, 1) ?> Days</strong></p>
                </div>
                <button onclick="openModal('leaveModal')" class="inline-flex items-center px-4 py-2 bg-amber-500 hover:bg-amber-400 text-espresso-950 font-bold text-xs rounded-xl transition-all shadow-sm">
                    <i class="fa-solid fa-plus mr-1.5"></i> Request New Leave
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-[11px] font-bold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                            <th class="py-3 px-6">Leave Type</th>
                            <th class="py-3 px-6">Start Date</th>
                            <th class="py-3 px-6">End Date</th>
                            <th class="py-3 px-6">Duration</th>
                            <th class="py-3 px-6">Reason / Memo</th>
                            <th class="py-3 px-6 text-center">Status</th>
                            <th class="py-3 px-6 text-right">Filed On</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <?php if (empty($myLeaves)): ?>
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    <i class="fa-solid fa-plane-departure text-4xl mb-3 text-slate-300 block"></i>
                                    No leave requests submitted yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($myLeaves as $l): ?>
                                <?php
                                $dStart = new DateTime($l['start_date']);
                                $dEnd = new DateTime($l['end_date']);
                                $dayDiff = $dStart->diff($dEnd)->days + 1;
                                ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-3.5 px-6 font-bold text-slate-800">
                                        <?= h($l['leave_type']) ?>
                                    </td>
                                    <td class="py-3.5 px-6 text-slate-600">
                                        <?= date('M d, Y', strtotime($l['start_date'])) ?>
                                    </td>
                                    <td class="py-3.5 px-6 text-slate-600">
                                        <?= date('M d, Y', strtotime($l['end_date'])) ?>
                                    </td>
                                    <td class="py-3.5 px-6 text-slate-500 font-medium">
                                        <?= $dayDiff ?> <?= $dayDiff === 1 ? 'day' : 'days' ?>
                                    </td>
                                    <td class="py-3.5 px-6 text-slate-600 max-w-xs truncate" title="<?= h($l['reason']) ?>">
                                        <?= h($l['reason'] ?: 'No notes') ?>
                                    </td>
                                    <td class="py-3.5 px-6 text-center">
                                        <?php if ($l['status'] === 'Approved'): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                                <i class="fa-solid fa-circle-check mr-1 text-[10px]"></i> Approved
                                            </span>
                                        <?php elseif ($l['status'] === 'Rejected'): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800">
                                                <i class="fa-solid fa-circle-xmark mr-1 text-[10px]"></i> Rejected
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                                                <i class="fa-solid fa-clock mr-1 text-[10px]"></i> Pending Review
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3.5 px-6 text-right text-xs text-slate-500">
                                        <?= date('M d, Y', strtotime($l['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- =================================================================== -->
    <!-- TAB 4: MY SCHEDULE -->
    <!-- =================================================================== -->
    <div id="tab-schedule" class="tab-pane hidden space-y-6">

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 bg-slate-50/50">
                <div>
                    <h2 class="text-lg font-bold text-slate-800 flex items-center">
                        <i class="fa-solid fa-calendar-days text-amber-500 mr-2"></i> Assigned Work Schedule
                    </h2>
                    <p class="text-xs text-slate-500">Showing upcoming shifts and recent schedule assignments</p>
                </div>
                <div class="text-xs text-slate-500 bg-white border border-slate-200 px-3 py-1.5 rounded-lg shadow-2xs">
                    Total Shifts: <span class="font-bold text-slate-800"><?= count($mySchedules) ?></span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-[11px] font-bold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                            <th class="py-3 px-6">Date</th>
                            <th class="py-3 px-6">Shift Type</th>
                            <th class="py-3 px-6">Time Window</th>
                            <th class="py-3 px-6 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <?php if (empty($mySchedules)): ?>
                            <tr>
                                <td colspan="4" class="py-12 text-center text-slate-400">
                                    <i class="fa-regular fa-calendar-xmark text-4xl mb-3 text-slate-300 block"></i>
                                    No shifts assigned in this schedule window.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($mySchedules as $s): ?>
                                <?php 
                                $isToday = ($s['shift_date'] === $today);
                                $isPast = ($s['shift_date'] < $today);
                                ?>
                                <tr class="<?= $isToday ? 'bg-amber-50/40 font-medium' : 'hover:bg-slate-50/80' ?> transition-colors">
                                    <td class="py-3.5 px-6 font-semibold text-slate-800 flex items-center gap-2">
                                        <?php if ($isToday): ?>
                                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                        <?php endif; ?>
                                        <?= date('l, M d, Y', strtotime($s['shift_date'])) ?>
                                    </td>
                                    <td class="py-3.5 px-6">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-xs font-bold bg-espresso-950 text-amber-400 border border-amber-500/20">
                                            <?= h($s['shift_type']) ?> Shift
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-6 text-slate-600 font-mono text-xs">
                                        <?= date('h:i A', strtotime($s['start_time'])) ?> &mdash; <?= date('h:i A', strtotime($s['end_time'])) ?>
                                    </td>
                                    <td class="py-3.5 px-6 text-center">
                                        <?php if ($isToday): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-900 border border-amber-300">
                                                <i class="fa-solid fa-star mr-1 text-[10px]"></i> Today
                                            </span>
                                        <?php elseif ($isPast): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">
                                                Completed
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                                Upcoming
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>

<!-- Leave Modal -->
<div id="leaveModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden flex flex-col">
        <div class="px-6 py-4 bg-espresso-950 text-cream-100 flex justify-between items-center border-b border-amber-500/20">
            <h3 class="text-base font-bold flex items-center font-serif text-cream-50">
                <i class="fa-solid fa-plane-departure text-amber-400 mr-2"></i> Request Leave
            </h3>
            <button type="button" onclick="closeModal('leaveModal')" class="text-cream-100/60 hover:text-amber-400 transition-colors">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
        <form autocomplete="off" method="POST" action="ess" class="p-6 space-y-4" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">
            <input type="hidden" name="action" value="request_leave">
            
            <p class="text-xs text-amber-900 bg-amber-50 p-3 rounded-xl border border-amber-200 leading-relaxed">
                <i class="fa-solid fa-circle-info text-amber-600 mr-1"></i> 
                Assigned shifts scheduled during approved leave dates are automatically published to the open Shift Board.
            </p>
            
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Leave Type *</label>
                <select name="leave_type" id="leaveType" required class="w-full rounded-lg border-slate-300 border p-2.5 focus:ring-amber-500 focus:border-amber-500 text-sm" onchange="handleLeaveTypeChange()">
                    <option value="Vacation">Vacation Leave</option>
                    <option value="Sick Leave">Sick Leave (Auto-Approved)</option>
                    <option value="Unpaid">Unpaid Leave</option>
                    <?php if (($employee['sex'] ?? '') === 'Female'): ?>
                        <option value="Maternal Leave">Maternal Leave (105 Days)</option>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Start Date *</label>
                    <input type="date" name="start_date" id="startDate" required class="w-full rounded-lg border-slate-300 border p-2.5 focus:ring-amber-500 focus:border-amber-500 text-sm" onchange="handleLeaveTypeChange()">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">End Date *</label>
                    <input type="date" name="end_date" id="endDate" required class="w-full rounded-lg border-slate-300 border p-2.5 focus:ring-amber-500 focus:border-amber-500 text-sm bg-white">
                </div>
            </div>
            
            <div id="medCertContainer" class="hidden bg-amber-50/70 p-3 rounded-xl border border-amber-200">
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Medical Certificate (Optional)</label>
                <p class="text-[11px] text-slate-500 mb-2">Upload medical proof if available.</p>
                <input type="file" name="med_cert" accept="image/*,.pdf" class="w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-100 file:text-amber-800 hover:file:bg-amber-200">
            </div>
            
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Reason *</label>
                <textarea name="reason" rows="2" required placeholder="State reason for leave request" class="w-full rounded-lg border-slate-300 border p-2.5 focus:ring-amber-500 focus:border-amber-500 text-sm"></textarea>
            </div>
            
            <div class="mt-6 flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal('leaveModal')" class="px-4 py-2 border border-slate-300 rounded-xl text-slate-700 hover:bg-slate-50 font-medium text-sm transition-colors">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-amber-500 hover:bg-amber-400 text-espresso-950 font-bold rounded-xl text-sm transition-colors shadow-sm">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('hidden');
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('hidden');
}

function handleLeaveTypeChange() {
    const type = document.getElementById('leaveType').value;
    const start = document.getElementById('startDate').value;
    const endInput = document.getElementById('endDate');
    
    if (type === 'Maternal Leave') {
        endInput.readOnly = true;
        endInput.classList.add('bg-slate-100');
        endInput.classList.remove('bg-white');
        
        if (start) {
            const date = new Date(start);
            date.setDate(date.getDate() + 105);
            const yyyy = date.getFullYear();
            const mm = String(date.getMonth() + 1).padStart(2, '0');
            const dd = String(date.getDate()).padStart(2, '0');
            endInput.value = `${yyyy}-${mm}-${dd}`;
        }
    } else {
        endInput.readOnly = false;
        endInput.classList.remove('bg-slate-100');
        endInput.classList.add('bg-white');
    }
    
    const medCertContainer = document.getElementById('medCertContainer');
    if (type === 'Sick Leave') {
        medCertContainer.classList.remove('hidden');
    } else {
        medCertContainer.classList.add('hidden');
    }
}

// -------------------------------------------------------------------------
// Tab Navigation Controller (Supports #dashboard, #payslips, #leave, #schedule)
// -------------------------------------------------------------------------
function switchTab(tabId) {
    const validTabs = ['dashboard', 'payslips', 'leave', 'schedule'];
    if (!validTabs.includes(tabId)) {
        tabId = 'dashboard';
    }

    validTabs.forEach(t => {
        const pane = document.getElementById('tab-' + t);
        const btn = document.getElementById('btn-tab-' + t);
        if (pane) {
            if (t === tabId) {
                pane.classList.remove('hidden');
            } else {
                pane.classList.add('hidden');
            }
        }
        if (btn) {
            if (t === tabId) {
                btn.className = "tab-btn px-5 py-2.5 rounded-xl font-bold text-sm transition-all shadow-sm bg-amber-500 text-espresso-950";
            } else {
                btn.className = "tab-btn px-5 py-2.5 rounded-xl font-semibold text-sm transition-all text-slate-600 hover:text-amber-800 hover:bg-amber-50";
            }
        }
    });

    if (window.location.hash.replace('#', '') !== tabId) {
        history.replaceState(null, null, '#' + tabId);
    }
}

function handleHash() {
    let hash = window.location.hash.replace('#', '').trim().toLowerCase();
    if (hash === 'payslips') {
        switchTab('payslips');
    } else if (hash === 'leave') {
        switchTab('leave');
    } else if (hash === 'schedule') {
        switchTab('schedule');
    } else {
        switchTab('dashboard');
    }
}

// Ensure both hashchange and DOMContentLoaded are listened to
window.addEventListener('hashchange', handleHash);
document.addEventListener('DOMContentLoaded', handleHash);

// Immediate execution if DOM is already parsed (e.g. page loads at ess.php#payslips)
if (document.readyState === 'interactive' || document.readyState === 'complete') {
    handleHash();
}

window.addEventListener('click', (e) => {
    const lm = document.getElementById('leaveModal');
    if (e.target === lm) closeModal('leaveModal');
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
