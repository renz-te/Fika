<?php
require __DIR__ . '/../app/bootstrap.php';

Auth::requireLogin();
Rbac::require_permission('hr.leave.view'); // Or whatever viewing perm is appropriate, assuming hr.leave.view or manage

global $pdo;

$canApprove = Rbac::can('hr.leave.approve');
$canCreate = Rbac::can('hr.leave.manage');

[$scopeSql, $scopeParams] = Rbac::branch_scope('e');

$sql = "
    SELECT lr.*, e.first_name, e.last_name, e.employee_code, lt.name as type_name, lt.is_paid
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.id
    JOIN leave_types lt ON lr.type = lt.code
    WHERE 1=1 {$scopeSql}
    ORDER BY lr.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($scopeParams);
$leaves = $stmt->fetchAll();

$leaveTypes = $pdo->query("SELECT * FROM leave_types ORDER BY name ASC")->fetchAll();
$emps = $pdo->prepare("SELECT id, first_name, last_name FROM employees e WHERE status = 'ACTIVE' {$scopeSql} ORDER BY first_name ASC");
$emps->execute($scopeParams);
$employees = $emps->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Leave Management - Fika HRMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800 font-sans">
    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Leave Requests</h1>
                <p class="mt-2 text-sm text-gray-600">View and approve employee time-off requests.</p>
            </div>
            <div class="text-right flex gap-4">
                <a href="pos_dashboard.php" class="text-gray-600 hover:underline pt-2">Back to Dashboard</a>
                <?php if($canCreate): ?>
                    <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 font-bold">File Leave</button>
                <?php endif; ?>
            </div>
        </div>

        <div id="alertBox" class="hidden mb-4 p-4 rounded text-sm font-bold"></div>

        <div class="bg-white shadow rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Employee</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Dates</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-500 uppercase">Days</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Reason</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-500 uppercase">Status</th>
                        <?php if($canApprove): ?>
                            <th class="px-4 py-3 text-center font-medium text-gray-500 uppercase">Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($leaves as $l): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">
                                <?= e($l['first_name'] . ' ' . $l['last_name']) ?> <br>
                                <span class="text-xs text-gray-500"><?= e($l['employee_code']) ?></span>
                            </td>
                            <td class="px-4 py-3">
                                <?= e($l['type_name']) ?><br>
                                <span class="text-xs font-bold <?= $l['is_paid'] ? 'text-green-600' : 'text-orange-600' ?>">
                                    <?= $l['is_paid'] ? 'PAID' : 'UNPAID' ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-700 font-mono text-xs">
                                <?= e($l['date_from']) ?> to <?= e($l['date_to']) ?>
                            </td>
                            <td class="px-4 py-3 text-center font-bold"><?= (float)$l['days'] ?></td>
                            <td class="px-4 py-3 text-gray-600 truncate max-w-xs" title="<?= e($l['reason']) ?>">
                                <?= e($l['reason']) ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-1 text-xs rounded-full font-bold 
                                    <?= $l['status'] === 'PENDING' ? 'bg-yellow-100 text-yellow-800' : ($l['status'] === 'APPROVED' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800') ?>">
                                    <?= e($l['status']) ?>
                                </span>
                            </td>
                            <?php if($canApprove): ?>
                                <td class="px-4 py-3 text-center flex justify-center gap-2">
                                    <?php if ($l['status'] === 'PENDING'): ?>
                                        <button onclick="decideLeave(<?= $l['id'] ?>, 'APPROVED')" class="bg-green-100 text-green-700 hover:bg-green-200 px-2 py-1 rounded text-xs font-bold">Approve</button>
                                        <button onclick="decideLeave(<?= $l['id'] ?>, 'REJECTED')" class="bg-red-100 text-red-700 hover:bg-red-200 px-2 py-1 rounded text-xs font-bold">Reject</button>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs">Done</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(empty($leaves)): ?>
                        <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No leave requests found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if($canCreate): ?>
    <!-- Create Leave Modal -->
    <div id="createModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg shadow-xl w-[500px]">
            <h2 class="text-2xl font-bold mb-4 text-gray-800">File Leave Request</h2>
            <form id="createForm">
                <input type="hidden" name="csrf_token" value="<?= e(Csrf::getToken()) ?>">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Employee</label>
                    <select name="employee_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:border-blue-500">
                        <option value="">-- Select --</option>
                        <?php foreach($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>"><?= e($emp['first_name'] . ' ' . $emp['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Leave Type</label>
                    <select name="type" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:border-blue-500">
                        <option value="">-- Select --</option>
                        <?php foreach($leaveTypes as $lt): ?>
                            <option value="<?= e($lt['code']) ?>"><?= e($lt['name']) ?> (<?= $lt['is_paid'] ? 'Paid' : 'Unpaid' ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Date From</label>
                        <input type="date" name="date_from" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Date To</label>
                        <input type="date" name="date_to" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:border-blue-500">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Days Count</label>
                    <input type="number" step="0.5" min="0.5" name="days" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:border-blue-500">
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700">Reason</label>
                    <textarea name="reason" required rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:border-blue-500"></textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">Cancel</button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded shadow">Submit</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        document.getElementById('createForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            try {
                const response = await fetch('api/fika_hrms_leave_create.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(Object.fromEntries(formData.entries()))
                });
                const data = await response.json();
                if (response.ok) {
                    window.location.reload();
                } else {
                    alert(data.error);
                }
            } catch(e) {
                alert("Network error");
            }
        });
    </script>
    <?php endif; ?>

    <script>
        async function decideLeave(leaveId, status) {
            if (!confirm(`Are you sure you want to ${status} this request?`)) return;
            
            try {
                const response = await fetch('api/fika_hrms_leave_decide.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        leave_id: leaveId,
                        status: status,
                        csrf_token: '<?= e(Csrf::getToken()) ?>'
                    })
                });
                const data = await response.json();
                const box = document.getElementById('alertBox');
                if (response.ok) {
                    window.location.reload();
                } else {
                    box.innerText = data.error;
                    box.className = 'mb-4 p-4 rounded text-sm font-bold bg-red-100 text-red-800';
                    box.classList.remove('hidden');
                    window.scrollTo(0,0);
                }
            } catch(e) {
                alert("Network error");
            }
        }
    </script>
</body>
</html>
