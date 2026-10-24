<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/core/Output.php';
require_once __DIR__ . '/../app/core/Audit.php';

Auth::requireLogin();
Rbac::require_permission('hr.employee.view');

global $pdo;

$currentUser = Auth::user();
$interview_id = isset($_GET['interview_id']) ? (int)$_GET['interview_id'] : 0;

// If applicant_id is given but no interview_id, locate latest interview or create placeholder
if ($interview_id <= 0 && !empty($_GET['applicant_id'])) {
    $appId = (int)$_GET['applicant_id'];
    $findStmt = $pdo->prepare("SELECT id FROM interviews WHERE applicant_id = ? ORDER BY id DESC LIMIT 1");
    $findStmt->execute([$appId]);
    $interview_id = (int)($findStmt->fetchColumn() ?: 0);

    if ($interview_id <= 0) {
        $ins = $pdo->prepare("INSERT INTO interviews (applicant_id, interview_date, interview_time, end_time, scheduled_at, stage, status, created_at) VALUES (?, CURDATE(), '09:00', '10:00', NOW(), 'Initial Interview', 'Scheduled', NOW())");
        $ins->execute([$appId]);
        $interview_id = (int)$pdo->lastInsertId();
    }
}

if ($interview_id <= 0) {
    header("Location: fika_hrms_applications.php?tab=calendar");
    exit;
}

// Fetch interview & applicant data
$stmt = $pdo->prepare("
    SELECT i.*, 
           a.id AS applicant_id, a.first_name, a.last_name, a.email, a.phone, 
           a.position, a.position_applied, a.resume, a.resume_path, a.stage, a.employment_category, a.branch_id,
           b.name AS branch_name,
           CONCAT(e.first_name, ' ', e.last_name) AS interviewer_name
    FROM interviews i
    JOIN applicants a ON i.applicant_id = a.id
    LEFT JOIN branches b ON a.branch_id = b.id
    LEFT JOIN employees e ON i.interviewer_id = e.id
    WHERE i.id = ?
");
$stmt->execute([$interview_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    header("Location: fika_hrms_applications.php?tab=calendar");
    exit;
}

$candidateFullName = trim($data['first_name'] . ' ' . $data['last_name']);
$candidatePosition = $data['position_applied'] ?: ($data['position'] ?: 'Staff');

// Check existing scorecard for this interview
$scStmt = $pdo->prepare("SELECT * FROM interview_scorecards WHERE interview_id = ?");
$scStmt->execute([$interview_id]);
$existingScorecard = $scStmt->fetch(PDO::FETCH_ASSOC);

// Fetch historical scorecards for this applicant
$histStmt = $pdo->prepare("
    SELECT s.*, i.interview_date, i.stage as interview_stage, 
           CONCAT(e.first_name, ' ', e.last_name) AS interviewer_name 
    FROM interview_scorecards s 
    INNER JOIN interviews i ON s.interview_id = i.id 
    LEFT JOIN employees e ON i.interviewer_id = e.id 
    WHERE i.applicant_id = ? AND i.id != ? 
    ORDER BY i.interview_date DESC
");
$histStmt->execute([$data['applicant_id'], $interview_id]);
$historicalScorecards = $histStmt->fetchAll(PDO::FETCH_ASSOC);

$error = null;
$success = null;

// Handle Scorecard Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existingScorecard) {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Csrf::verify($token)) {
        $error = "Invalid security token. Please refresh and try again.";
    } else {
        $technical_score = max(1, min(5, (int)($_POST['technical_score'] ?? 3)));
        $communication_score = max(1, min(5, (int)($_POST['communication_score'] ?? 3)));
        $culture_score = max(1, min(5, (int)($_POST['culture_score'] ?? 3)));
        $reliability_score = max(1, min(5, (int)($_POST['reliability_score'] ?? 3)));
        $problem_solving_score = max(1, min(5, (int)($_POST['problem_solving_score'] ?? 3)));

        $strengths = trim($_POST['strengths'] ?? '');
        $concerns = trim($_POST['concerns'] ?? '');
        $recommendation = $_POST['recommendation'] ?? 'Next Round';

        $rawWage = str_replace(',', '', trim($_POST['hourly_wage'] ?? ''));
        $hourly_wage = $rawWage !== '' ? max(0, floatval($rawWage)) : null;
        $monthly_wage = $hourly_wage !== null ? round($hourly_wage * 176, 2) : null;
        $global_pool_reason = trim($_POST['global_pool_reason'] ?? '');
        $rejection_reason = trim($_POST['rejection_reason'] ?? '');
        $createdBy = $currentUser['username'] ?? 'User';

        $ins = $pdo->prepare("
            INSERT INTO interview_scorecards 
                (interview_id, technical_score, communication_score, culture_score, reliability_score, problem_solving_score, strengths, concerns, recommendation, hourly_wage, monthly_wage, global_pool_reason, created_by, created_at)
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $ins->execute([
            $interview_id, $technical_score, $communication_score, $culture_score, $reliability_score, $problem_solving_score,
            $strengths, $concerns, $recommendation, $hourly_wage, $monthly_wage, $global_pool_reason, $createdBy
        ]);

        // Update interview status to Completed
        $upInt = $pdo->prepare("UPDATE interviews SET status = 'Completed' WHERE id = ?");
        $upInt->execute([$interview_id]);

        // Progress applicant stage
        $newStage = $data['stage'];
        if ($recommendation === 'Hire') {
            $upApp = $pdo->prepare("UPDATE applicants SET stage = 'Hireable', status = 'SHORTLISTED' WHERE id = ?");
            $upApp->execute([$data['applicant_id']]);
            $newStage = 'Hireable';
        } elseif ($recommendation === 'Next Round') {
            $upApp = $pdo->prepare("UPDATE applicants SET stage = 'Final Interview', status = 'REVIEWING' WHERE id = ?");
            $upApp->execute([$data['applicant_id']]);
            $newStage = 'Final Interview';
        } elseif ($recommendation === 'Reject') {
            $upApp = $pdo->prepare("UPDATE applicants SET stage = 'Rejected', status = 'REJECTED', rejection_reason = ? WHERE id = ?");
            $upApp->execute([$rejection_reason ?: 'Did not meet criteria', $data['applicant_id']]);
            $newStage = 'Rejected';
        } elseif ($recommendation === 'Global Pool') {
            $upApp = $pdo->prepare("UPDATE applicants SET stage = 'Hireable', status = 'SHORTLISTED', branch_id = NULL WHERE id = ?");
            $upApp->execute([$data['applicant_id']]);
            $newStage = 'Hireable (Global Pool)';
        }

        $logStmt = $pdo->prepare("INSERT INTO applicant_logs (applicant_id, user_name, action, created_at) VALUES (?, ?, ?, NOW())");
        $logStmt->execute([$data['applicant_id'], $createdBy, "Scorecard submitted. Decision: {$recommendation}. Moved to {$newStage}"]);

        $success = "Interview evaluation scorecard recorded successfully.";

        // Reload scorecard
        $scStmt = $pdo->prepare("SELECT * FROM interview_scorecards WHERE interview_id = ?");
        $scStmt->execute([$interview_id]);
        $existingScorecard = $scStmt->fetch(PDO::FETCH_ASSOC);
    }
}

$csrfToken = Csrf::getToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interview Workspace &middot; <?= e($candidateFullName) ?> &middot; Fika HRMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans antialiased min-h-screen flex">

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto">
        <!-- Top Nav -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
                <div class="flex items-center space-x-3">
                    <a href="fika_hrms_applications.php?tab=calendar" class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 flex items-center justify-center transition">
                        <i class="fa-solid fa-arrow-left text-sm"></i>
                    </a>
                    <div>
                        <h1 class="font-bold text-lg text-slate-900 leading-tight">Interview Evaluation Workspace</h1>
                        <p class="text-xs text-slate-500">Candidate: <?= e($candidateFullName) ?> &bull; <?= e($candidatePosition) ?></p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <?php if ($existingScorecard): ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            <i class="fa-solid fa-circle-check"></i> Scorecard Submitted
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200 animate-pulse">
                            <i class="fa-solid fa-pen-to-square"></i> Evaluation in Progress
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <?php if ($error): ?>
                <div class="mb-6 bg-rose-50 text-rose-800 border border-rose-200 rounded-xl p-4 flex items-center gap-3 text-sm font-medium shadow-sm">
                    <i class="fa-solid fa-circle-exclamation text-rose-600 text-base"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="mb-6 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-xl p-4 flex items-center gap-3 text-sm font-medium shadow-sm">
                    <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
                    <span><?= e($success) ?></span>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column: Candidate Dossier -->
                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 rounded-xl bg-teal-50 text-teal-700 font-black flex items-center justify-center text-lg border border-teal-200">
                                <?= strtoupper(substr($data['first_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <h2 class="font-bold text-slate-900 text-base"><?= e($candidateFullName) ?></h2>
                                <p class="text-xs text-slate-500"><?= e($candidatePosition) ?></p>
                            </div>
                        </div>

                        <div class="space-y-3 text-xs divide-y divide-slate-100">
                            <div class="pt-2 flex justify-between">
                                <span class="text-slate-400">Target Branch:</span>
                                <span class="font-bold text-slate-800"><?= e($data['branch_name'] ?: 'Main Branch') ?></span>
                            </div>
                            <div class="pt-2 flex justify-between">
                                <span class="text-slate-400">Email:</span>
                                <span class="font-medium text-slate-700"><?= e($data['email']) ?></span>
                            </div>
                            <div class="pt-2 flex justify-between">
                                <span class="text-slate-400">Phone:</span>
                                <span class="font-medium text-slate-700"><?= e($data['phone']) ?></span>
                            </div>
                            <div class="pt-2 flex justify-between">
                                <span class="text-slate-400">Interview Round:</span>
                                <span class="font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded"><?= e($data['stage']) ?></span>
                            </div>
                            <div class="pt-2 flex justify-between">
                                <span class="text-slate-400">Scheduled Date:</span>
                                <span class="font-bold text-slate-800"><?= date('M d, Y', strtotime($data['interview_date'])) ?> (<?= date('h:i A', strtotime($data['interview_time'])) ?>)</span>
                            </div>
                            <?php if (!empty($data['interviewer_name'])): ?>
                            <div class="pt-2 flex justify-between">
                                <span class="text-slate-400">Assigned Evaluator:</span>
                                <span class="font-bold text-slate-800"><?= e($data['interviewer_name']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($data['resume_path']) || !empty($data['resume'])): 
                            $resumeLink = $data['resume_path'] ?: '../storage/resumes/' . $data['resume'];
                        ?>
                        <div class="mt-5 pt-4 border-t border-slate-100">
                            <a href="<?= e($resumeLink) ?>" target="_blank" class="w-full flex items-center justify-center gap-2 py-2 px-3 bg-slate-50 hover:bg-slate-100 text-slate-700 rounded-xl text-xs font-bold border border-slate-200 transition">
                                <i class="fa-solid fa-file-pdf text-red-500"></i>
                                <span>Inspect Resume / CV</span>
                                <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-slate-400 ml-auto"></i>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Historical Scorecards -->
                    <?php if (!empty($historicalScorecards)): ?>
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                        <h3 class="font-bold text-xs uppercase tracking-wider text-slate-500 mb-3 flex items-center gap-2">
                            <i class="fa-solid fa-clock-rotate-left text-teal-600"></i> Past Interview Rounds
                        </h3>
                        <div class="space-y-3">
                            <?php foreach ($historicalScorecards as $hs): ?>
                            <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs space-y-1.5">
                                <div class="flex items-center justify-between font-bold text-slate-800">
                                    <span><?= e($hs['interview_stage'] ?: 'Previous Round') ?></span>
                                    <span class="text-[10px] text-slate-400"><?= date('M d, Y', strtotime($hs['interview_date'])) ?></span>
                                </div>
                                <div class="flex items-center gap-2 text-slate-600 text-[11px]">
                                    <span>Recommendation: <strong class="text-indigo-700"><?= e($hs['recommendation']) ?></strong></span>
                                    <?php if (!empty($hs['created_by'])): ?>
                                        <span>&bull; By: <?= e($hs['created_by']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($hs['strengths'])): ?>
                                    <p class="text-[11px] text-slate-600 italic">"<?= e(substr($hs['strengths'], 0, 100)) ?>..."</p>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Right Column: Evaluation Scorecard Form -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8">
                        <div class="border-b border-slate-100 pb-4 mb-6">
                            <h2 class="font-black text-xl text-slate-900">Structured Competency Rubric</h2>
                            <p class="text-xs text-slate-500 mt-1">Rate candidate from 1 (Unsatisfactory) to 5 (Exemplary) across core operational competencies</p>
                        </div>

                        <form method="POST" action="fika_hrms_interview_workspace.php?interview_id=<?= $interview_id ?>" class="space-y-6">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                            <!-- 5 Competency Rubric Rows -->
                            <div class="space-y-5">
                                <?php
                                $rubrics = [
                                    'technical_score' => ['label' => 'Technical Competence', 'desc' => 'Espresso calibration, milk steaming, recipe execution, equipment care', 'icon' => 'fa-mug-hot'],
                                    'communication_score' => ['label' => 'Customer Service & Communication', 'desc' => 'Warm greeting presence, order clarity, conflict de-escalation', 'icon' => 'fa-comments'],
                                    'culture_score' => ['label' => 'Culture & Values Alignment', 'desc' => 'Respectful team player, embraces slow hours mindset, punctuality', 'icon' => 'fa-heart'],
                                    'reliability_score' => ['label' => 'Operational Reliability', 'desc' => 'Dependable attendance, shift flexibility, follows food safety standards', 'icon' => 'fa-shield-halved'],
                                    'problem_solving_score' => ['label' => 'Speed & Problem Solving', 'desc' => 'Peak rush pacing, composure under pressure, handling order mistakes', 'icon' => 'fa-bolt']
                                ];

                                foreach ($rubrics as $field => $r):
                                    $val = $existingScorecard ? (int)($existingScorecard[$field] ?? 3) : 3;
                                ?>
                                <div class="p-4 bg-slate-50/70 border border-slate-200 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                    <div class="space-y-0.5">
                                        <div class="font-bold text-sm text-slate-900 flex items-center gap-2">
                                            <i class="fa-solid <?= $r['icon'] ?> text-teal-600 text-xs"></i>
                                            <span><?= e($r['label']) ?></span>
                                        </div>
                                        <p class="text-[11px] text-slate-500"><?= e($r['desc']) ?></p>
                                    </div>

                                    <?php if ($existingScorecard): ?>
                                        <div class="flex items-center gap-1">
                                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                                <i class="fa-solid fa-star <?= $s <= $val ? 'text-amber-500' : 'text-slate-200' ?> text-sm"></i>
                                            <?php endfor; ?>
                                            <span class="ml-2 font-black text-xs text-slate-800"><?= $val ?>/5</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="flex items-center gap-2">
                                            <select name="<?= $field ?>" class="border border-slate-300 rounded-xl px-3 py-1.5 text-xs font-bold text-slate-800 bg-white focus:ring-2 focus:ring-teal-500">
                                                <option value="1" <?= $val === 1 ? 'selected' : '' ?>>1 &mdash; Unsatisfactory</option>
                                                <option value="2" <?= $val === 2 ? 'selected' : '' ?>>2 &mdash; Needs Development</option>
                                                <option value="3" <?= $val === 3 ? 'selected' : '' ?>>3 &mdash; Meets Standards</option>
                                                <option value="4" <?= $val === 4 ? 'selected' : '' ?>>4 &mdash; Exceeds Standards</option>
                                                <option value="5" <?= $val === 5 ? 'selected' : '' ?>>5 &mdash; Exemplary</option>
                                            </select>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Qualitative Feedback -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Observed Key Strengths *</label>
                                    <?php if ($existingScorecard): ?>
                                        <p class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-700 whitespace-pre-wrap"><?= e($existingScorecard['strengths'] ?: 'No notes recorded.') ?></p>
                                    <?php else: ?>
                                        <textarea name="strengths" rows="3" required placeholder="Describe notable candidate strengths..." class="w-full border border-slate-300 rounded-xl p-3 text-xs focus:ring-2 focus:ring-teal-500"></textarea>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Concerns / Areas for Coaching *</label>
                                    <?php if ($existingScorecard): ?>
                                        <p class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-700 whitespace-pre-wrap"><?= e($existingScorecard['concerns'] ?: 'No notes recorded.') ?></p>
                                    <?php else: ?>
                                        <textarea name="concerns" rows="3" required placeholder="Describe any developmental areas or risks..." class="w-full border border-slate-300 rounded-xl p-3 text-xs focus:ring-2 focus:ring-teal-500"></textarea>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Proposed Wage & Final Decision -->
                            <div class="p-5 bg-teal-50/50 border border-teal-200 rounded-xl space-y-4">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Proposed Hourly Wage (₱)</label>
                                        <?php if ($existingScorecard): ?>
                                            <p class="font-bold text-slate-800 text-sm">₱<?= number_format((float)($existingScorecard['hourly_wage'] ?? 0), 2) ?></p>
                                        <?php else: ?>
                                            <input type="number" step="0.50" min="0" name="hourly_wage" placeholder="e.g. 75.00" class="w-full border border-slate-300 rounded-xl p-2.5 text-xs font-mono font-bold bg-white focus:ring-2 focus:ring-teal-500">
                                            <p class="text-[10px] text-slate-400 mt-1">Calculates baseline monthly equivalent (~176 regular monthly hours)</p>
                                        <?php endif; ?>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Final Evaluation Decision *</label>
                                        <?php if ($existingScorecard): ?>
                                            <span class="inline-flex px-3 py-1 rounded-lg text-xs font-bold bg-indigo-100 text-indigo-800">
                                                <?= e($existingScorecard['recommendation']) ?>
                                            </span>
                                        <?php else: ?>
                                            <select name="recommendation" required class="w-full border border-slate-300 rounded-xl p-2.5 text-xs font-bold text-slate-800 bg-white focus:ring-2 focus:ring-teal-500">
                                                <option value="Next Round">Advance to Next Round (Final Interview)</option>
                                                <option value="Hire">Extend Job Offer (Hireable)</option>
                                                <option value="Reject">Reject Application</option>
                                                <option value="Global Pool">Keep in Global Talent Pool</option>
                                            </select>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <?php if (!$existingScorecard): ?>
                            <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                                <a href="fika_hrms_applications.php?tab=calendar" class="px-5 py-2.5 border border-slate-300 rounded-xl text-slate-700 hover:bg-slate-50 text-xs font-bold transition">
                                    Cancel
                                </a>
                                <button type="submit" class="px-6 py-2.5 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-black shadow-sm transition">
                                    Submit Evaluation Scorecard
                                </button>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
