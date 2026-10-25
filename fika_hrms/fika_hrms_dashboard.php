<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/core/Output.php';

Auth::requireLogin();
Rbac::require_permission('hr.employee.view');

global $pdo;

$currentUser = Auth::user();

// 1. Core Operational Stat KPIs
$totalEmployees = 0;
$presentToday = 0;
$pendingLeaves = 0;
$todaySales = 0.0;

try {
    [$empScope, $empParams] = Rbac::branch_scope();
    $empStmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE status = 'ACTIVE' AND deleted_at IS NULL {$empScope}");
    $empStmt->execute($empParams);
    $totalEmployees = (int)($empStmt ? $empStmt->fetchColumn() : 0);

    [$clockScope, $clockParams] = Rbac::branch_scope();
    $clockStmt = $pdo->prepare("SELECT COUNT(DISTINCT employee_id) FROM attendance_logs WHERE work_date = CURDATE() AND clock_in IS NOT NULL {$clockScope}");
    $clockStmt->execute($clockParams);
    $presentToday = (int)($clockStmt ? $clockStmt->fetchColumn() : 0);

    [$leaveScope, $leaveParams] = Rbac::branch_scope('e');
    $leaveStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM leave_requests lr
        LEFT JOIN employees e ON lr.employee_id = e.id
        WHERE lr.status = 'PENDING' {$leaveScope}
    ");
    $leaveStmt->execute($leaveParams);
    $pendingLeaves = (int)($leaveStmt ? $leaveStmt->fetchColumn() : 0);

    [$salesScope, $salesParams] = Rbac::branch_scope();
    $salesStmt = $pdo->prepare("SELECT COALESCE(SUM(final_amount), 0) FROM orders WHERE DATE(created_at) = CURDATE() AND payment_status = 'PAID' {$salesScope}");
    $salesStmt->execute($salesParams);
    $todaySales = (float)($salesStmt ? $salesStmt->fetchColumn() : 0.0);
} catch (Throwable $e) {
    // Fallback defaults on query failure
}

// 2. Daily Agenda: Today's Scheduled Interviews
$todayInterviews = [];
try {
    [$appScopeA, $appParamsA] = Rbac::branch_scope('a');
    $intStmt = $pdo->prepare("
        SELECT i.id AS interview_id, i.interview_time AS start_time, 
               a.first_name, a.last_name, 
               COALESCE(NULLIF(a.position_applied, ''), a.position, 'Staff') AS position 
        FROM interviews i 
        INNER JOIN applicants a ON i.applicant_id = a.id 
        WHERE i.interview_date = CURDATE() AND i.status != 'Cancelled' {$appScopeA}
        ORDER BY i.interview_time ASC
    ");
    $intStmt->execute($appParamsA);
    $todayInterviews = $intStmt ? $intStmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    $todayInterviews = [];
}

// 3. Recruitment Data Mining Insights (Current Month)
$currentMonthStr = date('Y-m');
$insights = [
    'top_position' => 'N/A',
    'top_category' => 'N/A',
    'top_sex' => 'N/A',
    'total_month_applicants' => 0
];

try {
    [$appScope, $appParams] = Rbac::branch_scope();
    // Top Position Applied
    $stPos = $pdo->prepare("SELECT COALESCE(NULLIF(position_applied, ''), position) AS pos, COUNT(*) AS cnt FROM applicants WHERE DATE_FORMAT(created_at, '%Y-%m') = ? {$appScope} GROUP BY pos ORDER BY cnt DESC LIMIT 1");
    $stPos->execute(array_merge([$currentMonthStr], $appParams));
    $rowPos = $stPos->fetch(PDO::FETCH_ASSOC);
    if ($rowPos && !empty($rowPos['pos'])) {
        $insights['top_position'] = $rowPos['pos'];
    }

    // Top Category
    $stCat = $pdo->prepare("SELECT employment_category, COUNT(*) AS cnt FROM applicants WHERE DATE_FORMAT(created_at, '%Y-%m') = ? AND employment_category IS NOT NULL AND employment_category != '' {$appScope} GROUP BY employment_category ORDER BY cnt DESC LIMIT 1");
    $stCat->execute(array_merge([$currentMonthStr], $appParams));
    $rowCat = $stCat->fetch(PDO::FETCH_ASSOC);
    if ($rowCat && !empty($rowCat['employment_category'])) {
        $insights['top_category'] = $rowCat['employment_category'];
    }

    // Prevalent Gender
    $stSex = $pdo->prepare("SELECT sex, COUNT(*) AS cnt FROM applicants WHERE DATE_FORMAT(created_at, '%Y-%m') = ? AND sex IS NOT NULL AND sex != '' {$appScope} GROUP BY sex ORDER BY cnt DESC LIMIT 1");
    $stSex->execute(array_merge([$currentMonthStr], $appParams));
    $rowSex = $stSex->fetch(PDO::FETCH_ASSOC);
    if ($rowSex && !empty($rowSex['sex'])) {
        $insights['top_sex'] = $rowSex['sex'];
    }

    // Total applicants this month
    $stTot = $pdo->prepare("SELECT COUNT(*) FROM applicants WHERE DATE_FORMAT(created_at, '%Y-%m') = ? {$appScope}");
    $stTot->execute(array_merge([$currentMonthStr], $appParams));
    $insights['total_month_applicants'] = (int)$stTot->fetchColumn();
} catch (Throwable $e) {
    // Non-fatal fallback
}

// 4. Attendance Overview (Monthly aggregate chart)
$attendanceLabels = [];
$attendanceValues = [];
try {
    [$attScope, $attParams] = Rbac::branch_scope();
    $attStmt = $pdo->prepare("
        SELECT MONTH(work_date) AS month, COUNT(DISTINCT CONCAT(employee_id, '_', work_date)) AS total 
        FROM attendance_logs 
        WHERE YEAR(work_date) = YEAR(CURDATE()) AND clock_in IS NOT NULL {$attScope}
        GROUP BY MONTH(work_date) 
        ORDER BY month ASC
    ");
    $attStmt->execute($attParams);
    $attRows = $attStmt ? $attStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    foreach ($attRows as $row) {
        $attendanceLabels[] = date('M', mktime(0, 0, 0, (int)$row['month'], 1));
        $attendanceValues[] = (int)$row['total'];
    }
} catch (Throwable $e) {
    $attendanceLabels = [];
    $attendanceValues = [];
}

// 5. Recent Activity Stream (Audit Logs)
$recentActivities = [];
try {
    $actStmt = $pdo->query("
        SELECT a.id, a.action, a.details, a.created_at, COALESCE(u.username, 'System') AS user_name 
        FROM audit_logs a 
        LEFT JOIN users u ON a.user_id = u.id 
        ORDER BY a.id DESC 
        LIMIT 5
    ");
    $recentActivities = $actStmt ? $actStmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    $recentActivities = [];
}

$pageTitle = 'HRMS Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> &middot; Fika Café</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans antialiased min-h-screen flex">

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto">
        <!-- Top Navigation Header -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-black text-lg shadow-xs">
                        <i class="fa-solid fa-chart-line text-base"></i>
                    </div>
                    <div>
                        <h1 class="font-bold text-lg text-slate-900 leading-tight">HRMS Operations Dashboard</h1>
                        <p class="text-xs text-slate-500">Live operational overview across manpower, attendance, and recruitment</p>
                    </div>
                </div>

                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-600">
                    <i class="fa-regular fa-calendar-days text-indigo-600"></i>
                    <span><?= date('l, F j, Y') ?></span>
                </div>
            </div>
        </header>

        <!-- Main Workspace -->
        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
            
            <!-- 4 Top Stat Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Stat Card 1: Total Employees -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-6 flex items-center transition hover:shadow-md">
                    <div class="h-12 w-12 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center text-xl mr-4 flex-shrink-0">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Employees</p>
                        <p class="text-2xl font-black text-slate-900 mt-0.5"><?= number_format($totalEmployees) ?></p>
                        <a href="fika_hrms_employees.php" class="text-[11px] font-bold text-blue-600 hover:underline mt-0.5 inline-block">View staff list &rarr;</a>
                    </div>
                </div>
                
                <!-- Stat Card 2: Clocked In Today -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-6 flex items-center transition hover:shadow-md">
                    <div class="h-12 w-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-xl mr-4 flex-shrink-0">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Clocked In Today</p>
                        <p class="text-2xl font-black text-slate-900 mt-0.5"><?= number_format($presentToday) ?></p>
                        <span class="text-[11px] font-medium text-emerald-600 mt-0.5 inline-block">Staff clocked in</span>
                    </div>
                </div>
                
                <!-- Stat Card 3: Pending Leaves -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-6 flex items-center transition hover:shadow-md">
                    <div class="h-12 w-12 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center text-xl mr-4 flex-shrink-0">
                        <i class="fa-solid fa-plane-departure"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Pending Leaves</p>
                        <p class="text-2xl font-black text-slate-900 mt-0.5"><?= number_format($pendingLeaves) ?></p>
                        <a href="fika_hrms_leave.php" class="text-[11px] font-bold text-amber-600 hover:underline mt-0.5 inline-block">Review requests &rarr;</a>
                    </div>
                </div>
                
                <!-- Stat Card 4: Today's Sales -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-6 flex items-center transition hover:shadow-md">
                    <div class="h-12 w-12 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center text-xl mr-4 flex-shrink-0">
                        <i class="fa-solid fa-cash-register"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Today's Sales</p>
                        <p class="text-2xl font-black text-slate-900 mt-0.5">₱<?= number_format($todaySales, 2) ?></p>
                        <span class="text-[11px] font-medium text-indigo-600 mt-0.5 inline-block">Settled orders</span>
                    </div>
                </div>
            </div>

            <!-- Quick Links Hub (Applicants, Employees, Payroll, Shift Board) -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-bolt text-indigo-600"></i>
                    <span>HRMS Quick Links</span>
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <a href="fika_hrms_applicants.php" class="flex items-center gap-3.5 p-4 rounded-xl border border-slate-200 hover:border-indigo-400 hover:bg-indigo-50/40 transition-all group">
                        <div class="w-10 h-10 rounded-xl bg-purple-100 text-purple-700 flex items-center justify-center text-base group-hover:scale-105 transition-transform flex-shrink-0">
                            <i class="fa-solid fa-user-plus"></i>
                        </div>
                        <div>
                            <p class="font-bold text-slate-900 text-sm">Applicants</p>
                            <p class="text-xs text-slate-500">Candidate pipeline & reviews</p>
                        </div>
                    </a>

                    <a href="fika_hrms_employees.php" class="flex items-center gap-3.5 p-4 rounded-xl border border-slate-200 hover:border-indigo-400 hover:bg-indigo-50/40 transition-all group">
                        <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center text-base group-hover:scale-105 transition-transform flex-shrink-0">
                            <i class="fa-solid fa-id-card"></i>
                        </div>
                        <div>
                            <p class="font-bold text-slate-900 text-sm">Employees</p>
                            <p class="text-xs text-slate-500">Staff directory & records</p>
                        </div>
                    </a>

                    <a href="fika_hrms_payroll.php" class="flex items-center gap-3.5 p-4 rounded-xl border border-slate-200 hover:border-indigo-400 hover:bg-indigo-50/40 transition-all group">
                        <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-base group-hover:scale-105 transition-transform flex-shrink-0">
                            <i class="fa-solid fa-file-invoice-dollar"></i>
                        </div>
                        <div>
                            <p class="font-bold text-slate-900 text-sm">Payroll</p>
                            <p class="text-xs text-slate-500">Compensation & payslips</p>
                        </div>
                    </a>

                    <a href="fika_hrms_shift_board.php" class="flex items-center gap-3.5 p-4 rounded-xl border border-slate-200 hover:border-indigo-400 hover:bg-indigo-50/40 transition-all group">
                        <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center text-base group-hover:scale-105 transition-transform flex-shrink-0">
                            <i class="fa-solid fa-calendar-week"></i>
                        </div>
                        <div>
                            <p class="font-bold text-slate-900 text-sm">Shift Board</p>
                            <p class="text-xs text-slate-500">Rosters & assignments</p>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Daily Agenda: Today's Interviews Widget -->
            <div>
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-calendar-day text-indigo-600"></i>
                        <span>Today's Interview Schedule</span>
                    </h3>
                    <a href="fika_hrms_applications.php?tab=calendar" class="text-xs font-bold text-indigo-600 hover:underline flex items-center gap-1">
                        <span>Open ATS Calendar</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                </div>
                
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
                    <?php if (empty($todayInterviews)): ?>
                        <div class="p-8 text-center text-slate-500 flex flex-col items-center">
                            <div class="w-14 h-14 bg-slate-50 rounded-2xl flex items-center justify-center mb-3 border border-slate-100 text-slate-400">
                                <i class="fa-solid fa-mug-hot text-2xl"></i>
                            </div>
                            <p class="font-bold text-slate-700 text-sm">No interviews scheduled for today</p>
                            <p class="text-xs text-slate-400 mt-1">Check back later or schedule upcoming candidate rounds in ATS.</p>
                        </div>
                    <?php else: ?>
                        <div class="divide-y divide-slate-100">
                            <?php foreach ($todayInterviews as $interview): ?>
                                <a href="fika_hrms_interview_workspace.php?interview_id=<?= (int)$interview['interview_id'] ?>" class="block p-4 hover:bg-indigo-50/40 transition group">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-4">
                                            <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-700 flex flex-col items-center justify-center font-bold border border-indigo-200">
                                                <span class="text-xs font-black"><?= date('g:i', strtotime($interview['start_time'])) ?></span>
                                                <span class="text-[9px] uppercase"><?= date('A', strtotime($interview['start_time'])) ?></span>
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-slate-900 group-hover:text-indigo-700 transition"><?= e($interview['first_name'] . ' ' . $interview['last_name']) ?></h4>
                                                <p class="text-xs text-slate-500 mt-0.5">
                                                    <span class="inline-block px-2 py-0.5 bg-slate-100 rounded text-slate-600 font-semibold uppercase tracking-wider text-[10px]"><?= e($interview['position']) ?></span>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2 text-xs font-bold text-indigo-600 opacity-80 group-hover:opacity-100 transition">
                                            <span>Enter Workspace</span>
                                            <i class="fa-solid fa-chevron-right text-[10px] transform group-hover:translate-x-1 transition"></i>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recruitment Data Mining Insights -->
            <div>
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-chart-pie text-indigo-600"></i>
                        <span>Recruitment Intake Insights (<?= date('F Y') ?>)</span>
                    </h3>
                    <span class="text-xs font-bold text-slate-500">Pipeline Analytics</span>
                </div>
                <div class="bg-gradient-to-r from-indigo-700 to-purple-800 rounded-2xl shadow-md text-white p-6 relative overflow-hidden">
                    <div class="absolute right-0 top-0 opacity-10">
                        <i class="fa-solid fa-chart-pie text-9xl -mr-6 -mt-6"></i>
                    </div>
                    <div class="relative z-10 grid grid-cols-2 md:grid-cols-4 gap-6">
                        <div>
                            <p class="text-indigo-200 text-xs font-bold uppercase tracking-wider mb-1">Top Position</p>
                            <p class="text-xl sm:text-2xl font-black truncate"><?= e($insights['top_position']) ?></p>
                            <p class="text-[11px] text-indigo-200 mt-0.5">Highest applicant volume</p>
                        </div>
                        <div>
                            <p class="text-indigo-200 text-xs font-bold uppercase tracking-wider mb-1">Top Category</p>
                            <p class="text-xl sm:text-2xl font-black truncate"><?= e($insights['top_category']) ?></p>
                            <p class="text-[11px] text-indigo-200 mt-0.5">Workforce preference</p>
                        </div>
                        <div>
                            <p class="text-indigo-200 text-xs font-bold uppercase tracking-wider mb-1">Prevalent Gender</p>
                            <p class="text-xl sm:text-2xl font-black truncate"><?= e($insights['top_sex']) ?></p>
                            <p class="text-[11px] text-indigo-200 mt-0.5">Demographic distribution</p>
                        </div>
                        <div>
                            <p class="text-indigo-200 text-xs font-bold uppercase tracking-wider mb-1">Monthly Intake</p>
                            <p class="text-xl sm:text-2xl font-black truncate"><?= number_format($insights['total_month_applicants']) ?></p>
                            <p class="text-[11px] text-indigo-200 mt-0.5">Total submissions</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Operational Trends & Audit Stream Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Attendance Overview Chart -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-6 lg:col-span-2 flex flex-col justify-between">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Attendance Overview</h3>
                            <p class="text-xs text-slate-500">Monthly staff attendance volume for <?= date('Y') ?></p>
                        </div>
                        <a href="fika_hrms_shift_board.php" class="text-xs text-indigo-600 font-bold hover:underline">Open Roster &rarr;</a>
                    </div>

                    <div class="h-64 flex items-end space-x-3 w-full justify-between pb-6 border-b border-slate-100 relative">
                        <?php 
                        $maxVal = max(1, count($attendanceValues) > 0 ? max($attendanceValues) : 10); 
                        if (!empty($attendanceLabels)):
                            foreach ($attendanceLabels as $i => $label): 
                                $height = ($attendanceValues[$i] / $maxVal) * 100;
                        ?>
                            <div class="flex flex-col items-center flex-1 group">
                                <div class="text-[10px] font-bold text-indigo-600 opacity-0 group-hover:opacity-100 transition-opacity mb-1 font-mono">
                                    <?= $attendanceValues[$i] ?>
                                </div>
                                <div class="w-full max-w-[36px] bg-indigo-100 group-hover:bg-indigo-600 transition-colors rounded-t-lg" style="height: <?= max(8, $height) ?>%;"></div>
                                <span class="text-xs font-bold text-slate-500 mt-2"><?= e($label) ?></span>
                            </div>
                        <?php endforeach; 
                        else: ?>
                            <div class="absolute inset-0 flex flex-col items-center justify-center text-slate-400">
                                <i class="fa-solid fa-chart-column text-3xl mb-2 opacity-40"></i>
                                <span class="text-xs font-medium">No attendance records logged for current year yet</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="pt-3 flex items-center justify-between text-xs text-slate-400">
                        <span>Source: Clock-in logs</span>
                        <span>Year: <?= date('Y') ?></span>
                    </div>
                </div>
                
                <!-- Recent Activity Logs -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-6 flex flex-col">
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="text-base font-bold text-slate-900">Recent Activity</h3>
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    </div>

                    <div class="space-y-4 flex-1 overflow-y-auto max-h-[300px]">
                        <?php if (!empty($recentActivities)): ?>
                            <?php foreach ($recentActivities as $activity): ?>
                                <div class="flex items-start gap-3">
                                    <div class="h-8 w-8 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500 mt-0.5 shrink-0 border border-slate-200">
                                        <i class="fa-solid fa-bolt text-xs text-indigo-600"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-bold text-slate-800">
                                            <?= e($activity['user_name']) ?> 
                                            <span class="font-normal text-slate-600"><?= e($activity['action']) ?></span>
                                        </p>
                                        <?php if (!empty($activity['details'])): ?>
                                            <p class="text-[11px] text-slate-500 truncate mt-0.5" title="<?= e($activity['details']) ?>">
                                                <?= e($activity['details']) ?>
                                            </p>
                                        <?php endif; ?>
                                        <p class="text-[10px] text-slate-400 mt-0.5 font-mono"><?= date('M d, h:i A', strtotime($activity['created_at'])) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="py-12 text-center text-slate-400 text-xs">
                                <i class="fa-solid fa-clock-rotate-left text-2xl mb-2 block opacity-40"></i>
                                No recent activity logged.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </main>
    </div>
</body>
</html>
