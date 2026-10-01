<?php
require_once __DIR__ . '/init.php';
require_login();
$user = current_user();

// Check if user is a Head Barista via employee position
$stmt = $pdo->prepare('SELECT position FROM employees WHERE id = (SELECT employee_id FROM users WHERE id = ?)');
$stmt->execute([$user['id']]);
$empPos = $stmt->fetchColumn();
if ($empPos && stripos($empPos, 'Head Barista') !== false) {
    $user['role'] = 'Head Barista';
}

if (!in_array($user['role'], ['Admin', 'Super Admin', 'HR', 'Head Barista'])) {
    http_response_code(403);
    die('Forbidden');
}

// Handle Deployment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'deploy') {
    $emp_id = (int)($_POST['employee_id'] ?? 0);
    if ($emp_id) {
        $stmt = $pdo->prepare('UPDATE employees SET status = "Active", sick_leave_balance = 14 WHERE id = ? AND status = "Trainee"');
        $stmt->execute([$emp_id]);
        flash('success', 'Trainee successfully deployed as an Active Barista! Sick Leave balance reset to 14 days.');
        redirect('performance');
    }
}

$whereClause = 'WHERE e.status IN ("Active", "Trainee")';
if ($user['role'] !== 'Super Admin') {
    $whereClause .= ' AND e.position != "HR"';
}
if ($user['role'] === 'Head Barista') {
    $whereClause .= ' AND e.position NOT LIKE "%Head%"';
}

// Fetch all employees with their average collaborative performance review and attendance stats
$sql = '
SELECT e.id, e.employee_id, CONCAT(e.first_name, " ",   e.last_name) AS full_name, e.status, e.position, e.employment_category, e.date_hired, e.last_raise_date,
       (SELECT SUM(TIMESTAMPDIFF(MINUTE, time_in, time_out))/60 FROM attendance WHERE employee_id = e.id AND time_out IS NOT NULL) AS total_hours,
       (SELECT COUNT(*) FROM attendance WHERE employee_id = e.id AND status = "Auto-Closed") AS penalties,
       pr.is_leadership, pr.score_kitchen, pr.score_cashier, pr.score_cleaning, pr.score_inventory, 
       pr.score_floor_mgmt, pr.score_staff_training, pr.score_quality_control, pr.score_reliability,
       pr.total_score, pr.review_count
FROM employees e
LEFT JOIN (
    SELECT p.employee_id,
           MAX(p.is_leadership) as is_leadership,
           AVG(p.score_kitchen) as score_kitchen,
           AVG(p.score_cashier) as score_cashier,
           AVG(p.score_cleaning) as score_cleaning,
           AVG(p.score_inventory) as score_inventory,
           AVG(p.score_floor_mgmt) as score_floor_mgmt,
           AVG(p.score_staff_training) as score_staff_training,
           AVG(p.score_quality_control) as score_quality_control,
           AVG(p.score_reliability) as score_reliability,
           AVG(p.total_score) as total_score,
           COUNT(p.id) as review_count
    FROM performance_reviews p
    JOIN employees emp ON p.employee_id = emp.id
    WHERE p.evaluation_type = "Official"
      AND (emp.last_raise_date IS NULL OR p.review_date > emp.last_raise_date)
    GROUP BY p.employee_id
) pr ON e.id = pr.employee_id
' . $whereClause . '
ORDER BY pr.total_score DESC, total_hours DESC
';

$stmt = $pdo->query($sql);
$employees = $stmt->fetchAll();

// Determine leadership dynamically based on current position
$leadership_roles = ['Head Barista', 'HR'];
foreach ($employees as &$emp) {
    if (in_array($emp['position'], $leadership_roles)) {
        $emp['is_leadership'] = 1;
    } else {
        $emp['is_leadership'] = 0;
    }
}
unset($emp);

$pageTitle = 'Performance Leaderboard';
require_once __DIR__ . '/includes/header.php';

function renderStars($score) {
    if (!$score) return '<span class="text-slate-300 text-xs italic">Unrated</span>';
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= ($i <= round($score)) 
            ? '<i class="fa-solid fa-star text-amber-400 text-xs"></i>' 
            : '<i class="fa-regular fa-star text-slate-300 text-xs"></i>';
    }
    return "<div class='flex gap-0.5' title='$score / 5'>$html</div>";
}
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Manpower Performance</h1>
        <p class="text-slate-500">Track all-rounder skill levels and promote trainees to active roster.</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <!-- Toolbar -->
    <div class="p-6 border-b border-slate-200 bg-slate-50 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <form autocomplete="off" class="flex flex-1 gap-4 items-end" onsubmit="event.preventDefault()">
            <div class="flex-1 max-w-sm">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-solid fa-search text-sm"></i>
                    </div>
                    <input type="text" id="perfSearch" class="pl-10 block w-full rounded-lg border-slate-300 bg-white border p-2 focus:ring-primary focus:border-primary text-sm transition-colors" placeholder="Search employees...">
                </div>
            </div>
            
            <div class="w-full md:w-auto flex flex-wrap gap-2">
                <?php if ($user['role'] === 'Head Barista'): ?>
                    <select id="perfStatus" class="block w-36 rounded-lg border-slate-300 bg-white border p-2 focus:ring-primary focus:border-primary text-sm transition-colors">
                        <option value="">All Statuses</option>
                        <option value="Active">Active</option>
                        <option value="Trainee">Trainee</option>
                    </select>
                    <select id="perfCategory" class="block w-36 rounded-lg border-slate-300 bg-white border p-2 focus:ring-primary focus:border-primary text-sm transition-colors">
                        <option value="">All Categories</option>
                        <option value="Full-Time">Full-Time</option>
                        <option value="Part-Time">Part-Time</option>
                    </select>
                <?php else: ?>
                    <select id="perfPosition" class="block w-36 rounded-lg border-slate-300 bg-white border p-2 focus:ring-primary focus:border-primary text-sm transition-colors">
                        <option value="">All Positions</option>
                        <option value="Barista">Barista</option>
                        <option value="Head Barista">Head Barista</option>
                        <option value="HR">HR</option>
                    </select>
                    <select id="perfRoleType" class="block w-36 rounded-lg border-slate-300 bg-white border p-2 focus:ring-primary focus:border-primary text-sm transition-colors">
                        <option value="">All Role Types</option>
                        <option value="Staff">Staff</option>
                        <option value="Leadership">Leadership</option>
                    </select>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200">
                    <th class="py-4 px-6 font-semibold text-xs text-slate-500 uppercase tracking-wider">Employee</th>
                    <th class="py-4 px-6 font-semibold text-xs text-slate-500 uppercase tracking-wider text-center">Avg Score</th>
                    <th class="py-4 px-6 font-semibold text-xs text-slate-500 uppercase tracking-wider">Skills Breakdown</th>
                    <th class="py-4 px-6 font-semibold text-xs text-slate-500 uppercase tracking-wider">Attendance</th>
                    <th class="py-4 px-6 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach($employees as $index => $emp): ?>
                    <tr class="hover:bg-slate-50 transition-colors perf-row"
                        data-search="<?= h(strtolower($emp['full_name'] . ' ' . $emp['employee_id'])) ?>"
                        data-position="<?= h($emp['position']) ?>"
                        data-role="<?= $emp['is_leadership'] ? 'Leadership' : 'Staff' ?>"
                        data-status="<?= h($emp['status']) ?>"
                        data-category="<?= h($emp['employment_category'] ?? '') ?>">
                        <td class="py-4 px-6">
                            <div class="flex items-center">
                                <div class="font-bold text-slate-400 mr-4 w-4 text-center">
                                    #<?= $index + 1 ?>
                                </div>
                                <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold mr-3 shadow-sm border border-indigo-200">
                                    <?= h(strtoupper(substr($emp['full_name'], 0, 1))) ?>
                                </div>
                                <div>
                                    <div class="font-bold text-slate-800 flex items-center gap-2">
                                        <?= h($emp['full_name']) ?>
                                        <?php if ($emp['status'] === 'Trainee'): ?>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700 uppercase tracking-wider">Trainee</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-[10px] text-slate-500 mt-1 uppercase tracking-wider font-semibold">
                                        <?= h($emp['employee_id']) ?> &bull; <?= h($emp['position']) ?>
                                    </div>
                                    <?php if ($emp['status'] === 'Trainee' && !empty($emp['date_hired'])): 
                                        $daysInTraining = floor((time() - strtotime($emp['date_hired'])) / (60 * 60 * 24));
                                        $isOverdue = $daysInTraining >= 60;
                                    ?>
                                        <div class="text-[10px] mt-1 <?= $isOverdue ? 'text-red-600 font-bold' : 'text-amber-600 font-medium' ?>">
                                            <i class="fa-solid fa-hourglass-half w-3"></i> <?= $daysInTraining ?> Days in Training <?= $isOverdue ? '(Overdue)' : '' ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        
                        <td class="py-4 px-6 text-center">
                            <div class="flex flex-col items-center justify-center">
                                <?php if ($emp['total_score']): ?>
                                    <div class="text-xl font-black text-slate-800 mb-1"><?= number_format($emp['total_score'], 1) ?></div>
                                    <?= renderStars($emp['total_score']) ?>
                                    
                                    <?php if ($emp['status'] !== 'Trainee'): ?>
                                        <div class="mt-2 w-full max-w-[120px]">
                                            <div class="flex justify-between text-[9px] text-slate-500 font-bold mb-1 uppercase tracking-wider">
                                                <span>Reviews</span>
                                                <span><?= min(4, (int)$emp['review_count']) ?>/4</span>
                                            </div>
                                            <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden flex">
                                                <?php 
                                                    $pct = min(100, ((int)$emp['review_count'] / 4) * 100);
                                                    $color = ($pct == 100 && $emp['total_score'] >= 4.5) ? 'bg-green-500' : 'bg-indigo-500';
                                                ?>
                                                <div class="<?= $color ?> h-1.5 rounded-full transition-all" style="width: <?= $pct ?>%"></div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-[9px] text-slate-400 mt-1 uppercase font-semibold">Based on <?= (int)$emp['review_count'] ?> review(s)</div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-slate-400 text-[10px] font-bold bg-slate-100 px-3 py-1 rounded-full uppercase tracking-wider border border-slate-200">Unrated</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        
                        <td class="py-4 px-6">
                            <div class="grid grid-cols-2 gap-x-6 gap-y-2 max-w-xs">
                                <?php 
                                    $is_hr = (stripos($emp['position'], 'HR') !== false);
                                    if ($is_hr): 
                                ?>
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-slate-500 font-medium w-20">System Setup</span>
                                        <?= renderStars($emp['score_setup'] ?? 0) ?>
                                    </div>
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-slate-500 font-medium w-20">Scheduling</span>
                                        <?= renderStars($emp['score_scheduling'] ?? 0) ?>
                                    </div>
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-slate-500 font-medium w-20">Recruitment</span>
                                        <?= renderStars($emp['score_recruitment'] ?? 0) ?>
                                    </div>
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-slate-500 font-medium w-20">Compliance</span>
                                        <?= renderStars($emp['score_compliance'] ?? 0) ?>
                                    </div>
                                <?php elseif ($emp['is_leadership']): ?>
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-slate-500 font-medium w-20">Floor Mgmt</span>
                                        <?= renderStars($emp['score_floor_mgmt']) ?>
                                    </div>
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-slate-500 font-medium w-20">Training</span>
                                        <?= renderStars($emp['score_staff_training']) ?>
                                    </div>
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-slate-500 font-medium w-20">Quality</span>
                                        <?= renderStars($emp['score_quality_control']) ?>
                                    </div>
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-slate-500 font-medium w-20">Reliability</span>
                                        <?= renderStars($emp['score_reliability']) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-slate-500 font-medium w-16" title="Food and Drink Preparation">Kitchen</span>
                                        <?= renderStars($emp['score_kitchen']) ?>
                                    </div>
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-slate-500 font-medium w-16" title="POS and Cashiering">Cashier</span>
                                        <?= renderStars($emp['score_cashier']) ?>
                                    </div>
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-slate-500 font-medium w-16" title="Station Cleaning">Cleaning</span>
                                        <?= renderStars($emp['score_cleaning']) ?>
                                    </div>
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-slate-500 font-medium w-16">Inventory</span>
                                        <?= renderStars($emp['score_inventory']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        
                        <td class="py-4 px-6">
                            <div class="space-y-1">
                                <div class="text-sm font-medium text-slate-700">
                                    <i class="fa-solid fa-clock text-slate-400 w-4"></i> <?= number_format((float)$emp['total_hours'], 1) ?> Hrs Total
                                </div>
                                <?php if ($emp['penalties'] > 0): ?>
                                    <div class="text-xs font-bold text-red-600">
                                        <i class="fa-solid fa-triangle-exclamation text-red-500 w-4"></i> <?= $emp['penalties'] ?> Penalties
                                    </div>
                                <?php else: ?>
                                    <div class="text-xs font-medium text-green-600">
                                        <i class="fa-solid fa-check text-green-500 w-4"></i> Perfect Record
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        
                        <td class="py-4 px-6 text-right">
                            <div class="flex flex-col items-end gap-2">
                                <?php if (($user['role'] === 'Head Barista' && !$emp['is_leadership']) || (in_array($user['role'], ['Admin', 'Super Admin', 'HR Admin', 'HR']) && $emp['is_leadership'])): ?>
                                    <button onclick="openEvaluateModal('performance_form.php?employee_id=<?= $emp['id'] ?>')" class="bg-indigo-50 text-indigo-700 hover:bg-indigo-600 hover:text-white border border-indigo-200 hover:border-indigo-600 px-4 py-1.5 rounded text-xs font-bold transition-colors w-28 text-center shadow-sm">
                                        <i class="fa-solid fa-clipboard-check mr-1"></i> Evaluate
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($emp['status'] === 'Trainee' && in_array($user['role'], ['Admin', 'Super Admin', 'HR Admin', 'HR'])): ?>
                                    <form autocomplete="off" method="POST" action="performance" class="w-28">
                                        <input type="hidden" name="action" value="deploy">
                                        <input type="hidden" name="employee_id" value="<?= $emp['id'] ?>">
                                        <?php if ($emp['total_score'] >= 3.0): ?>
                                            <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-1.5 rounded text-xs transition shadow-sm border border-green-700">
                                                <i class="fa-solid fa-rocket mr-1"></i> Deploy
                                            </button>
                                        <?php else: ?>
                                            <button type="button" onclick="showAlertModal('This trainee needs an average score of at least 3.0 to be deployed.')" class="w-full bg-slate-300 text-slate-500 font-bold py-1.5 rounded text-xs shadow-sm border border-slate-300 cursor-not-allowed">
                                                <i class="fa-solid fa-rocket mr-1"></i> Deploy
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if ($emp['status'] !== 'Trainee' && in_array($user['role'], ['Admin', 'Super Admin', 'HR Admin', 'HR'])): ?>
                                    <?php if ((int)$emp['review_count'] >= 4 && $emp['total_score'] >= 4.5): ?>
                                        <button onclick="openEvaluateModal('raise_form.php?employee_id=<?= $emp['id'] ?>')" class="bg-amber-100 text-amber-700 hover:bg-amber-500 hover:text-white border border-amber-300 hover:border-amber-600 px-4 py-1.5 rounded text-xs font-bold transition-colors w-28 text-center shadow-sm animate-pulse">
                                            <i class="fa-solid fa-arrow-trend-up mr-1"></i> Give Raise
                                        </button>
                                    <?php else: ?>
                                        <button type="button" disabled title="Needs 4 reviews and an average of 4.5+ since last raise." class="bg-slate-100 text-slate-400 border border-slate-200 px-4 py-1.5 rounded text-xs font-bold cursor-not-allowed w-28 text-center shadow-sm relative group">
                                            <i class="fa-solid fa-arrow-trend-up mr-1"></i> Give Raise
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Evaluation Modal -->
<div id="evalModal" class="fixed inset-0 z-50 bg-black/60 hidden flex items-center justify-center backdrop-blur-sm transition-all duration-300">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl h-[90vh] flex flex-col relative overflow-hidden transform scale-95 transition-transform duration-300" id="evalModalContent">
        <div class="p-5 border-b flex justify-between items-center bg-slate-50">
            <h3 class="font-bold text-slate-800 text-lg flex items-center"><i class="fa-solid fa-star text-amber-500 mr-2"></i> Employee Evaluation</h3>
            <button onclick="closeEvaluateModal()" class="text-slate-400 hover:text-red-500 bg-white hover:bg-red-50 rounded-full h-8 w-8 flex items-center justify-center transition-colors border border-slate-200 hover:border-red-200">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <iframe id="evalIframe" class="w-full flex-1" src=""></iframe>
    </div>
</div>

<!-- Alert Modal -->
<div id="alertModal" class="fixed inset-0 z-50 bg-black/60 hidden flex items-center justify-center backdrop-blur-sm transition-all duration-300">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm flex flex-col relative overflow-hidden transform scale-95 transition-transform duration-300" id="alertModalContent">
        <div class="p-4 border-b flex justify-between items-center bg-slate-50">
            <h3 class="font-bold text-slate-800 text-lg flex items-center"><i class="fa-solid fa-circle-info text-blue-500 mr-2"></i> Notice</h3>
        </div>
        <div class="p-6 text-slate-600 text-center font-medium" id="alertModalMsg">
            Message goes here.
        </div>
        <div class="p-4 border-t bg-slate-50 flex justify-end">
            <button onclick="closeAlertModal()" class="bg-primary hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-medium shadow-sm transition">OK</button>
        </div>
    </div>
</div>

<script>
function showAlertModal(msg) {
    document.getElementById('alertModalMsg').innerText = msg;
    const modal = document.getElementById('alertModal');
    const content = document.getElementById('alertModalContent');
    modal.classList.remove('hidden');
    setTimeout(() => content.classList.replace('scale-95', 'scale-100'), 10);
}
function closeAlertModal() {
    const modal = document.getElementById('alertModal');
    const content = document.getElementById('alertModalContent');
    content.classList.replace('scale-100', 'scale-95');
    setTimeout(() => modal.classList.add('hidden'), 300);
}

function openEvaluateModal(url) {
    const modal = document.getElementById('evalModal');
    const content = document.getElementById('evalModalContent');
    document.getElementById('evalIframe').src = url;
    modal.classList.remove('hidden');
    // slight delay to allow display:block to apply before animating scale
    setTimeout(() => content.classList.replace('scale-95', 'scale-100'), 10);
}

function closeEvaluateModal() {
    const modal = document.getElementById('evalModal');
    const content = document.getElementById('evalModalContent');
    content.classList.replace('scale-100', 'scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
        document.getElementById('evalIframe').src = '';
        window.location.reload(); 
    }, 300);
}

document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('perfSearch');
    const posSelect = document.getElementById('perfPosition');
    const roleSelect = document.getElementById('perfRoleType');
    const statusSelect = document.getElementById('perfStatus');
    const categorySelect = document.getElementById('perfCategory');
    const rows = document.querySelectorAll('.perf-row');

    function filterPerfTable() {
        const query = searchInput.value.toLowerCase();
        const pos = posSelect ? posSelect.value : '';
        const role = roleSelect ? roleSelect.value : '';
        const status = statusSelect ? statusSelect.value : '';
        const category = categorySelect ? categorySelect.value : '';

        rows.forEach(row => {
            const rowSearch = row.getAttribute('data-search');
            const rowPos = row.getAttribute('data-position');
            const rowRole = row.getAttribute('data-role');
            const rowStatus = row.getAttribute('data-status');
            const rowCategory = row.getAttribute('data-category');

            const matchSearch = rowSearch.includes(query);
            const matchPos = pos === '' || rowPos === pos;
            const matchRole = role === '' || rowRole === role;
            const matchStatus = status === '' || rowStatus === status;
            const matchCategory = category === '' || rowCategory === category;

            if (matchSearch && matchPos && matchRole && matchStatus && matchCategory) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    if (searchInput) searchInput.addEventListener('input', filterPerfTable);
    if (posSelect) posSelect.addEventListener('change', filterPerfTable);
    if (roleSelect) roleSelect.addEventListener('change', filterPerfTable);
    if (statusSelect) statusSelect.addEventListener('change', filterPerfTable);
    if (categorySelect) categorySelect.addEventListener('change', filterPerfTable);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

