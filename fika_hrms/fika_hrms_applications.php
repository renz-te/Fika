<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/core/Output.php';
require_once __DIR__ . '/../app/core/Audit.php';

Auth::requireLogin();
Rbac::require_permission('hr.employee.view');

global $pdo;

$currentUser = Auth::user();
$currentRoleId = (int)($currentUser['role_id'] ?? 0);
$userBranchId = $currentUser['branch_id'] ?? null;

$tab = $_GET['tab'] ?? 'kanban';
if (!in_array($tab, ['kanban', 'directory', 'calendar'], true)) {
    $tab = 'kanban';
}

// Branch Filter Scoping
$branches = $pdo->query("SELECT id, name FROM branches WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

[$branchScopeSql, $branchScopeParams] = Rbac::branch_scope('a');

$selectedBranchId = null;
if ($userBranchId !== null) {
    $selectedBranchId = (int)$userBranchId;
    $branchSql = $branchScopeSql;
    $branchParams = $branchScopeParams;
} else {
    $bParam = $_GET['branch_id'] ?? 'all';
    if ($bParam === 'unassigned') {
        $branchSql = " AND a.branch_id IS NULL";
        $branchParams = [];
    } elseif ($bParam !== 'all' && is_numeric($bParam)) {
        $selectedBranchId = (int)$bParam;
        $branchSql = " AND a.branch_id = ?";
        $branchParams = [$selectedBranchId];
    } else {
        $branchSql = $branchScopeSql;
        $branchParams = $branchScopeParams;
    }
}

$csrfToken = Csrf::getToken();
$isJson = (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
    || (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'));

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Csrf::verify($token)) {
        http_response_code(403);
        die(json_encode(["error" => "Invalid security token. Please refresh and try again."]));
    }

    $action = $_POST['action'] ?? '';

    // Action 1: Change stage (Kanban drop or form submit)
    if ($action === 'change_stage') {
        $applicantId = (int)($_POST['applicant_id'] ?? 0);
        $newStage = trim($_POST['new_stage'] ?? '');
        $allowedStages = ['New', 'Applied', 'Initial Interview', 'Final Interview', 'Hireable', 'Rejected'];

        if ($applicantId > 0 && in_array($newStage, $allowedStages, true)) {
            $mappedStatus = match($newStage) {
                'Hireable' => 'SHORTLISTED',
                'Rejected' => 'REJECTED',
                'New', 'Applied' => 'NEW',
                default => 'REVIEWING'
            };

            if ($newStage === 'Rejected') {
                $rejectionReason = trim($_POST['rejection_reason'] ?? 'Did not meet criteria');
                $stmt = $pdo->prepare("UPDATE applicants SET stage = ?, status = ?, rejection_reason = ? WHERE id = ?");
                $stmt->execute([$newStage, $mappedStatus, $rejectionReason, $applicantId]);
            } else {
                $stmt = $pdo->prepare("UPDATE applicants SET stage = ?, status = ? WHERE id = ?");
                $stmt->execute([$newStage, $mappedStatus, $applicantId]);
            }

            $userName = $currentUser['username'] ?? 'User';
            $logStmt = $pdo->prepare("INSERT INTO applicant_logs (applicant_id, user_name, action, created_at) VALUES (?, ?, ?, NOW())");
            $logStmt->execute([$applicantId, $userName, "Moved to stage: {$newStage}"]);

            if ($isJson || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
                header('Content-Type: application/json');
                die(json_encode(['status' => 'success', 'message' => "Stage updated to {$newStage}"]));
            }
            header("Location: fika_hrms_applications.php?tab={$tab}" . ($selectedBranchId ? "&branch_id={$selectedBranchId}" : ""));
            exit;
        }
    }

    // Action 2: Schedule interview
    if ($action === 'schedule_interview') {
        $applicantId = (int)($_POST['applicant_id'] ?? 0);
        $targetStage = trim($_POST['target_stage'] ?? 'Initial Interview');
        $interviewDate = $_POST['interview_date'] ?? date('Y-m-d');
        $interviewTime = $_POST['interview_time'] ?? '09:00';
        $interviewEndTime = $_POST['interview_end_time'] ?? '10:00';
        $interviewerId = !empty($_POST['interviewer_id']) ? (int)$_POST['interviewer_id'] : null;

        if ($applicantId > 0) {
            $ins = $pdo->prepare("
                INSERT INTO interviews (applicant_id, interviewer_id, interview_date, interview_time, end_time, scheduled_at, stage, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Scheduled', NOW())
            ");
            $ins->execute([$applicantId, $interviewerId, $interviewDate, $interviewTime, $interviewEndTime, "{$interviewDate} {$interviewTime}", $targetStage]);

            $up = $pdo->prepare("UPDATE applicants SET stage = ?, status = 'REVIEWING' WHERE id = ?");
            $up->execute([$targetStage, $applicantId]);

            $userName = $currentUser['username'] ?? 'User';
            $logStmt = $pdo->prepare("INSERT INTO applicant_logs (applicant_id, user_name, action, created_at) VALUES (?, ?, ?, NOW())");
            $logStmt->execute([$applicantId, $userName, "Scheduled {$targetStage} on {$interviewDate} {$interviewTime}"]);

            header("Location: fika_hrms_applications.php?tab=calendar" . ($selectedBranchId ? "&branch_id={$selectedBranchId}" : ""));
            exit;
        }
    }
}

// Fetch all applicants for Kanban and Directory
$search = trim($_GET['search'] ?? '');
$filterStage = trim($_GET['stage'] ?? '');

// Role-based ATS Applicant Routing:
// 1. Branch HR (Role 5): Only Crew applicants ('Barista', 'Head Barista')
// 2. Central HR (Role 2) & Global Accountant (Role 3): Only Official applicants ('Branch Manager', 'Branch HR', 'Branch Accountant', 'Central HR', 'Global Accountant')
// 3. Admin (Role 1): Sees all applicants
$roleRoutingSql = "";
if ($currentRoleId === 5) {
    $roleRoutingSql = " AND COALESCE(a.position_applied, a.position) IN ('Barista', 'Head Barista')";
} elseif (in_array($currentRoleId, [2, 3], true)) {
    $roleRoutingSql = " AND COALESCE(a.position_applied, a.position) IN ('Branch Manager', 'Branch HR', 'Branch Accountant', 'Central HR', 'Global Accountant')";
}

$filterSql = $branchSql . $roleRoutingSql;
$filterParams = $branchParams;

if ($search !== '') {
    $filterSql .= " AND (a.first_name LIKE ? OR a.last_name LIKE ? OR a.email LIKE ? OR a.phone LIKE ? OR a.position LIKE ? OR a.position_applied LIKE ?)";
    $wild = "%{$search}%";
    $filterParams = array_merge($filterParams, [$wild, $wild, $wild, $wild, $wild, $wild]);
}

if ($filterStage !== '') {
    $filterSql .= " AND a.stage = ?";
    $filterParams[] = $filterStage;
}

$appStmt = $pdo->prepare("
    SELECT a.*, b.name as branch_name,
           (SELECT COUNT(*) FROM interviews i WHERE i.applicant_id = a.id) as interview_count,
           (SELECT MAX(i.id) FROM interviews i WHERE i.applicant_id = a.id AND i.status = 'Scheduled') as pending_interview_id
    FROM applicants a
    LEFT JOIN branches b ON a.branch_id = b.id
    WHERE 1=1 {$filterSql}
    ORDER BY a.created_at DESC
");
$appStmt->execute($filterParams);
$allApplicants = $appStmt->fetchAll(PDO::FETCH_ASSOC);

// Index applicants by pipeline stage for Kanban
$kanbanStages = [
    'New' => [],
    'Initial Interview' => [],
    'Final Interview' => [],
    'Hireable' => [],
    'Rejected' => []
];

foreach ($allApplicants as $app) {
    $st = $app['stage'] ?: 'New';
    if ($st === 'Applied') $st = 'New';
    if (!isset($kanbanStages[$st])) {
        $kanbanStages['New'][] = $app;
    } else {
        $kanbanStages[$st][] = $app;
    }
}

// Fetch Scheduled Interviews for Calendar tab
$intStmt = $pdo->prepare("
    SELECT i.*, 
           a.first_name, a.last_name, a.email as applicant_email, a.phone as applicant_phone, 
           a.position, a.position_applied, a.resume, a.resume_path, a.branch_id,
           b.name as branch_name,
           CONCAT(e.first_name, ' ', e.last_name) as interviewer_name
    FROM interviews i
    JOIN applicants a ON i.applicant_id = a.id
    LEFT JOIN branches b ON a.branch_id = b.id
    LEFT JOIN employees e ON i.interviewer_id = e.id
    WHERE 1=1 {$branchSql} {$roleRoutingSql}
    ORDER BY i.interview_date ASC, i.interview_time ASC
");
$intStmt->execute($branchParams);
$scheduledInterviews = $intStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch active interviewers (branch managers, HR, head baristas)
$interviewerSql = "
    SELECT e.id, CONCAT(e.first_name, ' ', e.last_name) as full_name, e.position 
    FROM employees e 
    WHERE e.status = 'Active' " . ($selectedBranchId ? "AND (e.branch_id = ? OR e.branch_id IS NULL)" : "") . "
    ORDER BY e.first_name ASC
";
$interviewerStmt = $pdo->prepare($interviewerSql);
$interviewerStmt->execute($selectedBranchId ? [$selectedBranchId] : []);
$interviewers = $interviewerStmt->fetchAll(PDO::FETCH_ASSOC);

// Pipeline Stage Ordering for drag validation
$pipelineRanks = [
    'New' => 1,
    'Applied' => 1,
    'Initial Interview' => 2,
    'Final Interview' => 3,
    'Hireable' => 4,
    'Rejected' => 99
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicant Tracking System (ATS) &middot; Fika HRMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .kanban-cards::-webkit-scrollbar { width: 5px; }
        .kanban-cards::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .applicant-card.dragging { opacity: 0.5; transform: scale(0.98); }
        .kanban-column.drag-over { background-color: #f1f5f9; border-color: #f59e0b; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans antialiased min-h-screen flex">

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto">
        <!-- Top Nav Header -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-teal-600 flex items-center justify-center text-white font-black text-xl shadow-sm">
                        <i class="fa-solid fa-id-card-clip text-base"></i>
                    </div>
                    <div>
                        <h1 class="font-bold text-lg text-slate-900 leading-tight">Applicant Tracking System</h1>
                        <p class="text-xs text-slate-500">Recruitment intake pipeline, scorecards, and candidate evaluations</p>
                    </div>
                </div>

                <!-- Controls & Branch Selector -->
                <div class="flex items-center space-x-3">
                    <?php if ($userBranchId === null): ?>
                    <form method="GET" action="fika_hrms_applications.php" class="flex items-center gap-2">
                        <input type="hidden" name="tab" value="<?= e($tab) ?>">
                        <select name="branch_id" onchange="this.form.submit()" class="text-xs font-semibold rounded-xl border border-slate-300 py-1.5 px-3 bg-white shadow-xs focus:ring-2 focus:ring-teal-500">
                            <option value="all">All Branch Locations</option>
                            <option value="unassigned" <?= (($_GET['branch_id'] ?? '') === 'unassigned' ? 'selected' : '') ?>>Unassigned (HQ / CHR Assigned)</option>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= (int)$b['id'] ?>" <?= ($selectedBranchId === (int)$b['id'] ? 'selected' : '') ?>>
                                    <?= e($b['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <?php endif; ?>

                    <button type="button" onclick="openScheduleModal()" class="inline-flex items-center gap-2 px-3.5 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-bold shadow-sm transition">
                        <i class="fa-solid fa-calendar-plus text-xs"></i>
                        <span>Schedule Interview</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Workspace -->
        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 flex flex-col">
            <!-- Tabs Navigation -->
            <div class="flex items-center justify-between border-b border-slate-200 pb-3 mb-6 gap-4 flex-wrap">
                <div class="flex items-center space-x-2 bg-slate-200/70 p-1 rounded-xl text-xs font-bold">
                    <a href="?tab=kanban<?= $selectedBranchId ? '&branch_id=' . $selectedBranchId : '' ?>" 
                       class="px-4 py-2 rounded-lg transition <?= $tab === 'kanban' ? 'bg-white text-teal-700 shadow-sm' : 'text-slate-600 hover:text-slate-900' ?>">
                        <i class="fa-solid fa-table-columns mr-1.5"></i> Kanban Board
                    </a>
                    <a href="?tab=directory<?= $selectedBranchId ? '&branch_id=' . $selectedBranchId : '' ?>" 
                       class="px-4 py-2 rounded-lg transition <?= $tab === 'directory' ? 'bg-white text-teal-700 shadow-sm' : 'text-slate-600 hover:text-slate-900' ?>">
                        <i class="fa-solid fa-list-check mr-1.5"></i> Master Directory
                    </a>
                    <a href="?tab=calendar<?= $selectedBranchId ? '&branch_id=' . $selectedBranchId : '' ?>" 
                       class="px-4 py-2 rounded-lg transition <?= $tab === 'calendar' ? 'bg-white text-teal-700 shadow-sm' : 'text-slate-600 hover:text-slate-900' ?>">
                        <i class="fa-solid fa-calendar-days mr-1.5"></i> Interview Calendar
                    </a>
                </div>

                <div class="text-xs font-semibold text-slate-500 flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-teal-50 text-teal-700 border border-teal-200">
                        <i class="fa-solid fa-user-clock text-xs"></i>
                        <span>Total Pipeline: <?= count($allApplicants) ?> Candidate<?= count($allApplicants) === 1 ? '' : 's' ?></span>
                    </span>
                </div>
            </div>

            <!-- TAB 1: KANBAN BOARD -->
            <?php if ($tab === 'kanban'): ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 flex-1 items-start">
                <?php
                $stageConfigs = [
                    'New' => ['title' => 'New / Applied', 'color' => 'slate', 'badge' => 'bg-slate-100 text-slate-700 border-slate-300'],
                    'Initial Interview' => ['title' => 'Initial Interview', 'color' => 'blue', 'badge' => 'bg-blue-50 text-blue-700 border-blue-200'],
                    'Final Interview' => ['title' => 'Final Interview', 'color' => 'purple', 'badge' => 'bg-purple-50 text-purple-700 border-purple-200']
                ];
                foreach ($stageConfigs as $stageKey => $cfg): 
                    $stageApps = $kanbanStages[$stageKey] ?? [];
                ?>
                <div class="kanban-column bg-slate-50 border border-slate-200 rounded-2xl p-3 flex flex-col min-h-[600px] transition" data-stage-name="<?= e($stageKey) ?>">
                    <!-- Column Header -->
                    <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-200">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-<?= $cfg['color'] ?>-500"></span>
                            <span class="font-bold text-xs uppercase tracking-wider text-slate-700"><?= e($cfg['title']) ?></span>
                        </div>
                        <span class="stage-count text-xs font-bold px-2 py-0.5 rounded-full <?= $cfg['badge'] ?> border">
                            <?= count($stageApps) ?>
                        </span>
                    </div>

                    <!-- Cards Container -->
                    <div class="kanban-cards space-y-3 flex-1 overflow-y-auto min-h-[450px]">
                        <?php if (empty($stageApps)): ?>
                            <div class="empty-placeholder text-center py-8 text-xs text-slate-400 font-medium border border-dashed border-slate-200 rounded-xl">
                                No candidates
                            </div>
                        <?php else: ?>
                            <?php foreach ($stageApps as $app): 
                                $appFullName = trim($app['first_name'] . ' ' . $app['last_name']);
                                $appPos = $app['position_applied'] ?: ($app['position'] ?: 'Staff');
                            ?>
                            <div class="applicant-card bg-white rounded-xl p-3.5 border border-slate-200 shadow-xs hover:shadow-md transition cursor-grab active:cursor-grabbing"
                                 draggable="true"
                                 data-applicant-id="<?= (int)$app['id'] ?>"
                                 data-name="<?= e($appFullName) ?>"
                                 data-stage="<?= e($stageKey) ?>">
                                
                                <div class="flex items-start justify-between gap-2 mb-2">
                                    <h4 class="font-bold text-slate-900 text-sm leading-tight hover:text-teal-600 transition" 
                                        onclick="openApplicantDrawer(<?= (int)$app['id'] ?>)">
                                        <?= e($appFullName) ?>
                                    </h4>
                                    <?php if (!empty($app['pending_interview_id'])): ?>
                                        <a href="fika_hrms_interview_workspace.php?interview_id=<?= (int)$app['pending_interview_id'] ?>" 
                                           title="Evaluation Workspace" 
                                           class="text-indigo-600 hover:text-indigo-800 text-xs p-1 bg-indigo-50 rounded-md">
                                            <i class="fa-solid fa-laptop-file"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <div class="text-[11px] font-semibold text-slate-600 mb-2 flex items-center gap-1.5">
                                    <i class="fa-solid fa-briefcase text-slate-400 text-[10px]"></i>
                                    <span><?= e($appPos) ?></span>
                                </div>

                                <div class="flex items-center justify-between text-[10px] text-slate-400 pt-2 border-t border-slate-100">
                                    <span><?= e($app['branch_name'] ?: 'HQ / General') ?></span>
                                    <span><?= date('M d', strtotime($app['created_at'])) ?></span>
                                </div>

                                <!-- Action Buttons -->
                                <div class="mt-2.5 pt-2 border-t border-slate-50 flex items-center justify-between gap-1 text-[11px]">
                                    <button type="button" onclick="openApplicantDrawer(<?= (int)$app['id'] ?>)" 
                                            class="text-teal-700 hover:underline font-bold text-[11px]">
                                        View Details
                                    </button>
                                    <?php if ($stageKey !== 'Rejected' && $stageKey !== 'Hireable'): ?>
                                    <button type="button" onclick="openScheduleForCard(<?= (int)$app['id'] ?>, '<?= e(addslashes($appFullName)) ?>', '<?= e($stageKey) ?>')" 
                                            class="text-indigo-600 hover:text-indigo-800 text-[11px] font-bold">
                                        <i class="fa-solid fa-calendar-check mr-0.5"></i> Interview
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- TAB 2: MASTER DIRECTORY -->
            <?php elseif ($tab === 'directory'): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
                <!-- Search & Filters Toolbar -->
                <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 bg-slate-50">
                    <form method="GET" action="fika_hrms_applications.php" class="flex items-center gap-2 w-full sm:w-auto">
                        <input type="hidden" name="tab" value="directory">
                        <?php if ($selectedBranchId): ?>
                            <input type="hidden" name="branch_id" value="<?= $selectedBranchId ?>">
                        <?php endif; ?>
                        <div class="relative w-full sm:w-72">
                            <i class="fa-solid fa-search absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search name, email, position..." 
                                   class="w-full pl-9 pr-3 py-1.5 text-xs rounded-xl border border-slate-300 focus:ring-2 focus:ring-teal-500 focus:outline-none bg-white">
                        </div>
                        <select name="stage" onchange="this.form.submit()" class="text-xs rounded-xl border border-slate-300 py-1.5 px-3 bg-white focus:ring-2 focus:ring-teal-500">
                            <option value="">All Stages</option>
                            <option value="New" <?= $filterStage === 'New' ? 'selected' : '' ?>>New / Applied</option>
                            <option value="Initial Interview" <?= $filterStage === 'Initial Interview' ? 'selected' : '' ?>>Initial Interview</option>
                            <option value="Final Interview" <?= $filterStage === 'Final Interview' ? 'selected' : '' ?>>Final Interview</option>
                            <option value="Hireable" <?= $filterStage === 'Hireable' ? 'selected' : '' ?>>Hireable</option>
                            <option value="Rejected" <?= $filterStage === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                        <button type="submit" class="px-3 py-1.5 bg-slate-800 text-white rounded-xl text-xs font-bold hover:bg-slate-900">Filter</button>
                    </form>
                    <div class="text-xs font-semibold text-slate-500">
                        Showing <?= count($allApplicants) ?> Candidate<?= count($allApplicants) === 1 ? '' : 's' ?>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                <th class="py-3.5 px-6">Candidate</th>
                                <th class="py-3.5 px-6">Position Applied</th>
                                <th class="py-3.5 px-6">Branch</th>
                                <th class="py-3.5 px-6">Current Stage</th>
                                <th class="py-3.5 px-6">Date Applied</th>
                                <th class="py-3.5 px-6 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <?php if (empty($allApplicants)): ?>
                                <tr>
                                    <td colspan="6" class="py-12 text-center text-slate-400">
                                        <i class="fa-solid fa-users text-3xl mb-2 block text-slate-300"></i>
                                        No applicants match the selected criteria.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($allApplicants as $app): 
                                    $appFullName = trim($app['first_name'] . ' ' . $app['last_name']);
                                    $curStage = $app['stage'] ?: 'New';
                                ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-3.5 px-6">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg bg-teal-500/10 text-teal-700 font-bold flex items-center justify-center text-xs flex-shrink-0 border border-teal-500/20">
                                                <?= strtoupper(substr($app['first_name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="font-bold text-slate-900"><?= e($appFullName) ?></div>
                                                <div class="text-xs text-slate-500"><?= e($app['email']) ?> &bull; <?= e($app['phone']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-6 font-semibold text-slate-700 text-xs">
                                        <?= e($app['position_applied'] ?: ($app['position'] ?: 'Staff')) ?>
                                    </td>
                                    <td class="py-3.5 px-6 text-slate-600 text-xs font-medium">
                                        <?= e($app['branch_name'] ?: 'HQ / General') ?>
                                    </td>
                                    <td class="py-3.5 px-6">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold 
                                            <?= $curStage === 'Hireable' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 
                                               ($curStage === 'Rejected' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-indigo-50 text-indigo-700 border border-indigo-200') ?>">
                                            <?= e($curStage) ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-6 text-xs text-slate-500">
                                        <?= date('M d, Y', strtotime($app['created_at'])) ?>
                                    </td>
                                    <td class="py-3.5 px-6 text-right space-x-2">
                                        <button type="button" onclick="openApplicantDrawer(<?= (int)$app['id'] ?>)" 
                                                class="px-2.5 py-1 bg-white border border-slate-200 text-slate-700 hover:text-teal-700 rounded-lg text-xs font-semibold shadow-xs">
                                            Details
                                        </button>
                                        <?php if (!empty($app['pending_interview_id'])): ?>
                                            <a href="fika_hrms_interview_workspace.php?interview_id=<?= (int)$app['pending_interview_id'] ?>" 
                                               class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold shadow-xs">
                                                Workspace
                                            </a>
                                        <?php else: ?>
                                            <button type="button" onclick="openScheduleForCard(<?= (int)$app['id'] ?>, '<?= e(addslashes($appFullName)) ?>', '<?= e($curStage) ?>')" 
                                                    class="px-2.5 py-1 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-xs font-bold shadow-xs">
                                                Schedule
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 3: INTERVIEW CALENDAR -->
            <?php elseif ($tab === 'calendar'): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Scheduled Interviews</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Upcoming candidate interview time slots and evaluation sessions</p>
                    </div>
                    <button type="button" onclick="openScheduleModal()" class="px-3.5 py-1.5 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-bold shadow-xs transition">
                        <i class="fa-solid fa-plus mr-1"></i> New Interview
                    </button>
                </div>

                <div class="divide-y divide-slate-100">
                    <?php if (empty($scheduledInterviews)): ?>
                        <div class="p-12 text-center text-slate-400">
                            <i class="fa-solid fa-calendar-xmark text-4xl mb-3 block text-slate-300"></i>
                            No interviews currently scheduled.
                        </div>
                    <?php else: ?>
                        <?php foreach ($scheduledInterviews as $int): 
                            $cName = trim($int['first_name'] . ' ' . $int['last_name']);
                            $isDone = ($int['status'] === 'Completed');
                        ?>
                        <div class="p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-slate-50 transition">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-xl bg-teal-50 border border-teal-200 text-teal-700 flex flex-col items-center justify-center flex-shrink-0">
                                    <span class="text-[10px] uppercase font-bold"><?= date('M', strtotime($int['interview_date'])) ?></span>
                                    <span class="text-base font-black leading-none"><?= date('d', strtotime($int['interview_date'])) ?></span>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-bold text-slate-900 text-sm"><?= e($cName) ?></h4>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $isDone ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-blue-50 text-blue-700 border border-blue-200' ?>">
                                            <?= e($int['status']) ?> &bull; <?= e($int['stage']) ?>
                                        </span>
                                    </div>
                                    <div class="text-xs text-slate-500 mt-1 flex flex-wrap items-center gap-3">
                                        <span><i class="fa-solid fa-clock text-slate-400 mr-1"></i><?= date('h:i A', strtotime($int['interview_time'])) ?> - <?= date('h:i A', strtotime($int['end_time'])) ?></span>
                                        <span><i class="fa-solid fa-briefcase text-slate-400 mr-1"></i><?= e($int['position_applied'] ?: ($int['position'] ?: 'Staff')) ?></span>
                                        <?php if (!empty($int['interviewer_name'])): ?>
                                            <span><i class="fa-solid fa-user-tie text-slate-400 mr-1"></i>Interviewer: <?= e($int['interviewer_name']) ?></span>
                                        <?php endif; ?>
                                        <span><i class="fa-solid fa-location-dot text-slate-400 mr-1"></i><?= e($int['branch_name'] ?: 'HQ / General') ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="fika_hrms_interview_workspace.php?interview_id=<?= (int)$int['id'] ?>" 
                                   class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-xs transition inline-flex items-center gap-1.5">
                                    <i class="fa-solid fa-laptop-file"></i>
                                    <span><?= $isDone ? 'View Evaluation' : 'Scorecard Workspace' ?></span>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Schedule Interview Modal -->
    <div id="scheduleModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 max-w-lg w-full overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                <h3 class="font-bold text-slate-900 text-lg flex items-center gap-2">
                    <i class="fa-solid fa-calendar-check text-teal-600"></i> Schedule Interview
                </h3>
                <button type="button" onclick="closeScheduleModal()" class="text-slate-400 hover:text-slate-600 transition">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <form method="POST" action="fika_hrms_applications.php" class="p-6 space-y-4">
                <input type="hidden" name="action" value="schedule_interview">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <!-- Applicant Selection -->
                <div>
                    <label for="schedApplicantId" class="block text-xs font-bold uppercase text-slate-600 mb-1">Candidate *</label>
                    <select name="applicant_id" id="schedApplicantId" required class="w-full border border-slate-300 rounded-xl p-2.5 text-xs font-semibold focus:ring-2 focus:ring-teal-500 bg-white">
                        <option value="">Select Candidate...</option>
                        <?php foreach ($allApplicants as $a): ?>
                            <option value="<?= (int)$a['id'] ?>">
                                <?= e($a['first_name'] . ' ' . $a['last_name']) ?> &mdash; <?= e($a['position_applied'] ?: $a['position']) ?> (<?= e($a['stage']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Stage Selection -->
                <div>
                    <label for="schedTargetStage" class="block text-xs font-bold uppercase text-slate-600 mb-1">Interview Round *</label>
                    <select name="target_stage" id="schedTargetStage" required class="w-full border border-slate-300 rounded-xl p-2.5 text-xs font-semibold focus:ring-2 focus:ring-teal-500 bg-white">
                        <option value="Initial Interview">Initial Interview</option>
                        <option value="Final Interview">Final Interview</option>
                    </select>
                </div>

                <!-- Date & Times -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-1">
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Date *</label>
                        <input type="date" name="interview_date" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required class="w-full border border-slate-300 rounded-xl p-2.5 text-xs font-semibold">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Start Time *</label>
                        <input type="time" name="interview_time" value="09:00" required class="w-full border border-slate-300 rounded-xl p-2.5 text-xs font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1">End Time *</label>
                        <input type="time" name="interview_end_time" value="10:00" required class="w-full border border-slate-300 rounded-xl p-2.5 text-xs font-mono">
                    </div>
                </div>

                <!-- Assigned Interviewer -->
                <div>
                    <label for="schedInterviewerId" class="block text-xs font-bold uppercase text-slate-600 mb-1">Interviewer (Optional)</label>
                    <select name="interviewer_id" id="schedInterviewerId" class="w-full border border-slate-300 rounded-xl p-2.5 text-xs font-semibold focus:ring-2 focus:ring-teal-500 bg-white">
                        <option value="">Select Interviewer...</option>
                        <?php foreach ($interviewers as $iv): ?>
                            <option value="<?= (int)$iv['id'] ?>">
                                <?= e($iv['full_name']) ?> (<?= e($iv['position']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" onclick="closeScheduleModal()" class="px-4 py-2 border border-slate-300 rounded-xl text-slate-700 hover:bg-slate-50 text-xs font-semibold transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-bold shadow-sm transition">
                        Schedule Interview
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div id="rejectModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 max-w-sm w-full p-6 relative">
            <h3 class="font-bold text-rose-600 text-base mb-2 flex items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation"></i> Confirm Rejection
            </h3>
            <p class="text-xs text-slate-500 mb-4">Moving this candidate to Rejected will archive their application.</p>
            <form method="POST" action="fika_hrms_applications.php">
                <input type="hidden" name="action" value="change_stage">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="applicant_id" id="rejectModalApplicantId" value="">
                <input type="hidden" name="new_stage" value="Rejected">

                <div class="mb-4">
                    <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Reason for Rejection *</label>
                    <select name="rejection_reason" required class="w-full border border-slate-300 rounded-xl p-2.5 text-xs font-semibold bg-white focus:ring-2 focus:ring-rose-500">
                        <option value="Did not meet criteria">Did not meet criteria</option>
                        <option value="Candidate withdrew">Candidate withdrew</option>
                        <option value="No-Show / Missed Interview">No-Show / Missed Interview</option>
                        <option value="Position filled">Position filled</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeRejectModal()" class="px-3.5 py-1.5 text-slate-600 hover:bg-slate-50 rounded-xl text-xs font-semibold">Cancel</button>
                    <button type="submit" class="px-4 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold shadow-xs">Confirm Reject</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Applicant Profile Drawer -->
    <div id="applicantDrawer" class="fixed inset-0 z-50 hidden overflow-hidden">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-xs" onclick="closeApplicantDrawer()"></div>
        <div class="absolute inset-y-0 right-0 max-w-full flex pl-10">
            <div class="w-screen max-w-md bg-white shadow-2xl border-l border-slate-200 flex flex-col">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                    <h3 class="font-bold text-slate-900 text-base" id="drawerTitle">Candidate Profile</h3>
                    <button type="button" onclick="closeApplicantDrawer()" class="text-slate-400 hover:text-slate-600">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto space-y-4 flex-1 text-xs" id="drawerBody">
                    <div class="flex items-center justify-center py-12 text-slate-400">
                        <i class="fa-solid fa-spinner fa-spin text-2xl mr-2"></i> Loading profile...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Kanban Drag and Drop & Modal Scripts -->
    <script>
    const csrfToken = <?= json_encode($csrfToken) ?>;
    const pipelineRanks = <?= json_encode($pipelineRanks) ?>;

    document.addEventListener('DOMContentLoaded', () => {
        initKanbanDragDrop();
    });

    function initKanbanDragDrop() {
        const cards = document.querySelectorAll('.applicant-card');
        const columns = document.querySelectorAll('.kanban-column');

        cards.forEach(card => {
            card.addEventListener('dragstart', (e) => {
                e.dataTransfer.setData('text/plain', card.dataset.applicantId);
                card.classList.add('dragging');
            });
            card.addEventListener('dragend', (e) => {
                card.classList.remove('dragging');
            });
        });

        columns.forEach(col => {
            col.addEventListener('dragover', (e) => {
                e.preventDefault();
                col.classList.add('drag-over');
            });

            col.addEventListener('dragleave', (e) => {
                col.classList.remove('drag-over');
            });

            col.addEventListener('drop', (e) => {
                e.preventDefault();
                col.classList.remove('drag-over');

                const applicantId = e.dataTransfer.getData('text/plain');
                const targetStage = col.dataset.stageName;

                if (!applicantId || !targetStage) return;

                const card = document.querySelector(`.applicant-card[data-applicant-id="${applicantId}"]`);
                if (!card) return;

                const currentStage = card.dataset.stage;
                if (currentStage === targetStage) return;

                // Intercept for Scheduling when moving to interview stages
                if (targetStage === 'Initial Interview' || targetStage === 'Final Interview') {
                    openScheduleForCard(applicantId, card.dataset.name, targetStage);
                    return;
                }

                // Intercept for Rejection Modal
                if (targetStage === 'Rejected') {
                    openRejectModal(applicantId);
                    return;
                }

                // Direct Stage Update
                updateApplicantStage(applicantId, targetStage, card, col);
            });
        });
    }

    function updateApplicantStage(applicantId, targetStage, card, targetCol) {
        const formData = new URLSearchParams();
        formData.append('action', 'change_stage');
        formData.append('applicant_id', applicantId);
        formData.append('new_stage', targetStage);
        formData.append('csrf_token', csrfToken);

        fetch('fika_hrms_applications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData.toString()
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const cardsContainer = targetCol.querySelector('.kanban-cards');
                const emptyMsg = cardsContainer.querySelector('.empty-placeholder');
                if (emptyMsg) emptyMsg.remove();
                
                card.dataset.stage = targetStage;
                cardsContainer.appendChild(card);
                updateColumnCounts();
            } else {
                alert(data.message || 'Error updating stage.');
            }
        })
        .catch(err => {
            console.error('Stage update network error', err);
            alert('Failed to update stage due to a network error.');
        });
    }

    function updateColumnCounts() {
        document.querySelectorAll('.kanban-column').forEach(col => {
            const count = col.querySelectorAll('.applicant-card').length;
            const badge = col.querySelector('.stage-count');
            if (badge) badge.textContent = count;
        });
    }

    function openScheduleModal() {
        document.getElementById('scheduleModal').classList.remove('hidden');
    }

    function closeScheduleModal() {
        document.getElementById('scheduleModal').classList.add('hidden');
    }

    function openScheduleForCard(applicantId, applicantName, targetStage) {
        document.getElementById('schedApplicantId').value = applicantId;
        if (targetStage === 'Final Interview') {
            document.getElementById('schedTargetStage').value = 'Final Interview';
        } else {
            document.getElementById('schedTargetStage').value = 'Initial Interview';
        }
        openScheduleModal();
    }

    function openRejectModal(applicantId) {
        document.getElementById('rejectModalApplicantId').value = applicantId;
        document.getElementById('rejectModal').classList.remove('hidden');
    }

    function closeRejectModal() {
        document.getElementById('rejectModal').classList.add('hidden');
    }

    function openApplicantDrawer(applicantId) {
        const drawer = document.getElementById('applicantDrawer');
        const body = document.getElementById('drawerBody');
        drawer.classList.remove('hidden');
        body.innerHTML = '<div class="flex items-center justify-center py-12 text-slate-400"><i class="fa-solid fa-spinner fa-spin text-2xl mr-2"></i> Loading profile...</div>';

        // Find candidate from all applicants
        const apps = <?= json_encode($allApplicants) ?>;
        const candidate = apps.find(a => a.id == applicantId);
        if (!candidate) {
            body.innerHTML = '<p class="text-rose-600">Candidate details not found.</p>';
            return;
        }

        document.getElementById('drawerTitle').textContent = candidate.first_name + ' ' + candidate.last_name;
        
        let html = `
            <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
                <div class="text-[11px] font-bold uppercase text-slate-400">Position Applied</div>
                <div class="text-sm font-bold text-slate-900">${candidate.position_applied || candidate.position || 'Staff'}</div>
                <div class="text-slate-600"><i class="fa-solid fa-envelope mr-1.5 text-slate-400"></i>${candidate.email}</div>
                <div class="text-slate-600"><i class="fa-solid fa-phone mr-1.5 text-slate-400"></i>${candidate.phone}</div>
                <div class="text-slate-600"><i class="fa-solid fa-location-dot mr-1.5 text-slate-400"></i>${candidate.branch_name || 'HQ / General'}</div>
            </div>

            <div class="space-y-1">
                <div class="text-[11px] font-bold uppercase text-slate-400">Application Stage</div>
                <span class="inline-flex px-2.5 py-1 rounded-lg font-bold text-xs bg-teal-50 text-teal-800 border border-teal-200">${candidate.stage || 'New'}</span>
            </div>

            ${candidate.notes ? `
            <div class="space-y-1">
                <div class="text-[11px] font-bold uppercase text-slate-400">Candidate Notes</div>
                <p class="text-slate-700 bg-white p-3 rounded-lg border border-slate-200 whitespace-pre-wrap">${candidate.notes}</p>
            </div>` : ''}

            <div class="pt-4 border-t border-slate-200 flex flex-col gap-2">
                ${candidate.pending_interview_id ? `
                    <a href="fika_hrms_interview_workspace.php?interview_id=${candidate.pending_interview_id}" 
                       class="w-full text-center py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold shadow-xs transition">
                        <i class="fa-solid fa-laptop-file mr-1.5"></i> Open Evaluation Workspace
                    </a>
                ` : `
                    <button type="button" onclick="closeApplicantDrawer(); openScheduleForCard(${candidate.id}, '${candidate.first_name} ${candidate.last_name}', '${candidate.stage}');" 
                            class="w-full text-center py-2.5 bg-teal-600 hover:bg-teal-700 text-white rounded-xl font-bold shadow-xs transition">
                        <i class="fa-solid fa-calendar-plus mr-1.5"></i> Schedule Interview
                    </button>
                `}
                <button type="button" onclick="closeApplicantDrawer(); openRejectModal(${candidate.id});" 
                        class="w-full text-center py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-xl font-bold transition">
                    <i class="fa-solid fa-circle-xmark mr-1.5"></i> Reject Candidate
                </button>
            </div>
        `;
        body.innerHTML = html;
    }

    function closeApplicantDrawer() {
        document.getElementById('applicantDrawer').classList.add('hidden');
    }
    </script>
</body>
</html>
