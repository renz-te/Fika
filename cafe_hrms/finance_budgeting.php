<?php
require_once __DIR__ . '/init.php';
require_login();
$user = current_user();
$user_role = $user['role'] ?? '';
$user_branch_id = $user['branch_id'] ?? null;

// RBAC Gate: Only Super Admin, Central HR, Global Accountant, and Branch Manager
$allowed_roles = ['Super Admin', 'Central HR', 'Global Accountant', 'Branch Manager', 'Branch Accountant'];
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
    $action = $_POST['action'] ?? '';

    if ($action === 'save_budget') {
        $b_branch_id = (int)($_POST['branch_id'] ?? 0);
        $b_month = trim($_POST['budget_month'] ?? '');
        $b_amount = (float)str_replace(',', '', $_POST['allocated_labor_budget'] ?? '0');

        // Branch Managers can only set budget for their own branch
        if (in_array($user_role, ['Branch Manager', 'Branch Accountant']) && $b_branch_id !== (int)$user_branch_id) {
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
                }
            } else {
                $pdo->prepare("DELETE FROM branch_budgets WHERE id = ?")->execute([$budget_id]);
                $success = "Budget entry deleted.";
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
        bb.branch_id,
        b.name AS branch_name,
        bb.budget_month,
        bb.allocated_labor_budget,
        COALESCE(actual.total_labor_cost, 0) AS actual_labor_cost,
        COALESCE(rev.gross_revenue, 0) AS gross_revenue,
        COALESCE(cogs.total_cogs, 0) AS total_cogs,
        (COALESCE(rev.gross_revenue, 0) - COALESCE(cogs.total_cogs, 0) - COALESCE(actual.total_labor_cost, 0)) AS net_profit
    FROM branch_budgets bb
    JOIN branches b ON bb.branch_id = b.id
    LEFT JOIN (
        SELECT 
            e.branch_id,
            DATE_FORMAT(p.period_end, '%Y-%m') AS pay_month,
            SUM(p.gross_pay) AS total_labor_cost
        FROM payroll p
        JOIN employees e ON p.employee_id = e.id
        WHERE p.status != 'Draft'
        GROUP BY e.branch_id, DATE_FORMAT(p.period_end, '%Y-%m')
    ) actual ON actual.branch_id = bb.branch_id AND actual.pay_month = bb.budget_month
    LEFT JOIN (
        SELECT 
            u.branch_id, 
            DATE_FORMAT(o.created_at, '%Y-%m') AS rev_month, 
            SUM(o.total_price) AS gross_revenue
        FROM cafe_pos.orders o
        JOIN users u ON o.cashier_id = u.id
        WHERE o.payment_status = 'PAID'
        GROUP BY u.branch_id, DATE_FORMAT(o.created_at, '%Y-%m')
    ) rev ON rev.branch_id = bb.branch_id AND rev.rev_month = bb.budget_month
    LEFT JOIN (
        SELECT 
            it.branch_id, 
            DATE_FORMAT(it.transaction_date, '%Y-%m') AS cogs_month, 
            SUM(it.quantity * i.unit_cost) AS total_cogs
        FROM cafe_pos.inventory_transactions it
        JOIN cafe_pos.inventory i ON it.inventory_id = i.id
        WHERE it.type = 'Stock Out'
        GROUP BY it.branch_id, DATE_FORMAT(it.transaction_date, '%Y-%m')
    ) cogs ON cogs.branch_id = bb.branch_id AND cogs.cogs_month = bb.budget_month
    WHERE 1=1
";

$params = [];
if ($filter_branch_id) {
    $budget_query .= " AND bb.branch_id = ?";
    $params[] = $filter_branch_id;
}

// Month filter
$filter_month = $_GET['month'] ?? '';
if (!empty($filter_month)) {
    $budget_query .= " AND bb.budget_month = ?";
    $params[] = $filter_month;
}

$budget_query .= " ORDER BY bb.budget_month DESC, b.name ASC";

$stmt = $pdo->prepare($budget_query);
$stmt->execute($params);
$budget_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Summary stats ---
$total_revenue = 0;
$total_cogs = 0;
$total_labor = 0;
$total_net_profit = 0;
$loss_count = 0;

foreach ($budget_rows as $r) {
    $total_revenue += (float)$r['gross_revenue'];
    $total_cogs += (float)$r['total_cogs'];
    $total_labor += (float)$r['actual_labor_cost'];
    $total_net_profit += (float)$r['net_profit'];
    if ((float)$r['net_profit'] < 0) $loss_count++;
}

$pageTitle = 'P&L Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Profit & Loss Dashboard</h1>
            <p class="text-slate-500">Track Revenue, COGS, Labor Costs, and Net Profit by branch.</p>
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
        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Net Profit</div>
        <div class="text-2xl font-bold <?= $total_net_profit >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">
            <?= $total_net_profit >= 0 ? '+' : '' ?>₱<?= number_format($total_net_profit, 2) ?>
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
                    <th class="py-4 px-6 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right">Labor Cost</th>
                    <th class="py-4 px-6 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right">Net Profit</th>
                    <th class="py-4 px-6 font-semibold text-xs text-slate-500 uppercase tracking-wider text-center">Status</th>
                    <th class="py-4 px-6 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($budget_rows)): ?>
                    <tr>
                        <td colspan="8" class="py-12 text-center text-slate-400">
                            <i class="fa-solid fa-chart-pie text-4xl mb-3 block text-slate-300"></i>
                            No data found for the selected criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($budget_rows as $row): 
                        $revenue = (float)$row['gross_revenue'];
                        $cogs = (float)$row['total_cogs'];
                        $labor = (float)$row['actual_labor_cost'];
                        $net = (float)$row['net_profit'];
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
                        <td class="py-4 px-6 text-sm text-red-500 font-medium text-right">₱<?= number_format($labor, 2) ?></td>
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
                            <button onclick='editBudget(<?= json_encode($row) ?>)' class="text-slate-400 hover:text-blue-600 transition-colors mr-2" title="Edit">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <form method="POST" class="inline" onsubmit="return confirm('Delete this budget entry?');">
                                <input type="hidden" name="action" value="delete_budget">
                                <input type="hidden" name="budget_id" value="<?= $row['budget_id'] ?>">
                                <button type="submit" class="text-slate-400 hover:text-red-600 transition-colors" title="Delete">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
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
