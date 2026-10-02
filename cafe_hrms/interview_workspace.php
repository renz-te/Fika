<?php
require_once __DIR__ . '/init.php';
require_login();
require_role(['Admin', 'Super Admin', 'HR', 'Branch Admin', 'Head Barista', 'Central HR']);

$interview_id = isset($_GET['interview_id']) ? (int)$_GET['interview_id'] : 0;

if (!$interview_id) {
    flash('error', 'Invalid interview ID.');
    redirect('applications.php?tab=calendar');
}

// Fetch interview & applicant data
$stmt = $pdo->prepare('
    SELECT i.*, 
           a.id AS applicant_id, a.first_name, a.last_name, a.email, a.phone, a.position_applied, a.resume, a.stage, a.employment_category, a.branch_id,
           b.name AS branch_name
    FROM interviews i
    JOIN applicants a ON i.applicant_id = a.id
    LEFT JOIN branches b ON a.branch_id = b.id
    WHERE i.id = ?
');
$stmt->execute([$interview_id]);
$data = $stmt->fetch();

if (!$data) {
    flash('error', 'Interview not found.');
    redirect('applications.php?tab=calendar');
}

// Check if a scorecard already exists
$scStmt = $pdo->prepare('SELECT * FROM interview_scorecards WHERE interview_id = ?');
$scStmt->execute([$interview_id]);
$existingScorecard = $scStmt->fetch();

// Fetch historical scorecards
$histStmt = $pdo->prepare('SELECT s.*, i.interview_date, CONCAT(e.first_name, " ", e.last_name) AS interviewer_name FROM interview_scorecards s INNER JOIN interviews i ON s.interview_id = i.id LEFT JOIN employees e ON i.interviewer_id = e.id WHERE i.applicant_id = ? AND i.id != ? ORDER BY i.interview_date DESC');
$histStmt->execute([$data['applicant_id'], $interview_id]);
$historicalScorecards = $histStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existingScorecard) {
    $technical_score = isset($_POST['technical_score']) ? (int)$_POST['technical_score'] : NULL;
    $culture_score = isset($_POST['culture_score']) ? (int)$_POST['culture_score'] : NULL;
    $communication = isset($_POST['communication_score']) ? (int)$_POST['communication_score'] : NULL;
    $reliability = isset($_POST['reliability_score']) ? (int)$_POST['reliability_score'] : NULL;
    $problem_solving = isset($_POST['problem_solving_score']) ? (int)$_POST['problem_solving_score'] : NULL;
    $strengths = trim($_POST['strengths']);
    $concerns = trim($_POST['concerns']);
    $recommendation = $_POST['recommendation'];
    
    $hourly_wage = NULL;
    $monthly_wage = NULL;
    if (!empty($_POST['hourly_wage'])) {
        $raw_wage = floatval(str_replace(',', '', $_POST['hourly_wage']));
        if ($raw_wage < 0 || $raw_wage > 999.99) {
            die("Invalid wage input. Wage must be between 0.00 and 999.99.");
        }
        $hourly_wage = $raw_wage;
        $monthly_wage = $raw_wage * 176;
    }
    
    $global_pool_reason = !empty($_POST['global_pool_reason']) ? $_POST['global_pool_reason'] : NULL;
    $rejection_reason = !empty($_POST['rejection_reason']) ? $_POST['rejection_reason'] : NULL;
    
    // Insert scorecard
    $insertStmt = $pdo->prepare('
        INSERT INTO interview_scorecards (interview_id, technical_score, communication_score, reliability_score, culture_score, problem_solving_score, strengths, concerns, recommendation, hourly_wage, monthly_wage, global_pool_reason, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ');
    $inserted = $insertStmt->execute([
        $interview_id, $technical_score, $communication, $reliability, $culture_score, $problem_solving, $strengths, $concerns, $recommendation, $hourly_wage, $monthly_wage, $global_pool_reason, $_SESSION['user']['name']
    ]);
    
    if ($inserted) {
        $updateInt = $pdo->prepare("UPDATE interviews SET status = 'Completed' WHERE id = ?");
        $updateInt->execute([$interview_id]);
        
        $newStage = $data['stage'];
        if ($recommendation === 'Hire') {
            $updateApp = $pdo->prepare("UPDATE applicants SET stage = 'Hireable' WHERE id = ?");
            $updateApp->execute([$data['applicant_id']]);
            $newStage = 'Hireable';
        } elseif ($recommendation === 'Next Round') {
            $updateApp = $pdo->prepare("UPDATE applicants SET stage = 'Final Interview' WHERE id = ?");
            $updateApp->execute([$data['applicant_id']]);
            $newStage = 'Final Interview';
            
            $next_date = $_POST['next_interview_date'] ?? '';
            $next_start = $_POST['next_start_time'] ?? '';
            $next_end = $_POST['next_end_time'] ?? '';
            $next_interviewer = (int)($_POST['next_interviewer_id'] ?? 0);
            
            if ($next_date && $next_start && $next_end && $next_interviewer) {
                $intStmt = $pdo->prepare('INSERT INTO interviews (applicant_id, interview_date, interview_time, end_time, interviewer_id, stage, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $intStmt->execute([$data['applicant_id'], $next_date, $next_start, $next_end, $next_interviewer, 'Final Interview', 'Scheduled']);
            }
        } elseif ($recommendation === 'Global Pool') {
            $updateApp = $pdo->prepare("UPDATE applicants SET stage = 'Hireable', branch_id = NULL WHERE id = ?");
            $updateApp->execute([$data['applicant_id']]);
            $newStage = 'Hireable (Global Pool)';
        } elseif ($recommendation === 'Reject') {
            $updateApp = $pdo->prepare("UPDATE applicants SET stage = 'Rejected', rejection_reason = ? WHERE id = ?");
            $updateApp->execute([$rejection_reason, $data['applicant_id']]);
            $newStage = 'Rejected';
        }
        
        // Add log
        $logStmt = $pdo->prepare('INSERT INTO applicant_logs (applicant_id, user_name, action, created_at) VALUES (?, ?, ?, NOW())');
        $logStmt->execute([$data['applicant_id'], 'System', "Interview evaluated. Recommended: $recommendation. Moved to $newStage."]);
        
        flash('success', 'Interview evaluation submitted successfully.');
        redirect('applications.php?tab=kanban');
    } else {
        $error = 'Failed to submit evaluation.';
    }
}

$pageTitle = 'Interview Workspace';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> | Café HRMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4f46e5',
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
    </style>
</head>
<body class="h-screen flex flex-col overflow-hidden">
    
    <!-- Header -->
    <header class="bg-white shadow-sm border-b border-slate-200 shrink-0">
        <div class="px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="applications?tab=calendar" class="w-10 h-10 flex items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 transition">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-slate-800"><?= h($data['first_name'] . ' ' . $data['last_name']) ?></h1>
                    <p class="text-gray-600 text-sm"><?= h($data['position_applied']) ?> &bull; <?= h($data['employment_category'] ?? 'Full-Time') ?> &bull; <?= h($data['branch_name'] ?? 'Not Assigned') ?></p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full font-semibold">
                    <?= h($data['stage']) ?>
                </span>
            </div>
        </div>
    </header>

    <!-- Workspace Area -->
    <main class="flex-1 flex overflow-hidden">
        
        <!-- Left Column: Context & CV -->
        <div class="w-1/2 flex flex-col h-[calc(100vh-80px)] border-r border-slate-200 bg-slate-50">

            <?php if (!empty($historicalScorecards)): ?>
            <div class="p-6 shrink-0 bg-white border-b border-slate-200 shadow-sm z-10">
                <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wider mb-3 flex items-center"><i class="fa-solid fa-clock-rotate-left mr-2 text-indigo-500"></i> Past Evaluations</h3>
                <div class="space-y-4 max-h-64 overflow-y-auto pr-2">
                    <?php foreach($historicalScorecards as $hs): ?>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 text-sm shadow-sm">
                        <div class="flex justify-between items-center mb-3 pb-3 border-b border-slate-200">
                            <span class="font-bold text-slate-800"><i class="fa-solid fa-user-tie text-slate-400 mr-1"></i> <?= h($hs['interviewer_name'] ?? 'Unknown') ?></span>
                            <span class="text-xs text-slate-500 font-medium bg-white px-2 py-1 rounded border border-slate-200 shadow-sm"><?= date('M j, Y', strtotime($hs['interview_date'])) ?></span>
                        </div>
                        <div class="flex flex-wrap gap-2 mb-3">
                            <?php if ($hs['technical_score']): ?><span class="bg-indigo-50 text-indigo-700 border border-indigo-100 text-[10px] px-2 py-1 rounded font-bold shadow-sm">Tech: <?= h($hs['technical_score']) ?>/5</span><?php endif; ?>
                            <?php if ($hs['communication_score']): ?><span class="bg-indigo-50 text-indigo-700 border border-indigo-100 text-[10px] px-2 py-1 rounded font-bold shadow-sm">Comm: <?= h($hs['communication_score']) ?>/5</span><?php endif; ?>
                            <?php if ($hs['reliability_score']): ?><span class="bg-indigo-50 text-indigo-700 border border-indigo-100 text-[10px] px-2 py-1 rounded font-bold shadow-sm">Reliability: <?= h($hs['reliability_score']) ?>/5</span><?php endif; ?>
                            <?php if ($hs['culture_score']): ?><span class="bg-indigo-50 text-indigo-700 border border-indigo-100 text-[10px] px-2 py-1 rounded font-bold shadow-sm">Culture: <?= h($hs['culture_score']) ?>/5</span><?php endif; ?>
                            <?php if ($hs['problem_solving_score']): ?><span class="bg-indigo-50 text-indigo-700 border border-indigo-100 text-[10px] px-2 py-1 rounded font-bold shadow-sm">Prob Solv: <?= h($hs['problem_solving_score']) ?>/5</span><?php endif; ?>
                            <span class="bg-slate-100 text-slate-700 border border-slate-200 text-[10px] px-2 py-1 rounded font-bold shadow-sm ml-auto text-right">Verdict: <?= h($hs['recommendation']) ?></span>
                        </div>
                        <div class="space-y-3 pt-2">
                            <div>
                                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1"><i class="fa-solid fa-arrow-trend-up text-green-500 mr-1"></i> Strengths</p>
                                <p class="text-slate-700 text-xs whitespace-pre-wrap leading-relaxed"><?= h($hs['strengths']) ?></p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1"><i class="fa-solid fa-arrow-trend-down text-red-400 mr-1"></i> Concerns</p>
                                <p class="text-slate-700 text-xs whitespace-pre-wrap leading-relaxed"><?= h($hs['concerns']) ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="flex-1 p-6 flex flex-col min-h-0 bg-slate-100">
                <div class="flex items-center justify-between mb-3 shrink-0">
                    <h3 class="font-bold text-slate-700 flex items-center"><i class="fa-solid fa-file-pdf text-red-500 mr-2 text-lg"></i> Resume Document</h3>
                    <?php if($data['resume']): ?>
                        <a href="file_proxy.php?file=applicants/<?= h($data['resume']) ?>" target="_blank" class="text-xs font-semibold bg-white px-3 py-1.5 rounded border border-slate-200 text-indigo-600 hover:bg-indigo-50 transition shadow-sm"><i class="fa-solid fa-up-right-from-square mr-1"></i> Open in tab</a>
                    <?php endif; ?>
                </div>
                <div class="flex-1 w-full h-full bg-white rounded-xl overflow-hidden border border-slate-200 shadow-sm relative">
                    <?php if($data['resume']): ?>
                        <iframe src="file_proxy.php?file=applicants/<?= h($data['resume']) ?>#toolbar=0" class="w-full h-full border-0"></iframe>
                    <?php else: ?>
                        <div class="absolute inset-0 flex items-center justify-center text-slate-500 flex-col">
                            <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mb-4">
                                <i class="fa-solid fa-file-circle-xmark text-2xl text-slate-400"></i>
                            </div>
                            <p class="font-medium text-slate-600">No document uploaded</p>
                            <p class="text-xs text-slate-400 mt-1">Applicant did not provide a resume.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Scorecard -->
        <div class="w-1/2 bg-white overflow-y-auto">
            <div class="p-8 max-w-2xl mx-auto">
                <h2 class="text-2xl font-bold text-slate-800 mb-2">Interview Scorecard</h2>
                <p class="text-slate-500 text-sm mb-8 pb-4 border-b border-slate-100">Evaluate the candidate's performance and provide a final verdict.</p>
                
                <?php if (isset($error)): ?>
                    <div class="bg-red-50 text-red-700 p-4 rounded-lg mb-6 border border-red-200 flex items-center">
                        <i class="fa-solid fa-triangle-exclamation mr-3 text-lg"></i>
                        <?= h($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($existingScorecard): ?>
                    <div class="bg-amber-50 text-amber-800 p-5 rounded-xl mb-6 border border-amber-200 shadow-sm flex items-start">
                        <i class="fa-solid fa-circle-check text-2xl mr-4 text-amber-500"></i>
                        <div>
                            <h4 class="font-bold">Evaluation Completed</h4>
                            <p class="text-sm mt-1">An evaluation has already been submitted for this interview by <strong><?= h($existingScorecard['created_by']) ?></strong>.</p>
                        </div>
                    </div>
                    
                    <div class="space-y-6 opacity-60 pointer-events-none mt-8">
                        <div class="bg-slate-50 p-6 rounded-xl border border-slate-200">
                            <p class="text-sm font-bold text-slate-700 uppercase tracking-wider mb-2">Recorded Verdict</p>
                            <p class="text-lg font-semibold text-slate-900"><?= h($existingScorecard['recommendation']) ?></p>
                        </div>
                    </div>
                    
                <?php else: ?>
                
                <?php if ($data['stage'] === 'Hireable'): ?>
                <div class="bg-green-50 border border-green-200 rounded-lg p-6 text-center">
                    <div class="text-green-700 font-bold text-lg mb-1">Applicant Marked as Hireable</div>
                    <p class="text-sm text-gray-600 mb-4">This candidate has completed all evaluation rounds and is awaiting Central HR / Finance onboarding.</p>
                    <a href="applications?tab=kanban" class="inline-block px-4 py-2 bg-green-600 text-white text-sm rounded shadow hover:bg-green-700">Return to Board</a>
                </div>
                <?php else: ?>
                
                <form method="POST" autocomplete="off" class="space-y-8">
                    
                    <!-- Scoring section -->
                    <?php if ($data['stage'] === 'Initial Interview' || $data['stage'] === 'New'): ?>
                    <div class="space-y-4 mb-6">
                        <div class="flex items-center justify-between p-3 border rounded bg-gray-50">
                            <label class="font-semibold text-sm text-gray-700">Technical Assessment <span class="text-red-500">*</span></label>
                            <div class="flex justify-between items-center bg-white p-2 rounded border border-slate-200 shadow-sm gap-4">
                                <span class="text-xs text-slate-500 font-semibold uppercase">Poor</span>
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <label class="flex flex-col items-center cursor-pointer group">
                                        <input type="radio" name="technical_score" value="<?= $i ?>" required class="w-4 h-4 text-indigo-600 border-slate-300 focus:ring-indigo-500">
                                        <span class="text-xs mt-1 text-slate-500 group-hover:text-indigo-600 font-bold"><?= $i ?></span>
                                    </label>
                                <?php endfor; ?>
                                <span class="text-xs text-slate-500 font-semibold uppercase">Great</span>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between p-3 border rounded bg-gray-50">
                            <label class="font-semibold text-sm text-gray-700">Communication & Presentation <span class="text-red-500">*</span></label>
                            <div class="flex justify-between items-center bg-white p-2 rounded border border-slate-200 shadow-sm gap-4">
                                <span class="text-xs text-slate-500 font-semibold uppercase">Poor</span>
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <label class="flex flex-col items-center cursor-pointer group">
                                        <input type="radio" name="communication_score" value="<?= $i ?>" required class="w-4 h-4 text-indigo-600 border-slate-300 focus:ring-indigo-500">
                                        <span class="text-xs mt-1 text-slate-500 group-hover:text-indigo-600 font-bold"><?= $i ?></span>
                                    </label>
                                <?php endfor; ?>
                                <span class="text-xs text-slate-500 font-semibold uppercase">Great</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between p-3 border rounded bg-gray-50">
                            <label class="font-semibold text-sm text-gray-700">Reliability & Availability <span class="text-red-500">*</span></label>
                            <div class="flex justify-between items-center bg-white p-2 rounded border border-slate-200 shadow-sm gap-4">
                                <span class="text-xs text-slate-500 font-semibold uppercase">Poor</span>
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <label class="flex flex-col items-center cursor-pointer group">
                                        <input type="radio" name="reliability_score" value="<?= $i ?>" required class="w-4 h-4 text-indigo-600 border-slate-300 focus:ring-indigo-500">
                                        <span class="text-xs mt-1 text-slate-500 group-hover:text-indigo-600 font-bold"><?= $i ?></span>
                                    </label>
                                <?php endfor; ?>
                                <span class="text-xs text-slate-500 font-semibold uppercase">Great</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($data['stage'] === 'Final Interview'): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-6">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-3">Culture Fit <span class="text-red-500">*</span></label>
                            <div class="flex justify-between items-center bg-slate-50 p-4 rounded-xl border border-slate-200 shadow-sm">
                                <span class="text-xs text-slate-500 font-semibold uppercase">Poor</span>
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <label class="flex flex-col items-center cursor-pointer group">
                                        <input type="radio" name="culture_score" value="<?= $i ?>" required class="w-5 h-5 text-indigo-600 border-slate-300 focus:ring-indigo-500">
                                        <span class="text-xs mt-1.5 text-slate-500 group-hover:text-indigo-600 font-bold"><?= $i ?></span>
                                    </label>
                                <?php endfor; ?>
                                <span class="text-xs text-slate-500 font-semibold uppercase">Great</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-3">Problem Solving & Scenarios <span class="text-red-500">*</span></label>
                            <div class="flex justify-between items-center bg-slate-50 p-4 rounded-xl border border-slate-200 shadow-sm">
                                <span class="text-xs text-slate-500 font-semibold uppercase">Poor</span>
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <label class="flex flex-col items-center cursor-pointer group">
                                        <input type="radio" name="problem_solving_score" value="<?= $i ?>" required class="w-5 h-5 text-indigo-600 border-slate-300 focus:ring-indigo-500">
                                        <span class="text-xs mt-1.5 text-slate-500 group-hover:text-indigo-600 font-bold"><?= $i ?></span>
                                    </label>
                                <?php endfor; ?>
                                <span class="text-xs text-slate-500 font-semibold uppercase">Great</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Qualitative section -->
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Strengths <span class="text-red-500">*</span></label>
                            <textarea name="strengths" rows="4" required placeholder="What did the candidate do well? What skills stood out?" class="w-full rounded-xl border-slate-300 p-4 bg-white border focus:ring-indigo-500 focus:border-indigo-500 transition-colors shadow-sm text-sm"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Concerns / Areas for Growth <span class="text-red-500">*</span></label>
                            <textarea name="concerns" rows="4" required placeholder="Any red flags, weak points, or areas needing improvement?" class="w-full rounded-xl border-slate-300 p-4 bg-white border focus:ring-indigo-500 focus:border-indigo-500 transition-colors shadow-sm text-sm"></textarea>
                        </div>
                    </div>
                    
                    <!-- Recommendation -->
                    <div class="bg-indigo-50/70 p-6 rounded-xl border border-indigo-100 shadow-sm">
                        <label class="block text-sm font-bold text-indigo-900 mb-3 uppercase tracking-wider flex items-center">
                            <i class="fa-solid fa-gavel mr-2"></i> Final Recommendation <span class="text-red-500 ml-1">*</span>
                        </label>
                        <select name="recommendation" id="recommendationSelect" required class="w-full rounded-xl border-indigo-300 p-3.5 bg-white text-indigo-900 border focus:ring-indigo-500 focus:border-indigo-500 font-semibold shadow-sm transition-colors cursor-pointer">
                            <option value="">-- Select Verdict --</option>
                            <option value="Hire">Hire - Local Branch</option>
                            <option value="Global Pool">Pass - Send to Global Pool</option>
                            <option value="Reject">Reject Candidate</option>
                            <?php if ($data['stage'] === 'Initial Interview'): ?>
                                <option value="Next Round">Proceed to Final Interview</option>
                            <?php endif; ?>
                        </select>
                        
                        <!-- Hire Container -->
                        <div id="hireDetails" style="display:none;" class="mt-4 p-4 border rounded bg-green-50 border-green-200 shadow-sm">
                            <h4 class="font-bold text-green-800 mb-2">Compensation Offer</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-green-900 mb-1">Hourly Wage (PHP)</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 font-medium">₱</span>
                                        <input type="text" name="hourly_wage" id="hourly_wage" class="form-input w-full rounded border-green-300 p-2 pl-8 text-sm focus:ring-green-500" placeholder="0.00" maxlength="6" autocomplete="off">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-green-900 mb-1">Est. Monthly Wage (PHP)</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 font-medium">₱</span>
                                        <input type="text" name="monthly_wage" id="monthly_wage" class="form-input w-full rounded border-green-300 p-2 pl-8 text-sm bg-green-100/50 focus:outline-none" readonly>
                                    </div>
                                </div>
                            </div>
                            <p class="text-xs text-green-700/80 mt-2">*Monthly calculated as 8 hrs/day × 22 days</p>
                        </div>

                        <!-- Global Pool Container -->
                        <div id="globalPoolDetails" style="display:none;" class="mt-4 p-4 border rounded bg-blue-50 border-blue-200 shadow-sm">
                            <h4 class="font-bold text-blue-800 mb-2">Global Pool Dispatch</h4>
                            <label class="block text-sm font-medium text-blue-900 mb-1">Reason for Dispatch</label>
                            <select name="global_pool_reason" id="global_pool_reason" class="form-select w-full rounded border-blue-300 p-2 text-sm focus:ring-blue-500">
                                <option value="" disabled selected>Select reason...</option>
                                <option value="Local position filled">Local position filled</option>
                                <option value="Branch overstaffed">Branch overstaffed</option>
                                <option value="Better fit for another branch">Better fit for another branch</option>
                            </select>
                        </div>
                        
                        <!-- Reject Container -->
                        <div id="rejectDetails" style="display:none;" class="mt-4 p-4 border rounded bg-red-50 border-red-200 shadow-sm">
                            <label class="block text-sm text-red-800 font-bold mb-2">Reason for Rejection *</label>
                            <select name="rejection_reason" id="workspace_rejection_reason" class="form-select w-full rounded border-red-300 p-2 text-sm focus:ring-red-500">
                                <option value="" disabled selected>Select reason...</option>
                                <option value="Did not meet criteria">Did not meet criteria</option>
                                <option value="Candidate withdrew">Candidate withdrew</option>
                                <option value="No-Show">No-Show / Missed Interview</option>
                                <option value="Position filled">Position filled</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <!-- Inline Scheduling -->
                        <div id="nextRoundSchedule" style="display:none;" class="mt-6 pt-6 border-t border-indigo-200">
                            <h4 class="font-bold text-indigo-900 mb-4 text-sm uppercase tracking-wider"><i class="fa-solid fa-calendar-plus mr-1"></i> Schedule Final Interview</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-bold text-indigo-800 mb-1">Interviewer</label>
                                    <select name="next_interviewer_id" class="w-full rounded border-indigo-300 p-2 text-sm bg-white focus:ring-indigo-500">
                                        <?php
                                            $empStmt = $pdo->query("SELECT e.id, CONCAT(e.first_name, ' ', e.last_name) AS full_name FROM employees e INNER JOIN users u ON u.employee_id = e.id INNER JOIN roles r ON u.role_id = r.id WHERE e.status = 'Active' AND r.name IN ('Branch Admin', 'Central HR', 'Super Admin') ORDER BY e.first_name");
                                            while ($emp = $empStmt->fetch()) {
                                                echo '<option value="' . $emp['id'] . '">' . h($emp['full_name']) . '</option>';
                                            }
                                        ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-indigo-800 mb-1">Date</label>
                                    <input type="date" name="next_interview_date" min="<?= date('Y-m-d') ?>" class="w-full rounded border-indigo-300 p-2 text-sm focus:ring-indigo-500">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold text-indigo-800 mb-1">Start Time</label>
                                        <input type="time" name="next_start_time" min="08:00" max="17:00" class="w-full rounded border-indigo-300 p-2 text-sm focus:ring-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-indigo-800 mb-1">End Time</label>
                                        <input type="time" name="next_end_time" min="08:00" max="18:00" class="w-full rounded border-indigo-300 p-2 text-sm focus:ring-indigo-500">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-6 flex justify-end border-t border-slate-100">
                        <button type="submit" class="bg-indigo-600 text-white px-10 py-3.5 rounded-xl font-bold text-lg hover:bg-indigo-700 transition-all transform hover:-translate-y-0.5 shadow-lg w-full flex items-center justify-center">
                            <i class="fa-solid fa-paper-plane mr-2"></i> Submit Scorecard & Finalize
                        </button>
                    </div>
                    
                </form>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        </div>
    </main>
    <script>
        const recSelect = document.getElementById('recommendationSelect');
        if (recSelect) {
            recSelect.addEventListener('change', function() {
                const scheduleContainer = document.getElementById('nextRoundSchedule');
                const inputs = scheduleContainer.querySelectorAll('input, select');
                const hireDiv = document.getElementById('hireDetails');
                const poolDiv = document.getElementById('globalPoolDetails');
                const rejectDiv = document.getElementById('rejectDetails');

                // Reset displays & required attributes
                hireDiv.style.display = 'none';
                poolDiv.style.display = 'none';
                rejectDiv.style.display = 'none';
                document.getElementById('hourly_wage').required = false;
                document.getElementById('global_pool_reason').required = false;
                document.getElementById('workspace_rejection_reason').required = false;

                if (this.value === 'Next Round') {
                    scheduleContainer.style.display = 'block';
                    inputs.forEach(i => i.setAttribute('required', 'required'));
                } else {
                    scheduleContainer.style.display = 'none';
                    inputs.forEach(i => i.removeAttribute('required'));
                }

                if (this.value === 'Hire') {
                    hireDiv.style.display = 'block';
                    document.getElementById('hourly_wage').required = true;
                } else if (this.value === 'Global Pool') {
                    poolDiv.style.display = 'block';
                    document.getElementById('global_pool_reason').required = true;
                } else if (this.value === 'Reject') {
                    rejectDiv.style.display = 'block';
                    document.getElementById('workspace_rejection_reason').required = true;
                }
            });

            // Auto-Calculate Monthly Wage
            const hourlyInput = document.getElementById('hourly_wage');
            const monthlyInput = document.getElementById('monthly_wage');

            if (hourlyInput) {
                hourlyInput.addEventListener('input', function(e) {
                    // Enforce strictly max 3 digits before decimal and up to 2 decimal places (Max 999.99)
                    let value = this.value.replace(/[^0-9.]/g, '');
                    const parts = value.split('.');
                    
                    if (parts.length > 2) {
                        value = parts[0] + '.' + parts.slice(1).join('');
                    }
                    if (parts[0].length > 3) {
                        parts[0] = parts[0].substring(0, 3);
                        value = parts.join('.');
                    }
                    if (parts[1] && parts[1].length > 2) {
                        parts[1] = parts[1].substring(0, 2);
                        value = parts[0] + '.' + parts[1];
                    }
                    
                    this.value = value;

                    const hourly = parseFloat(value) || 0;
                    if (hourly > 999.99) {
                        this.value = '999.99';
                    }
                    
                    // Auto-calculate monthly (176 hours = 8 hrs/day * 22 days)
                    const monthly = (parseFloat(this.value) || 0) * 176;
                    monthlyInput.value = monthly > 0 ? monthly.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '0.00';
                });
            }
        }
    </script>
</body>
</html>
