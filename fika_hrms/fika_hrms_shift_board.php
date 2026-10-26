<?php
require_once __DIR__ . '/../app/bootstrap.php';
class_exists('Output');

Auth::requireLogin();
Rbac::require_permission('schedule.view');

global $pdo;

$user = Auth::user();
$canManage = Rbac::can('schedule.manage');
$userBranchId = $user['branch_id'] ?? null;
$userRole = (int)($user['role_id'] ?? 0);

$roleStmt = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
$roleStmt->execute([$userRole]);
$userRoleName = (string)$roleStmt->fetchColumn();

$isBHR = ($userRole === 5) || in_array($userRoleName, ['BHR', 'Branch HR'], true);
$isBM = ($userRole === 6) || in_array($userRoleName, ['BM', 'Branch Manager'], true);
$isBA = ($userRole === 4) || in_array($userRoleName, ['BA', 'Branch Accountant'], true);
$isAdmin = ($userRole === 1) || in_array($userRoleName, ['ADMIN', 'Super Admin'], true);

$isAdminOrChr = in_array($userRole, [1, 2], true) || $userBranchId === null;

// Branch Scoping
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
} else {
    $selectedBranchId = (int)$userBranchId;
    $bStmt = $pdo->prepare("SELECT id, name, address FROM branches WHERE id = ?");
    $bStmt->execute([$selectedBranchId]);
    $branches = $bStmt->fetchAll();
}

// Week Navigation (Monday to Sunday)
$weekOffset = (int)($_GET['week_offset'] ?? 0);
$monday = new DateTime('monday this week');
if ($weekOffset !== 0) {
    $monday->modify($weekOffset > 0 ? "+{$weekOffset} week" : "{$weekOffset} week");
}
$sunday = (clone $monday)->modify('+6 days');

$weekDays = [];
$cur = clone $monday;
for ($i = 0; $i < 7; $i++) {
    $weekDays[] = [
        'date' => $cur->format('Y-m-d'),
        'dayName' => $cur->format('D'),
        'formatted' => $cur->format('M d')
    ];
    $cur->modify('+1 day');
}

$dateFrom = $monday->format('Y-m-d');
$dateTo = $sunday->format('Y-m-d');

// Fetch branch crew members (exclude officials/management: BM, BHR, BA)
$empStmt = $pdo->prepare("SELECT * FROM employees WHERE branch_id = ? AND status = 'Active' AND staff_class = 'CREW' AND deleted_at IS NULL ORDER BY first_name ASC");
$empStmt->execute([$selectedBranchId]);
$employees = $empStmt->fetchAll();

// Index employee hourly rates for budget calculation
$employeeRates = [];
foreach ($employees as $emp) {
    $rate = (float)($emp['hourly_rate'] ?? 0);
    if ($rate <= 0) {
        $rate = round(((float)($emp['basic_salary'] ?? 0)) / 208, 2);
    }
    $employeeRates[$emp['id']] = $rate;
}

// Fetch shifts for this week and branch from shifts table
$shiftStmt = $pdo->prepare("
    SELECT s.*, e.first_name, e.last_name, e.employee_code,
           u.username as creator_username
    FROM shifts s
    JOIN employees e ON s.employee_id = e.id
    LEFT JOIN users u ON s.created_by = u.id
    WHERE s.branch_id = ? AND s.shift_date BETWEEN ? AND ?
    ORDER BY s.start_time ASC
");
$shiftStmt->execute([$selectedBranchId, $dateFrom, $dateTo]);
$rawShifts = $shiftStmt->fetchAll();

// Index shifts and aggregate 4-step SoD stats
$gridShifts = [];
$draftCount = 0;
$pendingBmCount = 0;
$pendingBaCount = 0;
$approvedCount = 0;
$publishedCount = 0;
$rejectedCount = 0;
$rejectionReasons = [];

$dailyManpower = [];
foreach ($weekDays as $day) {
    $dailyManpower[$day['date']] = [];
}

$totalScheduledHours = 0.0;
$estimatedLaborCost = 0.0;

foreach ($rawShifts as $s) {
    $gridShifts[$s['employee_id']][$s['shift_date']][] = $s;
    $st = $s['status'] ?? 'DRAFT';
    if ($st === 'DRAFT') $draftCount++;
    elseif ($st === 'PENDING_BM') $pendingBmCount++;
    elseif ($st === 'PENDING_BA') $pendingBaCount++;
    elseif ($st === 'APPROVED') $approvedCount++;
    elseif ($st === 'PUBLISHED') $publishedCount++;
    elseif ($st === 'REJECTED') {
        $rejectedCount++;
        if (!empty($s['rejection_reason'])) {
            $rejectionReasons[] = $s['rejection_reason'];
        }
    }

    if (isset($dailyManpower[$s['shift_date']])) {
        $dailyManpower[$s['shift_date']][$s['employee_id']] = true;
    }

    $startTs = strtotime($s['start_time']);
    $endTs = strtotime($s['end_time']);
    $diffHours = ($endTs - $startTs) / 3600;
    if ($diffHours < 0) {
        $diffHours += 24;
    }
    $totalScheduledHours += $diffHours;
    $empRate = $employeeRates[$s['employee_id']] ?? 0;
    $estimatedLaborCost += ($diffHours * $empRate);
}
$rejectionReasons = array_unique($rejectionReasons);

// Active SoD Step
$activeStep = 1;
if ($publishedCount > 0 && $draftCount === 0 && $pendingBmCount === 0 && $pendingBaCount === 0 && $approvedCount === 0) {
    $activeStep = 4;
} elseif ($approvedCount > 0) {
    $activeStep = 4;
} elseif ($pendingBaCount > 0) {
    $activeStep = 3;
} elseif ($pendingBmCount > 0) {
    $activeStep = 2;
} else {
    $activeStep = 1;
}

// Fetch approved leaves overlapping this week from leave_requests
$leaveStmt = $pdo->prepare("
    SELECT lr.*, lt.name as type_name, lt.is_paid
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.id
    LEFT JOIN leave_types lt ON lr.type = lt.code
    WHERE e.branch_id = ? AND lr.status = 'APPROVED'
      AND (lr.date_from <= ? AND lr.date_to >= ?)
");
$leaveStmt->execute([$selectedBranchId, $dateTo, $dateFrom]);
$rawLeaves = $leaveStmt->fetchAll(PDO::FETCH_ASSOC);

$gridLeaves = [];
foreach ($rawLeaves as $l) {
    $curL = new DateTime(max($l['date_from'], $dateFrom));
    $endL = new DateTime(min($l['date_to'], $dateTo));
    while ($curL <= $endL) {
        $gridLeaves[$l['employee_id']][$curL->format('Y-m-d')][] = $l;
        $curL->modify('+1 day');
    }
}

$csrfToken = Csrf::getToken();
$pageTitle = "Roster & Scheduler";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Roster &amp; Scheduler - Fika HRMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex">

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto">
        <!-- Top Nav Header with Dynamic Action Buttons -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-black text-xl shadow-sm">
                        <i class="fa-solid fa-calendar-days text-base"></i>
                    </div>
                    <div>
                        <h1 class="font-bold text-lg text-slate-900 leading-tight">Roster &amp; Scheduler</h1>
                        <p class="text-xs text-slate-500">4-Step SoD Chain: BHR Draft &rarr; BM Manpower &rarr; BA Budget &rarr; BM Publish</p>
                    </div>
                </div>

                <div class="flex items-center gap-2.5">
                    <!-- 1. BHR Action: Submit to BM for Manpower Review -->
                    <?php if (($isBHR || $isAdmin) && ($draftCount > 0 || $rejectedCount > 0)): ?>
                        <button type="button" onclick="submitToBm()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-xl text-xs transition shadow-sm inline-flex items-center gap-1.5">
                            <i class="fa-solid fa-paper-plane"></i> Submit to BM for Manpower Review
                        </button>
                    <?php endif; ?>

                    <!-- 2. BM Action: Approve Manpower -->
                    <?php if (($isBM || $isAdmin) && $pendingBmCount > 0): ?>
                        <button type="button" onclick="approveManpower()" class="bg-sky-600 hover:bg-sky-700 text-white font-bold py-2 px-4 rounded-xl text-xs transition shadow-sm inline-flex items-center gap-1.5">
                            <i class="fa-solid fa-users-viewfinder"></i> Approve Manpower
                        </button>
                    <?php endif; ?>

                    <!-- 3. BA Action: Approve Budget -->
                    <?php if (($isBA || $isAdmin) && $pendingBaCount > 0): ?>
                        <button type="button" onclick="approveBudget()" class="bg-violet-600 hover:bg-violet-700 text-white font-bold py-2 px-4 rounded-xl text-xs transition shadow-sm inline-flex items-center gap-1.5">
                            <i class="fa-solid fa-file-signature"></i> Approve Budget
                        </button>
                    <?php endif; ?>

                    <!-- 4. BM Final Action: Publish to Staff -->
                    <?php if (($isBM || $isAdmin) && $approvedCount > 0): ?>
                        <button type="button" onclick="publishApprovedRoster()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-4 rounded-xl text-xs transition shadow-sm inline-flex items-center gap-1.5">
                            <i class="fa-solid fa-upload"></i> Publish to Staff
                        </button>
                    <?php endif; ?>

                    <!-- Add Shift Button (BHR & BM) -->
                    <?php if ($canManage): ?>
                        <button type="button" onclick="openAddShiftModal()" class="bg-slate-900 hover:bg-slate-800 text-white font-bold py-2 px-4 rounded-xl text-xs transition shadow-sm inline-flex items-center gap-1.5">
                            <i class="fa-solid fa-plus"></i> Add Shift
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full flex-1">

            <!-- Controls: Week navigation & Branch selector -->
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 mb-6 flex flex-wrap justify-between items-center gap-4">
                <div class="flex items-center gap-3">
                    <a href="?week_offset=<?= $weekOffset - 1 ?>&branch_id=<?= $selectedBranchId ?>" class="px-3.5 py-2 bg-white border border-slate-300 rounded-xl shadow-xs hover:bg-slate-50 text-slate-700 text-xs font-bold transition flex items-center gap-1.5">
                        <i class="fa-solid fa-chevron-left text-[11px]"></i> Prev Week
                    </a>
                    <span class="font-bold text-slate-900 text-sm px-2">
                        <?= $monday->format('M d') ?> &ndash; <?= $sunday->format('M d, Y') ?>
                    </span>
                    <a href="?week_offset=<?= $weekOffset + 1 ?>&branch_id=<?= $selectedBranchId ?>" class="px-3.5 py-2 bg-white border border-slate-300 rounded-xl shadow-xs hover:bg-slate-50 text-slate-700 text-xs font-bold transition flex items-center gap-1.5">
                        Next Week <i class="fa-solid fa-chevron-right text-[11px]"></i>
                    </a>
                    <?php if ($weekOffset !== 0): ?>
                        <a href="?week_offset=0&branch_id=<?= $selectedBranchId ?>" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 ml-1">
                            Current Week
                        </a>
                    <?php endif; ?>
                </div>

                <div class="flex items-center gap-3">
                    <?php if ($isAdminOrChr && count($branches) > 0): ?>
                        <form method="GET" class="inline-flex items-center gap-2">
                            <input type="hidden" name="week_offset" value="<?= $weekOffset ?>">
                            <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Branch:</label>
                            <select name="branch_id" onchange="this.form.submit()" class="border border-slate-300 rounded-xl px-3 py-1.5 text-xs font-semibold focus:ring-2 focus:ring-indigo-500 bg-white shadow-xs">
                                <?php foreach ($branches as $b): ?>
                                    <option value="<?= $b['id'] ?>" <?= $selectedBranchId == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 text-xs font-bold border border-slate-200">
                            <i class="fa-solid fa-location-dot text-indigo-500"></i> <?= e($branches[0]['name'] ?? 'General Trias Branch') ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 4-Step Separation of Duties (SoD) Stepper Banner (Constraint 2) -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 mb-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-indigo-600">Separation of Duties (SoD) Scheduling Chain</span>
                        <h2 class="text-base font-extrabold text-slate-900 mt-0.5">Roster Approval &amp; Release Workflow</h2>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <?php if ($rejectedCount > 0): ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-rose-50 border border-rose-200 text-rose-700 rounded-full text-xs font-bold shadow-xs">
                                <i class="fa-solid fa-triangle-exclamation"></i> <?= $rejectedCount ?> Shift(s) Rejected
                            </span>
                        <?php endif; ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-slate-100 border border-slate-200 text-slate-700 rounded-full text-xs font-bold">
                            Logged in as: <strong class="uppercase text-slate-900"><?= e($userRoleName ?: ('Role ' . $userRole)) ?></strong>
                        </span>
                    </div>
                </div>

                <!-- Stepper Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                    <!-- Step 1: BHR Drafts -->
                    <div class="p-3.5 rounded-xl border <?= $activeStep === 1 ? 'bg-indigo-50/70 border-indigo-300 ring-2 ring-indigo-200' : 'bg-slate-50 border-slate-200' ?>">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center font-bold text-xs <?= $activeStep > 1 ? 'bg-emerald-600 text-white' : ($activeStep === 1 ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-600') ?>">
                                <?= $activeStep > 1 ? '<i class="fa-solid fa-check"></i>' : '1' ?>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-slate-900">1. BHR Drafts</div>
                                <div class="text-[11px] text-slate-500">Maker: Shift Creation</div>
                            </div>
                        </div>
                        <div class="mt-2 text-[11px] font-semibold text-slate-600 flex justify-between">
                            <span>Drafts: <strong><?= $draftCount ?></strong></span>
                            <span class="text-slate-400">Branch HR</span>
                        </div>
                    </div>

                    <!-- Step 2: BM Screens Manpower -->
                    <div class="p-3.5 rounded-xl border <?= $activeStep === 2 ? 'bg-sky-50/70 border-sky-300 ring-2 ring-sky-200' : 'bg-slate-50 border-slate-200' ?>">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center font-bold text-xs <?= $activeStep > 2 ? 'bg-emerald-600 text-white' : ($activeStep === 2 ? 'bg-sky-600 text-white' : 'bg-slate-200 text-slate-600') ?>">
                                <?= $activeStep > 2 ? '<i class="fa-solid fa-check"></i>' : '2' ?>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-slate-900">2. BM Manpower Review</div>
                                <div class="text-[11px] text-slate-500">Checker 1: Headcount</div>
                            </div>
                        </div>
                        <div class="mt-2 text-[11px] font-semibold text-slate-600 flex justify-between">
                            <span>Pending BM: <strong><?= $pendingBmCount ?></strong></span>
                            <span class="text-slate-400">Branch Manager</span>
                        </div>
                    </div>

                    <!-- Step 3: BA Approves Budget -->
                    <div class="p-3.5 rounded-xl border <?= $activeStep === 3 ? 'bg-violet-50/70 border-violet-300 ring-2 ring-violet-200' : 'bg-slate-50 border-slate-200' ?>">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center font-bold text-xs <?= $activeStep > 3 ? 'bg-emerald-600 text-white' : ($activeStep === 3 ? 'bg-violet-600 text-white' : 'bg-slate-200 text-slate-600') ?>">
                                <?= $activeStep > 3 ? '<i class="fa-solid fa-check"></i>' : '3' ?>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-slate-900">3. BA Budget Approval</div>
                                <div class="text-[11px] text-slate-500">Checker 2: Labor Cost</div>
                            </div>
                        </div>
                        <div class="mt-2 text-[11px] font-semibold text-slate-600 flex justify-between">
                            <span>Pending BA: <strong><?= $pendingBaCount ?></strong></span>
                            <span class="text-slate-400">Branch Accountant</span>
                        </div>
                    </div>

                    <!-- Step 4: BM Publishes -->
                    <div class="p-3.5 rounded-xl border <?= $activeStep === 4 ? ($publishedCount > 0 ? 'bg-emerald-50/70 border-emerald-300 ring-2 ring-emerald-200' : 'bg-teal-50/70 border-teal-300 ring-2 ring-teal-200') : 'bg-slate-50 border-slate-200' ?>">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center font-bold text-xs <?= $publishedCount > 0 ? 'bg-emerald-600 text-white' : ($activeStep === 4 ? 'bg-teal-600 text-white' : 'bg-slate-200 text-slate-600') ?>">
                                <?= $publishedCount > 0 ? '<i class="fa-solid fa-check-double"></i>' : '4' ?>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-slate-900">4. BM Publish</div>
                                <div class="text-[11px] text-slate-500">Final Release to ESS</div>
                            </div>
                        </div>
                        <div class="mt-2 text-[11px] font-semibold text-slate-600 flex justify-between">
                            <span>Approved: <strong><?= $approvedCount ?></strong> · Live: <strong><?= $publishedCount ?></strong></span>
                            <span class="text-slate-400">Branch Manager</span>
                        </div>
                    </div>
                </div>

                <?php if (!empty($rejectionReasons)): ?>
                    <div class="mt-4 p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs">
                        <div class="font-bold flex items-center gap-1.5 mb-1">
                            <i class="fa-solid fa-circle-exclamation text-rose-600"></i> Feedback from Reviewer:
                        </div>
                        <ul class="list-disc list-inside space-y-0.5">
                            <?php foreach ($rejectionReasons as $rr): ?>
                                <li><?= e($rr) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

            <!-- BM Manpower Screening Widget (Constraint 2 & Task 3) -->
            <?php if ($isBM || $isAdmin || $pendingBmCount > 0): ?>
            <div class="bg-sky-50/90 border border-sky-200 rounded-2xl p-5 mb-6 shadow-sm">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-md bg-sky-100 text-sky-800 text-[11px] font-bold uppercase tracking-wider mb-1.5">
                            <i class="fa-solid fa-users-viewfinder"></i> BM Manpower Screening Widget
                        </div>
                        <h3 class="text-sm font-bold text-slate-900">Weekly Barista Staffing Breakdown (Monday &ndash; Sunday)</h3>
                        <p class="text-xs text-slate-600 mt-0.5">Screen daily headcount across the branch to verify coverage before finance budget authorization.</p>
                    </div>
                    
                    <?php if (($isBM || $isAdmin) && $pendingBmCount > 0): ?>
                    <div class="flex items-center gap-2.5">
                        <button type="button" onclick="openRejectModal('bm')" class="px-3.5 py-2 bg-white border border-rose-300 text-rose-700 hover:bg-rose-50 rounded-xl text-xs font-bold transition shadow-xs flex items-center gap-1.5">
                            <i class="fa-solid fa-xmark"></i> Reject Manpower
                        </button>
                        <button type="button" onclick="approveManpower()" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs font-bold transition shadow-sm flex items-center gap-1.5">
                            <i class="fa-solid fa-check"></i> Approve Manpower &amp; Send to Finance
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Daily Breakdown Pills (e.g., Monday: 3 Baristas, Tuesday: 2 Baristas...) -->
                <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-2.5 mt-4">
                    <?php foreach ($weekDays as $day): 
                        $dCnt = count($dailyManpower[$day['date']] ?? []);
                        $isLow = $dCnt < 2;
                    ?>
                    <div class="bg-white rounded-xl p-3 border <?= $isLow ? 'border-amber-300 bg-amber-50/40' : 'border-sky-100' ?> text-center shadow-xs">
                        <div class="text-[11px] font-bold text-slate-500 uppercase"><?= $day['dayName'] ?></div>
                        <div class="text-base font-black <?= $isLow ? 'text-amber-700' : 'text-slate-900' ?> mt-0.5">
                            <?= $dCnt ?> <span class="text-xs font-bold text-slate-500">Barista<?= $dCnt === 1 ? '' : 's' ?></span>
                        </div>
                        <div class="text-[10px] text-slate-400 mt-0.5"><?= $day['formatted'] ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- BA Budget & Labor Cost Review Widget (Constraint 2 & Task 4) -->
            <?php if ($isBA || $isAdmin || $pendingBaCount > 0): ?>
            <div class="bg-violet-50/90 border border-violet-200 rounded-2xl p-5 mb-6 shadow-sm">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-md bg-violet-100 text-violet-800 text-[11px] font-bold uppercase tracking-wider mb-1.5">
                            <i class="fa-solid fa-coins"></i> BA Labor Budget Approval Widget
                        </div>
                        <h3 class="text-sm font-bold text-slate-900">Estimated Schedule Labor Cost vs Delegated Budget</h3>
                        <p class="text-xs text-slate-600 mt-0.5">Calculated by multiplying scheduled barista shift hours by their respective hourly rates.</p>
                    </div>

                    <?php if (($isBA || $isAdmin) && $pendingBaCount > 0): ?>
                    <div class="flex items-center gap-2.5">
                        <button type="button" onclick="openRejectModal('ba')" class="px-3.5 py-2 bg-white border border-rose-300 text-rose-700 hover:bg-rose-50 rounded-xl text-xs font-bold transition shadow-xs flex items-center gap-1.5">
                            <i class="fa-solid fa-xmark"></i> Reject Budget
                        </button>
                        <button type="button" onclick="approveBudget()" class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white rounded-xl text-xs font-bold transition shadow-sm flex items-center gap-1.5">
                            <i class="fa-solid fa-file-signature"></i> Approve Budget &amp; Send to BM for Publishing
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Financial Summary Stats (Total Scheduled Hours & Estimated Labor Cost) -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-4">
                    <div class="bg-white rounded-xl p-3.5 border border-violet-100 shadow-xs flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-violet-100 text-violet-700 flex items-center justify-center font-bold text-lg">
                            <i class="fa-regular fa-clock"></i>
                        </div>
                        <div>
                            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Scheduled Hours</div>
                            <div class="text-lg font-black text-slate-900 font-mono"><?= number_format($totalScheduledHours, 1) ?> hrs</div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl p-3.5 border border-violet-100 shadow-xs flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-lg">
                            <i class="fa-solid fa-peso-sign"></i>
                        </div>
                        <div>
                            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Estimated Labor Cost</div>
                            <div class="text-lg font-black text-emerald-700 font-mono">₱<?= number_format($estimatedLaborCost, 2) ?></div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl p-3.5 border border-violet-100 shadow-xs flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-lg">
                            <i class="fa-solid fa-user-group"></i>
                        </div>
                        <div>
                            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Scheduled Baristas</div>
                            <div class="text-lg font-black text-slate-900"><?= count($gridShifts) ?> Crew Members</div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- BM Publish Banner (Task 5) -->
            <?php if (($isBM || $isAdmin) && $approvedCount > 0): ?>
            <div class="bg-teal-50 border border-teal-300 rounded-2xl p-5 mb-6 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-teal-600 text-white flex items-center justify-center font-bold text-lg shadow-sm">
                        <i class="fa-solid fa-bullhorn"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-teal-950">Roster Approved by Finance &mdash; Ready to Publish</h3>
                        <p class="text-xs text-teal-800 mt-0.5"><?= $approvedCount ?> shift(s) approved with labor budget of ₱<?= number_format($estimatedLaborCost, 2) ?>. Click below to release to the barista ESS portal.</p>
                    </div>
                </div>
                <button type="button" onclick="publishApprovedRoster()" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-sm flex items-center gap-2 whitespace-nowrap">
                    <i class="fa-solid fa-upload"></i> Publish to Staff
                </button>
            </div>
            <?php endif; ?>

            <!-- Weekly Calendar Grid Table (Monday to Sunday) -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm border-collapse">
                        <thead class="bg-slate-50 text-slate-600 font-bold text-xs uppercase tracking-wider border-b border-slate-200">
                            <tr>
                                <th class="px-5 py-4 text-left w-64 bg-slate-50 sticky left-0 z-10 border-r border-slate-200">Employee</th>
                                <?php foreach ($weekDays as $day): 
                                    $isToday = ($day['date'] === date('Y-m-d'));
                                ?>
                                    <th class="px-3 py-3.5 text-center min-w-[145px] border-r border-slate-100 <?= $isToday ? 'bg-indigo-50/70 text-indigo-900 font-extrabold' : '' ?>">
                                        <div class="text-[11px]"><?= $day['dayName'] ?></div>
                                        <div class="text-sm font-black mt-0.5"><?= $day['formatted'] ?></div>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($employees)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-12 text-slate-400">
                                        <div class="flex flex-col items-center justify-center">
                                            <i class="fa-solid fa-user-clock text-4xl mb-3 text-slate-300"></i>
                                            <p class="font-medium">No active employees found for this branch.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($employees as $emp): 
                                    $isHB = (stripos($emp['position'] ?? '', 'Head Barista') !== false);
                                ?>
                                    <tr class="hover:bg-slate-50/50 transition">
                                        <!-- Employee Sticky Column -->
                                        <td class="px-4 py-3 bg-white sticky left-0 z-10 border-r border-slate-200 shadow-[1px_0_0_0_#e2e8f0]">
                                            <div class="font-bold text-slate-900 text-sm"><?= e($emp['first_name'] . ' ' . $emp['last_name']) ?></div>
                                            <div class="text-[11px] text-slate-400 font-mono mt-0.5"><?= e($emp['employee_code']) ?></div>
                                            <div class="mt-1 flex items-center gap-1.5">
                                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider <?= $isHB ? 'bg-purple-100 text-purple-700 border border-purple-200' : 'bg-slate-100 text-slate-600' ?>">
                                                    <?= e($emp['position'] ?? 'Barista') ?>
                                                </span>
                                                <span class="text-[10px] text-slate-400 font-mono">
                                                    ₱<?= number_format($employeeRates[$emp['id']] ?? 0, 2) ?>/h
                                                </span>
                                            </div>
                                        </td>

                                        <!-- 7 Calendar Day Columns -->
                                        <?php foreach ($weekDays as $day): 
                                            $dateStr = $day['date'];
                                            $cellShifts = $gridShifts[$emp['id']][$dateStr] ?? [];
                                            $cellLeaves = $gridLeaves[$emp['id']][$dateStr] ?? [];
                                            $isOnLeave = !empty($cellLeaves);
                                            $isToday = ($dateStr === date('Y-m-d'));
                                        ?>
                                            <td class="p-2 align-top border-r border-slate-100 <?= $isToday ? 'bg-indigo-50/20' : ($isOnLeave ? 'bg-amber-50/15' : '') ?> relative group">
                                                <div class="min-h-[64px] flex flex-col justify-center space-y-1.5">
                                                    <?php if ($isOnLeave): ?>
                                                        <?php foreach ($cellLeaves as $lv): ?>
                                                            <div class="p-2 rounded-xl bg-amber-50 border border-amber-300/80 text-amber-900 text-left shadow-xs">
                                                                <div class="flex items-center justify-between">
                                                                    <span class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wider text-amber-700">
                                                                        <i class="fa-solid fa-plane-departure text-[9px]"></i>
                                                                        ON LEAVE
                                                                    </span>
                                                                    <?php if (!empty($lv['is_paid'])): ?>
                                                                        <span class="text-[9px] font-bold text-amber-600 uppercase">Paid</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="text-[10px] font-bold text-amber-950 mt-1 truncate" title="<?= e($lv['type_name'] ?? 'Approved Leave') ?>">
                                                                    <?= e($lv['type_name'] ?? 'Approved Leave') ?>
                                                                </div>
                                                                <?php if (!empty($lv['reason'])): ?>
                                                                    <div class="text-[9px] text-amber-800/80 mt-0.5 truncate italic" title="<?= e($lv['reason']) ?>">
                                                                        "<?= e($lv['reason']) ?>"
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>

                                                    <?php if (!empty($cellShifts)): ?>
                                                        <?php foreach ($cellShifts as $sh): 
                                                            $st = $sh['status'] ?? 'DRAFT';
                                                            $stType = $sh['shift_type'] ?? 'Regular';
                                                            
                                                            // SoD Status Badges
                                                            $statusBadge = 'bg-amber-100 text-amber-800 border-amber-200';
                                                            $statusLabel = 'DRAFT';
                                                            if ($st === 'PENDING_BM') {
                                                                $statusBadge = 'bg-sky-100 text-sky-800 border-sky-200';
                                                                $statusLabel = 'PENDING BM';
                                                            } elseif ($st === 'PENDING_BA') {
                                                                $statusBadge = 'bg-violet-100 text-violet-800 border-violet-200';
                                                                $statusLabel = 'PENDING BA';
                                                            } elseif ($st === 'APPROVED') {
                                                                $statusBadge = 'bg-teal-100 text-teal-800 border-teal-200';
                                                                $statusLabel = 'APPROVED';
                                                            } elseif ($st === 'PUBLISHED') {
                                                                $statusBadge = 'bg-emerald-100 text-emerald-800 border-emerald-200';
                                                                $statusLabel = 'PUBLISHED';
                                                            } elseif ($st === 'REJECTED') {
                                                                $statusBadge = 'bg-rose-100 text-rose-800 border-rose-200';
                                                                $statusLabel = 'REJECTED';
                                                            }

                                                            // Shift type badge style
                                                            $badgeStyle = 'bg-slate-50 text-slate-800 border-slate-200';
                                                            if (stripos($stType, 'Morning') !== false || stripos($stType, 'Opening') !== false) {
                                                                $badgeStyle = 'bg-sky-50 text-sky-800 border-sky-200';
                                                            } elseif (stripos($stType, 'Mid') !== false) {
                                                                $badgeStyle = 'bg-amber-50 text-amber-800 border-amber-200';
                                                            } elseif (stripos($stType, 'Night') !== false || stripos($stType, 'Closing') !== false) {
                                                                $badgeStyle = 'bg-purple-50 text-purple-800 border-purple-200';
                                                            }

                                                            $canEditThis = $canManage && !in_array($st, ['PENDING_BM', 'PENDING_BA', 'APPROVED'], true);
                                                        ?>
                                                            <div <?= $canEditThis ? 'onclick=\'openAssignModal(' . $emp['id'] . ', "' . e(addslashes($emp['first_name'] . ' ' . $emp['last_name'])) . '", "' . $dateStr . '", ' . json_encode($sh) . ')\'' : '' ?>
                                                                 class="border rounded-xl p-2 text-xs <?= $canEditThis ? 'cursor-pointer hover:shadow-sm' : '' ?> transition text-left <?= $badgeStyle ?>"
                                                                 title="<?= !empty($sh['rejection_reason']) ? 'Rejection Reason: ' . e($sh['rejection_reason']) : '' ?>">
                                                                <div class="font-bold flex items-center justify-between text-[11px]">
                                                                    <span><?= e($sh['shift_type']) ?></span>
                                                                    <span class="text-[8px] px-1.5 py-0.5 rounded font-black uppercase tracking-wider border <?= $statusBadge ?>">
                                                                        <?= $statusLabel ?>
                                                                    </span>
                                                                </div>
                                                                <div class="text-[10px] mt-0.5 opacity-90 font-mono">
                                                                    <?= date('H:i', strtotime($sh['start_time'])) ?> - <?= date('H:i', strtotime($sh['end_time'])) ?>
                                                                </div>
                                                                <?php if (!empty($sh['rejection_reason'])): ?>
                                                                    <div class="text-[9px] text-rose-700 font-semibold mt-1 truncate" title="<?= e($sh['rejection_reason']) ?>">
                                                                        <i class="fa-solid fa-triangle-exclamation"></i> <?= e($sh['rejection_reason']) ?>
                                                                    </div>
                                                                <?php elseif (!empty($sh['creator_username'])): ?>
                                                                    <div class="text-[9px] mt-1 opacity-70 italic truncate">
                                                                        By: <?= e($sh['creator_username']) ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php elseif (!$isOnLeave): ?>
                                                        <?php if ($canManage): ?>
                                                            <button type="button" onclick="openAssignModal(<?= $emp['id'] ?>, '<?= e(addslashes($emp['first_name'] . ' ' . $emp['last_name'])) ?>', '<?= $dateStr ?>', null)" class="w-full h-10 border border-dashed border-slate-200 hover:border-indigo-400 rounded-xl text-slate-300 hover:text-indigo-600 flex items-center justify-center text-xs transition">
                                                                <i class="fa-solid fa-plus"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="text-xs text-slate-300 text-center block">Off</span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <?php if ($canManage): ?>
    <!-- Assign / Edit Shift Modal -->
    <div id="assignModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-slate-200">
            <div class="p-4 border-b flex justify-between items-center bg-slate-50">
                <h3 class="font-bold text-slate-800 text-base flex items-center gap-2" id="assignModalTitle">
                    <i class="fa-solid fa-clock text-indigo-600"></i> Assign Shift
                </h3>
                <button type="button" onclick="closeModal('assignModal')" class="text-slate-400 hover:text-rose-600 transition p-1">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
            <form id="assignShiftForm" onsubmit="handleSaveShift(event)" class="p-5 space-y-4">
                <input type="hidden" name="shift_id" id="modalShiftId" value="">
                <input type="hidden" name="employee_id" id="modalEmpId" value="">
                <input type="hidden" name="branch_id" value="<?= $selectedBranchId ?>">
                <input type="hidden" name="shift_date" id="modalShiftDate" value="">

                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 text-xs">
                    <div class="font-bold text-slate-900 text-sm" id="modalEmpNameDisplay">Employee</div>
                    <div class="text-slate-500 mt-0.5" id="modalDateDisplay">Shift Date</div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Shift Type *</label>
                    <select name="shift_type" id="modalShiftType" required class="w-full border border-slate-300 rounded-xl p-2.5 text-xs font-semibold focus:ring-2 focus:ring-indigo-500 bg-white">
                        <option value="Morning">Morning (07:00 - 15:00)</option>
                        <option value="Mid">Mid (11:00 - 19:00)</option>
                        <option value="Night">Night (16:00 - 00:00)</option>
                        <option value="Opening">Opening (06:30 - 14:30)</option>
                        <option value="Closing">Closing (15:30 - 23:30)</option>
                        <option value="Regular">Regular (08:00 - 17:00)</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Start Time *</label>
                        <input type="time" name="start_time" id="modalStartTime" required class="w-full border border-slate-300 rounded-xl p-2.5 text-xs font-mono focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">End Time *</label>
                        <input type="time" name="end_time" id="modalEndTime" required class="w-full border border-slate-300 rounded-xl p-2.5 text-xs font-mono focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div id="modalReasonContainer" class="hidden">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Reason for Edit <span class="text-rose-500">*</span></label>
                    <textarea name="reason" id="modalReason" rows="2" placeholder="Required when modifying published shifts..." class="w-full border border-slate-300 rounded-xl p-2 text-xs focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>

                <div class="pt-3 flex items-center justify-between gap-2">
                    <button type="button" onclick="deleteCurrentShift()" id="modalDeleteBtn" class="hidden text-rose-600 hover:text-rose-800 hover:bg-rose-50 font-bold px-3 py-2 rounded-xl text-xs transition">
                        <i class="fa-solid fa-trash-can mr-1"></i> Delete
                    </button>
                    <div class="flex items-center gap-2 ml-auto">
                        <button type="button" onclick="closeModal('assignModal')" class="px-4 py-2 border border-slate-300 rounded-xl text-slate-600 text-xs font-semibold hover:bg-slate-50 transition">
                            Cancel
                        </button>
                        <button type="submit" id="modalSubmitBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-5 py-2 rounded-xl text-xs shadow-xs transition">
                            Save Shift
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Quick Add Shift Modal -->
    <div id="addShiftModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-slate-200">
            <div class="p-4 border-b flex justify-between items-center bg-slate-50">
                <h3 class="font-bold text-slate-800 text-base flex items-center gap-2">
                    <i class="fa-solid fa-calendar-plus text-indigo-600"></i> Add New Shift
                </h3>
                <button type="button" onclick="closeModal('addShiftModal')" class="text-slate-400 hover:text-rose-600 transition p-1">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
            <form onsubmit="handleQuickAddShift(event)" class="p-5 space-y-4">
                <input type="hidden" name="branch_id" value="<?= $selectedBranchId ?>">

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Select Employee *</label>
                    <select name="employee_id" required class="w-full border border-slate-300 rounded-xl p-2.5 text-xs font-semibold focus:ring-2 focus:ring-indigo-500 bg-white">
                        <option value="">-- Choose Employee --</option>
                        <?php foreach ($employees as $e): ?>
                            <option value="<?= $e['id'] ?>"><?= e($e['first_name'] . ' ' . $e['last_name']) ?> (<?= e($e['position'] ?? 'Staff') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Shift Date *</label>
                    <input type="date" name="shift_date" required value="<?= $dateFrom ?>" min="<?= $dateFrom ?>" max="<?= $dateTo ?>" class="w-full border border-slate-300 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Shift Type *</label>
                    <select name="shift_type" id="quickShiftType" required class="w-full border border-slate-300 rounded-xl p-2.5 text-xs font-semibold focus:ring-2 focus:ring-indigo-500 bg-white">
                        <option value="Morning">Morning (07:00 - 15:00)</option>
                        <option value="Mid">Mid (11:00 - 19:00)</option>
                        <option value="Night">Night (16:00 - 00:00)</option>
                        <option value="Opening">Opening (06:30 - 14:30)</option>
                        <option value="Closing">Closing (15:30 - 23:30)</option>
                        <option value="Regular" selected>Regular (08:00 - 17:00)</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Start Time *</label>
                        <input type="time" name="start_time" id="quickStartTime" required value="08:00" class="w-full border border-slate-300 rounded-xl p-2.5 text-xs font-mono focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">End Time *</label>
                        <input type="time" name="end_time" id="quickEndTime" required value="17:00" class="w-full border border-slate-300 rounded-xl p-2.5 text-xs font-mono focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="pt-3 flex justify-end gap-2">
                    <button type="button" onclick="closeModal('addShiftModal')" class="px-4 py-2 border border-slate-300 rounded-xl text-slate-600 text-xs font-semibold hover:bg-slate-50 transition">
                        Cancel
                    </button>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-5 py-2 rounded-xl text-xs shadow-xs transition">
                        Create Shift
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Rejection Modal (for BM / BA) -->
    <div id="rejectModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-slate-200">
            <div class="p-4 border-b flex justify-between items-center bg-slate-50">
                <h3 class="font-bold text-slate-800 text-base flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-rose-600"></i> Reject Schedule
                </h3>
                <button type="button" onclick="closeModal('rejectModal')" class="text-slate-400 hover:text-rose-600 transition p-1">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
            <form onsubmit="handleRejectSchedule(event)" class="p-5 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Feedback / Reason for Rejection <span class="text-rose-500">*</span></label>
                    <textarea id="rejectReason" required rows="3" placeholder="Explain what needs adjustment (e.g., Tuesday needs +1 barista, or overtime exceeds budget)..." class="w-full border border-slate-300 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-rose-500"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeModal('rejectModal')" class="px-3.5 py-2 rounded-xl text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 transition">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function closeModal(id) {
        const el = document.getElementById(id);
        if (el) el.classList.add('hidden');
    }

    function openAddShiftModal() {
        document.getElementById('addShiftModal').classList.remove('hidden');
    }

    const shiftHours = {
        'Morning': { start: '07:00', end: '15:00' },
        'Mid': { start: '11:00', end: '19:00' },
        'Night': { start: '16:00', end: '00:00' },
        'Opening': { start: '06:30', end: '14:30' },
        'Closing': { start: '15:30', end: '23:30' },
        'Regular': { start: '08:00', end: '17:00' }
    };

    const modalShiftTypeEl = document.getElementById('modalShiftType');
    if (modalShiftTypeEl) {
        modalShiftTypeEl.addEventListener('change', function() {
            const h = shiftHours[this.value];
            if (h) {
                document.getElementById('modalStartTime').value = h.start;
                document.getElementById('modalEndTime').value = h.end;
            }
        });
    }

    const quickShiftTypeEl = document.getElementById('quickShiftType');
    if (quickShiftTypeEl) {
        quickShiftTypeEl.addEventListener('change', function() {
            const h = shiftHours[this.value];
            if (h) {
                document.getElementById('quickStartTime').value = h.start;
                document.getElementById('quickEndTime').value = h.end;
            }
        });
    }

    function openAssignModal(empId, empName, dateStr, shift) {
        document.getElementById('modalEmpId').value = empId;
        document.getElementById('modalShiftDate').value = dateStr;
        document.getElementById('modalEmpNameDisplay').innerText = empName;
        
        const d = new Date(dateStr + "T12:00:00");
        document.getElementById('modalDateDisplay').innerText = d.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' });

        const deleteBtn = document.getElementById('modalDeleteBtn');
        const reasonBox = document.getElementById('modalReasonContainer');
        const title = document.getElementById('assignModalTitle');

        if (shift) {
            document.getElementById('modalShiftId').value = shift.id;
            title.innerHTML = '<i class="fa-solid fa-pen-to-square text-indigo-600"></i> Edit Shift';
            document.getElementById('modalShiftType').value = shift.shift_type || 'Regular';
            document.getElementById('modalStartTime').value = (shift.start_time || '').substring(0, 5);
            document.getElementById('modalEndTime').value = (shift.end_time || '').substring(0, 5);
            deleteBtn.classList.remove('hidden');
            if (shift.status === 'PUBLISHED') {
                reasonBox.classList.remove('hidden');
            } else {
                reasonBox.classList.add('hidden');
            }
        } else {
            document.getElementById('modalShiftId').value = '';
            title.innerHTML = '<i class="fa-solid fa-clock text-indigo-600"></i> Assign Shift';
            document.getElementById('modalShiftType').value = 'Morning';
            document.getElementById('modalStartTime').value = '07:00';
            document.getElementById('modalEndTime').value = '15:00';
            deleteBtn.classList.add('hidden');
            reasonBox.classList.add('hidden');
        }

        document.getElementById('assignModal').classList.remove('hidden');
    }

    async function handleSaveShift(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        data.csrf_token = csrfToken;
        data.action = data.shift_id ? 'edit' : 'create';

        try {
            const res = await fetch('api/fika_hrms_shift_save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify(data)
            });
            const json = await res.json();
            if (res.ok && json.success) {
                window.location.reload();
            } else {
                alert(json.error || 'Failed to save shift.');
            }
        } catch (err) {
            alert('Network error: ' + err.message);
        }
    }

    async function handleQuickAddShift(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        data.action = 'create';
        data.csrf_token = csrfToken;

        try {
            const res = await fetch('api/fika_hrms_shift_save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify(data)
            });
            const json = await res.json();
            if (res.ok && json.success) {
                window.location.reload();
            } else {
                alert(json.error || 'Failed to create shift.');
            }
        } catch (err) {
            alert('Network error: ' + err.message);
        }
    }

    async function deleteCurrentShift() {
        const shiftId = document.getElementById('modalShiftId').value;
        if (!shiftId) return;
        if (!confirm('Are you sure you want to remove this shift?')) return;

        try {
            const res = await fetch('api/fika_hrms_shift_save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ action: 'delete', shift_id: shiftId, csrf_token: csrfToken })
            });
            const json = await res.json();
            if (res.ok && json.success) {
                window.location.reload();
            } else {
                alert(json.error || 'Failed to delete shift.');
            }
        } catch (err) {
            alert('Network error: ' + err.message);
        }
    }

    // SoD Chain Step 1: BHR submits draft schedule to BM
    async function submitToBm() {
        if (!confirm('Are you sure you want to submit this week\'s schedule to the Branch Manager for manpower review?')) return;
        try {
            const res = await fetch('api/fika_hrms_shift_save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({
                    action: 'submit_to_bm',
                    branch_id: <?= $selectedBranchId ?>,
                    date_from: '<?= $dateFrom ?>',
                    date_to: '<?= $dateTo ?>',
                    csrf_token: csrfToken
                })
            });
            const json = await res.json();
            if (res.ok && json.success) {
                alert(json.message);
                window.location.reload();
            } else {
                alert(json.error || 'Failed to submit schedule to BM.');
            }
        } catch (err) {
            alert('Network error: ' + err.message);
        }
    }

    // SoD Chain Step 2: BM approves manpower and routes to BA
    async function approveManpower() {
        if (!confirm('Approve manpower staffing for this week and route to Finance (Branch Accountant) for labor budget authorization?')) return;
        try {
            const res = await fetch('api/fika_hrms_shift_save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({
                    action: 'approve_manpower',
                    branch_id: <?= $selectedBranchId ?>,
                    date_from: '<?= $dateFrom ?>',
                    date_to: '<?= $dateTo ?>',
                    csrf_token: csrfToken
                })
            });
            const json = await res.json();
            if (res.ok && json.success) {
                alert(json.message);
                window.location.reload();
            } else {
                alert(json.error || 'Failed to approve manpower.');
            }
        } catch (err) {
            alert('Network error: ' + err.message);
        }
    }

    // SoD Chain Step 3: BA approves labor budget and routes to BM
    async function approveBudget() {
        if (!confirm('Approve labor budget of ₱<?= number_format($estimatedLaborCost, 2) ?> and send to Branch Manager for final publishing?')) return;
        try {
            const res = await fetch('api/fika_hrms_shift_save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({
                    action: 'approve_budget',
                    branch_id: <?= $selectedBranchId ?>,
                    date_from: '<?= $dateFrom ?>',
                    date_to: '<?= $dateTo ?>',
                    csrf_token: csrfToken
                })
            });
            const json = await res.json();
            if (res.ok && json.success) {
                alert(json.message);
                window.location.reload();
            } else {
                alert(json.error || 'Failed to approve budget.');
            }
        } catch (err) {
            alert('Network error: ' + err.message);
        }
    }

    // SoD Chain Step 4: BM publishes approved schedule to staff
    async function publishApprovedRoster() {
        if (!confirm('Are you sure you want to publish this approved roster to staff? Shifts will become immediately visible in the Barista ESS portal.')) return;
        try {
            const res = await fetch('api/fika_hrms_shift_save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({
                    action: 'publish',
                    branch_id: <?= $selectedBranchId ?>,
                    date_from: '<?= $dateFrom ?>',
                    date_to: '<?= $dateTo ?>',
                    csrf_token: csrfToken
                })
            });
            const json = await res.json();
            if (res.ok && json.success) {
                alert(json.message);
                window.location.reload();
            } else {
                alert(json.error || 'Failed to publish roster.');
            }
        } catch (err) {
            alert('Network error: ' + err.message);
        }
    }

    function openRejectModal(type) {
        document.getElementById('rejectReason').value = '';
        document.getElementById('rejectModal').classList.remove('hidden');
    }

    async function handleRejectSchedule(e) {
        e.preventDefault();
        const reason = document.getElementById('rejectReason').value.trim();
        if (!reason) {
            alert('Please enter a rejection reason.');
            return;
        }
        try {
            const res = await fetch('api/fika_hrms_shift_save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({
                    action: 'reject',
                    branch_id: <?= $selectedBranchId ?>,
                    date_from: '<?= $dateFrom ?>',
                    date_to: '<?= $dateTo ?>',
                    reason: reason,
                    csrf_token: csrfToken
                })
            });
            const json = await res.json();
            if (res.ok && json.success) {
                alert(json.message);
                window.location.reload();
            } else {
                alert(json.error || 'Failed to reject schedule.');
            }
        } catch (err) {
            alert('Network error: ' + err.message);
        }
    }
    </script>
</body>
</html>
