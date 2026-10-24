<?php
/**
 * Fika Rebuild Core - Financial Reports Dashboard
 * Daily Sales, Shift Settlement, and P&L Statements.
 * Computes live integer centavos SQL metrics on-the-fly and enforces Rbac::branch_scope().
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/core/Output.php';

Auth::requireLogin();
Rbac::require_permission('finance.view');

$user = Auth::user();
$userId = (int)$user['id'];
$userBranchId = isset($user['branch_id']) ? (int)$user['branch_id'] : null;
$isAdminOrGa = in_array($user['role_name'] ?? '', ['ADMIN', 'Super Admin', 'GA', 'Global Accountant'], true) 
               || in_array((int)($user['role_id'] ?? 0), [1, 3], true);

$csrfToken = Csrf::getToken();
global $pdo;

// Date range filters
$startDate = trim($_GET['start_date'] ?? date('Y-m-01'));
$endDate = trim($_GET['end_date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $startDate = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $endDate = date('Y-m-d');
}

$startDateTime = $startDate . ' 00:00:00';
$endDateTime = $endDate . ' 23:59:59';

// Branch Filter / Scoping
if (!$isAdminOrGa) {
    if (isset($_GET['branch_id']) && $_GET['branch_id'] !== '' && (int)$_GET['branch_id'] !== $userBranchId) {
        http_response_code(403);
        die("Forbidden: Cannot view reports for another branch.");
    }
    $selectedBranchId = $userBranchId;
} else {
    $selectedBranchId = !empty($_GET['branch_id']) ? (int)$_GET['branch_id'] : null;
}


// Fetch branches for filter
if ($isAdminOrGa) {
    $branches = $pdo->query("SELECT id, name FROM branches ORDER BY name ASC")->fetchAll();
} else {
    $bStmt = $pdo->prepare("SELECT id, name FROM branches WHERE id = ?");
    $bStmt->execute([$userBranchId]);
    $branches = $bStmt->fetchAll();
}

$activeTab = trim($_GET['tab'] ?? 'sales');

// =====================================================================
// SQL COMPUTATIONS: DAILY SALES
// =====================================================================
$salesSql = "
    SELECT 
        COALESCE(NULLIF(payment_method, ''), 'CASH') AS method,
        COUNT(*) AS tx_count,
        COALESCE(SUM(ROUND(final_amount * 100)), 0) AS total_cents
    FROM orders
    WHERE payment_status = 'PAID'
      AND is_test = 0
      AND created_at BETWEEN ? AND ?
";
$salesParams = [$startDateTime, $endDateTime];
if ($selectedBranchId !== null) {
    $salesSql .= " AND branch_id = ?";
    $salesParams[] = $selectedBranchId;
}
$salesSql .= " GROUP BY method ORDER BY total_cents DESC";

$salesStmt = $pdo->prepare($salesSql);
$salesStmt->execute($salesParams);
$methodSales = $salesStmt->fetchAll();

$totalPaidSalesCents = 0;
foreach ($methodSales as $ms) {
    $totalPaidSalesCents += (int)$ms['total_cents'];
}

$refundSql = "
    SELECT 
        COALESCE(SUM(CASE WHEN payment_status = 'REFUNDED' THEN ROUND(final_amount * 100) ELSE 0 END), 0) AS refund_cents,
        COUNT(CASE WHEN payment_status = 'REFUNDED' THEN 1 END) AS refund_count,
        COALESCE(SUM(CASE WHEN order_status = 'VOID' THEN ROUND(final_amount * 100) ELSE 0 END), 0) AS void_cents,
        COUNT(CASE WHEN order_status = 'VOID' THEN 1 END) AS void_count
    FROM orders
    WHERE is_test = 0 AND created_at BETWEEN ? AND ?
";
$refundParams = [$startDateTime, $endDateTime];
if ($selectedBranchId !== null) {
    $refundSql .= " AND branch_id = ?";
    $refundParams[] = $selectedBranchId;
}
$refundStmt = $pdo->prepare($refundSql);
$refundStmt->execute($refundParams);
$refundRow = $refundStmt->fetch();

$refundCents = (int)($refundRow['refund_cents'] ?? 0);
$voidCents = (int)($refundRow['void_cents'] ?? 0);
$netSalesCents = $totalPaidSalesCents - $refundCents;

// =====================================================================
// SQL COMPUTATIONS: SHIFT SETTLEMENT
// =====================================================================
$settleSql = "
    SELECT cs.*, b.name AS branch_name, u.username AS cashier_username
    FROM cash_sessions cs
    JOIN branches b ON cs.branch_id = b.id
    JOIN users u ON cs.cashier_id = u.id
    WHERE cs.status = 'CLOSED'
      AND cs.closed_at BETWEEN ? AND ?
";
$settleParams = [$startDateTime, $endDateTime];
if ($selectedBranchId !== null) {
    $settleSql .= " AND cs.branch_id = ?";
    $settleParams[] = $selectedBranchId;
}
$settleSql .= " ORDER BY cs.closed_at DESC";

$settleStmt = $pdo->prepare($settleSql);
$settleStmt->execute($settleParams);
$settlementSessions = $settleStmt->fetchAll();

$totalExpectedCashCents = 0;
$totalCountedCashCents = 0;
$totalVarianceCents = 0;
foreach ($settlementSessions as $cs) {
    $totalExpectedCashCents += (int)round((float)$cs['expected_cash'] * 100);
    $totalCountedCashCents += (int)round((float)($cs['ending_cash'] ?? 0) * 100);
    $var = (float)($cs['ending_cash'] ?? 0) - (float)$cs['expected_cash'];
    $totalVarianceCents += (int)round($var * 100);
}

// =====================================================================
// SQL COMPUTATIONS: P&L STATEMENT (Constraint 1 & Constraint 2)
// =====================================================================
// 1. Gross Revenue & Net Revenue
$pnlRevSql = "
    SELECT 
        COALESCE(SUM(CASE WHEN payment_status = 'PAID' THEN ROUND(final_amount * 100) ELSE 0 END), 0) AS gross_cents,
        COALESCE(SUM(CASE WHEN payment_status = 'REFUNDED' THEN ROUND(final_amount * 100) ELSE 0 END), 0) AS refund_cents
    FROM orders
    WHERE is_test = 0
      AND created_at BETWEEN ? AND ?
";
$pnlRevParams = [$startDateTime, $endDateTime];
if ($selectedBranchId !== null) {
    $pnlRevSql .= " AND branch_id = ?";
    $pnlRevParams[] = $selectedBranchId;
}
$pnlRevStmt = $pdo->prepare($pnlRevSql);
$pnlRevStmt->execute($pnlRevParams);
$pnlRevRow = $pnlRevStmt->fetch();

$pnlGrossRevCents = (int)($pnlRevRow['gross_cents'] ?? 0);
$pnlRefundCents = (int)($pnlRevRow['refund_cents'] ?? 0);
$pnlNetRevCents = $pnlGrossRevCents - $pnlRefundCents;

// 2. Strict COGS Calculation (Constraint 1: from inventory_ledger WHERE type = 'SALE' for PAID orders)
$cogsSql = "
    SELECT COALESCE(SUM(ROUND(ABS(il.quantity) * il.unit_cost_snapshot * 100)), 0) AS cogs_cents
    FROM inventory_ledger il
    JOIN orders o ON il.order_id = o.id
    WHERE il.type = 'SALE'
      AND o.payment_status = 'PAID'
      AND o.is_test = 0
      AND o.created_at BETWEEN ? AND ?
";
$cogsParams = [$startDateTime, $endDateTime];
if ($selectedBranchId !== null) {
    $cogsSql .= " AND o.branch_id = ?";
    $cogsParams[] = $selectedBranchId;
}
$cogsStmt = $pdo->prepare($cogsSql);
$cogsStmt->execute($cogsParams);
$pnlCogsCents = (int)$cogsStmt->fetchColumn();

$pnlGrossProfitCents = $pnlNetRevCents - $pnlCogsCents;

// 3. Operating Expenses (APPROVED only)
$pnlExpSql = "
    SELECT 
        ec.name AS category_name,
        COALESCE(SUM(e.amount_cents), 0) AS cat_cents
    FROM expenses e
    JOIN expense_categories ec ON e.category_id = ec.id
    WHERE e.status = 'APPROVED'
      AND e.expense_date BETWEEN ? AND ?
";
$pnlExpParams = [$startDate, $endDate];
if ($selectedBranchId !== null) {
    $pnlExpSql .= " AND e.branch_id = ?";
    $pnlExpParams[] = $selectedBranchId;
}
$pnlExpSql .= " GROUP BY ec.id ORDER BY cat_cents DESC";

$pnlExpStmt = $pdo->prepare($pnlExpSql);
$pnlExpStmt->execute($pnlExpParams);
$pnlOpexCategories = $pnlExpStmt->fetchAll();

$pnlTotalOpexCents = 0;
foreach ($pnlOpexCategories as $poc) {
    $pnlTotalOpexCents += (int)$poc['cat_cents'];
}

// 4. Strict Payroll Expense Calculation (Constraint 2: gross_pay + employer contributions for RELEASED runs)
$pnlPaySql = "
    SELECT COALESCE(SUM(ROUND((
        CASE 
            WHEN pi.gross_pay > 0 THEN pi.gross_pay 
            ELSE (pi.basic_pay + pi.overtime_pay + pi.bonus_pay) 
        END 
        + pi.employer_sss + pi.employer_philhealth + pi.employer_pagibig
    ) * 100)), 0) AS payroll_cents
    FROM payroll_items pi
    JOIN payroll_runs pr ON pi.payroll_run_id = pr.id
    WHERE pr.status = 'RELEASED'
      AND pr.period_end BETWEEN ? AND ?
";
$pnlPayParams = [$startDate, $endDate];
if ($selectedBranchId !== null) {
    $pnlPaySql .= " AND pr.branch_id = ?";
    $pnlPayParams[] = $selectedBranchId;
}
$pnlPayStmt = $pdo->prepare($pnlPaySql);
$pnlPayStmt->execute($pnlPayParams);
$pnlPayrollCents = (int)$pnlPayStmt->fetchColumn();

// 5. Net Profit
$pnlNetProfitCents = $pnlGrossProfitCents - $pnlTotalOpexCents - $pnlPayrollCents;
$grossMarginPct = $pnlNetRevCents > 0 ? round(($pnlGrossProfitCents / $pnlNetRevCents) * 100, 2) : 0.00;
$netMarginPct = $pnlNetRevCents > 0 ? round(($pnlNetProfitCents / $pnlNetRevCents) * 100, 2) : 0.00;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reports & P&L - Fika</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <style>
        .tab-btn.active {
            border-bottom: 2px solid #2563eb;
            color: #2563eb;
            font-weight: 600;
        }
    </style>
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
                        <span class="text-base font-extrabold text-slate-900 tracking-tight block leading-none">Financial & Sales Intelligence</span>
                        <span class="text-[10px] uppercase font-bold text-emerald-700 tracking-wider">Reports & P&L Statements</span>
                    </div>
                </div>

                <nav class="flex items-center gap-6 text-sm font-semibold text-slate-600">
                    <a href="fika_finance_dashboard.php" class="hover:text-slate-900 transition-colors py-5">Dashboard</a>
                    <a href="fika_finance_budgets.php" class="hover:text-slate-900 transition-colors py-5">Budgets & Expenses</a>
                    <a href="fika_finance_reconcile.php" class="hover:text-slate-900 transition-colors py-5">Reconciliation</a>
                    <a href="fika_finance_reports.php" class="text-emerald-600 font-bold border-b-2 border-emerald-600 py-5">Financial Reports</a>
                </nav>
            </div>
        </header>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 w-full flex-1">

    <!-- Main Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Filter Bar -->
        <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6 flex flex-wrap items-end justify-between gap-4">
            <input type="hidden" name="tab" id="activeTabInput" value="<?= e($activeTab) ?>">
            
            <div class="flex flex-wrap items-center gap-4 text-xs">
                <div>
                    <label class="block font-semibold text-gray-500 mb-1">Start Date</label>
                    <input type="date" name="start_date" value="<?= e($startDate) ?>" class="border border-gray-300 rounded-md p-2 bg-white font-medium">
                </div>

                <div>
                    <label class="block font-semibold text-gray-500 mb-1">End Date</label>
                    <input type="date" name="end_date" value="<?= e($endDate) ?>" class="border border-gray-300 rounded-md p-2 bg-white font-medium">
                </div>

                <?php if ($isAdminOrGa): ?>
                <div>
                    <label class="block font-semibold text-gray-500 mb-1">Branch Scope</label>
                    <select name="branch_id" class="border border-gray-300 rounded-md p-2 bg-white font-medium">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $br): ?>
                            <option value="<?= (int)$br['id'] ?>" <?= $selectedBranchId === (int)$br['id'] ? 'selected' : '' ?>>
                                <?= e($br['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <div>
                    <label class="block font-semibold text-gray-500 mb-1">Branch</label>
                    <input type="text" readonly value="<?= e($branches[0]['name'] ?? 'Assigned Branch') ?>" class="border border-gray-200 rounded-md p-2 bg-gray-100 text-gray-500 font-medium">
                </div>
                <?php endif; ?>

                <div>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-semibold shadow-sm">
                        Apply Filter
                    </button>
                </div>
            </div>

            <!-- Quick Ranges -->
            <div class="flex items-center space-x-2 text-xs">
                <span class="text-gray-400 font-semibold">Quick:</span>
                <a href="?tab=<?= e($activeTab) ?>&start_date=<?= date('Y-m-d') ?>&end_date=<?= date('Y-m-d') ?><?= $selectedBranchId ? '&branch_id=' . $selectedBranchId : '' ?>" class="px-2.5 py-1 bg-gray-100 hover:bg-gray-200 rounded text-gray-700 font-medium">Today</a>
                <a href="?tab=<?= e($activeTab) ?>&start_date=<?= date('Y-m-01') ?>&end_date=<?= date('Y-m-d') ?><?= $selectedBranchId ? '&branch_id=' . $selectedBranchId : '' ?>" class="px-2.5 py-1 bg-gray-100 hover:bg-gray-200 rounded text-gray-700 font-medium">This Month</a>
                <a href="?tab=<?= e($activeTab) ?>&start_date=<?= date('Y-m-01', strtotime('first day of last month')) ?>&end_date=<?= date('Y-m-t', strtotime('last day of last month')) ?><?= $selectedBranchId ? '&branch_id=' . $selectedBranchId : '' ?>" class="px-2.5 py-1 bg-gray-100 hover:bg-gray-200 rounded text-gray-700 font-medium">Last Month</a>
            </div>
        </form>

        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 mb-6 flex justify-between items-center">
            <nav class="flex space-x-8" aria-label="Tabs">
                <button type="button" onclick="switchReportTab('sales')" id="tabBtnSales" class="tab-btn <?= $activeTab === 'sales' ? 'active' : '' ?> py-3 px-1 text-sm font-medium text-gray-500 hover:text-gray-700">
                    Daily Sales
                </button>
                <button type="button" onclick="switchReportTab('settlement')" id="tabBtnSettlement" class="tab-btn <?= $activeTab === 'settlement' ? 'active' : '' ?> py-3 px-1 text-sm font-medium text-gray-500 hover:text-gray-700">
                    Shift Settlement
                </button>
                <button type="button" onclick="switchReportTab('pnl')" id="tabBtnPnl" class="tab-btn <?= $activeTab === 'pnl' ? 'active' : '' ?> py-3 px-1 text-sm font-medium text-gray-500 hover:text-gray-700">
                    Profit & Loss (P&L) Statement
                </button>
            </nav>

            <!-- Export to CSV Button Form (Constraint 3) -->
            <form method="POST" action="api/fika_finance_export.php" target="_blank">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="report_type" id="exportReportType" value="<?= e($activeTab) ?>">
                <input type="hidden" name="start_date" value="<?= e($startDate) ?>">
                <input type="hidden" name="end_date" value="<?= e($endDate) ?>">
                <?php if ($selectedBranchId !== null): ?>
                    <input type="hidden" name="branch_id" value="<?= (int)$selectedBranchId ?>">
                <?php endif; ?>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold shadow-sm flex items-center gap-1.5 transition-colors">
                    <span><i class="fa-solid fa-file-arrow-down mr-1.5"></i> Export to CSV</span>
                </button>
            </form>
        </div>

        <!-- ============================================================= -->
        <!-- TAB 1: DAILY SALES -->
        <!-- ============================================================= -->
        <section id="sectionSales" class="<?= $activeTab === 'sales' ? '' : 'hidden' ?> space-y-6">
            <!-- Summary Metric Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                    <span class="text-xs font-semibold uppercase text-gray-500 block mb-1">Gross Sales Revenue</span>
                    <div class="text-2xl font-bold text-gray-900"><?= Money::format($totalPaidSalesCents) ?></div>
                    <span class="text-[11px] text-gray-400 mt-1 block">PAID customer transactions</span>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                    <span class="text-xs font-semibold uppercase text-gray-500 block mb-1">Customer Refunds</span>
                    <div class="text-2xl font-bold text-purple-600">-<?= Money::format($refundCents) ?></div>
                    <span class="text-[11px] text-gray-400 mt-1 block"><?= (int)$refundRow['refund_count'] ?> orders refunded</span>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                    <span class="text-xs font-semibold uppercase text-gray-500 block mb-1">Voids</span>
                    <div class="text-2xl font-bold text-gray-500"><?= Money::format($voidCents) ?></div>
                    <span class="text-[11px] text-gray-400 mt-1 block"><?= (int)$refundRow['void_count'] ?> voided orders</span>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                    <span class="text-xs font-semibold uppercase text-gray-500 block mb-1">Net Sales Revenue</span>
                    <div class="text-2xl font-bold text-emerald-600"><?= Money::format($netSalesCents) ?></div>
                    <span class="text-[11px] text-gray-400 mt-1 block">Gross sales minus refunds</span>
                </div>
            </div>

            <!-- Breakdown Table by Payment Method -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-4 border-b border-gray-200 bg-gray-50">
                    <h2 class="text-sm font-bold text-gray-700">Sales Breakdown by Payment Method</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600 text-xs uppercase border-b border-gray-200">
                                <th class="p-3.5 font-semibold">Payment Method</th>
                                <th class="p-3.5 font-semibold text-center">Transactions</th>
                                <th class="p-3.5 font-semibold text-right">Revenue (PHP)</th>
                                <th class="p-3.5 font-semibold text-right">Revenue Share</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-xs">
                            <?php if (empty($methodSales)): ?>
                                <tr><td colspan="4" class="p-8 text-center text-gray-500">No sales transactions recorded for this period.</td></tr>
                            <?php else: ?>
                                <?php foreach ($methodSales as $ms): 
                                    $cents = (int)$ms['total_cents'];
                                    $pct = $totalPaidSalesCents > 0 ? ($cents / $totalPaidSalesCents) * 100 : 0;
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="p-3.5 font-bold uppercase text-gray-800">
                                            <span class="inline-block px-2.5 py-1 rounded font-bold text-[11px] <?= strtolower($ms['method']) === 'cash' ? 'bg-amber-100 text-amber-800' : (strtolower($ms['method']) === 'gcash' ? 'bg-blue-100 text-blue-800' : 'bg-emerald-100 text-emerald-800') ?>">
                                                <?= e($ms['method']) ?>
                                            </span>
                                        </td>
                                        <td class="p-3.5 text-center font-mono text-gray-700"><?= number_format((int)$ms['tx_count']) ?></td>
                                        <td class="p-3.5 text-right font-bold font-mono text-gray-900"><?= Money::format($cents) ?></td>
                                        <td class="p-3.5 text-right font-mono text-gray-600"><?= number_format($pct, 2) ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ============================================================= -->
        <!-- TAB 2: SHIFT SETTLEMENT -->
        <!-- ============================================================= -->
        <section id="sectionSettlement" class="<?= $activeTab === 'settlement' ? '' : 'hidden' ?> space-y-6">
            <!-- Settlement KPI Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                    <span class="text-xs font-semibold uppercase text-gray-500 block mb-1">Expected Register Cash</span>
                    <div class="text-2xl font-bold text-gray-900"><?= Money::format($totalExpectedCashCents) ?></div>
                    <span class="text-[11px] text-gray-400 mt-1 block">Opening Float + Cash Sales - Refunds</span>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                    <span class="text-xs font-semibold uppercase text-gray-500 block mb-1">Actual Counted Cash</span>
                    <div class="text-2xl font-bold text-indigo-600"><?= Money::format($totalCountedCashCents) ?></div>
                    <span class="text-[11px] text-gray-400 mt-1 block">Total cash deposited at register close</span>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                    <span class="text-xs font-semibold uppercase text-gray-500 block mb-1">Net Cash Variance</span>
                    <div class="text-2xl font-bold <?= $totalVarianceCents < 0 ? 'text-red-600' : ($totalVarianceCents > 0 ? 'text-emerald-600' : 'text-gray-700') ?>">
                        <?= ($totalVarianceCents > 0 ? '+' : '') . Money::format($totalVarianceCents) ?>
                    </div>
                    <span class="text-[11px] text-gray-400 mt-1 block"><?= $totalVarianceCents === 0 ? 'Exact zero-discrepancy' : ($totalVarianceCents < 0 ? 'Shortage alert' : 'Cash overage') ?></span>
                </div>
            </div>

            <!-- Shifts Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-4 border-b border-gray-200 bg-gray-50">
                    <h2 class="text-sm font-bold text-gray-700">Closed Register Shifts</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600 text-xs uppercase border-b border-gray-200">
                                <th class="p-3.5 font-semibold">Shift #</th>
                                <th class="p-3.5 font-semibold">Branch</th>
                                <th class="p-3.5 font-semibold">Cashier</th>
                                <th class="p-3.5 font-semibold">Opened</th>
                                <th class="p-3.5 font-semibold">Closed</th>
                                <th class="p-3.5 font-semibold text-right">Opening Float</th>
                                <th class="p-3.5 font-semibold text-right">Expected Cash</th>
                                <th class="p-3.5 font-semibold text-right">Counted Cash</th>
                                <th class="p-3.5 font-semibold text-right">Variance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-xs">
                            <?php if (empty($settlementSessions)): ?>
                                <tr><td colspan="9" class="p-8 text-center text-gray-500">No closed register sessions found for this period.</td></tr>
                            <?php else: ?>
                                <?php foreach ($settlementSessions as $cs): 
                                    $expFloat = (float)$cs['expected_cash'];
                                    $actFloat = (float)($cs['ending_cash'] ?? 0);
                                    $var = $actFloat - $expFloat;
                                    $varCents = (int)round($var * 100);
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="p-3.5 font-bold font-mono text-gray-900">#<?= (int)$cs['id'] ?></td>
                                        <td class="p-3.5 text-gray-600"><?= e($cs['branch_name']) ?></td>
                                        <td class="p-3.5 font-medium text-gray-800"><?= e($cs['cashier_username']) ?></td>
                                        <td class="p-3.5 text-gray-500"><?= e(date('M d, h:i A', strtotime($cs['opened_at']))) ?></td>
                                        <td class="p-3.5 text-gray-500"><?= e($cs['closed_at'] ? date('M d, h:i A', strtotime($cs['closed_at'])) : '—') ?></td>
                                        <td class="p-3.5 text-right font-mono text-gray-600"><?= Money::format((int)round((float)$cs['starting_cash'] * 100)) ?></td>
                                        <td class="p-3.5 text-right font-mono text-gray-600"><?= Money::format((int)round($expFloat * 100)) ?></td>
                                        <td class="p-3.5 text-right font-mono font-bold text-gray-900"><?= Money::format((int)round($actFloat * 100)) ?></td>
                                        <td class="p-3.5 text-right font-mono font-bold <?= $varCents < 0 ? 'text-red-600' : ($varCents > 0 ? 'text-emerald-600' : 'text-gray-700') ?>">
                                            <?= ($varCents > 0 ? '+' : '') . Money::format($varCents) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ============================================================= -->
        <!-- TAB 3: P&L STATEMENT (Constraint 1, Constraint 2 & Constraint 5) -->
        <!-- ============================================================= -->
        <section id="sectionPnl" class="<?= $activeTab === 'pnl' ? '' : 'hidden' ?> space-y-6">
            <!-- Executive Statement Sheet -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden max-w-4xl mx-auto">
                <div class="p-6 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                    <div>
                        <h2 class="text-base font-bold text-gray-900">Profit & Loss Statement (P&L)</h2>
                        <p class="text-xs text-gray-500">Live on-the-fly SQL computation for period <?= e($startDate) ?> to <?= e($endDate) ?></p>
                    </div>
                    <div class="text-right">
                        <span class="text-xs uppercase text-gray-400 font-semibold block">Net Margin</span>
                        <div class="text-lg font-bold <?= $pnlNetProfitCents >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">
                            <?= number_format($netMarginPct, 2) ?>%
                        </div>
                    </div>
                </div>

                <div class="p-6 space-y-6 text-sm">
                    <!-- 1. Revenue -->
                    <div>
                        <div class="flex justify-between items-center border-b border-gray-300 pb-2">
                            <span class="font-bold uppercase tracking-wider text-xs text-gray-600">1. Revenue</span>
                            <span class="font-bold text-gray-900"><?= Money::format($pnlNetRevCents) ?></span>
                        </div>
                        <div class="divide-y divide-gray-100 text-xs pl-4 pt-2 space-y-1.5">
                            <div class="flex justify-between text-gray-700 pt-1">
                                <span>Gross Sales (PAID Orders)</span>
                                <span class="font-mono"><?= Money::format($pnlGrossRevCents) ?></span>
                            </div>
                            <div class="flex justify-between text-gray-500 pt-1">
                                <span>Less: Customer Refunds & Voids</span>
                                <span class="font-mono text-purple-600">-<?= Money::format($pnlRefundCents) ?></span>
                            </div>
                            <div class="flex justify-between font-bold text-gray-900 pt-1.5 border-t border-gray-200">
                                <span>Net Sales Revenue</span>
                                <span class="font-mono"><?= Money::format($pnlNetRevCents) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Cost of Goods Sold (Constraint 1) -->
                    <div>
                        <div class="flex justify-between items-center border-b border-gray-300 pb-2">
                            <span class="font-bold uppercase tracking-wider text-xs text-gray-600">2. Cost of Goods Sold (COGS)</span>
                            <span class="font-bold text-red-600">-<?= Money::format($pnlCogsCents) ?></span>
                        </div>
                        <div class="divide-y divide-gray-100 text-xs pl-4 pt-2 space-y-1.5">
                            <div class="flex justify-between text-gray-700 pt-1">
                                <span>Ingredient Deductions (inventory_ledger SALE entries)</span>
                                <span class="font-mono text-red-600">-<?= Money::format($pnlCogsCents) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Gross Profit Summary -->
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 flex justify-between items-center">
                        <div>
                            <span class="font-bold text-gray-900 uppercase tracking-wide text-xs">Gross Profit</span>
                            <span class="text-xs text-gray-500 block">Gross Margin: <?= number_format($grossMarginPct, 2) ?>%</span>
                        </div>
                        <div class="text-lg font-bold font-mono text-gray-900"><?= Money::format($pnlGrossProfitCents) ?></div>
                    </div>

                    <!-- 3. Operating Expenses -->
                    <div>
                        <div class="flex justify-between items-center border-b border-gray-300 pb-2">
                            <span class="font-bold uppercase tracking-wider text-xs text-gray-600">3. Operating Expenses (APPROVED)</span>
                            <span class="font-bold text-red-600">-<?= Money::format($pnlTotalOpexCents) ?></span>
                        </div>
                        <div class="divide-y divide-gray-100 text-xs pl-4 pt-2 space-y-1.5">
                            <?php if (empty($pnlOpexCategories)): ?>
                                <div class="text-gray-400 italic pt-1">No approved operating expenses for this period.</div>
                            <?php else: ?>
                                <?php foreach ($pnlOpexCategories as $poc): ?>
                                    <div class="flex justify-between text-gray-700 pt-1">
                                        <span><?= e($poc['category_name']) ?></span>
                                        <span class="font-mono text-red-600">-<?= Money::format((int)$poc['cat_cents']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <div class="flex justify-between font-bold text-gray-900 pt-1.5 border-t border-gray-200">
                                <span>Total Operating Expenses</span>
                                <span class="font-mono text-red-600">-<?= Money::format($pnlTotalOpexCents) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Payroll Expenses (Constraint 2) -->
                    <div>
                        <div class="flex justify-between items-center border-b border-gray-300 pb-2">
                            <span class="font-bold uppercase tracking-wider text-xs text-gray-600">4. Payroll Expenses (RELEASED Runs)</span>
                            <span class="font-bold text-red-600">-<?= Money::format($pnlPayrollCents) ?></span>
                        </div>
                        <div class="divide-y divide-gray-100 text-xs pl-4 pt-2 space-y-1.5">
                            <div class="flex justify-between text-gray-700 pt-1">
                                <span>Gross Wages & Statutory Employer Remittances</span>
                                <span class="font-mono text-red-600">-<?= Money::format($pnlPayrollCents) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- 5. Net Profit (Loss) -->
                    <div class="p-5 rounded-xl border <?= $pnlNetProfitCents >= 0 ? 'bg-emerald-50 border-emerald-300' : 'bg-red-50 border-red-300' ?> flex justify-between items-center">
                        <div>
                            <span class="font-extrabold uppercase tracking-wider text-sm <?= $pnlNetProfitCents >= 0 ? 'text-emerald-900' : 'text-red-900' ?>">
                                Net Profit / (Loss)
                            </span>
                            <span class="text-xs block <?= $pnlNetProfitCents >= 0 ? 'text-emerald-700' : 'text-red-700' ?>">
                                Net Margin: <?= number_format($netMarginPct, 2) ?>%
                            </span>
                        </div>
                        <div class="text-2xl font-black font-mono <?= $pnlNetProfitCents >= 0 ? 'text-emerald-700' : 'text-red-700' ?>">
                            <?= ($pnlNetProfitCents >= 0 ? '' : '-') . Money::format(abs($pnlNetProfitCents)) ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script>
        function switchReportTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('sectionSales').classList.add('hidden');
            document.getElementById('sectionSettlement').classList.add('hidden');
            document.getElementById('sectionPnl').classList.add('hidden');

            document.getElementById('activeTabInput').value = tab;
            document.getElementById('exportReportType').value = tab;

            if (tab === 'sales') {
                document.getElementById('tabBtnSales').classList.add('active');
                document.getElementById('sectionSales').classList.remove('hidden');
            } else if (tab === 'settlement') {
                document.getElementById('tabBtnSettlement').classList.add('active');
                document.getElementById('sectionSettlement').classList.remove('hidden');
            } else if (tab === 'pnl') {
                document.getElementById('tabBtnPnl').classList.add('active');
                document.getElementById('sectionPnl').classList.remove('hidden');
            }
        }
    </script>
    </div>
    </div>
</body>
</html>
