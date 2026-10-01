<?php
require_once __DIR__ . '/init.php';
require_login();
require_role(['Admin', 'Super Admin', 'HR', 'Head Barista']); // Ensure only admins and Head Baristas can see this

// KPI Branch Filter
$branchFilterE = get_branch_filter('e');

// KPIs
$stmt = $pdo->prepare('SELECT COUNT(*) FROM attendance a JOIN employees e ON a.employee_id = e.id WHERE DATE(a.time_in) = CURDATE() AND a.time_out IS NULL ' . $branchFilterE);
$stmt->execute();
$presentToday = $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM attendance a JOIN employees e ON a.employee_id = e.id WHERE DATE(a.time_in) = CURDATE() AND a.time_out IS NOT NULL AND a.status != "Auto-Closed" ' . $branchFilterE);
$stmt->execute();
$clockedOutToday = $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM attendance a JOIN employees e ON a.employee_id = e.id WHERE a.status = "Auto-Closed" AND DATE(a.time_in) >= CURDATE() - INTERVAL 7 DAY ' . $branchFilterE);
$stmt->execute();
$autoClosedRecent = $stmt->fetchColumn();

// Total Hours Today
$stmt = $pdo->prepare('SELECT SUM(TIMESTAMPDIFF(MINUTE, a.time_in, a.time_out))/60 AS total_hours FROM attendance a JOIN employees e ON a.employee_id = e.id WHERE DATE(a.time_in) = CURDATE() AND a.time_out IS NOT NULL ' . $branchFilterE);
$stmt->execute();
$totalHoursToday = round((float)$stmt->fetchColumn(), 2);

// On Leave Today
$stmt = $pdo->prepare('SELECT COUNT(*) FROM leaves l JOIN employees e ON l.employee_id = e.id WHERE l.status = "Approved" AND CURDATE() BETWEEN l.start_date AND l.end_date ' . $branchFilterE);
$stmt->execute();
$onLeaveToday = $stmt->fetchColumn();

// On Leave List
$stmt = $pdo->prepare('SELECT l.*, CONCAT(e.first_name, " ",   e.last_name) AS full_name FROM leaves l JOIN employees e ON l.employee_id = e.id WHERE l.status = "Approved" AND CURDATE() BETWEEN l.start_date AND l.end_date ' . $branchFilterE);
$stmt->execute();
$onLeaveList = $stmt->fetchAll();

// Anomalies
$stmt = $pdo->prepare('SELECT a.*, CONCAT(e.first_name, \' \', e.last_name) AS full_name FROM attendance a JOIN employees e ON a.employee_id = e.id WHERE a.status = "Auto-Closed" ' . $branchFilterE . ' ORDER BY a.time_in DESC LIMIT 5');
$stmt->execute();
$anomalies_autoClosed = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT a.*, CONCAT(e.first_name, \' \', e.last_name) AS full_name FROM attendance a JOIN employees e ON a.employee_id = e.id WHERE a.time_out IS NULL AND TIMESTAMPDIFF(HOUR, a.time_in, NOW()) >= 8 ' . $branchFilterE . ' ORDER BY a.time_in DESC LIMIT 5');
$stmt->execute();
$anomalies_missingOut = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT a.*, CONCAT(e.first_name, \' \', e.last_name) AS full_name, TIMESTAMPDIFF(MINUTE, a.time_in, a.time_out)/60 AS hours FROM attendance a JOIN employees e ON a.employee_id = e.id WHERE TIMESTAMPDIFF(MINUTE, a.time_in, a.time_out)/60 > 8.0 ' . $branchFilterE . ' ORDER BY a.time_in DESC LIMIT 5');
$stmt->execute();
$anomalies_overtime = $stmt->fetchAll();

// Filter logic for Main Log
$whereClauses = [];
$params = [];

$search = $_GET['search'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

if ($search !== '') {
    $whereClauses[] = '(CONCAT(e.first_name, \' \', e.last_name) LIKE ? OR e.employee_id LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($dateFrom !== '') {
    $whereClauses[] = 'DATE(a.time_in) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $whereClauses[] = 'DATE(a.time_in) <= ?';
    $params[] = $dateTo;
}

$whereSql = '';
if (!empty($whereClauses)) {
    $whereSql = 'WHERE ' . implode(' AND ', $whereClauses) . $branchFilterE;
} else {
    $whereSql = 'WHERE 1=1 ' . $branchFilterE;
}

$stmt = $pdo->prepare("SELECT a.*, CONCAT(e.first_name, ' ', e.last_name) AS full_name, e.employee_id AS emp_code FROM attendance a JOIN employees e ON e.id = a.employee_id $whereSql ORDER BY a.time_in DESC LIMIT 1000");
$stmt->execute($params);
$attendanceLogs = $stmt->fetchAll();

$pageTitle = 'Attendance Log';
require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Attendance Monitoring</h1>
    <p class="text-slate-500">Real-time Kiosk punches, hours tracked, and system anomalies.</p>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center">
        <div class="h-12 w-12 rounded-lg bg-green-100 text-green-600 flex items-center justify-center text-xl mr-4">
            <i class="fa-solid fa-user-check"></i>
        </div>
        <div>
            <p class="text-sm font-medium text-slate-500">Present Right Now</p>
            <p class="text-2xl font-bold text-slate-800" id="kpiPresent"><?= $presentToday ?></p>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center">
        <div class="h-12 w-12 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center text-xl mr-4">
            <i class="fa-solid fa-door-open"></i>
        </div>
        <div>
            <p class="text-sm font-medium text-slate-500">Clocked Out Today</p>
            <p class="text-2xl font-bold text-slate-800" id="kpiClockedOut"><?= $clockedOutToday ?></p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center">
        <div class="h-12 w-12 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center text-xl mr-4">
            <i class="fa-solid fa-stopwatch"></i>
        </div>
        <div>
            <p class="text-sm font-medium text-slate-500">Total Hours Today</p>
            <p class="text-2xl font-bold text-slate-800"><span id="kpiTotalHours"><?= $totalHoursToday ?></span> <span class="text-sm font-normal text-slate-500">hrs</span></p>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center">
        <div class="h-12 w-12 rounded-lg bg-orange-100 text-orange-600 flex items-center justify-center text-xl mr-4">
            <i class="fa-solid fa-plane-departure"></i>
        </div>
        <div>
            <p class="text-sm font-medium text-slate-500">On Leave Today</p>
            <p class="text-2xl font-bold text-slate-800"><?= $onLeaveToday ?></p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
    
    <!-- Anomaly Board & Leaves -->
    <div class="xl:col-span-1 space-y-6">

        <!-- On Leave Today -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="bg-slate-50 px-4 py-3 border-b border-slate-200 flex items-center justify-between">
                <h3 class="font-bold text-slate-700 flex items-center"><i class="fa-solid fa-umbrella-beach mr-2"></i> On Leave Today</h3>
                <span class="text-xs font-semibold bg-slate-200 text-slate-700 px-2 py-1 rounded-full"><?= count($onLeaveList) ?></span>
            </div>
            <div class="p-0">
                <?php if (empty($onLeaveList)): ?>
                    <p class="text-slate-500 text-sm p-4 text-center">No employees are on leave today.</p>
                <?php else: ?>
                    <ul class="divide-y divide-slate-100">
                        <?php foreach($onLeaveList as $leave): ?>
                            <li class="p-4 hover:bg-slate-50">
                                <p class="text-sm font-bold text-slate-800"><?= h($leave['full_name']) ?></p>
                                <p class="text-xs text-slate-500"><?= h($leave['leave_type']) ?> (Until <?= date('M j', strtotime($leave['end_date'])) ?>)</p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Missing Out Anomaly -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="bg-orange-50 px-4 py-3 border-b border-orange-100 flex items-center justify-between">
                <h3 class="font-bold text-orange-800 flex items-center"><i class="fa-solid fa-triangle-exclamation mr-2"></i> Missing Time Out</h3>
                <span class="text-xs font-semibold bg-orange-200 text-orange-800 px-2 py-1 rounded-full"><?= count($anomalies_missingOut) ?></span>
            </div>
            <div class="p-0">
                <?php if (empty($anomalies_missingOut)): ?>
                    <p class="text-slate-500 text-sm p-4 text-center">No missing punches detected.</p>
                <?php else: ?>
                    <ul class="divide-y divide-slate-100">
                        <?php foreach($anomalies_missingOut as $anomaly): ?>
                            <li class="p-4 hover:bg-slate-50">
                                <p class="text-sm font-bold text-slate-800"><?= h($anomaly['full_name']) ?></p>
                                <p class="text-xs text-slate-500">In: <?= date('M j, Y h:i A', strtotime($anomaly['time_in'])) ?></p>
                                <p class="text-xs text-orange-600 mt-1"><i class="fa-solid fa-circle-info"></i> Waiting for next punch to auto-close.</p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Auto Closed Penalties -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="bg-red-50 px-4 py-3 border-b border-red-100 flex items-center justify-between">
                <h3 class="font-bold text-red-800 flex items-center"><i class="fa-solid fa-ban mr-2"></i> System Penalties</h3>
            </div>
            <div class="p-0">
                <?php if (empty($anomalies_autoClosed)): ?>
                    <p class="text-slate-500 text-sm p-4 text-center">No auto-closed shifts recently.</p>
                <?php else: ?>
                    <ul class="divide-y divide-slate-100">
                        <?php foreach($anomalies_autoClosed as $anomaly): ?>
                            <li class="p-4 hover:bg-slate-50">
                                <p class="text-sm font-bold text-slate-800"><?= h($anomaly['full_name']) ?></p>
                                <p class="text-xs text-slate-500">Shift: <?= date('M j, Y', strtotime($anomaly['time_in'])) ?></p>
                                <p class="text-xs text-red-600 mt-1"><i class="fa-solid fa-scissors"></i> Capped at exactly 8.00 hours.</p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Excessive Overtime -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="bg-blue-50 px-4 py-3 border-b border-blue-100 flex items-center justify-between">
                <h3 class="font-bold text-blue-800 flex items-center"><i class="fa-solid fa-clock-rotate-left mr-2"></i> Overtime Log (> 8 Hrs)</h3>
            </div>
            <div class="p-0">
                <?php if (empty($anomalies_overtime)): ?>
                    <p class="text-slate-500 text-sm p-4 text-center">No overtime recorded recently.</p>
                <?php else: ?>
                    <ul class="divide-y divide-slate-100">
                        <?php foreach($anomalies_overtime as $anomaly): ?>
                            <li class="p-4 hover:bg-slate-50">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="text-sm font-bold text-slate-800"><?= h($anomaly['full_name']) ?></p>
                                        <p class="text-xs text-slate-500"><?= date('M j', strtotime($anomaly['time_in'])) ?></p>
                                    </div>
                                    <span class="text-sm font-bold text-blue-600"><?= number_format($anomaly['hours'], 2) ?> Hrs</span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main Data Table -->
    <div class="xl:col-span-2">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-800">Raw Attendance Log</h2>
                    <div class="text-xs text-slate-500"><i class="fa-solid fa-lock mr-1"></i> Immutable Audit Trail</div>
                </div>
                
                <form autocomplete="off" id="attendanceFilterForm" method="GET" action="attendance" class="flex flex-wrap items-center gap-2">
                    <input type="text" id="attendanceSearch" placeholder="Search Employee..." class="text-sm border-slate-300 rounded-lg px-3 py-2 bg-white border focus:ring-primary focus:border-primary">
                    <input type="date" name="date_from" value="<?= h($dateFrom) ?>" max="<?= date('Y-m-d') ?>" onchange="this.form.submit()" class="text-sm border-slate-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary" title="From Date">
                    <span class="text-slate-400 text-sm">to</span>
                    <input type="date" name="date_to" value="<?= h($dateTo) ?>" max="<?= date('Y-m-d') ?>" onchange="this.form.submit()" class="text-sm border-slate-300 rounded-lg px-3 py-2 focus:ring-primary focus:border-primary" title="To Date">
                    <?php if($dateFrom || $dateTo): ?>
                        <a href="attendance" class="text-sm text-slate-500 hover:text-red-500 px-2 transition-colors"><i class="fa-solid fa-times"></i> Clear</a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-white border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500">
                            <th class="p-4 font-semibold">Employee</th>
                            <th class="p-4 font-semibold">Date</th>
                            <th class="p-4 font-semibold">Time In</th>
                            <th class="p-4 font-semibold">Time Out</th>
                            <th class="p-4 font-semibold">Hours</th>
                            <th class="p-4 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody id="attendanceTableBody" class="divide-y divide-slate-100 text-sm">
                        <?php if (empty($attendanceLogs)): ?>
                            <tr><td colspan="6" class="p-6 text-center text-slate-500">No attendance records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($attendanceLogs as $log): 
                                $hours = '-';
                                if ($log['time_out']) {
                                    $diff = strtotime($log['time_out']) - strtotime($log['time_in']);
                                    $hours = number_format($diff / 3600, 2);
                                }
                                
                                $statusClass = 'bg-slate-100 text-slate-600';
                                if ($log['status'] === 'Present') $statusClass = 'bg-green-100 text-green-700';
                                if ($log['status'] === 'Clocked Out') $statusClass = 'bg-blue-100 text-blue-700';
                                if ($log['status'] === 'Auto-Closed') $statusClass = 'bg-red-100 text-red-700';
                            ?>
                                <tr class="hover:bg-slate-50 transition-colors" data-search="<?= h(strtolower($log['full_name'] . ' ' . $log['emp_code'])) ?>" data-status="<?= h($log['status']) ?>" data-hours="<?= $diff ? round($diff / 3600, 2) : 0 ?>">
                                    <td class="p-4">
                                        <div class="font-medium text-slate-800"><?= h($log['full_name']) ?></div>
                                        <div class="text-xs text-slate-500"><?= h($log['emp_code']) ?></div>
                                    </td>
                                    <td class="p-4 text-slate-600 whitespace-nowrap"><?= date('M j, Y', strtotime($log['time_in'])) ?></td>
                                    <td class="p-4 font-medium text-slate-700 whitespace-nowrap"><?= date('h:i A', strtotime($log['time_in'])) ?></td>
                                    <td class="p-4 font-medium text-slate-700 whitespace-nowrap">
                                        <?= $log['time_out'] ? date('h:i A', strtotime($log['time_out'])) : '<span class="text-slate-400 italic">Active</span>' ?>
                                    </td>
                                    <td class="p-4 font-bold text-slate-700">
                                        <?= $hours ?>
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= $statusClass ?>">
                                            <?= h($log['status']) ?>
                                        </span>
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

<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('attendanceSearch');
    const tbody = document.getElementById('attendanceTableBody');
    const rows = tbody.querySelectorAll('tr[data-search]');
    const kpiPresent = document.getElementById('kpiPresent');
    const kpiClockedOut = document.getElementById('kpiClockedOut');
    const kpiTotalHours = document.getElementById('kpiTotalHours');

    function filterTable() {
        const query = searchInput.value.toLowerCase();
        let presentCount = 0;
        let clockedOutCount = 0;
        let totalHours = 0;

        rows.forEach(row => {
            const rowSearch = row.getAttribute('data-search');
            const status = row.getAttribute('data-status');
            const hours = parseFloat(row.getAttribute('data-hours')) || 0;

            if (rowSearch.includes(query)) {
                row.style.display = '';
                
                // Only count for current day rows (for exactness, though typically all visible rows reflect the current filter)
                if (status === 'Present') presentCount++;
                if (status === 'Clocked Out') clockedOutCount++;
                totalHours += hours;
            } else {
                row.style.display = 'none';
            }
        });
        
        if (kpiPresent) kpiPresent.textContent = presentCount;
        if (kpiClockedOut) kpiClockedOut.textContent = clockedOutCount;
        if (kpiTotalHours) kpiTotalHours.textContent = totalHours.toFixed(2);
    }

    if(searchInput) {
        searchInput.addEventListener('input', filterTable);
        searchInput.addEventListener('keydown', (e) => {
            if(e.key === 'Enter') e.preventDefault();
        });
    }
    
    // Initial run to set KPIs based on initial rows (which may be pre-filtered by date)
    filterTable();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

