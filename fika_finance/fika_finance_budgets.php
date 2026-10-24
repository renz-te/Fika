<?php
require_once __DIR__ . '/../app/bootstrap.php';

Auth::requireLogin();
Rbac::require_permission('finance.view');

$user = Auth::user();
$canCreateExpense = Rbac::can('finance.expense.create');
$canApproveExpense = Rbac::can('finance.expense.approve');
$canManageBudget = Rbac::can('finance.budget.manage');
$csrfToken = Csrf::getToken();

// Fetch branches for filter / selector
global $pdo;
$isAdminOrGa = in_array($user['role_name'] ?? '', ['ADMIN', 'Super Admin', 'GA', 'Global Accountant'], true) 
               || in_array((int)($user['role_id'] ?? 0), [1, 3], true);

if ($isAdminOrGa) {
    $branches = $pdo->query("SELECT id, name FROM branches ORDER BY name ASC")->fetchAll();
} else {
    $bStmt = $pdo->prepare("SELECT id, name FROM branches WHERE id = ?");
    $bStmt->execute([$user['branch_id'] ?? 1]);
    $branches = $bStmt->fetchAll();
}

// Fetch categories for expense creation
$categories = $pdo->query("SELECT id, name FROM expense_categories ORDER BY name ASC")->fetchAll();

// Fetch threshold
$thStmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'finance_expense_approval_threshold_cents'");
$thresholdCents = (int)($thStmt->fetchColumn() ?: 100000);
$thresholdPesos = $thresholdCents / 100;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Budgets & Operating Expenses - Fika</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <style>
        .tab-btn.active {
            border-bottom: 2px solid #059669;
            color: #059669;
            font-weight: 600;
        }
        .modal {
            transition: opacity 0.2s ease, visibility 0.2s ease;
        }
        .modal.hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-100 text-slate-800 font-sans antialiased min-h-screen flex">

    <?php require_once __DIR__ . '/../fika_hrms/includes/header.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto">
        <!-- Module Header & Sub-Nav -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-xl shadow-sm">
                        <i class="fa-solid fa-chart-line text-base"></i>
                    </div>
                    <div>
                        <span class="text-base font-extrabold text-slate-900 tracking-tight block leading-none">Budgets & Operating Expenses</span>
                        <span class="text-[10px] uppercase font-bold text-emerald-700 tracking-wider">Disbursements & Maker-Checker</span>
                    </div>
                </div>

                <nav class="flex items-center gap-6 text-sm font-semibold text-slate-600">
                    <a href="fika_finance_dashboard.php" class="hover:text-slate-900 transition-colors py-5">Dashboard</a>
                    <a href="fika_finance_budgets.php" class="text-emerald-600 font-bold border-b-2 border-emerald-600 py-5">Budgets & Expenses</a>
                    <a href="fika_finance_reconcile.php" class="hover:text-slate-900 transition-colors py-5">Reconciliation</a>
                    <a href="fika_finance_reports.php" class="hover:text-slate-900 transition-colors py-5">Financial Reports</a>
                </nav>
            </div>
        </header>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 w-full flex-1">

    <!-- Main Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Toast / Alerts -->
        <div id="alertBox" class="hidden mb-6 p-4 rounded-lg text-sm border shadow-sm transition-all" role="alert"></div>

        <!-- Global Filters / Scope Bar -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6 flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-4 text-xs">
                <div>
                    <label class="block font-semibold text-gray-500 mb-1">Branch Scope</label>
                    <select id="scopeBranch" onchange="refreshAllData()" class="border border-gray-300 rounded-md p-1.5 bg-white font-medium">
                        <?php foreach ($branches as $br): ?>
                            <option value="<?= (int)$br['id'] ?>"><?= e($br['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block font-semibold text-gray-500 mb-1">Budget Month</label>
                    <input type="month" id="scopeMonth" value="<?= date('Y-m') ?>" onchange="refreshAllData()" class="border border-gray-300 rounded-md p-1.5 bg-white font-medium">
                </div>
            </div>

            <div class="flex items-center space-x-3">
                <?php if ($canCreateExpense): ?>
                <button type="button" onclick="openExpenseModal()" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 font-medium text-xs shadow-sm flex items-center gap-1.5">
                    <span>+ Add Operating Expense</span>
                </button>
                <?php endif; ?>

                <?php if ($canManageBudget): ?>
                <button type="button" onclick="openBudgetModal()" class="px-3 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-medium text-xs shadow-sm">
                    Set Monthly Budget
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 mb-6 flex justify-between items-center">
            <nav class="flex space-x-8" aria-label="Tabs">
                <button type="button" onclick="switchTab('budget')" id="tabBtnBudget" class="tab-btn active py-3 px-1 text-sm font-medium text-gray-500 hover:text-gray-700">
                    Budget Summary
                </button>
                <button type="button" onclick="switchTab('expenses')" id="tabBtnExpenses" class="tab-btn py-3 px-1 text-sm font-medium text-gray-500 hover:text-gray-700">
                    Operating Expenses
                </button>
                <?php if ($canApproveExpense): ?>
                <button type="button" onclick="switchTab('approvals')" id="tabBtnApprovals" class="tab-btn py-3 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 flex items-center gap-1.5">
                    <span>Approval Queue</span>
                    <span id="approvalBadge" class="hidden bg-amber-100 text-amber-800 text-xs px-2 py-0.5 rounded-full font-bold">0</span>
                </button>
                <?php endif; ?>
            </nav>
        </div>

        <!-- TAB 1: BUDGET OVERVIEW -->
        <section id="sectionBudget" class="space-y-6">
            <!-- Metric Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                    <span class="text-xs font-semibold uppercase text-gray-500 block mb-1">Allocated Budget</span>
                    <div class="text-2xl font-bold text-gray-900" id="cardAllocated">₱0.00</div>
                    <span class="text-[11px] text-gray-400 mt-1 block">Set for selected month</span>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                    <span class="text-xs font-semibold uppercase text-gray-500 block mb-1">Approved Spend</span>
                    <div class="text-2xl font-bold text-indigo-600" id="cardSpent">₱0.00</div>
                    <span class="text-[11px] text-gray-400 mt-1 block">Approved operating expenses</span>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                    <span class="text-xs font-semibold uppercase text-gray-500 block mb-1">Remaining Balance</span>
                    <div class="text-2xl font-bold text-emerald-600" id="cardRemaining">₱0.00</div>
                    <span class="text-[11px] text-gray-400 mt-1 block" id="cardRemainingSub">Within budget</span>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-xs font-semibold uppercase text-gray-500 block">Utilization</span>
                        <span class="text-xs font-bold text-gray-700" id="utilizationPct">0%</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2.5 mt-2 overflow-hidden">
                        <div id="utilizationBar" class="bg-emerald-500 h-2.5 rounded-full transition-all" style="width: 0%"></div>
                    </div>
                    <span class="text-[11px] text-gray-400 mt-2 block" id="utilizationLabel">Healthy spend</span>
                </div>
            </div>

            <!-- Auto-Approval Notice -->
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-xs text-blue-900 flex items-start gap-3">
                <span class="text-base"><i class="fa-solid fa-bolt text-blue-600"></i></span>
                <div>
                    <strong>Threshold Rules:</strong> Minor expenses of <strong>₱<?= number_format($thresholdPesos, 2) ?></strong> or less are <strong>automatically approved</strong> upon entry. Expenses exceeding ₱<?= number_format($thresholdPesos, 2) ?> require approval from the Branch Accountant (with Maker-Checker enforcement).
                </div>
            </div>
        </section>

        <!-- TAB 2: EXPENSES LIST -->
        <section id="sectionExpenses" class="hidden space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-4 border-b border-gray-200 flex flex-wrap justify-between items-center gap-3 bg-gray-50">
                    <div class="flex items-center space-x-2">
                        <span class="text-xs font-semibold text-gray-500 uppercase">Filter Status:</span>
                        <select id="expenseStatusFilter" onchange="loadExpenses()" class="text-xs border-gray-300 rounded-md shadow-sm border px-2.5 py-1.5 bg-white">
                            <option value="">All Statuses</option>
                            <option value="APPROVED">Approved</option>
                            <option value="DRAFT">Draft (Pending)</option>
                            <option value="VOIDED">Voided</option>
                        </select>
                    </div>
                    <div class="text-xs text-gray-500">
                        Expenses: <span id="expenseCount" class="font-bold text-gray-700">0</span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600 text-xs uppercase border-b border-gray-200">
                                <th class="p-3.5 font-semibold">Date</th>
                                <th class="p-3.5 font-semibold">Payee</th>
                                <th class="p-3.5 font-semibold">Category</th>
                                <th class="p-3.5 font-semibold text-right">Amount</th>
                                <th class="p-3.5 font-semibold">Status</th>
                                <th class="p-3.5 font-semibold">Created By</th>
                                <th class="p-3.5 font-semibold">Receipt</th>
                                <th class="p-3.5 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="expensesTableBody" class="divide-y divide-gray-200 text-xs">
                            <tr>
                                <td colspan="8" class="p-8 text-center text-gray-500">Loading expenses...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- TAB 3: APPROVAL QUEUE -->
        <?php if ($canApproveExpense): ?>
        <section id="sectionApprovals" class="hidden space-y-4">
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-xs text-amber-800 flex items-start gap-2.5">
                <span class="text-base"><i class="fa-solid fa-scale-balanced text-amber-600"></i></span>
                <div>
                    <span class="font-bold">Maker-Checker Policy:</span> Branch Managers and Staff submit expenses. Branch Accountants review and approve expenses exceeding ₱<?= number_format($thresholdPesos, 2) ?>. You cannot approve an expense that you submitted.
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-4 border-b border-gray-200 bg-gray-50">
                    <h2 class="text-sm font-bold text-gray-700">DRAFT Expenses Awaiting Approval</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600 text-xs uppercase border-b border-gray-200">
                                <th class="p-3.5 font-semibold">Date</th>
                                <th class="p-3.5 font-semibold">Payee</th>
                                <th class="p-3.5 font-semibold">Category</th>
                                <th class="p-3.5 font-semibold text-right">Amount</th>
                                <th class="p-3.5 font-semibold">Submitted By</th>
                                <th class="p-3.5 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="approvalTableBody" class="divide-y divide-gray-200 text-xs">
                            <tr>
                                <td colspan="6" class="p-8 text-center text-gray-500">Loading pending expenses...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <!-- MODAL 1: ADD OPERATING EXPENSE -->
    <div id="modalExpense" class="modal hidden fixed inset-0 z-50 bg-gray-900 bg-opacity-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl border border-gray-200 max-w-lg w-full overflow-hidden">
            <div class="p-5 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                <h3 class="text-base font-bold text-gray-900">Record Operating Expense</h3>
                <button type="button" onclick="closeModal('modalExpense')" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
            </div>

            <form id="formExpense" onsubmit="handleSaveExpense(event)" enctype="multipart/form-data" class="p-6 space-y-4 text-xs">
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Payee / Vendor *</label>
                    <input type="text" id="expPayee" required placeholder="e.g. Meralco, Manila Water, Landlord" class="w-full border border-gray-300 rounded-lg p-2.5">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-gray-700 mb-1">Category *</label>
                        <select id="expCategory" required class="w-full border border-gray-300 rounded-lg p-2.5 bg-white">
                            <option value="">-- Choose Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold text-gray-700 mb-1">Expense Date *</label>
                        <input type="date" id="expDate" required value="<?= date('Y-m-d') ?>" class="w-full border border-gray-300 rounded-lg p-2.5">
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Amount (₱) *</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 font-bold">₱</span>
                        <input type="number" step="0.01" min="0.01" id="expAmount" required oninput="checkAutoApprovalNotice(this.value)" placeholder="0.00" class="w-full pl-8 border border-gray-300 rounded-lg p-2.5 font-bold text-gray-900">
                    </div>
                    <p id="expThresholdNotice" class="text-[11px] text-gray-500 mt-1">
                        Amount <= ₱<?= number_format($thresholdPesos, 2) ?> will be automatically approved.
                    </p>
                </div>

                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Receipt Attachment (Optional)</label>
                    <input type="file" id="expReceipt" accept="image/jpeg,image/png,application/pdf" class="w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                    <p class="text-[11px] text-gray-400 mt-1">Allowed: JPG, PNG, PDF (max 5MB). Verified via secure MIME check.</p>
                </div>

                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Notes / Description</label>
                    <textarea id="expNotes" rows="2" placeholder="Optional reference notes..." class="w-full border border-gray-300 rounded-lg p-2.5"></textarea>
                </div>

                <div class="flex justify-end space-x-3 pt-3 border-t border-gray-200">
                    <button type="button" onclick="closeModal('modalExpense')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-xs font-medium hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" id="btnSubmitExpense" class="px-5 py-2 bg-emerald-600 text-white rounded-lg text-xs font-semibold hover:bg-emerald-700 shadow-sm">
                        Record Expense
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: SET / EDIT MONTHLY BUDGET -->
    <?php if ($canManageBudget): ?>
    <div id="modalBudget" class="modal hidden fixed inset-0 z-50 bg-gray-900 bg-opacity-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl border border-gray-200 max-w-md w-full overflow-hidden">
            <div class="p-5 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                <h3 class="text-base font-bold text-gray-900">Set Monthly Branch Budget</h3>
                <button type="button" onclick="closeModal('modalBudget')" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
            </div>

            <form id="formBudget" onsubmit="handleSaveBudget(event)" class="p-6 space-y-4 text-xs">
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Target Branch *</label>
                    <select id="budgetBranchSelect" required class="w-full border border-gray-300 rounded-lg p-2.5 bg-white">
                        <?php foreach ($branches as $br): ?>
                            <option value="<?= (int)$br['id'] ?>"><?= e($br['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Budget Month *</label>
                    <input type="month" id="budgetMonthInput" required value="<?= date('Y-m') ?>" class="w-full border border-gray-300 rounded-lg p-2.5">
                </div>

                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Allocated Budget (₱) *</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 font-bold">₱</span>
                        <input type="number" step="0.01" min="0" id="budgetAmountInput" required placeholder="0.00" class="w-full pl-8 border border-gray-300 rounded-lg p-2.5 font-bold text-gray-900">
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-3 border-t border-gray-200">
                    <button type="button" onclick="closeModal('modalBudget')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-xs font-medium hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 bg-emerald-600 text-white rounded-lg text-xs font-semibold hover:bg-emerald-700 shadow-sm">
                        Save Budget Allocation
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- MODAL 3: VOID EXPENSE REASON PROMPT -->
    <div id="modalVoid" class="modal hidden fixed inset-0 z-50 bg-gray-900 bg-opacity-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl border border-gray-200 max-w-md w-full overflow-hidden">
            <div class="p-5 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                <h3 class="text-base font-bold text-red-600">Void Operating Expense</h3>
                <button type="button" onclick="closeModal('modalVoid')" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
            </div>

            <form id="formVoid" onsubmit="handleVoidExpense(event)" class="p-6 space-y-4 text-xs">
                <input type="hidden" id="voidExpenseId" value="">
                <div class="bg-red-50 border border-red-200 p-3 rounded-lg text-red-800">
                    Voiding removes this expense from budget deductions while preserving an immutable audit record.
                </div>

                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Reason for Voiding *</label>
                    <textarea id="voidReasonInput" required rows="3" placeholder="Explain why this expense is being voided..." class="w-full border border-gray-300 rounded-lg p-2.5"></textarea>
                </div>

                <div class="flex justify-end space-x-3 pt-3 border-t border-gray-200">
                    <button type="button" onclick="closeModal('modalVoid')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-xs font-medium hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 bg-red-600 text-white rounded-lg text-xs font-semibold hover:bg-red-700 shadow-sm">
                        Confirm Void
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        const currentUserId = <?= (int)$user['id'] ?>;
        const userCanApprove = <?= $canApproveExpense ? 'true' : 'false' ?>;
        const approvalThresholdPesos = <?= $thresholdPesos ?>;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        let expensesList = [];

        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('sectionBudget').classList.add('hidden');
            document.getElementById('sectionExpenses').classList.add('hidden');
            const secApp = document.getElementById('sectionApprovals');
            if (secApp) secApp.classList.add('hidden');

            if (tab === 'budget') {
                document.getElementById('tabBtnBudget').classList.add('active');
                document.getElementById('sectionBudget').classList.remove('hidden');
                loadBudgetSummary();
            } else if (tab === 'expenses') {
                document.getElementById('tabBtnExpenses').classList.add('active');
                document.getElementById('sectionExpenses').classList.remove('hidden');
                loadExpenses();
            } else if (tab === 'approvals') {
                document.getElementById('tabBtnApprovals').classList.add('active');
                if (secApp) secApp.classList.remove('hidden');
                loadApprovalQueue();
            }
        }

        function showAlert(msg, isError = false) {
            const box = document.getElementById('alertBox');
            box.className = isError 
                ? 'mb-6 p-4 rounded-lg text-sm border shadow-sm transition-all bg-red-50 border-red-200 text-red-800'
                : 'mb-6 p-4 rounded-lg text-sm border shadow-sm transition-all bg-emerald-50 border-emerald-200 text-emerald-800';
            box.textContent = msg;
            box.classList.remove('hidden');
            box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            setTimeout(() => box.classList.add('hidden'), 5000);
        }

        function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
        function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

        function checkAutoApprovalNotice(val) {
            const num = parseFloat(val || 0);
            const notice = document.getElementById('expThresholdNotice');
            if (num > approvalThresholdPesos) {
                notice.innerHTML = `<span class="text-amber-600 font-semibold"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Exceeds ₱${approvalThresholdPesos.toFixed(2)}: Will be held in DRAFT queue for Accountant approval.</span>`;
            } else {
                notice.innerHTML = `<span class="text-emerald-600 font-semibold"><i class="fa-solid fa-bolt mr-1"></i> <= ₱${approvalThresholdPesos.toFixed(2)}: Will be automatically approved upon saving.</span>`;
            }
        }

        function refreshAllData() {
            loadBudgetSummary();
            loadExpenses();
        }

        // =================================================================
        // LOAD BUDGET SUMMARY
        // =================================================================
        async function loadBudgetSummary() {
            const branchId = document.getElementById('scopeBranch').value;
            const month = document.getElementById('scopeMonth').value;

            try {
                const res = await fetch(`api/fika_finance_expenses.php?action=get_budget_summary&branch_id=${branchId}&budget_month=${month}`);
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Failed to load budget summary');

                const data = json.data;
                const allocated = data.allocated_amount_cents / 100;
                const spent = data.spent_cents / 100;
                const remaining = data.remaining_cents / 100;
                const pct = data.utilization_percent;

                document.getElementById('cardAllocated').textContent = formatPesos(allocated);
                document.getElementById('cardSpent').textContent = formatPesos(spent);
                document.getElementById('cardRemaining').textContent = formatPesos(remaining);
                document.getElementById('utilizationPct').textContent = `${pct.toFixed(1)}%`;

                const bar = document.getElementById('utilizationBar');
                const sub = document.getElementById('cardRemainingSub');
                const remCard = document.getElementById('cardRemaining');

                bar.style.width = Math.min(100, Math.max(0, pct)) + '%';

                if (remaining < 0) {
                    remCard.className = 'text-2xl font-bold text-red-600';
                    sub.textContent = `Over budget by ${formatPesos(Math.abs(remaining))}`;
                    bar.className = 'bg-red-500 h-2.5 rounded-full transition-all';
                    document.getElementById('utilizationLabel').textContent = 'Deficit alert';
                } else {
                    remCard.className = 'text-2xl font-bold text-emerald-600';
                    sub.textContent = 'Within allocated budget';
                    bar.className = pct > 80 
                        ? 'bg-amber-500 h-2.5 rounded-full transition-all'
                        : 'bg-emerald-500 h-2.5 rounded-full transition-all';
                    document.getElementById('utilizationLabel').textContent = pct > 80 ? 'Approaching ceiling' : 'Healthy spend';
                }
            } catch (err) {
                showAlert(err.message, true);
            }
        }

        // =================================================================
        // LOAD EXPENSES
        // =================================================================
        async function loadExpenses() {
            const branchId = document.getElementById('scopeBranch').value;
            const month = document.getElementById('scopeMonth').value;
            const status = document.getElementById('expenseStatusFilter').value;

            let url = `api/fika_finance_expenses.php?action=list_expenses&branch_id=${branchId}&month=${month}`;
            if (status) url += `&status=${encodeURIComponent(status)}`;

            try {
                const res = await fetch(url);
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Failed to load expenses');

                expensesList = json.data || [];
                document.getElementById('expenseCount').textContent = expensesList.length;
                renderExpensesTable();
                updateApprovalBadge();
            } catch (err) {
                showAlert(err.message, true);
            }
        }

        function renderExpensesTable() {
            const tbody = document.getElementById('expensesTableBody');
            if (expensesList.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="p-8 text-center text-gray-500">No expenses recorded for this month.</td></tr>';
                return;
            }

            tbody.innerHTML = expensesList.map(e => `
                <tr class="hover:bg-gray-50">
                    <td class="p-3.5 text-gray-600 font-mono">${escapeHtml(e.expense_date)}</td>
                    <td class="p-3.5 font-bold text-gray-900">${escapeHtml(e.payee)}</td>
                    <td class="p-3.5 text-gray-600">${escapeHtml(e.category_name)}</td>
                    <td class="p-3.5 text-right font-bold font-mono text-gray-900">${formatPesos(e.amount_cents / 100)}</td>
                    <td class="p-3.5">${renderStatusBadge(e.status)}</td>
                    <td class="p-3.5 text-gray-500">${escapeHtml(e.creator_username || 'User #' + e.created_by)}</td>
                    <td class="p-3.5">
                        ${e.receipt_path ? `
                            <a href="../${escapeHtml(e.receipt_path)}" target="_blank" class="text-indigo-600 hover:text-indigo-800 font-semibold underline">View Receipt</a>
                        ` : '<span class="text-gray-400 italic">None</span>'}
                    </td>
                    <td class="p-3.5 text-right space-x-2">
                        ${e.status === 'APPROVED' && userCanApprove ? `
                            <button type="button" onclick="promptVoidExpense(${e.id})" class="text-red-600 hover:text-red-800 font-medium">Void</button>
                        ` : ''}
                        ${e.status === 'DRAFT' && userCanApprove && parseInt(e.created_by) !== currentUserId ? `
                            <button type="button" onclick="approveExpense(${e.id})" class="text-emerald-600 hover:text-emerald-800 font-semibold">Approve</button>
                            <button type="button" onclick="promptVoidExpense(${e.id})" class="text-red-600 hover:text-red-800 font-medium">Reject</button>
                        ` : ''}
                        ${e.status === 'VOIDED' && e.void_reason ? `
                            <span class="text-gray-400 italic text-[11px]" title="${escapeHtml(e.void_reason)}">Reason: ${escapeHtml(e.void_reason.substring(0, 15))}...</span>
                        ` : ''}
                    </td>
                </tr>
            `).join('');
        }

        function renderStatusBadge(st) {
            switch (st) {
                case 'APPROVED':
                    return '<span class="bg-emerald-100 text-emerald-800 border border-emerald-300 px-2 py-0.5 rounded text-xs font-semibold">APPROVED</span>';
                case 'DRAFT':
                    return '<span class="bg-amber-100 text-amber-800 border border-amber-300 px-2 py-0.5 rounded text-xs font-semibold">DRAFT</span>';
                case 'VOIDED':
                    return '<span class="bg-red-100 text-red-800 border border-red-300 px-2 py-0.5 rounded text-xs font-semibold">VOIDED</span>';
                default:
                    return `<span class="bg-gray-100 text-gray-700 px-2 py-0.5 rounded text-xs">${escapeHtml(st)}</span>`;
            }
        }

        function updateApprovalBadge() {
            const badge = document.getElementById('approvalBadge');
            if (!badge) return;
            const pending = expensesList.filter(e => e.status === 'DRAFT' && parseInt(e.created_by) !== currentUserId);
            if (pending.length > 0) {
                badge.textContent = pending.length;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }

        function loadApprovalQueue() {
            const tbody = document.getElementById('approvalTableBody');
            if (!tbody) return;
            const pending = expensesList.filter(e => e.status === 'DRAFT');

            if (pending.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="p-8 text-center text-gray-500">No expenses currently awaiting approval.</td></tr>';
                return;
            }

            tbody.innerHTML = pending.map(e => {
                const isCreator = parseInt(e.created_by) === currentUserId;
                return `
                    <tr class="hover:bg-gray-50">
                        <td class="p-3.5 text-gray-600 font-mono">${escapeHtml(e.expense_date)}</td>
                        <td class="p-3.5 font-bold text-gray-900">${escapeHtml(e.payee)}</td>
                        <td class="p-3.5 text-gray-600">${escapeHtml(e.category_name)}</td>
                        <td class="p-3.5 text-right font-bold text-gray-900">${formatPesos(e.amount_cents / 100)}</td>
                        <td class="p-3.5 text-gray-600">${escapeHtml(e.creator_username || 'User #' + e.created_by)} ${isCreator ? '<span class="text-xs text-amber-600 font-bold">(You)</span>' : ''}</td>
                        <td class="p-3.5 text-right">
                            ${isCreator ? `
                                <span class="text-xs text-gray-400 italic">Self-approval blocked</span>
                            ` : userCanApprove ? `
                                <button type="button" onclick="approveExpense(${e.id})" class="px-3 py-1 bg-emerald-600 text-white rounded text-xs font-semibold hover:bg-emerald-700">Approve</button>
                                <button type="button" onclick="promptVoidExpense(${e.id})" class="px-2 py-1 text-red-600 hover:text-red-800 text-xs font-medium">Reject</button>
                            ` : `
                                <span class="text-xs text-gray-400 italic">Approval restricted</span>
                            `}
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // =================================================================
        // ACTIONS: SAVE EXPENSE
        // =================================================================
        function openExpenseModal() {
            document.getElementById('formExpense').reset();
            document.getElementById('expDate').value = new Date().toISOString().split('T')[0];
            checkAutoApprovalNotice(0);
            openModal('modalExpense');
        }

        async function handleSaveExpense(e) {
            e.preventDefault();
            const branchId = document.getElementById('scopeBranch').value;
            const payee = document.getElementById('expPayee').value.trim();
            const categoryId = document.getElementById('expCategory').value;
            const expenseDate = document.getElementById('expDate').value;
            const amount = document.getElementById('expAmount').value;
            const notes = document.getElementById('expNotes').value.trim();
            const receiptFile = document.getElementById('expReceipt').files[0];

            const formData = new FormData();
            formData.append('branch_id', branchId);
            formData.append('payee', payee);
            formData.append('category_id', categoryId);
            formData.append('expense_date', expenseDate);
            formData.append('amount', amount);
            formData.append('notes', notes);
            if (receiptFile) {
                formData.append('receipt', receiptFile);
            }

            try {
                const res = await fetch('api/fika_finance_expenses.php?action=save_expense', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrfToken },
                    body: formData
                });
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Failed to record expense');

                const msg = json.auto_approved 
                    ? 'Expense recorded and automatically approved!' 
                    : 'Expense recorded in DRAFT queue awaiting approval.';
                showAlert(msg);
                closeModal('modalExpense');
                refreshAllData();
            } catch (err) {
                showAlert(err.message, true);
            }
        }

        // =================================================================
        // ACTIONS: APPROVE & VOID
        // =================================================================
        async function approveExpense(expenseId) {
            if (!confirm('Approve this expense?')) return;
            try {
                const res = await fetch('api/fika_finance_expenses.php?action=approve_expense', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({ expense_id: expenseId })
                });
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Approval failed');
                showAlert('Expense approved successfully!');
                refreshAllData();
            } catch (err) {
                showAlert(err.message, true);
            }
        }

        function promptVoidExpense(expenseId) {
            document.getElementById('voidExpenseId').value = expenseId;
            document.getElementById('voidReasonInput').value = '';
            openModal('modalVoid');
        }

        async function handleVoidExpense(e) {
            e.preventDefault();
            const expenseId = parseInt(document.getElementById('voidExpenseId').value);
            const voidReason = document.getElementById('voidReasonInput').value.trim();

            if (!voidReason) {
                showAlert('Void reason is strictly required', true);
                return;
            }

            try {
                const res = await fetch('api/fika_finance_expenses.php?action=void_expense', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        expense_id: expenseId,
                        void_reason: voidReason
                    })
                });
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Failed to void expense');
                showAlert('Expense voided successfully.');
                closeModal('modalVoid');
                refreshAllData();
            } catch (err) {
                showAlert(err.message, true);
            }
        }

        // =================================================================
        // ACTIONS: SAVE BUDGET
        // =================================================================
        function openBudgetModal() {
            document.getElementById('budgetBranchSelect').value = document.getElementById('scopeBranch').value;
            document.getElementById('budgetMonthInput').value = document.getElementById('scopeMonth').value;
            document.getElementById('budgetAmountInput').value = '';
            openModal('modalBudget');
        }

        async function handleSaveBudget(e) {
            e.preventDefault();
            const branchId = document.getElementById('budgetBranchSelect').value;
            const month = document.getElementById('budgetMonthInput').value;
            const amount = document.getElementById('budgetAmountInput').value;

            try {
                const res = await fetch('api/fika_finance_expenses.php?action=save_budget', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        branch_id: parseInt(branchId),
                        budget_month: month,
                        allocated_amount: amount
                    })
                });
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Failed to save budget');
                showAlert('Monthly budget allocation updated successfully!');
                closeModal('modalBudget');
                document.getElementById('scopeBranch').value = branchId;
                document.getElementById('scopeMonth').value = month;
                refreshAllData();
            } catch (err) {
                showAlert(err.message, true);
            }
        }

        function formatPesos(amt) {
            return (amt || 0).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        document.addEventListener('DOMContentLoaded', () => {
            refreshAllData();
        });
    </script>
    </div>
    </div>
</body>
</html>
