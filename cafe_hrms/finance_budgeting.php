<?php
require_once __DIR__ . '/init.php';
require_login();
$user = current_user();
$user_role = $user['role'] ?? '';
$user_branch_id = $user['branch_id'] ?? null;

// RBAC Gate: Only Super Admin, Central HR, Global Accountant, and Branch Manager
$allowed_roles = [ROLE_SUPER_ADMIN, ROLE_EXECUTIVE, ROLE_GLOBAL_ACCOUNTANT, ROLE_BRANCH_MANAGER, ROLE_BRANCH_ACCOUNTANT];
if (!in_array($user_role, $allowed_roles)) {
    redirect('dashboard');
}

$error = '';
$success = '';

// --- RBAC: Determine branch filter ---
$filter_branch_id = null;
if (in_array($user_role, ['Branch Manager', 'Branch Accountant'])) {
    // Hard-locked to their own branch
    $filter_branch_id = (int)$user_branch_id;
} else {
    // Super Admin / Central HR / Global Accountant can filter or view all
    if (!empty($_GET['branch_id']) && $_GET['branch_id'] !== '') {
        $filter_branch_id = (int)$_GET['branch_id'];
    }
}

// --- Handle POST: Add/Edit Budget Allocation ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die('CSRF token validation failed.');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'save_budget') {
        $b_branch_id = (int)($_POST['branch_id'] ?? 0);
        $b_month = trim($_POST['budget_month'] ?? '');
        $b_amount = (float)str_replace(',', '', $_POST['allocated_labor_budget'] ?? '0');

        // strict server-side validation on budget_month
        if (!preg_match('/^\d{4}-\d{2}$/', $b_month)) {
            $error = "Invalid budget month format. Must be YYYY-MM.";
        } elseif (in_array($user_role, ['Branch Manager', 'Branch Accountant']) && $b_branch_id !== (int)$user_branch_id) {
            $error = "You can only manage budgets for your assigned branch.";
        } elseif (empty($b_branch_id) || empty($b_month) || $b_amount <= 0) {
            $error = "All fields are required. Budget must be greater than zero.";
        } else {
            // UPSERT: Insert or update on duplicate key
            $stmt = $pdo->prepare("INSERT INTO branch_budgets (branch_id, budget_month, allocated_labor_budget) 
                                   VALUES (?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE allocated_labor_budget = VALUES(allocated_labor_budget)");
            $stmt->execute([$b_branch_id, $b_month, $b_amount]);
            $success = "Budget allocation saved successfully.";
            log_activity($pdo, $user['id'], 'save_budget', "Set budget for branch $b_branch_id month $b_month to $b_amount");
        }
    } elseif ($action === 'delete_budget') {
        $budget_id = (int)($_POST['budget_id'] ?? 0);
        if ($budget_id) {
            // Verify ownership for branch managers
            if (in_array($user_role, ['Branch Manager', 'Branch Accountant'])) {
                $chk = $pdo->prepare("SELECT branch_id FROM branch_budgets WHERE id = ?");
                $chk->execute([$budget_id]);
                $row = $chk->fetch();
                if (!$row || (int)$row['branch_id'] !== (int)$user_branch_id) {
                    $error = "You cannot delete budgets for other branches.";
                } else {
                    $pdo->prepare("DELETE FROM branch_budgets WHERE id = ?")->execute([$budget_id]);
                    $success = "Budget entry deleted.";
                    log_activity($pdo, $user['id'], 'delete_budget', "Deleted budget ID: $budget_id");
                }
            } else {
                $pdo->prepare("DELETE FROM branch_budgets WHERE id = ?")->execute([$budget_id]);
                $success = "Budget entry deleted.";
                log_activity($pdo, $user['id'], 'delete_budget', "Deleted budget ID: $budget_id");
            }
        }
    }
}

// --- Fetch active branches for dropdowns ---
$branches_list = $pdo->query("SELECT id, name FROM branches WHERE status = 'Active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// --- Build the main budget query with actual labor cost, gross revenue, and COGS ---
$budget_query = "
    SELECT 
        bb.id AS budget_id,
        b.id AS branch_id,
        b.name AS branch_name,
        master.report_month AS budget_month,
        bb.allocated_labor_budget,
        COALESCE(actual.total_labor_cost, 0) AS actual_labor_cost,
        COALESCE(rev.gross_revenue, 0) AS gross_revenue,
        COALESCE(cogs.total_cogs, 0) AS total_cogs,
        (COALESCE(rev.gross_revenue, 0) - COALESCE(cogs.total_cogs, 0) - COALESCE(actual.total_labor_cost, 0)) AS margin_before_overhead,
        (bb.allocated_labor_budget IS NOT NULL AND COALESCE(actual.total_labor_cost, 0) > bb.allocated_labor_budget) AS is_over_budget,
        (bb.allocated_labor_budget - COALESCE(actual.total_labor_cost, 0)) AS budget_variance
    FROM branches b
    CROSS JOIN (
        SELECT DISTINCT budget_month AS report_month FROM branch_budgets
        UNION
        SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') FROM cafe_pos.orders WHERE payment_status = 'PAID'
        UNION
        SELECT DISTINCT DATE_FORMAT(period_end, '%Y-%m') FROM payroll WHERE status != 'Draft'
    ) master
    LEFT JOIN branch_budgets bb ON bb.branch_id = b.id AND bb.budget_month = master.report_month
    LEFT JOIN (
        SELECT 
            p.branch_id,
            DATE_FORMAT(p.period_end, '%Y-%m') AS pay_month,
            SUM(p.gross_pay + COALESCE(p.bonus_amount, 0) + p.employer_sss + p.employer_philhealth + p.employer_pagibig) AS total_labor_cost
        FROM payroll p
        WHERE p.status != 'Draft'
        GROUP BY p.branch_id, DATE_FORMAT(p.period_end, '%Y-%m')
    ) actual ON actual.branch_id = b.id AND actual.pay_month = master.report_month
    LEFT JOIN (
        SELECT 
            o.branch_id, 
            DATE_FORMAT(o.created_at, '%Y-%m') AS rev_month, 
            SUM(o.total_price) AS gross_revenue
        FROM cafe_pos.orders o
        WHERE o.payment_status = 'PAID'
        GROUP BY o.branch_id, DATE_FORMAT(o.created_at, '%Y-%m')
    ) rev ON rev.branch_id = b.id AND rev.rev_month = master.report_month
    LEFT JOIN (
        SELECT 
            branch_id, 
            DATE_FORMAT(transaction_date, '%Y-%m') AS cogs_month, 
            SUM(CASE 
                WHEN type IN ('Usage', 'Write-off') THEN quantity * COALESCE(unit_cost_snapshot, cost)
                WHEN type = 'Restock' THEN -(quantity * COALESCE(unit_cost_snapshot, cost))
                ELSE 0 
            END) AS total_cogs
        FROM inventory_transactions
        WHERE type IN ('Usage', 'Write-off', 'Restock') AND status = 'Completed'
        GROUP BY branch_id, DATE_FORMAT(transaction_date, '%Y-%m')
    ) cogs ON cogs.branch_id = b.id AND cogs.cogs_month = master.report_month
    WHERE master.report_month IS NOT NULL
";

$params = [];
if ($filter_branch_id) {
    $budget_query .= " AND b.id = ?";
    $params[] = $filter_branch_id;
}

// Month filter
$filter_month = $_GET['month'] ?? '';
if (!empty($filter_month)) {
    $budget_query .= " AND master.report_month = ?";
    $params[] = $filter_month;
}

$budget_query .= " ORDER BY master.report_month DESC, b.name ASC";

$stmt = $pdo->prepare($budget_query);
$stmt->execute($params);
$budget_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Summary stats ---
$total_revenue = 0;
$total_cogs = 0;
$total_labor = 0;
$total_margin = 0;
$loss_count = 0;

foreach ($budget_rows as $r) {
    $total_revenue += (float)$r['gross_revenue'];
    $total_cogs += (float)$r['total_cogs'];
    $total_labor += (float)$r['actual_labor_cost'];
    $total_margin += (float)$r['margin_before_overhead'];
    if ((float)$r['margin_before_overhead'] < 0) $loss_count++;
}

$pageTitle = 'P&L Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Profit & Loss Dashboard</h1>
            <p class="text-slate-500">Track Revenue, COGS, Labor Costs, and Margin Before Overhead by branch.</p>
        </div>
        <button onclick="openModal('budgetModal')" class="bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg font-medium transition-colors shadow-sm flex items-center">
            <i class="fa-solid fa-plus mr-2"></i> Allocate Labor Budget
        </button>
    </div>
</div>

<?php if ($error): ?>
    <div class="mb-4 bg-red-50 text-red-700 border border-red-200 rounded-lg p-4 flex items-center">
        <i class="fa-solid fa-circle-exclamation mr-3 text-lg"></i> <?= h($error) ?>
    </div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="mb-4 bg-green-50 text-green-700 border border-green-200 rounded-lg p-4 flex items-center">
        <i class="fa-solid fa-circle-check mr-3 text-lg"></i> <?= h($success) ?>
    </div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Gross Revenue</div>
        <div class="text-2xl font-bold text-emerald-600">₱<?= number_format($total_revenue, 2) ?></div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Total COGS</div>
        <div class="text-2xl font-bold text-red-500">₱<?= number_format($total_cogs, 2) ?></div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Total Labor Cost</div>
        <div class="text-2xl font-bold text-red-500">₱<?= number_format($total_labor, 2) ?></div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Margin Before Overhead</div>
        <div class="text-2xl font-bold <?= $total_margin >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">
            <?= $total_margin >= 0 ? '+' : '' ?>₱<?= number_format($total_margin, 2) ?>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<form method="GET" action="finance_budgeting" class="flex flex-wrap gap-4 mb-6 p-4 bg-white border border-slate-200 rounded-xl shadow-sm items-end">
    <?php if (!in_array($user_role, ['Branch Manager', 'Branch Accountant'])): ?>
    <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Branch</label>
        <select name="branch_id" class="rounded-lg border-slate-300 border p-2 text-sm focus:ring-primary focus:border-primary" onchange="this.form.submit()">
            <option value="">All Branches</option>
            <?php foreach ($branches_list as $br): ?>
                <option value="<?= $br['id'] ?>" <?= ($filter_branch_id == $br['id']) ? 'selected' : '' ?>><?= h($br['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Month</label>
        <input type="month" name="month" value="<?= h($filter_month) ?>" class="rounded-lg border-slate-300 border p-2 text-sm focus:ring-primary focus:border-primary" onchange="this.form.submit()">
    </div>
    <?php if (!empty($filter_branch_id) || !empty($filter_month)): ?>
    <div>
        <a href="finance_budgeting" class="inline-flex items-center px-3 py-2 text-sm text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200 transition-colors">
            <i class="fa-solid fa-xmark mr-1"></i> Clear
        </a>
    </div>
    <?php endif; ?>
</form>

<!-- Budget Data Table -->
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200">
                    <th class="py-4 px-6 font-semibold text-xs text-slate-500 uppercase tracking-wider">Branch</th>
                    <th class="py-4 px-6 font-semibold text-xs text-slate-500 uppercase tracking-wider">Month</th>
                    <th class="py-4 px-6 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right">Revenue</th>
                    <th class="py-4 px-6 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right">COGS</th>
                    <th class="py-4 px-6 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right">Labor Budget</th>
                    <th class="py-4 px-6 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right">Labor Cost</th>
                    <th class="py-4 px-6 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right">Variance</th>
                    <th class="py-4 px-6 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right">Margin Before Overhead</th>
                    <th class="py-4 px-6 font-semibold text-xs text-slate-500 uppercase tracking-wider text-center">Status</th>
                    <th class="py-4 px-6 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($budget_rows)): ?>
                    <tr>
                        <td colspan="10" class="py-12 text-center text-slate-400">
                            <i class="fa-solid fa-chart-pie text-4xl mb-3 block text-slate-300"></i>
                            No data found for the selected criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($budget_rows as $row): 
                        $revenue = (float)$row['gross_revenue'];
                        $cogs = (float)$row['total_cogs'];
                        $labor = (float)$row['actual_labor_cost'];
                        $net = (float)$row['margin_before_overhead'];
                        $is_loss = $net < 0;
                    ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="py-4 px-6">
                            <div class="font-medium text-slate-800"><?= h($row['branch_name']) ?></div>
                        </td>
                        <td class="py-4 px-6 text-sm text-slate-600">
                            <?php 
                                $dt = DateTime::createFromFormat('Y-m', $row['budget_month']);
                                echo $dt ? $dt->format('F Y') : h($row['budget_month']);
                            ?>
                        </td>
                        <td class="py-4 px-6 text-sm text-emerald-600 font-medium text-right">₱<?= number_format($revenue, 2) ?></td>
                        <td class="py-4 px-6 text-sm text-red-500 font-medium text-right">₱<?= number_format($cogs, 2) ?></td>
                        <?php 
                            $allocated = $row['allocated_labor_budget'];
                            $variance = $row['budget_variance'];
                        ?>
                        <td class="py-4 px-6 text-sm text-slate-600 font-medium text-right"><?= $allocated !== null ? '₱' . number_format($allocated, 2) : '<span class="text-slate-400 italic font-normal">Unbudgeted</span>' ?></td>
                        <td class="py-4 px-6 text-sm text-red-500 font-medium text-right">₱<?= number_format($labor, 2) ?></td>
                        <td class="py-4 px-6 text-sm font-medium text-right <?= $variance !== null ? ($variance < 0 ? 'text-red-500' : 'text-emerald-500') : 'text-slate-400' ?>">
                            <?= $variance !== null ? ($variance < 0 ? '' : '+') . '₱' . number_format($variance, 2) : '-' ?>
                        </td>
                        <td class="py-4 px-6 text-sm font-bold text-right <?= $is_loss ? 'text-red-600' : 'text-emerald-600' ?>">
                            <?= $net >= 0 ? '+' : '' ?>₱<?= number_format($net, 2) ?>
                        </td>
                        <td class="py-4 px-6 text-center">
                            <?php if ($is_loss): ?>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700">
                                    <i class="fa-solid fa-arrow-trend-down mr-1"></i> Loss
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700">
                                    <i class="fa-solid fa-arrow-trend-up mr-1"></i> Profitable
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="py-4 px-6 text-right">
                            <?php if ($row['budget_id']): ?>
                            <button type="button" onclick="editBudget(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)" class="text-slate-400 hover:text-blue-600 transition-colors mr-2" title="Edit">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <form method="POST" class="inline" onsubmit="return confirm('Delete this budget entry?');">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="delete_budget">
                                <input type="hidden" name="budget_id" value="<?= $row['budget_id'] ?>">
                                <button type="submit" class="text-slate-400 hover:text-red-600 transition-colors" title="Delete">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                            <?php else: ?>
                                <span class="text-slate-300 italic text-xs">No Actions</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Allocate/Edit Budget Modal -->
<div id="budgetModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 overflow-hidden flex flex-col max-h-[90vh]">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h3 class="text-lg font-bold text-slate-800" id="budgetModalTitle">Allocate Labor Budget</h3>
            <button type="button" onclick="closeModal('budgetModal')" class="text-slate-400 hover:text-slate-600 transition-colors">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto">
            <form id="budgetForm" method="POST" action="finance_budgeting">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" id="budgetAction" value="save_budget">

                <div class="space-y-4">
                    <div>
                        <label for="budgetBranch" class="block text-sm font-medium text-slate-700 mb-1">Branch *</label>
                        <?php if (in_array($user_role, ['Branch Manager', 'Branch Accountant'])): ?>
                            <!-- Locked to own branch -->
                            <input type="hidden" name="branch_id" id="budgetBranch" value="<?= (int)$user_branch_id ?>">
                            <?php 
                                $own_branch_name = '';
                                foreach ($branches_list as $br) { if ($br['id'] == $user_branch_id) { $own_branch_name = $br['name']; break; } }
                            ?>
                            <input type="text" value="<?= h($own_branch_name) ?>" disabled class="w-full rounded-lg border-slate-300 border p-2.5 bg-slate-100 text-sm text-slate-600">
                        <?php else: ?>
                            <select name="branch_id" id="budgetBranch" required class="w-full rounded-lg border-slate-300 border p-2.5 focus:ring-primary focus:border-primary text-sm">
                                <option value="">Select Branch...</option>
                                <?php foreach ($branches_list as $br): ?>
                                    <option value="<?= $br['id'] ?>"><?= h($br['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label for="budgetMonth" class="block text-sm font-medium text-slate-700 mb-1">Budget Month *</label>
                        <input type="month" name="budget_month" id="budgetMonth" required class="w-full rounded-lg border-slate-300 border p-2.5 focus:ring-primary focus:border-primary text-sm" value="<?= date('Y-m') ?>">
                    </div>

                    <div>
                        <label for="budgetAmount" class="block text-sm font-medium text-slate-700 mb-1">Allocated Labor Budget (₱) *</label>
                        <div class="flex">
                            <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-slate-300 bg-amber-100 text-amber-700 font-medium text-sm">₱</span>
                            <input type="text" name="allocated_labor_budget" id="budgetAmount" required placeholder="0.00"
                                   oninput="this.value = this.value.replace(/[^0-9.,]/g, '')"
                                   class="w-full rounded-r-lg border-slate-300 border p-2.5 focus:ring-primary focus:border-primary text-sm">
                        </div>
                        <p class="text-xs text-slate-500 mt-1">The maximum labor spend for this branch during this month.</p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="closeModal('budgetModal')" class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 font-medium transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg font-medium transition-colors">Save Budget</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.remove('hidden');
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
    if (id === 'budgetModal') {
        document.getElementById('budgetModalTitle').innerText = 'Allocate Labor Budget';
        document.getElementById('budgetForm').reset();
        // Reset month to current
        document.getElementById('budgetMonth').value = '<?= date('Y-m') ?>';
    }
}

function editBudget(row) {
    document.getElementById('budgetModalTitle').innerText = 'Edit Budget Allocation';
    const branchSelect = document.getElementById('budgetBranch');
    if (branchSelect.tagName === 'SELECT') {
        branchSelect.value = row.branch_id;
    }
    document.getElementById('budgetMonth').value = row.budget_month;
    document.getElementById('budgetAmount').value = parseFloat(row.allocated_labor_budget).toLocaleString('en-US', {minimumFractionDigits: 2});
    openModal('budgetModal');
}

// Close modal on backdrop click
window.addEventListener('click', (e) => {
    const modal = document.getElementById('budgetModal');
    if (e.target === modal) closeModal('budgetModal');
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
