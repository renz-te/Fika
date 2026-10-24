<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/core/Output.php';

Auth::requireLogin();
Rbac::require_permission('finance.view');

global $pdo;
$currentUser = Auth::user();

// 1. KPI Queries
$monthRevenue = 0.0;
$monthRevenueCents = 0;
$monthExpensesCents = 0;
$monthExpenses = 0.0;

try {
    $revStmt = $pdo->query("
        SELECT COALESCE(SUM(final_amount), 0) 
        FROM orders 
        WHERE payment_status = 'PAID' 
          AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00')
    ");
    $monthRevenue = (float)($revStmt ? $revStmt->fetchColumn() : 0.0);
    $monthRevenueCents = Money::toCentavos($monthRevenue);

    $expStmt = $pdo->query("
        SELECT COALESCE(SUM(amount_cents), 0) 
        FROM expenses 
        WHERE status = 'APPROVED' 
          AND expense_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
    ");
    $monthExpensesCents = (int)($expStmt ? $expStmt->fetchColumn() : 0);
    $monthExpenses = $monthExpensesCents / 100;
} catch (Throwable $e) {
    // Graceful fallback
}

$netProfitCents = $monthRevenueCents - $monthExpensesCents;
$isProfitable = $netProfitCents >= 0;

// 2. Recent Approved Expenses (Last 5 records)
$recentExpenses = [];
try {
    $recentStmt = $pdo->query("
        SELECT e.id, e.amount_cents, e.expense_date, e.payee, e.notes, ec.name as category_name, b.name as branch_name 
        FROM expenses e 
        LEFT JOIN expense_categories ec ON e.category_id = ec.id 
        LEFT JOIN branches b ON e.branch_id = b.id 
        WHERE e.status = 'APPROVED' 
        ORDER BY e.expense_date DESC, e.id DESC 
        LIMIT 5
    ");
    if ($recentStmt) {
        $recentExpenses = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $recentExpenses = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance & Accounting Dashboard &middot; Fika Café</title>
    <link rel="icon" type="image/svg+xml" href="../public/assets/logo.svg">
    <script src="https://cdn.tailwindcss.com"></script>
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
                        <span class="text-base font-extrabold text-slate-900 tracking-tight block leading-none">Finance & Treasury</span>
                        <span class="text-[10px] uppercase font-bold text-emerald-700 tracking-wider">General Ledger & P&L</span>
                    </div>
                </div>

                <nav class="flex items-center gap-6 text-sm font-semibold text-slate-600">
                    <a href="fika_finance_dashboard.php" class="text-emerald-600 font-bold border-b-2 border-emerald-600 py-5">Dashboard</a>
                    <a href="fika_finance_budgets.php" class="hover:text-slate-900 transition-colors py-5">Budgets & Expenses</a>
                    <a href="fika_finance_reconcile.php" class="hover:text-slate-900 transition-colors py-5">Reconciliation</a>
                    <a href="fika_finance_reports.php" class="hover:text-slate-900 transition-colors py-5">Financial Reports</a>
                </nav>
            </div>
        </header>

    <!-- Main Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Header & Month Badge -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Finance Overview</h1>
                <p class="text-sm text-slate-500 mt-1">Month-to-date revenue intake, audited operational disbursements, and bottom-line margin.</p>
            </div>
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-semibold text-slate-600 shadow-sm">
                <i class="fa-regular fa-calendar-days text-emerald-600"></i>
                <span>Period: <?= date('F Y') ?> (MTD)</span>
            </div>
        </div>

        <!-- 3 KPI Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- 1. This Month's Revenue -->
            <div class="bg-white rounded-xl p-6 border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">This Month's Revenue</span>
                    <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-arrow-trend-up"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <span class="text-3xl font-black text-slate-900 tracking-tight">
                        <?= Money::format($monthRevenueCents) ?>
                    </span>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                    <span>Reconciled POS sales</span>
                    <a href="fika_finance_reports.php" class="text-emerald-600 font-semibold hover:underline">View sales report</a>
                </div>
            </div>

            <!-- 2. This Month's Expenses -->
            <div class="bg-white rounded-xl p-6 border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">This Month's Expenses</span>
                    <div class="w-10 h-10 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <span class="text-3xl font-black text-slate-900 tracking-tight">
                        <?= Money::format($monthExpensesCents) ?>
                    </span>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                    <span>Approved disbursements</span>
                    <a href="fika_finance_budgets.php" class="text-rose-600 font-semibold hover:underline">Manage expenses</a>
                </div>
            </div>

            <!-- 3. Net Profit -->
            <div class="bg-white rounded-xl p-6 border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Net Margin / Profit</span>
                    <div class="w-10 h-10 rounded-lg <?= $isProfitable ? 'bg-blue-50 text-blue-600' : 'bg-red-50 text-red-600' ?> flex items-center justify-center text-lg">
                        <i class="fa-solid <?= $isProfitable ? 'fa-scale-balanced' : 'fa-triangle-exclamation' ?>"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-baseline gap-2">
                    <span class="text-3xl font-black <?= $isProfitable ? 'text-emerald-600' : 'text-red-600' ?> tracking-tight">
                        <?= ($netProfitCents < 0 ? '-' : '') . Money::format(abs($netProfitCents)) ?>
                    </span>
                    <span class="text-xs font-bold px-2 py-0.5 rounded-full <?= $isProfitable ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' ?>">
                        <?= $isProfitable ? 'Positive Cash Flow' : 'Deficit' ?>
                    </span>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                    <span>Gross Revenue minus OPEX</span>
                    <a href="fika_finance_reports.php?tab=pnl" class="text-blue-600 font-semibold hover:underline">Full P&amp;L</a>
                </div>
            </div>
        </div>

        <!-- Quick Links Hub -->
        <div class="mb-8 bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-600 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-bolt text-emerald-600"></i>
                <span>Finance Navigation</span>
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <a href="fika_finance_budgets.php" class="flex items-center gap-3.5 p-3.5 rounded-lg border border-slate-200 hover:border-emerald-400 hover:bg-emerald-50/40 transition-all group">
                    <div class="w-10 h-10 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center text-base group-hover:scale-105 transition-transform">
                        <i class="fa-solid fa-money-bill-wave"></i>
                    </div>
                    <div>
                        <p class="font-bold text-slate-900 text-sm">Operating Budgets</p>
                        <p class="text-xs text-slate-500">Branch allocations, limits & expense vouchers</p>
                    </div>
                </a>

                <a href="fika_finance_reconcile.php" class="flex items-center gap-3.5 p-3.5 rounded-lg border border-slate-200 hover:border-emerald-400 hover:bg-emerald-50/40 transition-all group">
                    <div class="w-10 h-10 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center text-base group-hover:scale-105 transition-transform">
                        <i class="fa-solid fa-cash-register"></i>
                    </div>
                    <div>
                        <p class="font-bold text-slate-900 text-sm">Cashier Reconciliation</p>
                        <p class="text-xs text-slate-500">Register drawer sessions & over/short audits</p>
                    </div>
                </a>

                <a href="fika_finance_reports.php" class="flex items-center gap-3.5 p-3.5 rounded-lg border border-slate-200 hover:border-emerald-400 hover:bg-emerald-50/40 transition-all group">
                    <div class="w-10 h-10 rounded-lg bg-purple-100 text-purple-700 flex items-center justify-center text-base group-hover:scale-105 transition-transform">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                    </div>
                    <div>
                        <p class="font-bold text-slate-900 text-sm">Financial Statements</p>
                        <p class="text-xs text-slate-500">P&amp;L statements, tax summaries & CSV export</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- Recent Approved Expenses Table -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-slate-900 text-base">Recent Approved Expenses</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Audited operational vouchers authorized by management</p>
                </div>
                <a href="fika_finance_budgets.php" class="text-xs font-semibold px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg transition-colors">
                    View All Expenses
                </a>
            </div>

            <?php if (!empty($recentExpenses)): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-700">
                        <thead class="bg-slate-50 text-xs uppercase font-bold text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-3">Expense Date</th>
                                <th class="px-6 py-3">Branch</th>
                                <th class="px-6 py-3">Payee</th>
                                <th class="px-6 py-3">Category</th>
                                <th class="px-6 py-3 text-right">Amount</th>
                                <th class="px-6 py-3 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($recentExpenses as $exp): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="px-6 py-3.5 text-xs text-slate-500 whitespace-nowrap">
                                        <?= date('M d, Y', strtotime($exp['expense_date'])) ?>
                                    </td>
                                    <td class="px-6 py-3.5 text-xs font-semibold text-slate-800">
                                        <?= e($exp['branch_name'] ?? 'All Branches') ?>
                                    </td>
                                    <td class="px-6 py-3.5 text-xs font-bold text-slate-900">
                                        <?= e($exp['payee']) ?>
                                    </td>
                                    <td class="px-6 py-3.5 text-xs text-slate-600">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-700">
                                            <?= e($exp['category_name'] ?? 'General') ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-3.5 text-right font-black text-slate-900 text-sm whitespace-nowrap">
                                        <?= Money::format((int)$exp['amount_cents']) ?>
                                    </td>
                                    <td class="px-6 py-3.5 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                            APPROVED
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <!-- Clean Empty State -->
                <div class="p-8 text-center">
                    <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3 text-lg">
                        <i class="fa-regular fa-credit-card"></i>
                    </div>
                    <p class="font-semibold text-slate-700 text-sm">No approved expenses found</p>
                    <p class="text-xs text-slate-400 mt-1">Approved operating disbursements will be displayed here.</p>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- Footer -->
    <footer class="mt-auto bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-400">
        &copy; <?= date('Y') ?> Fika Café &middot; Finance & General Ledger
    </footer>
    </div>
</body>
</html>
