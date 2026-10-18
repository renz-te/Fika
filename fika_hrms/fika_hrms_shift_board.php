<?php
require __DIR__ . '/../app/bootstrap.php';
class_exists('Output');

Auth::requireLogin();
Rbac::require_permission('schedule.view');

global $pdo;

$user = Auth::user();
$canManage = Rbac::can('schedule.manage');
$userBranchId = $user['branch_id'] ?? null;

// Branch Scoping
$branches = [];
if ($userBranchId === null) {
    $branches = $pdo->query("SELECT id, name FROM branches WHERE status = 'Active' ORDER BY name ASC")->fetchAll();
    $selectedBranchId = (int)($_GET['branch_id'] ?? ($branches[0]['id'] ?? 1));
} else {
    $selectedBranchId = (int)$userBranchId;
    $bStmt = $pdo->prepare("SELECT id, name FROM branches WHERE id = ?");
    $bStmt->execute([$selectedBranchId]);
    $branches = $bStmt->fetchAll();
}

// Week Navigation (Monday to Sunday)
$weekInput = $_GET['week'] ?? date('Y-\WW'); // e.g. 2026-W42
$dto = new DateTime();
$parts = explode('-W', $weekInput);
$year = (int)($parts[0] ?? date('Y'));
$weekNum = (int)($parts[1] ?? date('W'));
$dto->setISODate($year, $weekNum);

$monday = clone $dto;
$sunday = (clone $dto)->modify('+6 days');

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

// Fetch branch employees
$empStmt = $pdo->prepare("SELECT id, first_name, last_name, employee_code, position FROM employees WHERE branch_id = ? AND deleted_at IS NULL ORDER BY first_name ASC");
$empStmt->execute([$selectedBranchId]);
$employees = $empStmt->fetchAll();

// Fetch shifts for this week and branch
$shiftStmt = $pdo->prepare("
    SELECT s.*, e.first_name, e.last_name, e.employee_code,
           os.id as open_shift_id, os.status as open_status, os.claimed_by,
           c.first_name as claimer_first, c.last_name as claimer_last
    FROM shifts s
    JOIN employees e ON s.employee_id = e.id
    LEFT JOIN open_shifts os ON os.shift_id = s.id AND os.status IN ('OPEN', 'PENDING_APPROVAL')
    LEFT JOIN employees c ON os.claimed_by = c.id
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

// Fetch pending claim approvals
$pendingClaims = [];
if ($canManage) {
    $claimStmt = $pdo->prepare("
        SELECT os.*, s.shift_date, s.start_time, s.end_time, s.shift_type,
               orig.first_name as orig_first, orig.last_name as orig_last, orig.employee_code as orig_code,
               cl.first_name as cl_first, cl.last_name as cl_last, cl.employee_code as cl_code
        FROM open_shifts os
        JOIN shifts s ON os.shift_id = s.id
        JOIN employees orig ON os.original_employee_id = orig.id
        JOIN employees cl ON os.claimed_by = cl.id
        WHERE os.branch_id = ? AND os.status = 'PENDING_APPROVAL'
        ORDER BY os.created_at ASC
    ");
    $claimStmt->execute([$selectedBranchId]);
    $pendingClaims = $claimStmt->fetchAll();
}

$csrfToken = Csrf::getToken();

// Calculate prev/next weeks
$prevWeekObj = (clone $monday)->modify('-1 week');
$nextWeekObj = (clone $monday)->modify('+1 week');
$prevWeek = $prevWeekObj->format('Y-\WW');
$nextWeek = $nextWeekObj->format('Y-\WW');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shift Board - Fika HRMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">

    <!-- Top Navigation -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-black text-xl shadow-sm">
                    <i class="fa-solid fa-calendar-days text-base"></i>
                </div>
                <div>
                    <h1 class="font-bold text-lg text-slate-900 leading-tight">Branch Shift Board</h1>
                    <p class="text-xs text-slate-500">Weekly scheduling, shift drafting, and peer swap approvals</p>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <a href="fika_hrms_dashboard.php" class="text-sm font-medium text-slate-600 hover:text-indigo-600 transition flex items-center gap-1.5">
                    <i class="fa-solid fa-arrow-left text-xs"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Action & Filter Bar -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 mb-6 flex flex-wrap justify-between items-center gap-4">
            <div class="flex items-center gap-3">
                <!-- Branch Selector -->
                <?php if ($userBranchId === null && count($branches) > 1): ?>
                    <form method="GET" class="inline-block">
                        <input type="hidden" name="week" value="<?= e($weekInput) ?>">
                        <select name="branch_id" onchange="this.form.submit()" class="border border-slate-300 rounded-xl px-3 py-2 text-sm font-medium focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= $selectedBranchId == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 text-xs font-bold border border-slate-200">
                        <i class="fa-solid fa-location-dot text-indigo-500"></i> <?= e($branches[0]['name'] ?? 'Branch') ?>
                    </span>
                <?php endif; ?>

                <!-- Week Navigator -->
                <div class="flex items-center bg-slate-100 rounded-xl p-1 border border-slate-200">
                    <a href="?week=<?= $prevWeek ?>&branch_id=<?= $selectedBranchId ?>" class="px-2.5 py-1 text-slate-600 hover:text-slate-900 rounded-lg hover:bg-white text-xs font-bold transition">
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>
                    <span class="px-3 text-xs font-bold text-slate-800">
                        <?= $monday->format('M d') ?> &mdash; <?= $sunday->format('M d, Y') ?> (Week <?= $weekNum ?>)
                    </span>
                    <a href="?week=<?= $nextWeek ?>&branch_id=<?= $selectedBranchId ?>" class="px-2.5 py-1 text-slate-600 hover:text-slate-900 rounded-lg hover:bg-white text-xs font-bold transition">
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </div>
            </div>

            <!-- Management Buttons -->
            <div class="flex items-center gap-3">
                <?php if ($canManage): ?>
                    <?php if ($draftCount > 0): ?>
                        <button onclick="publishWeekDrafts()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-2 rounded-xl text-xs shadow-sm transition flex items-center gap-1.5">
                            <i class="fa-solid fa-bullhorn"></i> Publish All Drafts (<?= $draftCount ?>)
                        </button>
                    <?php endif; ?>
                    <button onclick="openCreateShiftModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2 rounded-xl text-xs shadow-sm transition flex items-center gap-1.5">
                        <i class="fa-solid fa-plus"></i> Add Shift
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pending Claim Approvals Alert Box (Maker-Checker) -->
        <?php if ($canManage && count($pendingClaims) > 0): ?>
            <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 mb-6 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold text-amber-900 flex items-center gap-2">
                        <i class="fa-solid fa-bell text-amber-600"></i> Pending Shift Claim Requests (<?= count($pendingClaims) ?>)
                    </h3>
                    <span class="text-xs text-amber-700 font-medium">Requires Manager Approval (Maker-Checker)</span>
                </div>
                <div class="space-y-2">
                    <?php foreach ($pendingClaims as $claim): ?>
                        <div class="bg-white p-3.5 rounded-xl border border-amber-100 flex flex-wrap justify-between items-center gap-3">
                            <div class="text-xs">
                                <span class="font-bold text-slate-800"><?= date('D, M d', strtotime($claim['shift_date'])) ?> (<?= date('h:i A', strtotime($claim['start_time'])) ?> - <?= date('h:i A', strtotime($claim['end_time'])) ?>)</span>:
                                <span class="text-slate-500">Dropped by <strong class="text-slate-700"><?= e($claim['orig_first'] . ' ' . $claim['orig_last']) ?></strong></span>
                                &rarr; Claimed by <strong class="text-indigo-600"><?= e($claim['cl_first'] . ' ' . $claim['cl_last']) ?></strong>
                                <?php if ($claim['drop_reason']): ?>
                                    <span class="italic text-slate-400">("<?= e($claim['drop_reason']) ?>")</span>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center gap-2">
                                <button onclick="decideClaim(<?= $claim['id'] ?>, 'APPROVE')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1 rounded-lg text-xs font-bold transition">
                                    <i class="fa-solid fa-check mr-1"></i> Approve Transfer
                                </button>
                                <button onclick="decideClaim(<?= $claim['id'] ?>, 'REJECT')" class="bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 px-3 py-1 rounded-lg text-xs font-bold transition">
                                    <i class="fa-solid fa-xmark mr-1"></i> Reject
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Weekly Calendar Grid Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-slate-600 font-semibold text-xs">
                        <tr>
                            <th class="px-4 py-3.5 text-left w-56 bg-slate-50 sticky left-0 z-10 border-r border-slate-200">Employee</th>
                            <?php foreach ($weekDays as $day): ?>
                                <th class="px-3 py-3.5 text-center min-w-[140px] <?= $day['date'] === date('Y-m-d') ? 'bg-indigo-50/70 text-indigo-900 font-bold' : '' ?>">
                                    <div class="uppercase tracking-wider text-[11px]"><?= $day['dayName'] ?></div>
                                    <div class="text-sm font-bold mt-0.5"><?= $day['formatted'] ?></div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($employees)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-12 text-slate-400">No active employees assigned to this branch.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($employees as $emp): ?>
                                <tr class="hover:bg-slate-50/50 transition">
                                    <!-- Employee Header Cell -->
                                    <td class="px-4 py-3 font-medium text-slate-900 bg-white sticky left-0 z-10 border-r border-slate-100">
                                        <div class="font-bold text-slate-800"><?= e($emp['first_name'] . ' ' . $emp['last_name']) ?></div>
                                        <div class="text-[11px] text-slate-400 font-mono"><?= e($emp['employee_code']) ?> &bull; <?= e($emp['position'] ?? 'Staff') ?></div>
                                    </td>

                                    <!-- 7 Days Grid Cells -->
                                    <?php foreach ($weekDays as $day): 
                                        $d = $day['date'];
                                        $cellShifts = $gridShifts[$emp['id']][$d] ?? [];
                                    ?>
                                        <td class="px-2 py-2 text-center align-top border-r border-slate-50 <?= $d === date('Y-m-d') ? 'bg-indigo-50/20' : '' ?>">
                                            <div class="space-y-1.5 min-h-[56px] flex flex-col justify-center">
                                                <?php if (!empty($cellShifts)): ?>
                                                    <?php foreach ($cellShifts as $sh): 
                                                        $isDraft = ($sh['status'] === 'DRAFT');
                                                        $hasOpen = !empty($sh['open_status']);
                                                    ?>
                                                        <div class="p-2 rounded-xl text-xs font-medium border text-left transition shadow-xs relative group
                                                            <?= $isDraft ? 'bg-amber-50/80 border-amber-200 text-amber-900' : 
                                                               ($hasOpen ? 'bg-purple-50 border-purple-200 text-purple-900' : 'bg-slate-50 border-slate-200 text-slate-800') ?>">
                                                            
                                                            <div class="flex justify-between items-center mb-1">
                                                                <span class="font-bold text-[11px]">
                                                                    <?= date('H:i', strtotime($sh['start_time'])) ?> - <?= date('H:i', strtotime($sh['end_time'])) ?>
                                                                </span>
                                                                <span class="text-[9px] font-bold px-1.5 py-0.2 rounded-md uppercase tracking-wider
                                                                    <?= $isDraft ? 'bg-amber-200/60 text-amber-800' : 'bg-emerald-100 text-emerald-800' ?>">
                                                                    <?= e($sh['status']) ?>
                                                                </span>
                                                            </div>

                                                            <div class="text-[10px] text-slate-500 truncate">
                                                                <?= e($sh['shift_type']) ?>
                                                                <?php if ($hasOpen): ?>
                                                                    <span class="text-purple-600 font-bold ml-1">
                                                                        [<?= $sh['open_status'] === 'PENDING_APPROVAL' ? 'Claim Pending' : 'Dropped' ?>]
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>

                                                            <?php if ($canManage): ?>
                                                                <div class="mt-1.5 pt-1.5 border-t border-slate-200/60 flex justify-end gap-1">
                                                                    <button onclick='openEditShiftModal(<?= json_encode($sh) ?>)' class="text-[10px] text-indigo-600 hover:text-indigo-800 font-bold px-1.5 py-0.5 rounded hover:bg-white">
                                                                        Edit
                                                                    </button>
                                                                    <button onclick="deleteShift(<?= $sh['id'] ?>)" class="text-[10px] text-rose-600 hover:text-rose-800 font-bold px-1.5 py-0.5 rounded hover:bg-white">
                                                                        Del
                                                                    </button>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <?php if ($canManage): ?>
                                                        <button onclick="quickAddShift(<?= $emp['id'] ?>, '<?= $d ?>')" class="w-full h-10 border border-dashed border-slate-200 hover:border-indigo-400 rounded-xl text-slate-300 hover:text-indigo-600 flex items-center justify-center text-xs transition">
                                                            <i class="fa-solid fa-plus"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-[11px] text-slate-300">Off</span>
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

    <!-- CREATE SHIFT MODAL -->
    <div id="createModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 max-w-md w-full overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex justify-between items-center">
                <h3 class="font-bold text-slate-900 text-base">Assign Shift Schedule</h3>
                <button onclick="closeModal('createModal')" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form onsubmit="handleCreateShift(event)" class="p-5 space-y-4">
                <input type="hidden" name="branch_id" value="<?= $selectedBranchId ?>">
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Employee</label>
                    <select name="employee_id" id="createEmpId" required class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <?php foreach ($employees as $e): ?>
                            <option value="<?= $e['id'] ?>"><?= e($e['first_name'] . ' ' . $e['last_name']) ?> (<?= e($e['employee_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Shift Date</label>
                    <input type="date" name="shift_date" id="createDate" required class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Start Time</label>
                        <input type="time" name="start_time" value="07:00" required class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1">End Time</label>
                        <input type="time" name="end_time" value="15:00" required class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Shift Type</label>
                        <select name="shift_type" class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <option value="Opening">Opening</option>
                            <option value="Mid">Mid</option>
                            <option value="Closing">Closing</option>
                            <option value="Regular" selected>Regular</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Status</label>
                        <select name="status" class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <option value="DRAFT" selected>Draft</option>
                            <option value="PUBLISHED">Published</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3">
                    <button type="button" onclick="closeModal('createModal')" class="px-4 py-2 text-slate-600 text-sm font-medium">Cancel</button>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2 rounded-xl text-sm transition">Save Shift</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT SHIFT MODAL -->
    <div id="editModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 max-w-md w-full overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex justify-between items-center">
                <h3 class="font-bold text-slate-900 text-base">Edit Shift Schedule</h3>
                <button onclick="closeModal('editModal')" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form onsubmit="handleEditShift(event)" class="p-5 space-y-4">
                <input type="hidden" name="shift_id" id="editShiftId">
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Employee</label>
                    <select name="employee_id" id="editEmpId" required class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <?php foreach ($employees as $e): ?>
                            <option value="<?= $e['id'] ?>"><?= e($e['first_name'] . ' ' . $e['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Shift Date</label>
                    <input type="date" name="shift_date" id="editDate" required class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Start Time</label>
                        <input type="time" name="start_time" id="editStart" required class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1">End Time</label>
                        <input type="time" name="end_time" id="editEnd" required class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Shift Type</label>
                        <select name="shift_type" id="editType" class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <option value="Opening">Opening</option>
                            <option value="Mid">Mid</option>
                            <option value="Closing">Closing</option>
                            <option value="Regular">Regular</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Status</label>
                        <select name="status" id="editStatus" class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <option value="DRAFT">Draft</option>
                            <option value="PUBLISHED">Published</option>
                        </select>
                    </div>
                </div>

                <div id="editReasonBox">
                    <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Reason for Edit <span class="text-rose-500">*</span></label>
                    <textarea name="reason" id="editReason" rows="2" placeholder="Mandatory when editing published shifts..." class="w-full border border-slate-300 rounded-xl p-2 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none"></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-3">
                    <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 text-slate-600 text-sm font-medium">Cancel</button>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2 rounded-xl text-sm transition">Update Shift</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
        }

        function openCreateShiftModal() {
            document.getElementById('createModal').classList.remove('hidden');
        }

        function quickAddShift(empId, date) {
            document.getElementById('createEmpId').value = empId;
            document.getElementById('createDate').value = date;
            openCreateShiftModal();
        }

        function openEditShiftModal(sh) {
            document.getElementById('editShiftId').value = sh.id;
            document.getElementById('editEmpId').value = sh.employee_id;
            document.getElementById('editDate').value = sh.shift_date;
            document.getElementById('editStart').value = sh.start_time.substring(0, 5);
            document.getElementById('editEnd').value = sh.end_time.substring(0, 5);
            document.getElementById('editType').value = sh.shift_type;
            document.getElementById('editStatus').value = sh.status;
            document.getElementById('editReason').value = '';
            document.getElementById('editModal').classList.remove('hidden');
        }

        async function handleCreateShift(e) {
            e.preventDefault();
            const form = e.target;
            const data = Object.fromEntries(new FormData(form).entries());
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
                    location.reload();
                } else {
                    alert(json.error || 'Failed to create shift.');
                }
            } catch (err) {
                alert('Network error: ' + err.message);
            }
        }

        async function handleEditShift(e) {
            e.preventDefault();
            const form = e.target;
            const data = Object.fromEntries(new FormData(form).entries());
            data.action = 'edit';
            data.csrf_token = csrfToken;

            try {
                const res = await fetch('api/fika_hrms_shift_save.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify(data)
                });
                const json = await res.json();
                if (res.ok && json.success) {
                    location.reload();
                } else {
                    alert(json.error || 'Failed to update shift.');
                }
            } catch (err) {
                alert('Network error: ' + err.message);
            }
        }

        async function deleteShift(shiftId) {
            if (!confirm('Are you sure you want to delete this shift?')) return;
            try {
                const res = await fetch('api/fika_hrms_shift_save.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ action: 'delete', shift_id: shiftId, csrf_token: csrfToken })
                });
                const json = await res.json();
                if (res.ok && json.success) {
                    location.reload();
                } else {
                    alert(json.error || 'Failed to delete shift.');
                }
            } catch (err) {
                alert('Network error: ' + err.message);
            }
        }

        async function publishWeekDrafts() {
            if (!confirm('Are you sure you want to publish all draft shifts for this week?')) return;
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
                    location.reload();
                } else {
                    alert(json.error || 'Failed to publish drafts.');
                }
            } catch (err) {
                alert('Network error: ' + err.message);
            }
        }

        async function decideClaim(openShiftId, decision) {
            const promptMsg = decision === 'APPROVE' ? 'Approve shift transfer to claimer?' : 'Reject shift claim?';
            if (!confirm(promptMsg)) return;

            try {
                const res = await fetch('api/fika_hrms_shift_claim.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({
                        action: 'decide',
                        open_shift_id: openShiftId,
                        decision: decision,
                        csrf_token: csrfToken
                    })
                });
                const json = await res.json();
                if (res.ok && json.success) {
                    location.reload();
                } else {
                    alert(json.error || 'Action failed.');
                }
            } catch (err) {
                alert('Network error: ' + err.message);
            }
        }
    </script>
</body>
</html>
