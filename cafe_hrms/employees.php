<?php
require_once __DIR__ . '/init.php';
require_login();
$user = current_user();
$role = $user['role'];

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$filters = ['status' => $status];

$branchFilter = get_branch_filter('e');
$sql = 'SELECT e.*, CONCAT(e.first_name, " ", e.last_name) AS full_name, 
        (SELECT SUM(TIMESTAMPDIFF(MINUTE, time_in, time_out))/60 FROM attendance WHERE employee_id = e.id AND time_out IS NOT NULL) AS total_hours 
        FROM employees e WHERE 1=1' . $branchFilter;
$params = [];

if ($search !== '') {
    $sql .= ' AND (e.employee_id LIKE ? OR CONCAT(e.first_name, " ", e.last_name) LIKE ? OR e.position LIKE ? OR e.email LIKE ?)';
    $params = array_fill(0, 4, "%{$search}%");
}
if ($status !== '') {
    $sql .= ' AND e.status = ?';
    $params[] = $status;
} else {
    $sql .= ' AND e.status != "Archived"';
}
$sql .= ' ORDER BY e.first_name ASC';

$tab = $_GET['tab'] ?? 'directory';

$employees = [];
$pending_hires = [];

if ($tab === 'onboarding') {
    $stmt = $pdo->query("SELECT id, first_name, last_name, email, phone, position_applied AS target_role, employment_category, created_at FROM applicants WHERE stage = 'Hireable' ORDER BY created_at ASC");
    $pending_hires = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = 'Employees Directory';
require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Employees & Onboarding</h1>
        <p class="text-slate-500">Manage active staff, their roles, and process pending hires.</p>
    </div>
    <div class="flex items-center gap-3">
        <div class="flex bg-slate-100 p-1 rounded-lg border border-slate-200">
            <a href="?tab=directory" class="px-4 py-1.5 text-sm font-medium rounded-md transition-colors <?= $tab === 'directory' ? 'bg-white shadow-sm text-primary' : 'text-slate-500 hover:text-slate-700' ?>">
                <i class="fa-solid fa-users mr-1"></i> Active Roster
            </a>
            <a href="?tab=onboarding" class="px-4 py-1.5 text-sm font-medium rounded-md transition-colors <?= $tab === 'onboarding' ? 'bg-white shadow-sm text-primary' : 'text-slate-500 hover:text-slate-700' ?>">
                <i class="fa-solid fa-clipboard-user mr-1"></i> Pending Onboarding
                <?php
                    $pendingCount = $pdo->query("SELECT COUNT(*) FROM applicants WHERE stage = 'Hireable'")->fetchColumn();
                    if ($pendingCount > 0):
                ?>
                    <span class="ml-1 inline-flex items-center justify-center px-2 py-0.5 text-xs font-bold leading-none text-red-100 bg-red-600 rounded-full"><?= $pendingCount ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
<?php if ($tab === 'directory'): ?>
    <!-- Toolbar -->
    <div class="p-6 border-b border-slate-200 bg-slate-50 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <form autocomplete="off" method="GET" action="employees" class="flex flex-1 gap-4 items-end">
            <div class="flex-1 max-w-sm">
                <label for="search" class="sr-only">Search</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-solid fa-search text-sm"></i>
                    </div>
                    <input type="text" name="search" id="search" class="pl-10 block w-full rounded-lg border-slate-300 bg-white border p-2 focus:ring-primary focus:border-primary text-sm transition-colors" placeholder="Search employees..." value="<?= h($search) ?>">
                </div>
            </div>
            
            <div class="w-full md:w-auto flex flex-wrap gap-2">
                <label for="status" class="sr-only">Status</label>
                <select name="status" id="status" class="block w-36 rounded-lg border-slate-300 bg-white border p-2 focus:ring-primary focus:border-primary text-sm transition-colors">
                    <option value="">All Statuses</option>
                    <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Trainee" <?= $status === 'Trainee' ? 'selected' : '' ?>>Trainee</option>
                    <option value="Archived" <?= $status === 'Archived' ? 'selected' : '' ?>>Archived</option>
                </select>
                <label for="position" class="sr-only">Position</label>
                <select id="filterPosition" class="block w-36 rounded-lg border-slate-300 bg-white border p-2 focus:ring-primary focus:border-primary text-sm transition-colors">
                    <option value="">All Positions</option>
                    <option value="Barista">Barista</option>
                    <option value="HR">HR</option>
                    <option value="Branch Admin">Branch Admin</option>
                    <option value="Central HR">Central HR</option>
                </select>
                <label for="category" class="sr-only">Category</label>
                <select id="filterCategory" class="block w-36 rounded-lg border-slate-300 bg-white border p-2 focus:ring-primary focus:border-primary text-sm transition-colors">
                    <option value="">All Categories</option>
                    <option value="Full-Time">Full-Time</option>
                    <option value="Part-Time">Part-Time</option>
                </select>
            </div>
        </form>
    </div>

    <!-- Data Table -->
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-white border-b border-slate-200">
                    <th class="py-4 px-6 font-semibold text-sm text-slate-500 uppercase tracking-wider">Employee</th>
                    <th class="py-4 px-6 font-semibold text-sm text-slate-500 uppercase tracking-wider">ID</th>
                    <th class="py-4 px-6 font-semibold text-sm text-slate-500 uppercase tracking-wider">Position</th>
                    <th class="py-4 px-6 font-semibold text-sm text-slate-500 uppercase tracking-wider">Tenure</th>
                    <th class="py-4 px-6 font-semibold text-sm text-slate-500 uppercase tracking-wider">Total Hours</th>
                    <th class="py-4 px-6 font-semibold text-sm text-slate-500 uppercase tracking-wider">Status</th>
                    <th class="py-4 px-6 font-semibold text-sm text-slate-500 uppercase tracking-wider text-right">Actions</th>
                </tr>
            </thead>
            <tbody id="employeesTableBody" class="divide-y divide-slate-100">
                <?php if (empty($employees)): ?>
                <tr>
                    <td colspan="7" class="py-10 text-center text-slate-500">
                        <div class="flex flex-col items-center justify-center">
                            <i class="fa-solid fa-users-slash text-4xl mb-3 text-slate-300"></i>
                            <p>No employees found matching your criteria.</p>
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
                    ?>
                    <tr class="hover:bg-slate-50 transition-colors" 
                        data-status="<?= h($emp['status']) ?>" 
                        data-position="<?= h($emp['position']) ?>" 
                        data-category="<?= h($emp['employment_category'] ?? '') ?>" 
                        data-search="<?= h(strtolower(($emp['full_name'] ?? '') . ' ' . ($emp['employee_id'] ?? '') . ' ' . ($emp['position'] ?? '') . ' ' . ($emp['email'] ?? ''))) ?>">
                        <td class="py-4 px-6">
                            <div class="flex items-center">
                                <div class="h-10 w-10 rounded-full bg-slate-200 flex items-center justify-center text-slate-600 font-bold mr-3">
                                    <?= h(strtoupper(substr($emp['full_name'] ?? '', 0, 1))) ?>
                                </div>
                                <div>
                                    <div class="font-medium text-slate-800"><?= h($emp['full_name'] ?? '') ?></div>
                                    <div class="text-sm text-slate-500"><?= h($emp['email'] ?? '') ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-6 text-sm text-slate-600 font-medium">
                            <?= h($emp['employee_id']) ?>
                        </td>
                        <td class="py-4 px-6">
                            <div class="text-sm text-slate-800"><?= h($emp['position']) ?></div>
                        </td>
                        <td class="py-4 px-6">
                            <div class="text-sm text-slate-600 font-medium"><?= h($tenure) ?></div>
                            <div class="text-xs text-slate-400">Since <?= !empty($emp['date_hired']) ? date('M Y', strtotime($emp['date_hired'])) : 'Unknown' ?></div>
                        </td>
                        <td class="py-4 px-6">
                            <div class="text-sm font-bold text-blue-600"><?= number_format((float)$emp['total_hours'], 2) ?> Hrs</div>
                        </td>
                        <td class="py-4 px-6">
                            <?php 
                                $statusClass = 'bg-slate-100 text-slate-600';
                                if ($emp['status'] === 'Active') $statusClass = 'bg-green-100 text-green-700';
                                if ($emp['status'] === 'On Leave') $statusClass = 'bg-amber-100 text-amber-700';
                            ?>
                            <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $statusClass ?>">
                                <?= h($emp['status']) ?>
                            </span>
                        </td>
                        <td class="py-4 px-6 text-right">
                            <a href="employee_view?id=<?= $emp['id'] ?>" class="text-primary hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded text-xs font-medium transition-colors">View Profile</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div class="p-4 border-t border-slate-200 bg-slate-50 flex items-center justify-between text-sm text-slate-500" id="employeeCount">
        Showing <?= count($employees) ?> employee(s).
    </div>
<?php else: ?>
    <!-- ONBOARDING QUEUE -->
    <div class="p-6 border-b border-slate-200 bg-slate-50">
        <h3 class="font-bold text-slate-800 text-lg">Pending Onboarding Queue</h3>
        <p class="text-sm text-slate-500">These candidates have completed their interviews and are waiting for account provisioning.</p>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-white border-b border-slate-200">
                    <th class="py-4 px-6 font-semibold text-sm text-slate-500 uppercase tracking-wider">Candidate Name</th>
                    <th class="py-4 px-6 font-semibold text-sm text-slate-500 uppercase tracking-wider">Contact Info</th>
                    <th class="py-4 px-6 font-semibold text-sm text-slate-500 uppercase tracking-wider">Target Role</th>
                    <th class="py-4 px-6 font-semibold text-sm text-slate-500 uppercase tracking-wider">Date Marked Hireable</th>
                    <th class="py-4 px-6 font-semibold text-sm text-slate-500 uppercase tracking-wider text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($pending_hires)): ?>
                <tr>
                    <td colspan="5" class="py-10 text-center text-slate-500">
                        <div class="flex flex-col items-center justify-center">
                            <i class="fa-solid fa-clipboard-check text-4xl mb-3 text-emerald-300"></i>
                            <p>Queue is empty. All candidates have been successfully onboarded.</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach($pending_hires as $hire): ?>
                    <tr class="hover:bg-slate-50 transition-colors" data-hire-id="<?= $hire['id'] ?>">
                        <td class="py-4 px-6">
                            <div class="font-medium text-slate-800"><?= h($hire['first_name'] . ' ' . $hire['last_name']) ?></div>
                        </td>
                        <td class="py-4 px-6">
                            <div class="text-sm text-slate-600"><?= h($hire['email']) ?></div>
                            <div class="text-xs text-slate-400"><?= h($hire['phone']) ?></div>
                        </td>
                        <td class="py-4 px-6">
                            <div class="text-sm font-medium text-slate-800"><?= h($hire['target_role']) ?></div>
                            <div class="text-xs text-slate-500"><?= h($hire['employment_category'] ?? 'Full-Time') ?></div>
                        </td>
                        <td class="py-4 px-6 text-sm text-slate-600">
                            <?= date('M j, Y', strtotime($hire['created_at'])) ?>
                        </td>
                        <td class="py-4 px-6 text-right">
                            <button type="button" onclick="provisionEmployee(<?= $hire['id'] ?>, this)" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition inline-flex items-center text-sm">
                                <i class="fa-solid fa-user-plus mr-2"></i> Provision Account
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <script>
    function provisionEmployee(applicantId, btnElement) {
        if (!confirm('Are you sure you want to provision an employee account for this candidate?')) return;
        
        let originalHtml = btnElement.innerHTML;
        btnElement.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Processing...';
        btnElement.disabled = true;
        
        let formData = new FormData();
        formData.append('applicant_id', applicantId);
        
        fetch('api/convert_to_employee.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                // Instantly remove row from DOM
                const row = document.querySelector(`tr[data-hire-id="${applicantId}"]`);
                if (row) {
                    row.remove();
                }
                alert(data.message);
                
                // Update counter if possible
                const badge = document.querySelector('.bg-red-600.rounded-full');
                if (badge) {
                    let count = parseInt(badge.innerText) - 1;
                    if (count > 0) {
                        badge.innerText = count;
                    } else {
                        badge.remove();
                    }
                }
            } else {
                alert('Error: ' + data.message);
                btnElement.innerHTML = originalHtml;
                btnElement.disabled = false;
            }
        })
        .catch(err => {
            alert('Network error.');
            btnElement.innerHTML = originalHtml;
            btnElement.disabled = false;
        });
    }
    </script>
<?php endif; ?>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 z-50 bg-black/50 hidden flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl h-[85vh] flex flex-col relative overflow-hidden">
        <div class="p-4 border-b flex justify-between items-center bg-slate-50">
            <h3 class="font-bold text-slate-800">Edit Employee</h3>
            <button onclick="closeEditModal()" class="text-slate-400 hover:text-red-500 transition"><i class="fa-solid fa-times text-xl"></i></button>
        </div>
        <iframe id="editIframe" class="w-full flex-1" src=""></iframe>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('search');
    const statusSelect = document.getElementById('status');
    const positionSelect = document.getElementById('filterPosition');
    const categorySelect = document.getElementById('filterCategory');
    const tbody = document.getElementById('employeesTableBody');
    const rows = tbody.querySelectorAll('tr[data-search]');
    const countDisplay = document.getElementById('employeeCount');

    function filterTable() {
        const query = searchInput.value.toLowerCase();
        const status = statusSelect.value;
        const position = positionSelect.value;
        const category = categorySelect.value;
        let count = 0;

        rows.forEach(row => {
            const rowSearch = row.getAttribute('data-search');
            const rowStatus = row.getAttribute('data-status');
            const rowPos = row.getAttribute('data-position');
            const rowCat = row.getAttribute('data-category');
            
            const matchesSearch = rowSearch.includes(query);
            const matchesStatus = status === '' || rowStatus === status;
            const matchesPos = position === '' || rowPos === position;
            const matchesCat = category === '' || rowCat === category;

            if (matchesSearch && matchesStatus && matchesPos && matchesCat) {
                row.style.display = '';
                count++;
            } else {
                row.style.display = 'none';
            }
        });
        countDisplay.innerHTML = `Showing ${count} employee(s).`;
    }

    const form = searchInput.closest('form');

    searchInput.addEventListener('input', filterTable);
    
    // Status needs to fetch from DB to get Archived employees
    statusSelect.addEventListener('change', () => {
        if (form) form.submit();
    });
    
    positionSelect.addEventListener('change', filterTable);
    categorySelect.addEventListener('change', filterTable);
    
    // Prevent form submit since we are doing live filtering (except for status dropdown)
    if(form) form.addEventListener('submit', (e) => e.preventDefault());
});

function openEditModal(url) {
    document.getElementById('editIframe').src = url + '&modal=1';
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
    document.getElementById('editIframe').src = '';
    window.location.reload(); // Reload to see changes
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

