<?php
require_once __DIR__ . '/../app/bootstrap.php';

Auth::requireLogin();
Rbac::require_permission('hr.leave.view');

global $pdo;

$canApprove = Rbac::can('hr.leave.approve');
$canCreate = Rbac::can('hr.leave.manage');
$currentUser = Auth::user();
$userBranchId = $currentUser['branch_id'] ?? null;
$userRole = (int)($currentUser['role_id'] ?? 0);
$isAdminOrChr = in_array($userRole, [1, 2], true) || $userBranchId === null;

// Branch scope
$branches = [];
if ($isAdminOrChr) {
    $branches = $pdo->query("SELECT id, name, address FROM branches WHERE status = 'Active' AND deleted_at IS NULL ORDER BY name ASC, id ASC")->fetchAll();
    if (isset($_GET['branch_id']) && is_numeric($_GET['branch_id'])) {
        $selectedBranchId = (int)$_GET['branch_id'];
    } else {
        $defStmt = $pdo->query("
            SELECT b.id FROM branches b 
            WHERE b.status = 'Active' AND b.deleted_at IS NULL 
            ORDER BY (SELECT COUNT(*) FROM employees e WHERE e.branch_id = b.id AND e.status = 'Active') DESC, b.id ASC 
            LIMIT 1
        ");
        $selectedBranchId = (int)($defStmt ? $defStmt->fetchColumn() : ($branches[0]['id'] ?? 1));
    }
    $scopeSql = " AND e.branch_id = ?";
    $scopeParams = [$selectedBranchId];
} else {
    $selectedBranchId = (int)$userBranchId;
    [$scopeSql, $scopeParams] = Rbac::branch_scope('e');
}

// Current Month for Leave Calendar
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
if ($currentMonth < 1) { $currentMonth = 12; $currentYear--; }
if ($currentMonth > 12) { $currentMonth = 1; $currentYear++; }

$firstDayOfMonth = sprintf('%04d-%02d-01', $currentYear, $currentMonth);
$lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));

// Active tab
$activeTab = $_GET['tab'] ?? 'requests'; // 'requests' or 'calendar'
$statusFilter = $_GET['status'] ?? 'ALL';

// 1. Fetch leave requests
$filterSql = "";
$queryParams = $scopeParams;
if ($statusFilter !== 'ALL' && in_array($statusFilter, ['PENDING', 'APPROVED', 'REJECTED'])) {
    $filterSql = " AND lr.status = ?";
    $queryParams[] = $statusFilter;
}

$reqStmt = $pdo->prepare("
    SELECT lr.*, e.first_name, e.last_name, e.employee_code, e.position, b.name as branch_name,
           lt.name as type_name, lt.is_paid,
           u.username as decider_username
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.id
    LEFT JOIN branches b ON e.branch_id = b.id
    LEFT JOIN leave_types lt ON lr.type = lt.code
    LEFT JOIN users u ON lr.decided_by = u.id
    WHERE 1=1 {$scopeSql} {$filterSql}
    ORDER BY lr.created_at DESC
");
$reqStmt->execute($queryParams);
$leaves = $reqStmt->fetchAll();

// 2. Fetch approved leaves for the Calendar View in this month
$calStmt = $pdo->prepare("
    SELECT lr.*, e.first_name, e.last_name, e.employee_code, lt.name as type_name, lt.is_paid
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.id
    LEFT JOIN leave_types lt ON lr.type = lt.code
    WHERE lr.status = 'APPROVED'
      AND (lr.date_from <= ? AND lr.date_to >= ?)
      {$scopeSql}
    ORDER BY lr.date_from ASC
");
$calParams = array_merge([$lastDayOfMonth, $firstDayOfMonth], $scopeParams);
$calStmt->execute($calParams);
$monthLeaves = $calStmt->fetchAll();

// Map leaves to calendar dates
$calendarDayLeaves = [];
foreach ($monthLeaves as $ml) {
    $curDate = new DateTime(max($ml['date_from'], $firstDayOfMonth));
    $endDate = new DateTime(min($ml['date_to'], $lastDayOfMonth));
    while ($curDate <= $endDate) {
        $dStr = $curDate->format('Y-m-d');
        $calendarDayLeaves[$dStr][] = $ml;
        $curDate->modify('+1 day');
    }
}

// 3. Stats KPIs
$pendingCount = 0;
$todayLeavesCount = 0;
$monthApprovedCount = 0;
$todayDate = date('Y-m-d');

foreach ($leaves as $l) {
    if ($l['status'] === 'PENDING') $pendingCount++;
    if ($l['status'] === 'APPROVED') {
        if ($l['date_from'] <= $todayDate && $l['date_to'] >= $todayDate) {
            $todayLeavesCount++;
        }
        if (substr($l['date_from'], 0, 7) === date('Y-m') || substr($l['date_to'], 0, 7) === date('Y-m')) {
            $monthApprovedCount++;
        }
    }
}

// 4. Form dropdown data
$leaveTypes = $pdo->query("SELECT * FROM leave_types ORDER BY name ASC")->fetchAll();
$emps = $pdo->prepare("SELECT id, first_name, last_name, employee_code, position FROM employees e WHERE status = 'ACTIVE' {$scopeSql} ORDER BY first_name ASC");
$emps->execute($scopeParams);
$employees = $emps->fetchAll();

$csrfToken = Csrf::getToken();
$pageTitle = "Leave Management";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management - Fika HRMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex">

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto">
        
        <!-- Header -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-30 px-6 py-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                        <span>HR Operations</span>
                        <span>/</span>
                        <span class="text-indigo-600 font-bold">Leave Management</span>
                    </div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2 mt-0.5">
                        <i class="fa-solid fa-plane-departure text-indigo-600"></i>
                        Leave Management &amp; Calendar
                    </h1>
                </div>

                <div class="flex items-center gap-3">
                    <?php if ($isAdminOrChr && count($branches) > 0): ?>
                        <form method="GET" class="inline-flex items-center gap-2">
                            <input type="hidden" name="tab" value="<?= e($activeTab) ?>">
                            <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
                            <?php if ($activeTab === 'calendar'): ?>
                                <input type="hidden" name="year" value="<?= $currentYear ?>">
                                <input type="hidden" name="month" value="<?= $currentMonth ?>">
                            <?php endif; ?>
                            <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Branch:</label>
                            <select name="branch_id" onchange="this.form.submit()" class="border border-slate-300 rounded-xl px-3 py-1.5 text-xs font-semibold focus:ring-2 focus:ring-indigo-500 bg-white shadow-xs">
                                <?php foreach ($branches as $b): ?>
                                    <option value="<?= $b['id'] ?>" <?= $selectedBranchId == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    <?php endif; ?>

                    <!-- Tab buttons -->
                    <div class="bg-slate-100 p-1 rounded-xl flex items-center border border-slate-200">
                        <a href="?tab=requests&status=<?= urlencode($statusFilter) ?>&branch_id=<?= $selectedBranchId ?>" class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 <?= $activeTab === 'requests' ? 'bg-white text-indigo-700 shadow-xs' : 'text-slate-600 hover:text-slate-900' ?>">
                            <i class="fa-solid fa-list-check"></i>
                            <span>Requests Queue</span>
                            <?php if ($pendingCount > 0): ?>
                                <span class="bg-amber-500 text-white text-[10px] px-1.5 py-0.2 rounded-full font-black"><?= $pendingCount ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="?tab=calendar&year=<?= $currentYear ?>&month=<?= $currentMonth ?>&branch_id=<?= $selectedBranchId ?>" class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 <?= $activeTab === 'calendar' ? 'bg-white text-indigo-700 shadow-xs' : 'text-slate-600 hover:text-slate-900' ?>">
                            <i class="fa-solid fa-calendar-days"></i>
                            <span>Team Calendar</span>
                        </a>
                    </div>

                    <?php if ($canCreate): ?>
                        <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-sm transition flex items-center gap-2">
                            <i class="fa-solid fa-plus"></i>
                            <span>File Leave Request</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <div class="p-6 space-y-6 max-w-7xl w-full mx-auto">
            
            <div id="alertBox" class="hidden p-4 rounded-xl text-sm font-bold border transition"></div>

            <!-- KPI Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
                    <div>
                        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Pending Approvals</div>
                        <div class="text-2xl font-black text-slate-900 mt-1"><?= $pendingCount ?></div>
                        <div class="text-[11px] text-amber-600 font-medium mt-0.5"><?= $pendingCount > 0 ? 'Requires attention' : 'All requests reviewed' ?></div>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
                    <div>
                        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">On Leave Today</div>
                        <div class="text-2xl font-black text-slate-900 mt-1"><?= $todayLeavesCount ?></div>
                        <div class="text-[11px] text-indigo-600 font-medium mt-0.5">Active team leaves</div>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl">
                        <i class="fa-solid fa-user-slash"></i>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
                    <div>
                        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Approved This Month</div>
                        <div class="text-2xl font-black text-slate-900 mt-1"><?= $monthApprovedCount ?></div>
                        <div class="text-[11px] text-emerald-600 font-medium mt-0.5">Recorded scheduled days</div>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
                    <div>
                        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Leave Types</div>
                        <div class="text-2xl font-black text-slate-900 mt-1"><?= count($leaveTypes) ?></div>
                        <div class="text-[11px] text-slate-500 font-medium mt-0.5">Active policies configured</div>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl">
                        <i class="fa-solid fa-briefcase"></i>
                    </div>
                </div>
            </div>

            <?php if ($activeTab === 'requests'): ?>
                <!-- Requests Queue Table View -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                    <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/50">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Filter Status:</span>
                            <div class="flex items-center gap-1">
                                <?php foreach (['ALL' => 'All', 'PENDING' => 'Pending', 'APPROVED' => 'Approved', 'REJECTED' => 'Rejected'] as $st => $stLbl): ?>
                                    <a href="?tab=requests&status=<?= $st ?>&branch_id=<?= $selectedBranchId ?>" class="px-2.5 py-1 rounded-lg text-xs font-bold transition <?= $statusFilter === $st ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-600' ?>">
                                        <?= $stLbl ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="text-xs text-slate-400 font-medium">
                            Showing <span class="font-bold text-slate-700"><?= count($leaves) ?></span> request(s)
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-sm">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                    <th class="py-3 px-4">Employee</th>
                                    <th class="py-3 px-4">Leave Type</th>
                                    <th class="py-3 px-4">Duration &amp; Dates</th>
                                    <th class="py-3 px-4 text-center">Days</th>
                                    <th class="py-3 px-4">Reason</th>
                                    <th class="py-3 px-4 text-center">Status</th>
                                    <th class="py-3 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                <?php if (empty($leaves)): ?>
                                    <tr>
                                        <td colspan="7" class="py-12 text-center text-slate-400">
                                            <i class="fa-solid fa-folder-open text-4xl mb-2 opacity-50 block"></i>
                                            <p class="font-medium text-sm">No leave requests found matching criteria.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($leaves as $l): ?>
                                        <tr class="hover:bg-slate-50/70 transition">
                                            <td class="py-3 px-4">
                                                <div class="font-bold text-slate-900"><?= e($l['first_name'] . ' ' . $l['last_name']) ?></div>
                                                <div class="text-[11px] text-slate-400 font-mono flex items-center gap-1.5 mt-0.5">
                                                    <span><?= e($l['employee_code']) ?></span>
                                                    <span>•</span>
                                                    <span><?= e($l['position'] ?? 'Staff') ?></span>
                                                </div>
                                            </td>
                                            <td class="py-3 px-4">
                                                <div class="font-semibold text-slate-800"><?= e($l['type_name'] ?? $l['type']) ?></div>
                                                <span class="inline-block mt-0.5 text-[9px] font-black uppercase tracking-wider px-1.5 py-0.2 rounded <?= $l['is_paid'] ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' ?>">
                                                    <?= $l['is_paid'] ? 'PAID' : 'UNPAID' ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <div class="text-xs font-mono font-medium text-slate-900">
                                                    <?= date('M d, Y', strtotime($l['date_from'])) ?> → <?= date('M d, Y', strtotime($l['date_to'])) ?>
                                                </div>
                                                <div class="text-[11px] text-slate-400 mt-0.5">
                                                    Filed: <?= date('M d, H:i', strtotime($l['created_at'])) ?>
                                                </div>
                                            </td>
                                            <td class="py-3 px-4 text-center">
                                                <span class="font-black text-slate-800 text-sm"><?= (float)$l['days'] ?></span>
                                            </td>
                                            <td class="py-3 px-4 max-w-xs">
                                                <div class="text-xs text-slate-600 truncate" title="<?= e($l['reason']) ?>">
                                                    <?= e($l['reason']) ?>
                                                </div>
                                            </td>
                                            <td class="py-3 px-4 text-center">
                                                <?php
                                                    $stBadge = 'bg-slate-100 text-slate-700 border-slate-200';
                                                    if ($l['status'] === 'PENDING') $stBadge = 'bg-amber-50 text-amber-800 border-amber-300';
                                                    elseif ($l['status'] === 'APPROVED') $stBadge = 'bg-emerald-50 text-emerald-800 border-emerald-300';
                                                    elseif ($l['status'] === 'REJECTED') $stBadge = 'bg-rose-50 text-rose-800 border-rose-300';
                                                ?>
                                                <span class="inline-block px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider border <?= $stBadge ?>">
                                                    <?= e($l['status']) ?>
                                                </span>
                                                <?php if ($l['decider_username']): ?>
                                                    <div class="text-[9px] text-slate-400 mt-0.5 italic">By <?= e($l['decider_username']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-4 text-right">
                                                <?php if ($l['status'] === 'PENDING' && $canApprove): ?>
                                                    <div class="flex items-center justify-end gap-1.5">
                                                        <button onclick="decideLeave(<?= $l['id'] ?>, 'APPROVED')" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-2xs transition flex items-center gap-1">
                                                            <i class="fa-solid fa-check text-[10px]"></i>
                                                            <span>Approve</span>
                                                        </button>
                                                        <button onclick="decideLeave(<?= $l['id'] ?>, 'REJECTED')" class="px-2.5 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-bold shadow-2xs transition flex items-center gap-1">
                                                            <i class="fa-solid fa-xmark text-[10px]"></i>
                                                            <span>Reject</span>
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-xs text-slate-300 font-medium">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php else: ?>
                <!-- Team Leave Calendar View -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                    <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                        <div class="flex items-center gap-3">
                            <span class="text-lg font-black text-slate-800">
                                <?= date('F Y', strtotime($firstDayOfMonth)) ?>
                            </span>
                            <div class="flex items-center gap-1">
                                <?php
                                    $prevMonth = $currentMonth - 1;
                                    $prevYear = $currentYear;
                                    if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
                                    $nextMonth = $currentMonth + 1;
                                    $nextYear = $currentYear;
                                    if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
                                ?>
                                <a href="?tab=calendar&year=<?= $prevYear ?>&month=<?= $prevMonth ?>&branch_id=<?= $selectedBranchId ?>" class="w-8 h-8 rounded-lg border border-slate-200 hover:bg-slate-100 flex items-center justify-center text-slate-600 text-xs transition">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </a>
                                <a href="?tab=calendar&year=<?= date('Y') ?>&month=<?= date('m') ?>&branch_id=<?= $selectedBranchId ?>" class="px-2.5 py-1 rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-700 text-xs font-bold transition">
                                    Today
                                </a>
                                <a href="?tab=calendar&year=<?= $nextYear ?>&month=<?= $nextMonth ?>&branch_id=<?= $selectedBranchId ?>" class="w-8 h-8 rounded-lg border border-slate-200 hover:bg-slate-100 flex items-center justify-center text-slate-600 text-xs transition">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            </div>
                        </div>

                        <div class="text-xs text-slate-500 flex items-center gap-4">
                            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-amber-400"></span> Approved Leave</span>
                            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-indigo-500"></span> Current Day</span>
                        </div>
                    </div>

                    <?php
                        $startDayOfWeek = (int)date('w', strtotime($firstDayOfMonth)); // 0=Sun, 1=Mon, ...
                        // Adjust for Monday start: 0=Mon, 6=Sun
                        $startOffset = ($startDayOfWeek === 0) ? 6 : $startDayOfWeek - 1;
                        $daysInMonth = (int)date('t', strtotime($firstDayOfMonth));
                        $dayHeaders = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                    ?>

                    <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500 text-center py-2.5">
                        <?php foreach ($dayHeaders as $dh): ?>
                            <div><?= $dh ?></div>
                        <?php endforeach; ?>
                    </div>

                    <div class="grid grid-cols-7 auto-rows-fr divide-x divide-y divide-slate-100">
                        <?php 
                        // Empty leading cells
                        for ($i = 0; $i < $startOffset; $i++): ?>
                            <div class="min-h-[110px] bg-slate-50/40 p-2 text-slate-300"></div>
                        <?php endfor; ?>

                        <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                            $dateKey = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $d);
                            $isCurToday = ($dateKey === $todayDate);
                            $dayLeaves = $calendarDayLeaves[$dateKey] ?? [];
                        ?>
                            <div class="min-h-[110px] p-2 flex flex-col justify-between <?= $isCurToday ? 'bg-indigo-50/25 ring-1 ring-inset ring-indigo-400' : 'bg-white' ?>">
                                <div>
                                    <div class="flex items-center justify-between mb-1.5">
                                        <span class="text-xs font-bold <?= $isCurToday ? 'w-6 h-6 rounded-full bg-indigo-600 text-white flex items-center justify-center font-black' : 'text-slate-700' ?>">
                                            <?= $d ?>
                                        </span>
                                        <?php if (!empty($dayLeaves)): ?>
                                            <span class="text-[10px] font-bold text-amber-700 bg-amber-100 px-1.5 rounded-full">
                                                <?= count($dayLeaves) ?> on leave
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="space-y-1 overflow-y-auto max-h-[85px]">
                                        <?php foreach ($dayLeaves as $dl): ?>
                                            <div class="p-1.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-[10px] shadow-2xs">
                                                <div class="font-bold truncate flex items-center gap-1">
                                                    <i class="fa-solid fa-plane-departure text-[8px] text-amber-600"></i>
                                                    <span><?= e($dl['first_name'] . ' ' . $dl['last_name']) ?></span>
                                                </div>
                                                <div class="text-[9px] text-amber-800/80 truncate mt-0.5">
                                                    <?= e($dl['type_name'] ?? 'Leave') ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endfor; ?>

                        <?php
                        // Empty trailing cells to complete the 7-column grid row
                        $totalCells = $startOffset + $daysInMonth;
                        $trailingCells = (7 - ($totalCells % 7)) % 7;
                        for ($j = 0; $j < $trailingCells; $j++): ?>
                            <div class="min-h-[110px] bg-slate-50/40 p-2 text-slate-300"></div>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <?php if ($canCreate): ?>
    <!-- Create / File Leave Modal -->
    <div id="createModal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-slate-200">
            <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                <h3 class="font-bold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-plane-departure text-indigo-600"></i>
                    <span>File Employee Leave Request</span>
                </h3>
                <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-lg">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form id="createForm" class="p-6 space-y-4">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Select Employee *</label>
                    <select name="employee_id" required class="w-full text-xs rounded-xl border border-slate-300 p-2.5 focus:ring-2 focus:ring-indigo-500 bg-white">
                        <option value="">-- Choose Staff Member --</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>">
                                <?= e($emp['first_name'] . ' ' . $emp['last_name']) ?> (<?= e($emp['employee_code']) ?> - <?= e($emp['position'] ?? 'Staff') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Leave Type *</label>
                    <select name="type" required class="w-full text-xs rounded-xl border border-slate-300 p-2.5 focus:ring-2 focus:ring-indigo-500 bg-white">
                        <option value="">-- Choose Type --</option>
                        <?php foreach ($leaveTypes as $lt): ?>
                            <option value="<?= e($lt['code']) ?>">
                                <?= e($lt['name']) ?> (<?= $lt['is_paid'] ? 'Paid Leave' : 'Unpaid' ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Date From *</label>
                        <input type="date" name="date_from" id="modal_date_from" required class="w-full text-xs rounded-xl border border-slate-300 p-2.5 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Date To *</label>
                        <input type="date" name="date_to" id="modal_date_to" required class="w-full text-xs rounded-xl border border-slate-300 p-2.5 focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Days Count *</label>
                    <input type="number" step="0.5" min="0.5" name="days" id="modal_days" required placeholder="e.g. 1.0" class="w-full text-xs rounded-xl border border-slate-300 p-2.5 focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Reason / Justification *</label>
                    <textarea name="reason" required rows="3" placeholder="Provide reason for time off..." class="w-full text-xs rounded-xl border border-slate-300 p-2.5 focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>

                <div class="pt-2 flex justify-end gap-3 border-t border-slate-100">
                    <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="px-4 py-2 border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-bold rounded-xl transition">
                        Cancel
                    </button>
                    <button type="submit" id="btnSubmitLeave" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-xs transition flex items-center gap-1.5">
                        <i class="fa-solid fa-paper-plane text-[11px]"></i>
                        <span>Submit Request</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Auto-calculate days count on date change
        function calcDays() {
            const d1 = document.getElementById('modal_date_from').value;
            const d2 = document.getElementById('modal_date_to').value;
            if (d1 && d2) {
                const start = new Date(d1);
                const end = new Date(d2);
                if (end >= start) {
                    const diffTime = Math.abs(end - start);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                    document.getElementById('modal_days').value = diffDays;
                }
            }
        }
        document.getElementById('modal_date_from')?.addEventListener('change', calcDays);
        document.getElementById('modal_date_to')?.addEventListener('change', calcDays);

        document.getElementById('createForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSubmitLeave');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';

            const formData = new FormData(this);
            const payload = Object.fromEntries(formData.entries());
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            try {
                const response = await fetch('api/fika_hrms_leave_create.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify(payload)
                });
                const data = await response.json();
                if (response.ok) {
                    window.location.reload();
                } else {
                    alert(data.error || 'Failed to submit leave request.');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-paper-plane text-[11px]"></i> Submit Request';
                }
            } catch (err) {
                alert('Network or server error while submitting leave request.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-paper-plane text-[11px]"></i> Submit Request';
            }
        });
    </script>
    <?php endif; ?>

    <script>
        async function decideLeave(leaveId, status) {
            const verb = status === 'APPROVED' ? 'approve' : 'reject';
            if (!confirm(`Are you sure you want to ${verb} this leave request?`)) return;

            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            try {
                const response = await fetch('api/fika_hrms_leave_decide.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        leave_id: leaveId,
                        status: status,
                        csrf_token: csrfToken
                    })
                });
                const data = await response.json();
                const box = document.getElementById('alertBox');
                if (response.ok) {
                    window.location.reload();
                } else {
                    box.innerText = data.error || 'Operation failed.';
                    box.className = 'mb-4 p-4 rounded-xl text-sm font-bold bg-rose-50 text-rose-800 border border-rose-200 block';
                    box.classList.remove('hidden');
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            } catch (err) {
                alert('Network error while processing request.');
            }
        }
    </script>
</body>
</html>
