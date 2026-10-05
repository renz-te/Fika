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
                                <button class="text-blue-600 hover:underline text-xs font-bold">View</button>
                                <?php if ($r['status'] === 'DRAFT' || $r['status'] === 'GENERATED'): ?>
                                    <?php if (can_perform_action($r, 'APPROVE', $user, $pdo)): ?>
                                        <button class="text-green-600 hover:underline text-xs font-bold">Approve</button>
                                    <?php endif; ?>
                                <?php elseif ($r['status'] === 'APPROVED'): ?>
                                    <?php if (can_perform_action($r, 'RELEASE', $user, $pdo)): ?>
                                        <button class="text-purple-600 hover:underline text-xs font-bold">Release</button>
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

    <!-- Generate Modal Dummy -->
    <div id="generateModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg shadow-xl w-[400px]">
            <h2 class="text-xl font-bold mb-4 text-gray-800">Generate Payroll</h2>
            <p class="text-sm text-gray-600 mb-4">Select period to generate a new DRAFT run.</p>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('generateModal').classList.add('hidden')" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">Cancel</button>
            </div>
        </div>
    </div>
</body>
</html>
