<?php
require_once __DIR__ . '/../init.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Access control check (only logged in users can view this)
if (!isset($_SESSION['user']['id'])) {
    http_response_code(403);
    echo '<div class="text-red-500 p-4">Unauthorized access.</div>';
    exit;
}

$applicant_id = (int)($_GET['id'] ?? 0);
if (!$applicant_id) {
    echo '<div class="text-red-500 p-4">Invalid applicant ID.</div>';
    exit;
}

// Fetch applicant details
$stmt = $pdo->prepare('
    SELECT a.*, 
           CONCAT(a.first_name, " ", a.last_name) AS full_name,
           (SELECT status FROM interviews WHERE applicant_id = a.id ORDER BY created_at DESC LIMIT 1) as latest_interview_status,
           (SELECT interview_date FROM interviews WHERE applicant_id = a.id ORDER BY created_at DESC LIMIT 1) as latest_interview_date,
           (SELECT interview_time FROM interviews WHERE applicant_id = a.id ORDER BY created_at DESC LIMIT 1) as latest_interview_time
    FROM applicants a
    WHERE a.id = ?
');
$stmt->execute([$applicant_id]);
$app = $stmt->fetch();

if (!$app) {
    echo '<div class="text-red-500 p-4">Applicant not found.</div>';
    exit;
}

// Fetch pending interviews
$pendingInterviews = [];
$pendingStmt = $pdo->query("SELECT id, applicant_id FROM interviews WHERE status = 'Completed' AND id NOT IN (SELECT interview_id FROM interview_scorecards)");
while ($row = $pendingStmt->fetch(PDO::FETCH_ASSOC)) {
    $pendingInterviews[$row['applicant_id']] = $row['id'];
}

$stage_name = $app['stage'];
?>

<div class="flex justify-between items-start mb-6">
    <div>
        <h3 class="text-xl font-bold text-slate-800 mb-1"><?= h($app['full_name']) ?></h3>
        <p class="text-sm text-slate-500"><i class="fa-solid fa-briefcase mr-1"></i> <?= h($app['position_applied']) ?> &bull; <?= h($app['employment_category'] ?? 'Full-Time') ?></p>
        <p class="text-xs text-slate-400 mt-1"><i class="fa-solid fa-clock mr-1"></i> Applied <?= date('M j, Y', strtotime($app['created_at'])) ?></p>
    </div>
    <button onclick="openEditModal('applicant_form.php?id=<?= $app['id'] ?>')" class="text-slate-400 hover:text-amber-500 bg-slate-100 p-2 rounded transition" title="Edit Applicant">
        <i class="fa-solid fa-pen"></i>
    </button>
</div>

<!-- Interview Status Alerts -->
<?php 
    if ($app['latest_interview_status'] === 'Scheduled') {
        $interview_dt = $app['latest_interview_date'] . ' ' . $app['latest_interview_time'];
        if (strtotime($interview_dt) < time()) {
?>
            <div class="bg-red-50 border border-red-200 p-4 rounded-lg mb-6">
                <div class="flex items-start gap-3 text-red-800">
                    <i class="fa-solid fa-triangle-exclamation text-xl mt-0.5"></i>
                    <div>
                        <h4 class="font-bold">Overdue / Missed Interview</h4>
                        <p class="text-sm mt-1 text-red-600"><?= date('l, M j, Y \a\t g:i A', strtotime($interview_dt)) ?></p>
                    </div>
                </div>
            </div>
<?php 
        } else {
?>
            <div class="bg-blue-50 border border-blue-200 p-4 rounded-lg mb-6">
                <div class="flex items-start gap-3 text-blue-800">
                    <i class="fa-regular fa-calendar-check text-xl mt-0.5"></i>
                    <div>
                        <h4 class="font-bold">Upcoming Interview</h4>
                        <p class="text-sm mt-1 text-blue-600"><?= date('l, M j, Y \a\t g:i A', strtotime($interview_dt)) ?></p>
                    </div>
                </div>
            </div>
<?php 
        }
    } elseif ($app['latest_interview_status'] === 'Completed') {
?>
    <div class="bg-green-50 border border-green-200 p-4 rounded-lg mb-6 flex items-center gap-3 text-green-800">
        <i class="fa-solid fa-check-circle text-xl"></i>
        <span class="font-medium">Previous Round Completed</span>
    </div>
<?php } ?>

<!-- Documents -->
<?php if(!empty($app['resume']) || !empty($app['valid_id_photo'])): ?>
<div class="mb-6">
    <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider mb-3">Documents</h4>
    <div class="space-y-2">
        <?php if(!empty($app['resume'])): ?>
        <a href="file_proxy.php?file=applicants/<?= h($app['resume']) ?>" target="_blank" class="flex items-center justify-between bg-white border border-slate-200 p-3 rounded-lg hover:border-indigo-300 hover:shadow-sm transition group">
            <span class="text-sm font-medium text-slate-700 group-hover:text-indigo-600"><i class="fa-solid fa-file-pdf text-red-500 mr-2"></i> Resume</span>
            <i class="fa-solid fa-external-link-alt text-slate-400 text-xs"></i>
        </a>
        <?php endif; ?>
        <?php if(!empty($app['valid_id_photo'])): ?>
        <a href="file_proxy.php?file=applicants/<?= h($app['valid_id_photo']) ?>" target="_blank" class="flex items-center justify-between bg-white border border-slate-200 p-3 rounded-lg hover:border-indigo-300 hover:shadow-sm transition group">
            <span class="text-sm font-medium text-slate-700 group-hover:text-indigo-600"><i class="fa-solid fa-id-card text-blue-500 mr-2"></i> Valid ID</span>
            <i class="fa-solid fa-external-link-alt text-slate-400 text-xs"></i>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Actions -->
<?php if (empty($_GET['readonly'])): ?>
<div class="mt-8 pt-6 border-t border-slate-200">
    <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider mb-4">Pipeline Actions</h4>
    
    <?php if ($stage_name === 'Hireable'): ?>
        <button type="button" onclick="convertToEmployee(<?= $app['id'] ?>)" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg shadow-sm transition flex items-center justify-center text-lg">
            <i class="fa-solid fa-user-check mr-2"></i> Convert to Employee Account
        </button>
        
        <script>
        function convertToEmployee(applicantId) {
            if (!confirm('Are you sure you want to convert this applicant into an active employee account?')) return;
            
            let btn = event.currentTarget;
            let originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Processing...';
            btn.disabled = true;
            
            let formData = new FormData();
            formData.append('applicant_id', applicantId);
            
            fetch('api/convert_to_employee.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    // Update the master list row visually if it exists
                    const row = document.querySelector(`tr[data-applicant-id="${applicantId}"]`);
                    if (row) {
                        const statusBadge = row.querySelector('.stage-badge');
                        if (statusBadge) {
                            statusBadge.className = 'stage-badge px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800';
                            statusBadge.innerText = 'Hired';
                        }
                    }
                    alert(data.message);
                    if(typeof closeApplicantDrawer === 'function') closeApplicantDrawer();
                } else {
                    alert('Error: ' + data.message);
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }
            })
            .catch(err => {
                alert('Network error.');
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            });
        }
        </script>
    <?php else: ?>
        <?php if (isset($pendingInterviews[$app['id']])): ?>
            <div class="space-y-3">
                <a href="interview_workspace?interview_id=<?= $pendingInterviews[$app['id']] ?>" class="w-full text-center bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-3 rounded-lg shadow-sm transition block">
                    <i class="fa-solid fa-laptop-file mr-2"></i> Open Evaluation Workspace
                </a>
                <button type="button" onclick="openRejectModal(<?= $app['id'] ?>)" class="w-full bg-white text-red-600 border border-red-200 hover:bg-red-50 font-medium py-2 rounded-lg transition">
                    <i class="fa-solid fa-times mr-1"></i> Reject Candidate
                </button>
            </div>
        <?php else: ?>
            <form autocomplete="off" method="POST" action="applications" class="space-y-4" id="form-stage-<?= $app['id'] ?>">
                <input type="hidden" name="action" value="change_stage">
                <input type="hidden" name="applicant_id" value="<?= $app['id'] ?>">
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Move forward to:</label>
                    <select name="new_stage" class="w-full border-slate-300 rounded-lg py-2 focus:ring-primary focus:border-primary">
                        <option value="" disabled selected>Select next stage...</option>
                        <?php if($stage_name === 'New'): ?>
                            <option value="Initial Interview">Initial Interview</option>
                            <option value="Final Interview">Final Interview</option>
                        <?php endif; ?>
                        <?php if($stage_name === 'Initial Interview'): ?>
                            <option value="Final Interview">Final Interview</option>
                        <?php endif; ?>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>
                
                <button type="button" onclick="handleStageChange(this.form.elements['new_stage'], <?= $app['id'] ?>, <?= json_encode(htmlspecialchars($app['full_name'], ENT_QUOTES, 'UTF-8')) ?>, <?= json_encode(htmlspecialchars($app['email'], ENT_QUOTES, 'UTF-8')) ?>)" class="w-full bg-slate-800 hover:bg-slate-900 text-white font-medium py-2 rounded-lg transition">
                    Update Stage
                </button>
            </form>
            
            <?php if (in_array($stage_name, ['Initial Interview', 'Final Interview'])): ?>
            <hr class="border-slate-200 my-4">
            <button type="button" onclick="markAsHireable(<?= $app['id'] ?>)" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg shadow-sm transition flex items-center justify-center text-sm">
                <i class="fa-solid fa-check-double mr-2"></i> Mark as Hireable
            </button>
            <?php endif; ?>
            
            <script>
            function markAsHireable(applicantId) {
                if (!confirm('Are you sure you want to mark this candidate as Hireable and remove them from the active pipeline?')) return;
                
                let formData = new FormData();
                formData.append('applicant_id', applicantId);
                formData.append('new_stage', 'Hireable');
                
                fetch('api/update_applicant_stage.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Close drawer
                        if(typeof closeApplicantDrawer === 'function') closeApplicantDrawer();
                        
                        // Remove Kanban card
                        const card = document.querySelector(`.applicant-card[data-applicant-id="${applicantId}"]`);
                        if (card) {
                            card.remove();
                            if(typeof window.updateStageCounts === 'function') window.updateStageCounts();
                        }
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(err => {
                    alert('Network error.');
                });
            }
            </script>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php endif; ?>
