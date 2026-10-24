<?php
require __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/core/Output.php';

Auth::requireLogin();
Rbac::require_permission('hr.employee.view');

global $pdo;

// 1. KPI Queries
$totalEmployees = 0;
$clockedInToday = 0;
$pendingLeaves = 0;
$todaySales = 0.0;

try {
    $empStmt = $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'ACTIVE' AND deleted_at IS NULL");
    $totalEmployees = (int)($empStmt ? $empStmt->fetchColumn() : 0);

    $clockStmt = $pdo->query("SELECT COUNT(DISTINCT employee_id) FROM attendance_logs WHERE work_date = CURDATE() AND clock_in IS NOT NULL AND clock_out IS NULL");
    $clockedInToday = (int)($clockStmt ? $clockStmt->fetchColumn() : 0);

    $leaveStmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'PENDING'");
    $pendingLeaves = (int)($leaveStmt ? $leaveStmt->fetchColumn() : 0);

    $salesStmt = $pdo->query("SELECT COALESCE(SUM(final_amount), 0) FROM orders WHERE DATE(created_at) = CURDATE() AND payment_status = 'PAID'");
    $todaySales = (float)($salesStmt ? $salesStmt->fetchColumn() : 0.0);
} catch (Throwable $e) {
    // Graceful fallback to 0 values
}

// 2. Recent Activity (Last 5 Audit Logs)
$recentLogs = [];
try {
    $auditStmt = $pdo->query("
        SELECT a.id, a.action, a.details, a.created_at, u.username 
        FROM audit_logs a 
        LEFT JOIN users u ON a.user_id = u.id 
        ORDER BY a.id DESC 
        LIMIT 5
    ");
    if ($auditStmt) {
        $recentLogs = $auditStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $recentLogs = [];
}

$currentUser = Auth::user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS Operations Dashboard &middot; Fika Café</title>
    <link rel="icon" type="image/svg+xml" href="../public/assets/logo.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-100 text-slate-800 font-sans antialiased min-h-screen flex">

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto">
        <!-- Main Container -->
        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Welcome Banner & Date Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">HRMS Overview</h1>
                <p class="text-sm text-slate-500 mt-1">Real-time workforce attendance, leaves, and daily branch operational metrics.</p>
            </div>
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-semibold text-slate-600 shadow-sm">
                <i class="fa-regular fa-calendar text-amber-600"></i>
                <span><?= date('l, F j, Y') ?></span>
            </div>
        </div>

        <!-- 4 KPI Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            <!-- 1. Total Employees -->
            <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Active Staff</span>
                    <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-user-group"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <span class="text-3xl font-black text-slate-900 tracking-tight"><?= number_format($totalEmployees) ?></span>
                    <span class="text-xs font-semibold text-slate-500 ml-1">staff active</span>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <a href="fika_hrms_employees.php" class="text-blue-600 font-semibold hover:underline flex items-center gap-1">
                        <span>View directory</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                </div>
            </div>

            <!-- 2. Clocked In Today -->
            <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Clocked In Today</span>
                    <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-business-time"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <span class="text-3xl font-black text-slate-900 tracking-tight"><?= number_format($clockedInToday) ?></span>
                    <span class="text-xs font-semibold text-slate-500 ml-1">on shift</span>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <a href="fika_hrms_attendance.php" class="text-emerald-600 font-semibold hover:underline flex items-center gap-1">
                        <span>Attendance logs</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                </div>
            </div>

            <!-- 3. Pending Leaves -->
            <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Pending Leaves</span>
                    <div class="w-10 h-10 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-lg">
                        <i class="fa-regular fa-calendar-xmark"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <span class="text-3xl font-black text-slate-900 tracking-tight"><?= number_format($pendingLeaves) ?></span>
                    <span class="text-xs font-semibold text-slate-500 ml-1">requests</span>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <a href="fika_hrms_leave.php" class="text-amber-600 font-semibold hover:underline flex items-center gap-1">
                        <span>Review requests</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                </div>
            </div>

            <!-- 4. Today's Sales -->
            <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Today's Sales</span>
                    <div class="w-10 h-10 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-cash-register"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <span class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight"><?= Money::format(Money::toCentavos($todaySales)) ?></span>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="text-slate-400 font-medium">Reconciled POS orders</span>
                </div>
            </div>
        </div>

        <!-- Quick Links Hub -->
        <div class="mb-8 bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-600 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-bolt text-amber-500"></i>
                <span>Quick Operations</span>
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <a href="fika_hrms_employees.php" class="flex items-center gap-3.5 p-3.5 rounded-lg border border-slate-200 hover:border-amber-400 hover:bg-amber-50/40 transition-all group">
                    <div class="w-10 h-10 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center text-base group-hover:scale-105 transition-transform">
                        <i class="fa-solid fa-id-card"></i>
                    </div>
                    <div>
                        <p class="font-bold text-slate-900 text-sm">Staff Directory</p>
                        <p class="text-xs text-slate-500">Profiles, positions, rates & docs</p>
                    </div>
                </a>

                <a href="fika_hrms_payroll.php" class="flex items-center gap-3.5 p-3.5 rounded-lg border border-slate-200 hover:border-amber-400 hover:bg-amber-50/40 transition-all group">
                    <div class="w-10 h-10 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center text-base group-hover:scale-105 transition-transform">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                    <div>
                        <p class="font-bold text-slate-900 text-sm">Payroll Cycles</p>
                        <p class="text-xs text-slate-500">Runs, payslips & tax contributions</p>
                    </div>
                </a>

                <a href="fika_hrms_shift_board.php" class="flex items-center gap-3.5 p-3.5 rounded-lg border border-slate-200 hover:border-amber-400 hover:bg-amber-50/40 transition-all group">
                    <div class="w-10 h-10 rounded-lg bg-purple-100 text-purple-700 flex items-center justify-center text-base group-hover:scale-105 transition-transform">
                        <i class="fa-solid fa-calendar-days"></i>
                    </div>
                    <div>
                        <p class="font-bold text-slate-900 text-sm">Shift Board</p>
                        <p class="text-xs text-slate-500">Weekly schedules & claim swaps</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- Recent Activity Table (Last 5 Audit Logs) -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-slate-900 text-base">Recent System Activity</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Last 5 security & backoffice events</p>
                </div>
                <span class="text-xs font-semibold px-2.5 py-1 bg-slate-100 text-slate-700 rounded-full">
                    Audited Logs
                </span>
            </div>

            <?php if (!empty($recentLogs)): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-700">
                        <thead class="bg-slate-50 text-xs uppercase font-bold text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-3">Timestamp</th>
                                <th class="px-6 py-3">Operator</th>
                                <th class="px-6 py-3">Action</th>
                                <th class="px-6 py-3">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($recentLogs as $log): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="px-6 py-3.5 text-xs text-slate-500 whitespace-nowrap">
                                        <?= date('M d, Y &bull; h:i A', strtotime($log['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-3.5 font-semibold text-slate-900 text-xs">
                                        <?= e($log['username'] ?? 'System') ?>
                                    </td>
                                    <td class="px-6 py-3.5">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                            <?= e($log['action']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-3.5 text-xs text-slate-600 max-w-md truncate">
                                        <?= e($log['details']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <!-- Empty state for activity logs -->
                <div class="p-8 text-center">
                    <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3 text-lg">
                        <i class="fa-regular fa-folder-open"></i>
                    </div>
                    <p class="font-semibold text-slate-700 text-sm">No recent activity logs recorded</p>
                    <p class="text-xs text-slate-400 mt-1">Actions taken across HRMS will appear here in real time.</p>
                </div>
            <?php endif; ?>
        </div>

        </main>

        <!-- Footer -->
        <footer class="mt-auto bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-400">
            &copy; <?= date('Y') ?> Fika Café &middot; Human Resources Management Suite
        </footer>
    </div>

</body>
</html>
