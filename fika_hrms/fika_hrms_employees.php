<?php
require __DIR__ . '/../app/bootstrap.php';
class_exists('Output');

Auth::requireLogin();
Rbac::require_permission('hr.employee.view');

global $pdo;

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$tab = $_GET['tab'] ?? 'directory';

$currentUser = Auth::user();
$userRoleId = (int)($currentUser['role_id'] ?? 0);
$userBranchId = $currentUser['branch_id'] ?? null;
$isCentral = ($userRoleId === 1 || $userRoleId === 2 || empty($userBranchId));

// Base SQL with calculated total hours from attendance_logs
$sql = "SELECT e.*, 
        CONCAT(e.first_name, ' ', e.last_name) AS full_name,
        COALESCE(e.employee_code, CONCAT('EMP-', e.id)) AS emp_code_display,
        b.name AS branch_name,
        (SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, clock_in, clock_out))/60, 0) 
         FROM attendance_logs 
         WHERE employee_id = e.id AND clock_out IS NOT NULL) AS total_hours
        FROM employees e 
        LEFT JOIN branches b ON e.branch_id = b.id
        LEFT JOIN users u ON u.employee_id = e.id
        WHERE e.deleted_at IS NULL 
          AND e.branch_id IS NOT NULL 
          AND (u.role_id IS NULL OR u.role_id != 1)
          AND e.employee_code NOT LIKE 'ADMIN%'
          AND e.position NOT LIKE '%Admin%'";

$params = [];

// Branch Scoping:
// Admin (1) or CHR (2) sees all 24 branch employees.
// BHR (5) and branch roles see only the 8 employees for their branch.
if (!$isCentral && !empty($userBranchId)) {
    $sql .= " AND e.branch_id = ?";
    $params[] = (int)$userBranchId;
}

if ($search !== '') {
    $sql .= " AND (e.employee_code LIKE ? OR CONCAT(e.first_name, ' ', e.last_name) LIKE ? OR e.position LIKE ? OR e.email LIKE ?)";
    $sWild = "%{$search}%";
    $params = array_merge($params, [$sWild, $sWild, $sWild, $sWild]);
}

if ($status !== '') {
    $sql .= " AND e.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY e.branch_id ASC, e.first_name ASC, e.last_name ASC";

$employees = [];
$pending_hires = [];

// Onboarding Scope Filters:
// 1. Status Scope: only Hired / Hireable applicants
// 2. Branch Scope: scoped to logged-in user's branch (unless Admin or CHR, who see all)
// 3. Role Scope: if Role 5 (BHR), only Crew applicants ('Barista', 'Head Barista')
$onboardingWhere = ["(status = 'HIRED' OR stage IN ('Hireable', 'Hired'))"];
$onboardingParams = [];

if (!in_array($userRoleId, [1, 2], true) && !empty($userBranchId)) {
    $onboardingWhere[] = "branch_id = ?";
    $onboardingParams[] = (int)$userBranchId;
}

if ($userRoleId === 5) {
    $onboardingWhere[] = "COALESCE(position_applied, position) IN ('Barista', 'Head Barista')";
}

$onboardingWhereClause = implode(' AND ', $onboardingWhere);

if ($tab === 'onboarding') {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, phone, COALESCE(position_applied, position) AS target_role, created_at FROM applicants WHERE {$onboardingWhereClause} ORDER BY created_at ASC");
    $stmt->execute($onboardingParams);
    $pending_hires = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $pendingCount = count($pending_hires);
} else {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count pending onboarding with the exact same scoped filters
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM applicants WHERE {$onboardingWhereClause}");
    $countStmt->execute($onboardingParams);
    $pendingCount = (int)$countStmt->fetchColumn();
}

// Positions list for filter dropdown
$posStmt = $pdo->query("SELECT DISTINCT position FROM employees WHERE position IS NOT NULL AND position != '' AND deleted_at IS NULL AND branch_id IS NOT NULL ORDER BY position ASC");
$positionsList = $posStmt->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Employees Directory';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees Directory - Fika HRMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex">

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto">
        <!-- Top Nav / Action Header -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-black text-xl shadow-sm">
                        <i class="fa-solid fa-users text-base"></i>
                    </div>
                    <div>
                        <h1 class="font-bold text-lg text-slate-900 leading-tight">Employees &amp; Onboarding</h1>
                        <p class="text-xs text-slate-500">Manage active roster, roles, tenure, and pending hires</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <a href="fika_hrms_employee_form.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-xl text-sm transition shadow-sm inline-flex items-center gap-2">
                        <i class="fa-solid fa-user-plus text-xs"></i> New Employee
                    </a>
                </div>
            </div>
        </header>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full flex-1">

            <!-- Sub Nav Tabs -->
            <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div class="flex bg-slate-200/80 p-1 rounded-xl border border-slate-300">
                    <a href="?tab=directory" class="px-4 py-2 text-sm font-bold rounded-lg transition-colors <?= $tab === 'directory' ? 'bg-white shadow-sm text-indigo-700' : 'text-slate-600 hover:text-slate-900' ?>">
                        <i class="fa-solid fa-users mr-1.5"></i> Active Roster
                    </a>
                    <a href="?tab=onboarding" class="px-4 py-2 text-sm font-bold rounded-lg transition-colors flex items-center <?= $tab === 'onboarding' ? 'bg-white shadow-sm text-indigo-700' : 'text-slate-600 hover:text-slate-900' ?>">
                        <i class="fa-solid fa-clipboard-user mr-1.5"></i> Pending Onboarding
                        <?php if ($pendingCount > 0): ?>
                            <span class="ml-2 inline-flex items-center justify-center px-2 py-0.5 text-xs font-black leading-none text-white bg-rose-600 rounded-full"><?= $pendingCount ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <?php if ($tab === 'directory'): ?>
                <!-- Toolbar with Search & Dropdown Filters -->
                <div class="p-5 border-b border-slate-200 bg-slate-50/70 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <form autocomplete="off" method="GET" action="fika_hrms_employees.php" class="flex flex-1 flex-wrap gap-3 items-end">
                        <input type="hidden" name="tab" value="directory">
                        
                        <div class="flex-1 min-w-[220px] max-w-sm">
                            <label for="search" class="sr-only">Search</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <i class="fa-solid fa-magnifying-glass text-sm"></i>
                                </div>
                                <input type="text" name="search" id="search" class="pl-10 block w-full rounded-xl border-slate-300 bg-white border p-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm shadow-sm" placeholder="Search by name, ID, position..." value="<?= e($search) ?>">
                            </div>
                        </div>
                        
                        <div class="flex flex-wrap gap-2.5 items-center">
                            <label for="status" class="sr-only">Status</label>
                            <select name="status" id="status" class="block w-36 rounded-xl border-slate-300 bg-white border p-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm shadow-sm">
                                <option value="">All Statuses</option>
                                <option value="ACTIVE" <?= $status === 'ACTIVE' ? 'selected' : '' ?>>Active</option>
                                <option value="INACTIVE" <?= $status === 'INACTIVE' ? 'selected' : '' ?>>Inactive</option>
                                <option value="SEPARATED" <?= $status === 'SEPARATED' ? 'selected' : '' ?>>Separated</option>
                            </select>

                            <label for="filterPosition" class="sr-only">Position</label>
                            <select id="filterPosition" class="block w-40 rounded-xl border-slate-300 bg-white border p-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm shadow-sm">
                                <option value="">All Positions</option>
                                <?php foreach ($positionsList as $pos): ?>
                                    <option value="<?= e($pos) ?>"><?= e($pos) ?></option>
                                <?php endforeach; ?>
                            </select>

                            <label for="filterCategory" class="sr-only">Category</label>
                            <select id="filterCategory" class="block w-36 rounded-xl border-slate-300 bg-white border p-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm shadow-sm">
                                <option value="">All Types</option>
                                <option value="REGULAR">Regular</option>
                                <option value="PROBATIONARY">Probationary</option>
                                <option value="PART_TIME">Part-Time</option>
                            </select>
                        </div>
                    </form>
                </div>

                <!-- Data Table with View / Edit Actions -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 uppercase tracking-wider text-xs border-b border-slate-200">
                                <th class="py-3.5 px-6 font-bold">Employee</th>
                                <th class="py-3.5 px-6 font-bold">ID Code</th>
                                <th class="py-3.5 px-6 font-bold">Position</th>
                                <th class="py-3.5 px-6 font-bold">Tenure</th>
                                <th class="py-3.5 px-6 font-bold">Total Hours</th>
                                <th class="py-3.5 px-6 font-bold">Status</th>
                                <th class="py-3.5 px-6 font-bold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="employeesTableBody" class="divide-y divide-slate-100 text-sm">
                            <?php if (empty($employees)): ?>
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <i class="fa-solid fa-users-slash text-4xl mb-3 text-slate-300"></i>
                                        <p class="font-medium">No employees found matching your criteria.</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach($employees as $emp): 
                                    $tenure = 'N/A';
                                    if (!empty($emp['date_hired'])) {
                                        $d1 = new DateTime($emp['date_hired']);
                                        $d2 = new DateTime();
                                        $diff = $d1->diff($d2);
                                        if ($diff->y > 0) $tenure = $diff->y . ' yrs, ' . $diff->m . ' mos';
                                        elseif ($diff->m > 0) $tenure = $diff->m . ' mos, ' . $diff->d . ' days';
                                        else $tenure = $diff->d . ' days';
                                    }
                                    $typeDisplay = str_replace('_', '-', $emp['employment_type'] ?? 'REGULAR');
                                ?>
                                <tr class="hover:bg-slate-50/70 transition-colors" 
                                    data-status="<?= e(strtoupper($emp['status'] ?? 'ACTIVE')) ?>" 
                                    data-position="<?= e($emp['position'] ?? '') ?>" 
                                    data-category="<?= e($emp['employment_type'] ?? '') ?>" 
                                    data-staff-class="<?= e(strtoupper($emp['staff_class'] ?? '')) ?>"
                                    data-search="<?= e(strtolower(($emp['full_name'] ?? '') . ' ' . ($emp['emp_code_display'] ?? '') . ' ' . ($emp['position'] ?? '') . ' ' . ($emp['staff_class'] ?? '') . ' ' . ($emp['email'] ?? ''))) ?>">
                                    <td class="py-4 px-6">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-700 font-extrabold mr-3 shadow-xs">
                                                <?= e(strtoupper(substr($emp['full_name'] ?? 'E', 0, 1))) ?>
                                            </div>
                                            <div>
                                                <div class="font-bold text-slate-900"><?= e($emp['full_name'] ?? '') ?></div>
                                                <div class="text-xs text-slate-400 font-mono"><?= e($emp['email'] ?? '') ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 px-6 font-mono text-xs font-bold text-slate-700">
                                        <?= e($emp['emp_code_display']) ?>
                                    </td>
                                    <td class="py-4 px-6">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-slate-800"><?= e($emp['position'] ?? 'Staff') ?></span>
                                            <?php 
                                                $staffClass = strtoupper($emp['staff_class'] ?? '');
                                                if ($staffClass === 'OFFICIAL'): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-100 text-amber-800 border border-amber-300 uppercase tracking-wider shadow-xs">OFFICIAL</span>
                                            <?php elseif ($staffClass === 'CREW'): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-slate-100 text-slate-700 border border-slate-300 uppercase tracking-wider shadow-xs">CREW</span>
                                            <?php elseif ($staffClass === 'HQ'): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-purple-100 text-purple-800 border border-purple-300 uppercase tracking-wider shadow-xs">HQ</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex items-center gap-1.5 mt-0.5">
                                            <span class="text-[11px] text-slate-400 uppercase tracking-wider font-bold"><?= e($typeDisplay) ?></span>
                                            <?php if ($isCentral && !empty($emp['branch_name'])): ?>
                                                <span class="text-slate-300">&bull;</span>
                                                <span class="text-[11px] text-indigo-600 font-semibold"><?= e($emp['branch_name']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="py-4 px-6">
                                        <div class="font-medium text-slate-700"><?= e($tenure) ?></div>
                                        <div class="text-xs text-slate-400">Since <?= !empty($emp['date_hired']) ? date('M Y', strtotime($emp['date_hired'])) : 'Unknown' ?></div>
                                    </td>
                                    <td class="py-4 px-6">
                                        <div class="font-bold text-indigo-600 font-mono"><?= number_format((float)$emp['total_hours'], 2) ?> Hrs</div>
                                    </td>
                                    <td class="py-4 px-6">
                                        <?php 
                                            $st = strtoupper($emp['status'] ?? 'ACTIVE');
                                            $statusClass = 'bg-slate-100 text-slate-600';
                                            if ($st === 'ACTIVE') $statusClass = 'bg-emerald-100 text-emerald-800 border border-emerald-200';
                                            elseif ($st === 'INACTIVE') $statusClass = 'bg-amber-100 text-amber-800 border border-amber-200';
                                            elseif ($st === 'SEPARATED') $statusClass = 'bg-rose-100 text-rose-800 border border-rose-200';
                                        ?>
                                        <span class="px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider <?= $statusClass ?>">
                                            <?= e($st) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-6 text-right whitespace-nowrap">
                                        <div class="inline-flex items-center gap-2">
                                            <a href="fika_hrms_employee_view.php?id=<?= $emp['id'] ?>" class="text-indigo-600 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors inline-flex items-center gap-1">
                                                <i class="fa-solid fa-eye text-[11px]"></i> View Profile
                                            </a>
                                            <button type="button" onclick="openEditModal('fika_hrms_employee_form.php?id=<?= $emp['id'] ?>')" class="text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors inline-flex items-center gap-1">
                                                <i class="fa-solid fa-pen-to-square text-[11px]"></i> Edit
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="p-4 border-t border-slate-200 bg-slate-50 flex items-center justify-between text-xs font-medium text-slate-500" id="employeeCount">
                    Showing <?= count($employees) ?> employee(s).
                </div>
            <?php else: ?>
                <!-- ONBOARDING QUEUE TAB -->
                <div class="p-6 border-b border-slate-200 bg-slate-50/70">
                    <h3 class="font-bold text-slate-800 text-lg">Pending Onboarding Queue</h3>
                    <p class="text-xs text-slate-500">Candidates awaiting account provisioning and assignment.</p>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase tracking-wider text-xs">
                                <th class="py-3.5 px-6 font-bold">Candidate Name</th>
                                <th class="py-3.5 px-6 font-bold">Contact Info</th>
                                <th class="py-3.5 px-6 font-bold">Target Role</th>
                                <th class="py-3.5 px-6 font-bold">Applied Date</th>
                                <th class="py-3.5 px-6 font-bold text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <?php if (empty($pending_hires)): ?>
                            <tr>
                                <td colspan="5" class="py-12 text-center text-slate-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <i class="fa-solid fa-clipboard-check text-4xl mb-3 text-emerald-400"></i>
                                        <p class="font-medium">Queue is clear. No pending hires awaiting provisioning.</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach($pending_hires as $hire): ?>
                                <tr class="hover:bg-slate-50 transition-colors" data-hire-id="<?= $hire['id'] ?>">
                                    <td class="py-4 px-6 font-bold text-slate-900">
                                        <?= e($hire['first_name'] . ' ' . $hire['last_name']) ?>
                                    </td>
                                    <td class="py-4 px-6">
                                        <div class="text-xs text-slate-700 font-mono"><?= e($hire['email']) ?></div>
                                        <div class="text-xs text-slate-400"><?= e($hire['phone'] ?? 'N/A') ?></div>
                                    </td>
                                    <td class="py-4 px-6">
                                        <span class="inline-block px-2.5 py-1 rounded-md text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
                                            <?= e($hire['target_role'] ?? 'Barista') ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-6 text-xs text-slate-600 font-medium">
                                        <?= date('M j, Y', strtotime($hire['created_at'])) ?>
                                    </td>
                                    <td class="py-4 px-6 text-right">
                                        <a href="fika_hrms_employee_form.php?applicant_id=<?= $hire['id'] ?>" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-4 rounded-xl shadow-xs transition inline-flex items-center gap-1.5 text-xs">
                                            <i class="fa-solid fa-user-plus text-xs"></i> Provision Account
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- Edit Employee Modal -->
    <div id="editModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl h-[85vh] flex flex-col relative overflow-hidden border border-slate-200">
            <div class="p-4 border-b flex justify-between items-center bg-slate-50">
                <h3 class="font-bold text-slate-800 text-base flex items-center gap-2">
                    <i class="fa-solid fa-user-gear text-indigo-600"></i> Edit Employee Profile
                </h3>
                <button type="button" onclick="closeEditModal()" class="text-slate-400 hover:text-rose-600 transition p-1">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
            <iframe id="editIframe" class="w-full flex-1 border-0" src=""></iframe>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('search');
        const statusSelect = document.getElementById('status');
        const positionSelect = document.getElementById('filterPosition');
        const categorySelect = document.getElementById('filterCategory');
        const tbody = document.getElementById('employeesTableBody');
        if (!tbody) return;
        const rows = tbody.querySelectorAll('tr[data-search]');
        const countDisplay = document.getElementById('employeeCount');

        function filterTable() {
            const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
            const status = statusSelect ? statusSelect.value.toUpperCase() : '';
            const position = positionSelect ? positionSelect.value : '';
            const category = categorySelect ? categorySelect.value : '';
            let count = 0;

            rows.forEach(row => {
                const rowSearch = row.getAttribute('data-search') || '';
                const rowStatus = (row.getAttribute('data-status') || '').toUpperCase();
                const rowPos = row.getAttribute('data-position') || '';
                const rowCat = row.getAttribute('data-category') || '';
                
                const matchesSearch = !query || rowSearch.includes(query);
                const matchesStatus = !status || rowStatus === status;
                const matchesPos = !position || rowPos === position;
                const matchesCat = !category || rowCat === category;

                if (matchesSearch && matchesStatus && matchesPos && matchesCat) {
                    row.style.display = '';
                    count++;
                } else {
                    row.style.display = 'none';
                }
            });
            if (countDisplay) {
                countDisplay.innerHTML = `Showing ${count} employee(s).`;
            }
        }

        if (searchInput) searchInput.addEventListener('input', filterTable);
        if (statusSelect) statusSelect.addEventListener('change', filterTable);
        if (positionSelect) positionSelect.addEventListener('change', filterTable);
        if (categorySelect) categorySelect.addEventListener('change', filterTable);
    });

    function openEditModal(url) {
        const iframe = document.getElementById('editIframe');
        iframe.src = url + (url.includes('?') ? '&' : '?') + 'modal=1';
        document.getElementById('editModal').classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
        document.getElementById('editIframe').src = '';
        window.location.reload();
    }
    </script>
</body>
</html>
