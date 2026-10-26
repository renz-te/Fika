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
$isAdminOrChr = in_array($userRole, [1, 2], true) || $userBranchId === null;

// Branch Scoping
$branches = [];
if ($isAdminOrChr) {
    // List all active branches for Admin/CHR
    $branches = $pdo->query("SELECT id, name, address FROM branches WHERE status = 'Active' AND deleted_at IS NULL ORDER BY name ASC, id ASC")->fetchAll();
    
    if (isset($_GET['branch_id']) && is_numeric($_GET['branch_id'])) {
        $selectedBranchId = (int)$_GET['branch_id'];
    } else {
        // Default to the first active branch with active staff, or first active branch in DB
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

// Index shifts by employee_id and date
$gridShifts = [];
$draftCount = 0;
foreach ($rawShifts as $s) {
    $gridShifts[$s['employee_id']][$s['shift_date']][] = $s;
    if ($s['status'] === 'DRAFT') {
        $draftCount++;
    }
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
        <!-- Top Nav Header -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-black text-xl shadow-sm">
                        <i class="fa-solid fa-calendar-days text-base"></i>
                    </div>
                    <div>
                        <h1 class="font-bold text-lg text-slate-900 leading-tight">Roster &amp; Scheduler</h1>
                        <p class="text-xs text-slate-500">Weekly scheduling, shift assignment, and roster management</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <?php if ($canManage && $draftCount > 0): ?>
                        <button type="button" onclick="publishWeekDrafts()" class="bg-amber-500 hover:bg-amber-600 text-white font-bold py-2 px-4 rounded-xl text-xs transition shadow-sm inline-flex items-center gap-1.5">
                            <i class="fa-solid fa-upload"></i> Publish <?= $draftCount ?> Draft(s)
                        </button>
                    <?php endif; ?>
                    <?php if ($canManage): ?>
                        <button type="button" onclick="openAddShiftModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-xl text-xs transition shadow-sm inline-flex items-center gap-1.5">
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
                                    <th class="px-3 py-3.5 text-center min-w-[140px] border-r border-slate-100 <?= $isToday ? 'bg-indigo-50/70 text-indigo-900 font-extrabold' : '' ?>">
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
                                            <div class="mt-1">
                                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider <?= $isHB ? 'bg-purple-100 text-purple-700 border border-purple-200' : 'bg-slate-100 text-slate-600' ?>">
                                                    <?= e($emp['position'] ?? 'Barista') ?>
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
                                                            $isDraft = ($sh['status'] === 'DRAFT');
                                                            $stType = $sh['shift_type'] ?? 'Regular';
                                                            
                                                            // Badge color styling
                                                            $badgeStyle = 'bg-blue-50 text-blue-800 border-blue-200';
                                                            if (stripos($stType, 'Morning') !== false || stripos($stType, 'Opening') !== false) {
                                                                $badgeStyle = 'bg-sky-50 text-sky-800 border-sky-200';
                                                            } elseif (stripos($stType, 'Mid') !== false) {
                                                                $badgeStyle = 'bg-amber-50 text-amber-800 border-amber-200';
                                                            } elseif (stripos($stType, 'Night') !== false || stripos($stType, 'Closing') !== false) {
                                                                $badgeStyle = 'bg-purple-50 text-purple-800 border-purple-200';
                                                            }
                                                        ?>
                                                            <div <?= $canManage ? 'onclick=\'openAssignModal(' . $emp['id'] . ', "' . e(addslashes($emp['first_name'] . ' ' . $emp['last_name'])) . '", "' . $dateStr . '", ' . json_encode($sh) . ')\'' : '' ?>
                                                                 class="border rounded-xl p-2 text-xs <?= $canManage ? 'cursor-pointer hover:shadow-sm' : '' ?> transition text-left <?= $badgeStyle ?>">
                                                                <div class="font-bold flex items-center justify-between text-[11px]">
                                                                    <span><?= e($sh['shift_type']) ?></span>
                                                                    <span class="text-[9px] px-1 py-0.2 rounded font-black uppercase <?= $isDraft ? 'bg-amber-200 text-amber-900' : 'bg-emerald-100 text-emerald-800' ?>">
                                                                        <?= e($sh['status']) ?>
                                                                    </span>
                                                                </div>
                                                                <div class="text-[10px] mt-0.5 opacity-90 font-mono">
                                                                    <?= date('H:i', strtotime($sh['start_time'])) ?> - <?= date('H:i', strtotime($sh['end_time'])) ?>
                                                                </div>
                                                                <?php if (!empty($sh['creator_username'])): ?>
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

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Status</label>
                    <select name="status" id="modalStatus" class="w-full border border-slate-300 rounded-xl p-2.5 text-xs font-semibold focus:ring-2 focus:ring-indigo-500 bg-white">
                        <option value="DRAFT">Draft</option>
                        <option value="PUBLISHED">Published</option>
                    </select>
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
                        <input type="time" name="start_time" id="quickStartTime" value="08:00" required class="w-full border border-slate-300 rounded-xl p-2.5 text-xs font-mono focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">End Time *</label>
                        <input type="time" name="end_time" id="quickEndTime" value="17:00" required class="w-full border border-slate-300 rounded-xl p-2.5 text-xs font-mono focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Status</label>
                    <select name="status" class="w-full border border-slate-300 rounded-xl p-2.5 text-xs font-semibold focus:ring-2 focus:ring-indigo-500 bg-white">
                        <option value="DRAFT">Draft</option>
                        <option value="PUBLISHED">Published</option>
                    </select>
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

    <script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
    }

    function openAddShiftModal() {
        document.getElementById('addShiftModal').classList.remove('hidden');
    }

    // Auto-fill hours helper
    const shiftHours = {
        'Morning': { start: '07:00', end: '15:00' },
        'Mid':     { start: '11:00', end: '19:00' },
        'Night':   { start: '16:00', end: '00:00' },
        'Opening': { start: '06:30', end: '14:30' },
        'Closing': { start: '15:30', end: '23:30' },
        'Regular': { start: '08:00', end: '17:00' }
    };

    document.getElementById('modalShiftType').addEventListener('change', function() {
        const h = shiftHours[this.value];
        if (h) {
            document.getElementById('modalStartTime').value = h.start;
            document.getElementById('modalEndTime').value = h.end;
        }
    });

    document.getElementById('quickShiftType').addEventListener('change', function() {
        const h = shiftHours[this.value];
        if (h) {
            document.getElementById('quickStartTime').value = h.start;
            document.getElementById('quickEndTime').value = h.end;
        }
    });

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
            document.getElementById('modalStatus').value = shift.status || 'DRAFT';
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
            document.getElementById('modalStatus').value = 'DRAFT';
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

    async function publishWeekDrafts() {
        if (!confirm('Are you sure you want to publish all <?= $draftCount ?> draft shifts for this week?')) return;
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
                window.location.reload();
            } else {
                alert(json.error || 'Failed to publish drafts.');
            }
        } catch (err) {
            alert('Network error: ' + err.message);
        }
    }
    </script>
</body>
</html>
