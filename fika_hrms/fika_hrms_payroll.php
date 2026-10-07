<?php
require __DIR__ . '/../app/bootstrap.php';
Auth::requireLogin();
Rbac::require_permission('payroll.view');

global $pdo;
$user = Auth::user();

list($scopeSql, $params) = Rbac::branch_scope('pr');

$stmt = $pdo->prepare("
    SELECT pr.*, b.name as branch_name,
           (SELECT COUNT(*) FROM payroll_items pi WHERE pi.payroll_run_id = pr.id) as emp_count,
           (SELECT SUM(net_pay) FROM payroll_items pi WHERE pi.payroll_run_id = pr.id) as total_net,
           u.username as processed_by_username
    FROM payroll_runs pr
    LEFT JOIN branches b ON pr.branch_id = b.id
    LEFT JOIN users u ON pr.processed_by = u.id
    WHERE 1=1 $scopeSql
    ORDER BY pr.period_start DESC, pr.id DESC
");
$stmt->execute($params);
$runs = $stmt->fetchAll();

// Fetch branches for dropdown
$branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();

$canManage = Rbac::can('payroll.manage');

function can_perform_action($run, $action, $user, $pdo) {
    if (!Rbac::can('payroll.manage')) return false;

    if ($action === 'APPROVE' && $run['processed_by'] == $user['id']) {
        return false; // Drafter cannot approve
    }

    if ($user['employee_id']) {
        $check = $pdo->prepare("SELECT id FROM payroll_items WHERE payroll_run_id = ? AND employee_id = ?");
        $check->execute([$run['id'], $user['employee_id']]);
        if ($check->fetch()) {
            return false;
        }
    }
    return true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payroll Runs - Fika HRMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800 font-sans">
    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Payroll Runs</h1>
                <p class="mt-2 text-sm text-gray-600">Manage and review payroll periods.</p>
            </div>
            <div class="text-right flex gap-3">
                <a href="pos_dashboard.php" class="text-blue-600 hover:underline mt-2 inline-block">Back to Dashboard</a>
                <?php if ($canManage): ?>
                    <button onclick="document.getElementById('generateModal').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded shadow">
                        Generate New Run
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-white shadow rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Scope / Target</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Period</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-500 uppercase">Employees</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase">Total Net Pay</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Processed By</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($runs as $r): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-900">
                                <span class="bg-gray-200 text-xs px-2 py-1 rounded font-bold mr-2"><?= e($r['scope']) ?></span>
                                <?= $r['scope'] === 'BRANCH' ? e($r['branch_name']) : 'All Branches / HQ' ?>
                            </td>
                            <td class="px-4 py-3 text-gray-700 font-mono text-xs">
                                <?= e($r['period_start']) ?> to <?= e($r['period_end']) ?>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-900 font-bold">
                                <?= (int)$r['emp_count'] ?>
                            </td>
                            <td class="px-4 py-3 text-right text-gray-900 font-mono">
                                <?= format_money_decimal($r['total_net']) ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <?php 
                                    $bg = 'bg-gray-100 text-gray-800';
                                    if ($r['status'] === 'GENERATED') $bg = 'bg-blue-100 text-blue-800';
                                    if ($r['status'] === 'APPROVED') $bg = 'bg-green-100 text-green-800';
                                    if ($r['status'] === 'RELEASED') $bg = 'bg-purple-100 text-purple-800';
                                ?>
                                <span class="px-2 py-1 text-xs rounded-full font-bold <?= $bg ?>">
                                    <?= e($r['status']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 text-xs">
                                <?= e($r['processed_by_username']) ?><br>
                                <span class="text-gray-400"><?= e($r['created_at']) ?></span>
                            </td>
                            <td class="px-4 py-3 text-center space-x-2">
                                <a href="fika_hrms_payroll_run.php?id=<?= $r['id'] ?>" class="text-blue-600 hover:underline text-xs font-bold">View</a>
                                <?php if ($r['status'] === 'DRAFT' || $r['status'] === 'GENERATED'): ?>
                                    <?php if (can_perform_action($r, 'APPROVE', $user, $pdo)): ?>
                                        <button onclick="approveRun(<?= $r['id'] ?>)" class="text-green-600 hover:underline text-xs font-bold">Approve</button>
                                    <?php endif; ?>
                                    <button onclick="clearRun(<?= $r['id'] ?>)" class="text-red-600 hover:underline text-xs font-bold">Clear Draft</button>
                                <?php elseif ($r['status'] === 'APPROVED'): ?>
                                    <?php if (can_perform_action($r, 'RELEASE', $user, $pdo)): ?>
                                        <button onclick="releaseRun(<?= $r['id'] ?>)" class="text-purple-600 hover:underline text-xs font-bold">Release</button>
                                    <?php endif; ?>
                                <?php elseif ($r['status'] === 'RELEASED'): ?>
                                    <?php if (Rbac::can('payroll.export')): ?>
                                        <a href="api/fika_hrms_payroll_export.php?run_id=<?= $r['id'] ?>" class="text-gray-600 hover:underline text-xs font-bold" target="_blank">Export CSV</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(empty($runs)): ?>
                        <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No payroll runs found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Generate Modal -->
    <div id="generateModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg shadow-xl w-[400px]">
            <h2 class="text-xl font-bold mb-4 text-gray-800">Generate Payroll</h2>
            <form id="generateForm" onsubmit="generateRun(event)">
                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Scope</label>
                    <select id="genScope" class="w-full p-2 border rounded" required onchange="toggleBranchSelect()">
                        <option value="BRANCH">BRANCH (Branch Staff)</option>
                        <?php if ($user['branch_id'] === null): ?>
                            <option value="OFFICIALS">OFFICIALS (Branch Managers/HR/Acc)</option>
                            <option value="HQ">HQ (Central Staff)</option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="mb-4" id="branchDiv">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Branch</label>
                    <select id="genBranch" class="w-full p-2 border rounded">
                        <?php foreach ($branches as $b): ?>
                            <?php if ($user['branch_id'] === null || $user['branch_id'] == $b['id']): ?>
                                <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Period Start</label>
                        <input type="date" id="genStart" class="w-full p-2 border rounded" required>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Period End</label>
                        <input type="date" id="genEnd" class="w-full p-2 border rounded" required>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Cutoff Number (for Contributions)</label>
                    <select id="genCutoff" class="w-full p-2 border rounded" required>
                        <option value="1">1st Cutoff (Floor Half)</option>
                        <option value="2">2nd Cutoff (Remainder)</option>
                    </select>
                </div>
                
                <div id="genError" class="hidden mb-4 p-3 bg-red-100 text-red-700 rounded text-sm font-bold"></div>

                <div class="flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('generateModal').classList.add('hidden')" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">Cancel</button>
                    <button type="submit" id="genSubmit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Generate</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function toggleBranchSelect() {
            const scope = document.getElementById('genScope').value;
            const branchDiv = document.getElementById('branchDiv');
            const branchSel = document.getElementById('genBranch');
            if (scope === 'BRANCH') {
                branchDiv.style.display = 'block';
                branchSel.required = true;
            } else {
                branchDiv.style.display = 'none';
                branchSel.required = false;
                branchSel.value = '';
            }
        }
        
        async function generateRun(e) {
            e.preventDefault();
            const btn = document.getElementById('genSubmit');
            const err = document.getElementById('genError');
            btn.disabled = true;
            btn.innerText = 'Generating...';
            err.classList.add('hidden');
            
            const payload = {
                scope: document.getElementById('genScope').value,
                branch_id: document.getElementById('genBranch').value || null,
                period_start: document.getElementById('genStart').value,
                period_end: document.getElementById('genEnd').value,
                cutoff_number: parseInt(document.getElementById('genCutoff').value, 10),
                csrf_token: '<?= Csrf::getToken() ?>'
            };
            
            try {
                const res = await fetch('api/fika_hrms_payroll_generate.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                
                if (data.error) {
                    err.innerText = data.error;
                    err.classList.remove('hidden');
                } else {
                    window.location.reload();
                }
            } catch (ex) {
                err.innerText = "Network error occurred.";
                err.classList.remove('hidden');
            }
            btn.disabled = false;
            btn.innerText = 'Generate';
        }
        async function approveRun(id) {
            if (!confirm('Are you sure you want to approve this payroll run?')) return;
            try {
                const res = await fetch('api/fika_hrms_payroll_approve.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ payroll_run_id: id, csrf_token: '<?= Csrf::getToken() ?>' })
                });
                const data = await res.json();
                if (data.error) alert(data.error);
                else window.location.reload();
            } catch (ex) {
                alert("Network error occurred.");
            }
        }

        async function releaseRun(id) {
            if (!confirm('Are you sure you want to release this payroll run? This will lock the attendance records for this period.')) return;
            try {
                const res = await fetch('api/fika_hrms_payroll_release.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ payroll_run_id: id, csrf_token: '<?= Csrf::getToken() ?>' })
                });
                const data = await res.json();
                if (data.error) alert(data.error);
                else window.location.reload();
            } catch (ex) {
                alert("Network error occurred.");
            }
        }
        async function clearRun(id) {
            if (!confirm('Are you sure you want to completely clear/delete this draft run?')) return;
            try {
                const res = await fetch('api/fika_hrms_payroll_delete.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ payroll_run_id: id, csrf_token: '<?= Csrf::getToken() ?>' })
                });
                const data = await res.json();
                if (data.error) alert(data.error);
                else window.location.reload();
            } catch (ex) {
                alert("Network error occurred.");
            }
        }
    </script>
</body>
</html>
