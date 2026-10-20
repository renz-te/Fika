<?php
require_once __DIR__ . '/init.php';
require_login();
$user = current_user();
$user_role = $user['role'] ?? '';
$user_branch_id = $user['branch_id'] ?? 1;

// RBAC Gate: Only Super Admin, Central HR, Global Accountant, Branch Manager, and Branch Accountant
$allowed_roles = [ROLE_SUPER_ADMIN, ROLE_EXECUTIVE, ROLE_GLOBAL_ACCOUNTANT, ROLE_BRANCH_MANAGER, ROLE_BRANCH_ACCOUNTANT, 'HR', 'Central HR', 'Admin'];
if (!in_array($user_role, $allowed_roles)) {
    redirect('dashboard');
}

$error = '';
$success = '';

// --- Ensure tables exist so dashboard never crashes ---
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `expense_categories` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL UNIQUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `expenses` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `branch_id` INT NOT NULL,
            `category_id` INT NULL,
            `category` VARCHAR(100) NULL,
            `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `amount_cents` BIGINT NOT NULL DEFAULT 0,
            `expense_date` DATE NOT NULL,
            `payee` VARCHAR(255) NOT NULL,
            `notes` TEXT NULL,
            `receipt_path` VARCHAR(255) NULL,
            `status` VARCHAR(50) NOT NULL DEFAULT 'Approved',
            `created_by` INT NULL,
            `approved_by` INT NULL DEFAULT NULL,
            `void_reason` TEXT NULL DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_exp_branch` (`branch_id`),
            INDEX `idx_exp_category` (`category_id`),
            INDEX `idx_exp_status` (`status`),
            INDEX `idx_exp_date` (`expense_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (Exception $e) {
    // Graceful fallback if already exists
}

// --- RBAC: Determine branch filter ---
$filter_branch_id = null;
if (in_array($user_role, ['Branch Manager', 'Branch Accountant'])) {
    $filter_branch_id = (int)$user_branch_id;
} else {
    if (!empty($_GET['branch_id']) && $_GET['branch_id'] !== '') {
        $filter_branch_id = (int)$_GET['branch_id'];
    }
}

// Default selected branch and month
$selected_branch_id = $filter_branch_id ?: ($user_branch_id ?: 1);
$selected_month = !empty($_GET['month']) ? $_GET['month'] : date('Y-m');

// --- Handle POST Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die('CSRF token validation failed.');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'save_expense') {
        $b_id = in_array($user_role, ['Branch Manager', 'Branch Accountant']) ? (int)$user_branch_id : (int)($_POST['branch_id'] ?? $selected_branch_id);
        $exp_cat = trim($_POST['category'] ?? 'Miscellaneous');
        $exp_payee = trim($_POST['payee'] ?? '');
        $exp_amount = (float)str_replace(',', '', $_POST['amount'] ?? '0');
        $exp_date = trim($_POST['expense_date'] ?? date('Y-m-d'));
        $exp_notes = trim($_POST['notes'] ?? '');
        $exp_status = 'Approved';

        if (empty($exp_payee) || $exp_amount <= 0 || empty($exp_date)) {
            $error = "Payee, Date, and a positive Amount are required.";
        } else {
            // Find category id if available
            $catStmt = $pdo->prepare("SELECT id FROM expense_categories WHERE name = ?");
            $catStmt->execute([$exp_cat]);
            $catId = $catStmt->fetchColumn() ?: null;
            $amtCents = (int)round($exp_amount * 100);

            $insStmt = $pdo->prepare("
                INSERT INTO expenses (branch_id, category_id, category, amount, amount_cents, expense_date, payee, notes, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insStmt->execute([$b_id, $catId, $exp_cat, $exp_amount, $amtCents, $exp_date, $exp_payee, $exp_notes, $exp_status, $user['id']]);
            $success = "Operating expense recorded successfully.";
            log_activity($pdo, $user['id'], 'save_expense', "Added expense ₱$exp_amount to $exp_payee");
        }
    } elseif ($action === 'save_budget') {
        $b_branch_id = in_array($user_role, ['Branch Manager', 'Branch Accountant']) ? (int)$user_branch_id : (int)($_POST['branch_id'] ?? $selected_branch_id);
        $b_month = trim($_POST['budget_month'] ?? '');
        $b_amount = (float)str_replace(',', '', $_POST['allocated_labor_budget'] ?? '0');

        if (!preg_match('/^\d{4}-\d{2}$/', $b_month)) {
            $error = "Invalid budget month format. Must be YYYY-MM.";
        } elseif (empty($b_branch_id) || empty($b_month) || $b_amount <= 0) {
            $error = "All fields are required. Budget must be greater than zero.";
        } else {
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

// --- Fetch active branches and categories for dropdowns ---
$branches_list = $pdo->query("SELECT id, name FROM branches WHERE status = 'Active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$categories_list = $pdo->query("SELECT id, name FROM expense_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Active branch name
$active_branch_name = 'Main Branch';
foreach ($branches_list as $br) {
    if ((int)$br['id'] === (int)$selected_branch_id) {
        $active_branch_name = $br['name'];
        break;
    }
}

// =========================================================================
// TASK 3: KPI CALCULATIONS
// =========================================================================
// 1. Allocated Budget (from branch_budgets for this branch and current/selected month)
$bStmt = $pdo->prepare("SELECT allocated_labor_budget FROM branch_budgets WHERE branch_id = ? AND budget_month = ?");
$bStmt->execute([$selected_branch_id, $selected_month]);
$allocated_budget = (float)($bStmt->fetchColumn() ?: 0.00);

// 2. Total Spent (Sum of expenses where status = 'Approved' for this branch and month)
$sStmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) 
    FROM expenses 
    WHERE branch_id = ? 
      AND DATE_FORMAT(expense_date, '%Y-%m') = ? 
      AND (status = 'Approved' OR status = 'APPROVED')
");
$sStmt->execute([$selected_branch_id, $selected_month]);
$total_spent = (float)$sStmt->fetchColumn();

// If 0 spent in this exact month, check if there's any all-time spent for context
$allTimeStmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) 
    FROM expenses 
    WHERE branch_id = ? 
      AND (status = 'Approved' OR status = 'APPROVED')
");
$allTimeStmt->execute([$selected_branch_id]);
$all_time_spent = (float)$allTimeStmt->fetchColumn();

// 3. Remaining Budget (Allocated - Total Spent)
$remaining_budget = $allocated_budget - $total_spent;

// 4. Recent Transactions Table: List last 10 expenses (Category, Payee, Amount, Status)
$txStmt = $pdo->prepare("
    SELECT e.id, 
           COALESCE(ec.name, e.category, 'General') AS category, 
           e.payee, 
           e.amount, 
           e.status, 
           e.expense_date, 
           e.notes
    FROM expenses e
    LEFT JOIN expense_categories ec ON e.category_id = ec.id
    WHERE e.branch_id = ?
    ORDER BY e.expense_date DESC, e.id DESC
    LIMIT 10
");
$txStmt->execute([$selected_branch_id]);
$recent_expenses = $txStmt->fetchAll(PDO::FETCH_ASSOC);

// =========================================================================
// Monthly Branch Budget Overview Breakdown
// =========================================================================
$budget_query = "
    SELECT 
        bb.id AS budget_id,
        b.id AS branch_id,
        b.name AS branch_name,
        bb.budget_month,
        bb.allocated_labor_budget,
        COALESCE(exp.total_spent, 0) AS total_spent,
        (bb.allocated_labor_budget - COALESCE(exp.total_spent, 0)) AS budget_variance
    FROM branch_budgets bb
    JOIN branches b ON b.id = bb.branch_id
    LEFT JOIN (
        SELECT 
            branch_id, 
            DATE_FORMAT(expense_date, '%Y-%m') AS exp_month, 
            SUM(amount) AS total_spent 
        FROM expenses 
        WHERE status IN ('Approved', 'APPROVED') 
        GROUP BY branch_id, DATE_FORMAT(expense_date, '%Y-%m')
    ) exp ON exp.branch_id = bb.branch_id AND exp.exp_month = bb.budget_month
    WHERE 1=1
";
$params = [];
if ($filter_branch_id) {
    $budget_query .= " AND b.id = ?";
    $params[] = $filter_branch_id;
}
if (!empty($selected_month)) {
    $budget_query .= " AND bb.budget_month = ?";
    $params[] = $selected_month;
}
$budget_query .= " ORDER BY bb.budget_month DESC, b.name ASC";
$stmt = $pdo->prepare($budget_query);
$stmt->execute($params);
$budget_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Financial Overview';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Embedded Tailwind configuration check to guarantee espresso and amber palette -->
<script>
    if (typeof tailwind !== 'undefined') {
        tailwind.config = tailwind.config || {};
        tailwind.config.theme = tailwind.config.theme || {};
        tailwind.config.theme.extend = tailwind.config.theme.extend || {};
        tailwind.config.theme.extend.colors = Object.assign(tailwind.config.theme.extend.colors || {}, {
            espresso: {
                50: '#fbf8f5',
                100: '#f5efe9',
                200: '#ebdcd0',
                300: '#dbc0ad',
                400: '#c59d83',
                700: '#5c3821',
                800: '#422415',
                900: '#2c180e',
                950: '#1a0d07'
            },
            amber: {
                50: '#fffbeb',
                100: '#fef3c7',
                200: '#fde68a',
                300: '#fcd34d',
                400: '#fbbf24',
                500: '#f59e0b',
                600: '#d97706',
                700: '#b45309',
                800: '#92400e',
                900: '#78350f'
            },
            cream: {
                50: '#fdfbf7',
                100: '#f7f2ea',
                200: '#efe6d5',
                300: '#e5d5bc'
            }
        });
    }
</script>

<div class="p-6 max-w-7xl mx-auto space-y-6">

    <!-- Header & Action Bar -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-espresso-950 p-6 rounded-2xl border border-amber-500/20 shadow-md">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-400/10 text-amber-400 border border-amber-400/30 uppercase tracking-wider">
                    <?= h($active_branch_name) ?>
                </span>
                <span class="text-xs text-cream-100/60">
                    Period: <strong class="text-cream-100"><?= date('F Y', strtotime($selected_month . '-01')) ?></strong>
                </span>
            </div>
            <h1 class="text-2xl md:text-3xl font-extrabold text-cream-100 tracking-tight font-serif">Financial Overview</h1>
            <p class="text-cream-100/70 text-sm mt-1">Real-time financial dashboard tracking allocated budgets, operating expenses, and remaining runway.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <button onclick="openModal('expenseModal')" class="bg-amber-500 hover:bg-amber-400 text-espresso-950 font-bold px-4 py-2.5 rounded-xl transition shadow-sm flex items-center text-sm">
                <i class="fa-solid fa-receipt mr-2"></i> Record Expense
            </button>
            <button onclick="openModal('budgetModal')" class="bg-espresso-900 hover:bg-espresso-800 text-amber-400 border border-amber-500/30 font-semibold px-4 py-2.5 rounded-xl transition shadow-sm flex items-center text-sm">
                <i class="fa-solid fa-wallet mr-2"></i> Allocate Budget
            </button>
        </div>
    </div>

    <!-- Feedback Alerts -->
    <?php if ($error): ?>
        <div class="bg-rose-50 text-rose-700 border border-rose-200 rounded-xl p-4 flex items-center shadow-sm">
            <i class="fa-solid fa-circle-exclamation mr-3 text-lg text-rose-600"></i>
            <span class="font-medium text-sm"><?= h($error) ?></span>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-xl p-4 flex items-center shadow-sm">
            <i class="fa-solid fa-circle-check mr-3 text-lg text-emerald-600"></i>
            <span class="font-medium text-sm"><?= h($success) ?></span>
        </div>
    <?php endif; ?>

    <!-- Filter Bar -->
    <form method="GET" action="finance_budgeting" class="flex flex-wrap gap-4 p-4 bg-white border border-slate-200 rounded-xl shadow-sm items-end">
        <?php if (!in_array($user_role, ['Branch Manager', 'Branch Accountant'])): ?>
        <div class="w-full sm:w-auto flex-1 min-w-[200px]">
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Branch</label>
            <select name="branch_id" class="w-full rounded-lg border-slate-300 border p-2 text-sm focus:ring-amber-500 focus:border-amber-500" onchange="this.form.submit()">
                <?php foreach ($branches_list as $br): ?>
                    <option value="<?= $br['id'] ?>" <?= ((int)$selected_branch_id === (int)$br['id']) ? 'selected' : '' ?>><?= h($br['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="w-full sm:w-auto flex-1 min-w-[180px]">
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Month</label>
            <input type="month" name="month" value="<?= h($selected_month) ?>" class="w-full rounded-lg border-slate-300 border p-2 text-sm focus:ring-amber-500 focus:border-amber-500" onchange="this.form.submit()">
        </div>
        <div>
            <a href="finance_budgeting" class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200 transition-colors">
                <i class="fa-solid fa-rotate-left mr-1.5"></i> Reset
            </a>
        </div>
    </form>

    <!-- TASK 3.2: 3 KPI CARDS (Styled with Espresso & Amber Palette) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        <!-- KPI CARD 1: Allocated Budget -->
        <div class="bg-espresso-950 text-cream-100 border border-amber-500/20 rounded-2xl p-6 shadow-md relative overflow-hidden transition-all hover:border-amber-400/40">
            <div class="absolute -right-4 -bottom-4 w-28 h-28 bg-amber-500/5 rounded-full pointer-events-none"></div>
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold text-amber-400/80 uppercase tracking-wider">Allocated Budget</span>
                <div class="w-10 h-10 rounded-xl bg-amber-400/10 border border-amber-400/20 flex items-center justify-center text-amber-400">
                    <i class="fa-solid fa-wallet text-base"></i>
                </div>
            </div>
            <div class="text-3xl lg:text-4xl font-extrabold text-amber-400 tracking-tight">
                ₱<?= number_format($allocated_budget, 2) ?>
            </div>
            <div class="mt-3 text-xs text-cream-100/60 flex items-center justify-between border-t border-espresso-900 pt-3">
                <span>From <code class="text-amber-300/80 font-mono">branch_budgets</code></span>
                <span class="font-medium text-cream-100/90"><?= date('M Y', strtotime($selected_month . '-01')) ?></span>
            </div>
        </div>

        <!-- KPI CARD 2: Total Spent -->
        <div class="bg-espresso-950 text-cream-100 border border-amber-500/20 rounded-2xl p-6 shadow-md relative overflow-hidden transition-all hover:border-amber-400/40">
            <div class="absolute -right-4 -bottom-4 w-28 h-28 bg-rose-500/5 rounded-full pointer-events-none"></div>
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold text-amber-400/80 uppercase tracking-wider">Total Spent</span>
                <div class="w-10 h-10 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400">
                    <i class="fa-solid fa-receipt text-base"></i>
                </div>
            </div>
            <div class="text-3xl lg:text-4xl font-extrabold text-cream-100 tracking-tight">
                ₱<?= number_format($total_spent, 2) ?>
            </div>
            <div class="mt-3 text-xs text-cream-100/60 flex items-center justify-between border-t border-espresso-900 pt-3">
                <span>Sum of <code class="text-cream-100/90 font-mono">Approved</code> expenses</span>
                <span class="text-rose-400 font-semibold"><?= count($recent_expenses) ?> logged tx</span>
            </div>
        </div>

        <!-- KPI CARD 3: Remaining Budget -->
        <div class="bg-espresso-950 text-cream-100 border border-amber-500/20 rounded-2xl p-6 shadow-md relative overflow-hidden transition-all hover:border-amber-400/40">
            <div class="absolute -right-4 -bottom-4 w-28 h-28 <?= $remaining_budget >= 0 ? 'bg-emerald-500/5' : 'bg-rose-500/5' ?> rounded-full pointer-events-none"></div>
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold text-amber-400/80 uppercase tracking-wider">Remaining Budget</span>
                <div class="w-10 h-10 rounded-xl <?= $remaining_budget >= 0 ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' : 'bg-rose-500/10 border-rose-500/20 text-rose-400' ?> flex items-center justify-center border">
                    <i class="fa-solid fa-scale-balanced text-base"></i>
                </div>
            </div>
            <div class="text-3xl lg:text-4xl font-extrabold <?= $remaining_budget >= 0 ? 'text-emerald-400' : 'text-rose-400' ?> tracking-tight">
                ₱<?= number_format($remaining_budget, 2) ?>
            </div>
            <div class="mt-3 text-xs text-cream-100/60 flex items-center justify-between border-t border-espresso-900 pt-3">
                <span>Allocated - Total Spent</span>
                <?php if ($remaining_budget >= 0): ?>
                    <span class="text-emerald-400 font-bold inline-flex items-center">
                        <i class="fa-solid fa-circle-check mr-1 text-[10px]"></i> On Track
                    </span>
                <?php else: ?>
                    <span class="text-rose-400 font-bold inline-flex items-center">
                        <i class="fa-solid fa-triangle-exclamation mr-1 text-[10px]"></i> Budget Exceeded
                    </span>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- TASK 3.3: RECENT TRANSACTIONS TABLE (Last 10 Expenses) -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 bg-slate-50/50">
            <div>
                <h2 class="text-lg font-bold text-slate-800 flex items-center">
                    <i class="fa-solid fa-list-check text-amber-500 mr-2"></i> Recent Transactions
                </h2>
                <p class="text-xs text-slate-500">Showing the last 10 expenses for <?= h($active_branch_name) ?></p>
            </div>
            <button onclick="openModal('expenseModal')" class="text-xs font-semibold text-amber-600 hover:text-amber-700 bg-amber-50 hover:bg-amber-100 px-3 py-1.5 rounded-lg transition-colors border border-amber-200">
                <i class="fa-solid fa-plus mr-1"></i> Add Expense
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-[11px] font-bold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                        <th class="py-3 px-6">Category</th>
                        <th class="py-3 px-6">Payee</th>
                        <th class="py-3 px-6 text-right">Amount</th>
                        <th class="py-3 px-6 text-center">Status</th>
                        <th class="py-3 px-6 text-right">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php if (empty($recent_expenses)): ?>
                        <tr>
                            <td colspan="5" class="py-12 text-center text-slate-400">
                                <i class="fa-solid fa-receipt text-3xl mb-2 text-slate-300 block"></i>
                                No expenses recorded for this branch yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_expenses as $exp): 
                            $st = strtolower($exp['status']);
                        ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3.5 px-6">
                                <span class="inline-flex items-center text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-700">
                                    <i class="fa-solid fa-tag text-[10px] mr-1.5 text-amber-500"></i>
                                    <?= h($exp['category']) ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-6 font-medium text-slate-800">
                                <?= h($exp['payee']) ?>
                                <?php if (!empty($exp['notes'])): ?>
                                    <div class="text-xs text-slate-400 font-normal truncate max-w-xs"><?= h($exp['notes']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-6 text-right font-extrabold text-slate-900">
                                ₱<?= number_format($exp['amount'], 2) ?>
                            </td>
                            <td class="py-3.5 px-6 text-center">
                                <?php if ($st === 'approved'): ?>
                                    <span class="inline-flex items-center text-xs font-bold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800">
                                        <i class="fa-solid fa-circle-check text-[10px] mr-1"></i> Approved
                                    </span>
                                <?php elseif ($st === 'draft'): ?>
                                    <span class="inline-flex items-center text-xs font-bold px-2.5 py-1 rounded-full bg-amber-100 text-amber-800">
                                        <i class="fa-solid fa-clock text-[10px] mr-1"></i> Draft
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center text-xs font-bold px-2.5 py-1 rounded-full bg-slate-100 text-slate-600">
                                        <?= h($exp['status']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-6 text-right text-xs text-slate-500 font-mono">
                                <?= date('M d, Y', strtotime($exp['expense_date'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Monthly Branch Budget Allocation Breakdown Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <div>
                <h3 class="text-base font-bold text-slate-800">Monthly Branch Budget Allocation Overview</h3>
                <p class="text-xs text-slate-500">Historical budget allocations and expense spending tracking by branch.</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-[11px] font-bold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                        <th class="py-3.5 px-6">Branch</th>
                        <th class="py-3.5 px-6">Month</th>
                        <th class="py-3.5 px-6 text-right">Allocated Budget</th>
                        <th class="py-3.5 px-6 text-right">Total Spent</th>
                        <th class="py-3.5 px-6 text-right">Remaining Budget</th>
                        <th class="py-3.5 px-6 text-center">Status</th>
                        <th class="py-3.5 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php if (empty($budget_rows)): ?>
                        <tr>
                            <td colspan="7" class="py-10 text-center text-slate-400">
                                No budget records found for the selected criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($budget_rows as $row): 
                            $allocated = (float)$row['allocated_labor_budget'];
                            $spent = (float)$row['total_spent'];
                            $variance = (float)$row['budget_variance'];
                            $is_over = $variance < 0;
                        ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3.5 px-6 font-medium text-slate-800"><?= h($row['branch_name']) ?></td>
                            <td class="py-3.5 px-6 text-xs text-slate-600 font-mono">
                                <?php 
                                    $dt = DateTime::createFromFormat('Y-m', $row['budget_month']);
                                    echo $dt ? $dt->format('F Y') : h($row['budget_month']);
                                ?>
                            </td>
                            <td class="py-3.5 px-6 text-right font-medium text-slate-800">₱<?= number_format($allocated, 2) ?></td>
                            <td class="py-3.5 px-6 text-right font-medium text-rose-500">₱<?= number_format($spent, 2) ?></td>
                            <td class="py-3.5 px-6 text-right font-bold <?= $is_over ? 'text-rose-600' : 'text-emerald-600' ?>">
                                <?= $variance >= 0 ? '+' : '' ?>₱<?= number_format($variance, 2) ?>
                            </td>
                            <td class="py-3.5 px-6 text-center">
                                <?php if ($is_over): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-700">
                                        <i class="fa-solid fa-arrow-trend-down mr-1"></i> Over Budget
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700">
                                        <i class="fa-solid fa-arrow-trend-up mr-1"></i> Within Budget
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-6 text-right">
                                <?php if ($row['budget_id']): ?>
                                <button type="button" onclick="editBudget(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)" class="text-slate-400 hover:text-amber-600 transition-colors mr-2" title="Edit Budget">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <form method="POST" class="inline" onsubmit="return confirm('Delete this budget entry?');">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="action" value="delete_budget">
                                    <input type="hidden" name="budget_id" value="<?= $row['budget_id'] ?>">
                                    <button type="submit" class="text-slate-400 hover:text-rose-600 transition-colors" title="Delete">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                                <?php else: ?>
                                    <span class="text-slate-300 italic text-xs">None</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Modal 1: Record Operating Expense -->
<div id="expenseModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden flex flex-col">
        <div class="px-6 py-4 bg-espresso-950 text-cream-100 flex justify-between items-center border-b border-amber-500/20">
            <h3 class="text-lg font-bold flex items-center font-serif">
                <i class="fa-solid fa-receipt text-amber-400 mr-2"></i> Record Operating Expense
            </h3>
            <button type="button" onclick="closeModal('expenseModal')" class="text-cream-100/60 hover:text-amber-400 transition-colors">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
        <form method="POST" action="finance_budgeting" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="save_expense">

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Branch</label>
                <?php if (in_array($user_role, ['Branch Manager', 'Branch Accountant'])): ?>
                    <input type="hidden" name="branch_id" value="<?= (int)$user_branch_id ?>">
                    <input type="text" value="<?= h($active_branch_name) ?>" disabled class="w-full rounded-lg border-slate-300 border p-2.5 bg-slate-100 text-sm text-slate-600">
                <?php else: ?>
                    <select name="branch_id" required class="w-full rounded-lg border-slate-300 border p-2.5 focus:ring-amber-500 focus:border-amber-500 text-sm">
                        <?php foreach ($branches_list as $br): ?>
                            <option value="<?= $br['id'] ?>" <?= ((int)$selected_branch_id === (int)$br['id']) ? 'selected' : '' ?>><?= h($br['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Category *</label>
                <select name="category" required class="w-full rounded-lg border-slate-300 border p-2.5 focus:ring-amber-500 focus:border-amber-500 text-sm">
                    <?php foreach ($categories_list as $cat): ?>
                        <option value="<?= h($cat['name']) ?>"><?= h($cat['name']) ?></option>
                    <?php endforeach; ?>
                    <?php if (empty($categories_list)): ?>
                        <option value="Rent">Rent</option>
                        <option value="Utilities">Utilities</option>
                        <option value="Supplies">Supplies</option>
                        <option value="Maintenance">Maintenance</option>
                        <option value="Marketing">Marketing</option>
                        <option value="Miscellaneous" selected>Miscellaneous</option>
                    <?php endif; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Payee / Vendor *</label>
                <input type="text" name="payee" required placeholder="e.g. Manila Electric Co. or Barista Supply" class="w-full rounded-lg border-slate-300 border p-2.5 focus:ring-amber-500 focus:border-amber-500 text-sm">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Amount (₱) *</label>
                    <input type="text" name="amount" required placeholder="0.00" oninput="this.value = this.value.replace(/[^0-9.,]/g, '')" class="w-full rounded-lg border-slate-300 border p-2.5 focus:ring-amber-500 focus:border-amber-500 text-sm font-bold">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Date *</label>
                    <input type="date" name="expense_date" required value="<?= date('Y-m-d') ?>" class="w-full rounded-lg border-slate-300 border p-2.5 focus:ring-amber-500 focus:border-amber-500 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Notes / Memo</label>
                <textarea name="notes" rows="2" placeholder="Optional purchase details or invoice number" class="w-full rounded-lg border-slate-300 border p-2.5 focus:ring-amber-500 focus:border-amber-500 text-sm"></textarea>
            </div>

            <div class="mt-6 flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal('expenseModal')" class="px-4 py-2 border border-slate-300 rounded-xl text-slate-700 hover:bg-slate-50 font-medium text-sm transition-colors">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-amber-500 hover:bg-amber-400 text-espresso-950 font-bold rounded-xl text-sm transition-colors shadow-sm">Save Expense</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Allocate Budget Modal -->
<div id="budgetModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden flex flex-col">
        <div class="px-6 py-4 bg-espresso-950 text-cream-100 flex justify-between items-center border-b border-amber-500/20">
            <h3 class="text-lg font-bold flex items-center font-serif" id="budgetModalTitle">
                <i class="fa-solid fa-wallet text-amber-400 mr-2"></i> Allocate Branch Budget
            </h3>
            <button type="button" onclick="closeModal('budgetModal')" class="text-cream-100/60 hover:text-amber-400 transition-colors">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
        <form id="budgetForm" method="POST" action="finance_budgeting" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" id="budgetAction" value="save_budget">

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Branch *</label>
                <?php if (in_array($user_role, ['Branch Manager', 'Branch Accountant'])): ?>
                    <input type="hidden" name="branch_id" id="budgetBranch" value="<?= (int)$user_branch_id ?>">
                    <input type="text" value="<?= h($active_branch_name) ?>" disabled class="w-full rounded-lg border-slate-300 border p-2.5 bg-slate-100 text-sm text-slate-600">
                <?php else: ?>
                    <select name="branch_id" id="budgetBranch" required class="w-full rounded-lg border-slate-300 border p-2.5 focus:ring-amber-500 focus:border-amber-500 text-sm">
                        <?php foreach ($branches_list as $br): ?>
                            <option value="<?= $br['id'] ?>" <?= ((int)$selected_branch_id === (int)$br['id']) ? 'selected' : '' ?>><?= h($br['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Budget Month *</label>
                <input type="month" name="budget_month" id="budgetMonth" required class="w-full rounded-lg border-slate-300 border p-2.5 focus:ring-amber-500 focus:border-amber-500 text-sm" value="<?= h($selected_month) ?>">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Allocated Labor Budget (₱) *</label>
                <div class="flex">
                    <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-slate-300 bg-amber-100 text-amber-800 font-bold text-sm">₱</span>
                    <input type="text" name="allocated_labor_budget" id="budgetAmount" required placeholder="0.00"
                           oninput="this.value = this.value.replace(/[^0-9.,]/g, '')"
                           class="w-full rounded-r-lg border-slate-300 border p-2.5 focus:ring-amber-500 focus:border-amber-500 text-sm font-bold">
                </div>
                <p class="text-xs text-slate-500 mt-1">The maximum labor and expense spend for this branch during this month.</p>
            </div>

            <div class="mt-6 flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal('budgetModal')" class="px-4 py-2 border border-slate-300 rounded-xl text-slate-700 hover:bg-slate-50 font-medium text-sm transition-colors">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-amber-500 hover:bg-amber-400 text-espresso-950 font-bold rounded-xl text-sm transition-colors shadow-sm">Save Budget</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('hidden');
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('hidden');
    if (id === 'budgetModal') {
        document.getElementById('budgetModalTitle').innerHTML = '<i class="fa-solid fa-wallet text-amber-400 mr-2"></i> Allocate Branch Budget';
        document.getElementById('budgetForm').reset();
        document.getElementById('budgetMonth').value = '<?= h($selected_month) ?>';
    }
}

function editBudget(row) {
    document.getElementById('budgetModalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square text-amber-400 mr-2"></i> Edit Budget Allocation';
    const branchSelect = document.getElementById('budgetBranch');
    if (branchSelect && branchSelect.tagName === 'SELECT') {
        branchSelect.value = row.branch_id;
    }
    document.getElementById('budgetMonth').value = row.budget_month;
    document.getElementById('budgetAmount').value = parseFloat(row.allocated_labor_budget).toLocaleString('en-US', {minimumFractionDigits: 2});
    openModal('budgetModal');
}

window.addEventListener('click', (e) => {
    const bm = document.getElementById('budgetModal');
    const em = document.getElementById('expenseModal');
    if (e.target === bm) closeModal('budgetModal');
    if (e.target === em) closeModal('expenseModal');
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
