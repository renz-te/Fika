<?php
require __DIR__ . '/../app/bootstrap.php';

Auth::requireLogin();
Rbac::require_permission('hr.employee.view');

global $pdo;

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$branchFilter = $_GET['branch_id'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

// Base query
$sql = "SELECT e.id, e.employee_code, e.first_name, e.last_name, e.position, e.department, 
               e.employment_type, e.status, e.date_hired, b.name as branch_name 
        FROM employees e 
        LEFT JOIN branches b ON e.branch_id = b.id 
        WHERE e.deleted_at IS NULL";
$countSql = "SELECT COUNT(*) FROM employees e WHERE e.deleted_at IS NULL";

$params = [];

// Rbac Branch Scoping
list($scopeSql, $scopeParams) = Rbac::branch_scope('e');
$sql .= $scopeSql;
$countSql .= $scopeSql;
$params = array_merge($params, $scopeParams);

// Search
if ($search !== '') {
    $sql .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_code LIKE ?)";
    $countSql .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_code LIKE ?)";
    $searchWild = '%' . $search . '%';
    $params = array_merge($params, [$searchWild, $searchWild, $searchWild]);
}

// Status filter
if ($status !== '') {
    $sql .= " AND e.status = ?";
    $countSql .= " AND e.status = ?";
    $params[] = $status;
}

// Branch filter (User selects from dropdown)
if ($branchFilter !== '') {
    // Rbac::branch_scope already limits it, but if they are global, they can filter further
    $sql .= " AND e.branch_id = ?";
    $countSql .= " AND e.branch_id = ?";
    $params[] = $branchFilter;
}

$sql .= " ORDER BY e.last_name ASC, e.first_name ASC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $limit);

// Fetch branches for filter dropdown (respecting branch_scope)
$bSql = "SELECT id, name FROM branches WHERE deleted_at IS NULL";
$bParams = [];
list($bScopeSql, $bScopeParams) = Rbac::branch_scope();
// Rbac::branch_scope returns " AND branch_id = ?" which applies to branches table as " AND id = ?"
$bScopeSql = str_replace('branch_id', 'id', $bScopeSql);
$bSql .= $bScopeSql . " ORDER BY name ASC";
$bStmt = $pdo->prepare($bSql);
$bStmt->execute($bScopeParams);
$branches = $bStmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Directory - Fika HRMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800 font-sans">
    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        
        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Employee Directory</h1>
                <p class="mt-2 text-sm text-gray-600">Manage and view all staff members.</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white p-4 rounded shadow mb-6">
            <form method="GET" action="fika_hrms_employees.php" class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700">Search</label>
                    <input type="text" name="search" value="<?= e($search) ?>" placeholder="Name or Code..." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div class="w-full md:w-48">
                    <label class="block text-sm font-medium text-gray-700">Branch</label>
                    <select name="branch_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Allowed Branches</option>
                        <?php foreach($branches as $b): ?>
                            <option value="<?= e($b['id']) ?>" <?= $branchFilter == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="w-full md:w-48">
                    <label class="block text-sm font-medium text-gray-700">Status</label>
                    <select name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Statuses</option>
                        <option value="ACTIVE" <?= $status === 'ACTIVE' ? 'selected' : '' ?>>Active</option>
                        <option value="INACTIVE" <?= $status === 'INACTIVE' ? 'selected' : '' ?>>Inactive</option>
                        <option value="SEPARATED" <?= $status === 'SEPARATED' ? 'selected' : '' ?>>Separated</option>
                    </select>
                </div>

                <div class="flex items-end">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded shadow">Filter</button>
                    <a href="fika_hrms_employees.php" class="ml-2 text-blue-600 hover:underline py-2 px-2">Clear</a>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="bg-white shadow rounded overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Branch</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if(empty($employees)): ?>
                        <tr><td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">No employees found.</td></tr>
                    <?php else: ?>
                        <?php foreach($employees as $emp): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= e($emp['employee_code']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= e($emp['last_name'] . ', ' . $emp['first_name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= e($emp['position']) ?><br>
                                    <span class="text-xs text-gray-400"><?= e($emp['department']) ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= e($emp['branch_name'] ?? 'HQ') ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if($emp['status'] === 'ACTIVE'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                    <?php elseif($emp['status'] === 'INACTIVE'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Inactive</span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Separated</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="mt-4 flex items-center justify-between">
            <div class="text-sm text-gray-700">
                Showing <?= e($offset + 1) ?> to <?= e(min($offset + $limit, $total)) ?> of <?= e($total) ?> results
            </div>
            <div class="flex space-x-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&branch_id=<?= urlencode($branchFilter) ?>" class="px-3 py-1 border rounded bg-white text-gray-600 hover:bg-gray-50">Prev</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&branch_id=<?= urlencode($branchFilter) ?>" class="px-3 py-1 border rounded bg-white text-gray-600 hover:bg-gray-50">Next</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</body>
</html>
