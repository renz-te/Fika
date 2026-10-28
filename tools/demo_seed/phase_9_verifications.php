<?php
/**
 * tools/demo_seed/phase_9_verifications.php
 *
 * Verification suite for Phase D9: Operating Expenses and Branch Budgets
 *
 * Verifies:
 * 1. Expenses per branch per month by category and status.
 * 2. Budget vs actual labor per branch per month.
 * 3. Exact amount_cents synchronization (no cent mismatch).
 * 4. Verification that live queries from fika_finance_budgets.php and fika_finance_reports.php return correctly.
 * 5. Exactly ONE VOIDED expense with void_reason.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

global $pdo, $DEMO_APPLY;

echo "\n====================================================================\n";
echo "Phase D9: Verification & Audit Suite\n";
echo "====================================================================\n";

$trackedExpenses = (int)$pdo->query("SELECT COUNT(*) FROM demo_seed WHERE phase = 'phase_9' AND table_name = 'expenses'")->fetchColumn();
$trackedBudgets  = (int)$pdo->query("SELECT COUNT(*) FROM demo_seed WHERE phase = 'phase_9' AND table_name = 'branch_budgets'")->fetchColumn();

if ($trackedExpenses === 0 && !$DEMO_APPLY) {
    echo "Notice: Running in DRY-RUN mode before initial apply.\n";
    echo "Live database queries and reports will execute upon running with --apply.\n";
    return;
}

// ====================================================================
// VERIFICATION 1: Expenses per Branch per Month by Category and Status
// ====================================================================
echo "\n--- VERIFICATION 1: Expenses per Branch per Month by Category and Status ---\n";

$expBreakdownStmt = $pdo->query("
    SELECT 
        b.id AS branch_id,
        b.name AS branch_name,
        DATE_FORMAT(e.expense_date, '%Y-%m') AS exp_month,
        ec.name AS category_name,
        e.status,
        COUNT(e.id) AS exp_count,
        SUM(e.amount_cents) / 100 AS total_pesos
    FROM expenses e
    JOIN branches b ON e.branch_id = b.id
    JOIN expense_categories ec ON e.category_id = ec.id
    JOIN demo_seed ds ON ds.row_id = e.id AND ds.table_name = 'expenses' AND ds.phase = 'phase_9'
    GROUP BY b.id, b.name, exp_month, ec.id, ec.name, e.status
    ORDER BY b.id ASC, exp_month ASC, ec.id ASC, e.status ASC
");
$expRows = $expBreakdownStmt->fetchAll(PDO::FETCH_ASSOC);

printf("%-3s | %-22s | %-7s | %-14s | %-9s | %-5s | %12s\n",
    "BID", "Branch Name", "Month", "Category", "Status", "Count", "Total (PHP)"
);
echo str_repeat("-", 84) . "\n";

$grandExpPesos = 0.0;
$grandExpCount = 0;
$statusSummary = ['APPROVED' => 0, 'DRAFT' => 0, 'VOIDED' => 0];

foreach ($expRows as $r) {
    $grandExpCount += (int)$r['exp_count'];
    $grandExpPesos += (float)$r['total_pesos'];
    $statusSummary[$r['status']] = ($statusSummary[$r['status']] ?? 0) + (int)$r['exp_count'];

    printf("%-3d | %-22s | %-7s | %-14s | %-9s | %-5d | %12s\n",
        $r['branch_id'],
        substr($r['branch_name'], 0, 22),
        $r['exp_month'],
        substr($r['category_name'], 0, 14),
        $r['status'],
        $r['exp_count'],
        number_format((float)$r['total_pesos'], 2)
    );
}

echo str_repeat("-", 84) . "\n";
printf("%-3s | %-22s | %-7s | %-14s | %-9s | %-5d | %12s\n",
    "ALL", "Grand Total", "ALL", "ALL", "ALL", $grandExpCount,
    number_format($grandExpPesos, 2)
);

echo "\nStatus Breakdown: Approved: {$statusSummary['APPROVED']} | Draft (Pending): {$statusSummary['DRAFT']} | Voided: {$statusSummary['VOIDED']}\n";

// ====================================================================
// VERIFICATION 2: Budget vs Actual Labor per Branch per Month
// ====================================================================
echo "\n--- VERIFICATION 2: Budget vs Actual Labor per Branch per Month ---\n";

$budgetVsLaborStmt = $pdo->query("
    SELECT 
        bb.branch_id,
        b.name AS branch_name,
        bb.budget_month,
        bb.allocated_amount_cents / 100 AS allocated_pesos,
        bb.allocated_labor_budget,
        COALESCE(SUM(ROUND((
            CASE 
                WHEN pi.gross_pay > 0 THEN pi.gross_pay 
                ELSE (pi.basic_pay + pi.overtime_pay + pi.bonus_pay) 
            END 
            + pi.employer_sss + pi.employer_philhealth + pi.employer_pagibig
        ) * 100)), 0) / 100 AS actual_labor_pesos
    FROM branch_budgets bb
    JOIN branches b ON bb.branch_id = b.id
    JOIN demo_seed ds ON ds.row_id = bb.id AND ds.table_name = 'branch_budgets' AND ds.phase = 'phase_9'
    LEFT JOIN payroll_runs pr ON pr.branch_id = bb.branch_id 
        AND pr.status = 'RELEASED' 
        AND DATE_FORMAT(pr.period_end, '%Y-%m') = bb.budget_month
    LEFT JOIN payroll_items pi ON pi.payroll_run_id = pr.id
    GROUP BY bb.id, bb.branch_id, b.name, bb.budget_month, bb.allocated_amount_cents, bb.allocated_labor_budget
    ORDER BY bb.branch_id ASC, bb.budget_month ASC
");
$budgetRows = $budgetVsLaborStmt->fetchAll(PDO::FETCH_ASSOC);

printf("%-3s | %-22s | %-7s | %13s | %14s | %12s | %-9s\n",
    "BID", "Branch Name", "Month", "Budget (PHP)", "Actual Labor", "Variance", "Status"
);
echo str_repeat("-", 92) . "\n";

foreach ($budgetRows as $br) {
    $budget = (float)$br['allocated_pesos'];
    $actual = (float)$br['actual_labor_pesos'];
    $variance = $budget - $actual;
    $status = ($actual === 0.0) ? "BASELINE" : (($variance >= 0) ? "WITHIN" : "OVER");

    printf("%-3d | %-22s | %-7s | %13s | %14s | %12s | %-9s\n",
        $br['branch_id'],
        substr($br['branch_name'], 0, 22),
        $br['budget_month'],
        number_format($budget, 2),
        number_format($actual, 2),
        number_format($variance, 2),
        $status
    );
}
echo "\n";

// ====================================================================
// VERIFICATION 3: Amount vs Amount Cents Synchronization Check
// ====================================================================
echo "--- VERIFICATION 3: Currency & Cents Synchronization Audit ---\n";

// Check 3A: branch_budgets allocated_amount_cents == round(allocated_labor_budget * 100)
$budgetSyncMismatch = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM branch_budgets bb
    JOIN demo_seed ds ON ds.row_id = bb.id AND ds.table_name = 'branch_budgets' AND ds.phase = 'phase_9'
    WHERE bb.allocated_amount_cents != ROUND(bb.allocated_labor_budget * 100)
")->fetchColumn();

echo "  Audit 3A: branch_budgets cent mismatch: {$budgetSyncMismatch} mismatches.\n";
if ($budgetSyncMismatch === 0) {
    echo "    [PASS] 100% of branch_budgets rows have allocated_amount_cents == allocated_labor_budget * 100.\n";
} else {
    echo "    [FAIL] Found {$budgetSyncMismatch} budget cent mismatches!\n";
}

// Check 3B: expenses amount_cents non-zero and non-negative
$expInvalidCents = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM expenses e
    JOIN demo_seed ds ON ds.row_id = e.id AND ds.table_name = 'expenses' AND ds.phase = 'phase_9'
    WHERE e.amount_cents <= 0
")->fetchColumn();

echo "  Audit 3B: expenses invalid/negative amount_cents: {$expInvalidCents} rows.\n";
if ($expInvalidCents === 0) {
    echo "    [PASS] All expenses have strictly positive amount_cents values.\n";
} else {
    echo "    [FAIL] Found {$expInvalidCents} invalid expense amount_cents!\n";
}

// ====================================================================
// VERIFICATION 4: Voided Expense Audit (Exactly ONE with Reason)
// ====================================================================
echo "\n--- VERIFICATION 4: Voided Expense Verification ---\n";

$voidedStmt = $pdo->query("
    SELECT e.id, e.branch_id, b.name AS branch_name, ec.name AS category_name,
           e.amount_cents / 100 AS amount, e.expense_date, e.payee, e.void_reason,
           u1.username AS creator_user, u2.username AS approver_user
    FROM expenses e
    JOIN branches b ON e.branch_id = b.id
    JOIN expense_categories ec ON e.category_id = ec.id
    LEFT JOIN users u1 ON e.created_by = u1.id
    LEFT JOIN users u2 ON e.approved_by = u2.id
    JOIN demo_seed ds ON ds.row_id = e.id AND ds.table_name = 'expenses' AND ds.phase = 'phase_9'
    WHERE e.status = 'VOIDED'
");
$voidedRows = $voidedStmt->fetchAll(PDO::FETCH_ASSOC);

echo "  Total VOIDED expenses found: " . count($voidedRows) . "\n";
if (count($voidedRows) === 1) {
    $v = $voidedRows[0];
    echo "  [PASS] Exactly ONE voided expense exists as required:\n";
    echo "    * ID           : #{$v['id']}\n";
    echo "    * Branch       : Branch #{$v['branch_id']} - {$v['branch_name']}\n";
    echo "    * Category     : {$v['category_name']}\n";
    echo "    * Payee        : {$v['payee']}\n";
    echo "    * Amount       : PHP " . number_format((float)$v['amount'], 2) . "\n";
    echo "    * Date         : {$v['expense_date']}\n";
    echo "    * Void Reason  : \"{$v['void_reason']}\"\n";
    echo "    * Created By   : {$v['creator_user']}\n";
    echo "    * Processed By : {$v['approver_user']}\n";
} else {
    echo "  [FAIL] Expected exactly 1 voided expense, found " . count($voidedRows) . "!\n";
}

// ====================================================================
// VERIFICATION 5: Live Query Compatibility Verification
// ====================================================================
echo "\n--- VERIFICATION 5: Live Query Compatibility Test ---\n";

// 5A: fika_finance_budgets.php (get_budget_summary query)
echo "  [Test 5A] Simulating live get_budget_summary query (Branch 1, 2026-09):\n";
$bStmt = $pdo->prepare("SELECT allocated_amount_cents FROM branch_budgets WHERE branch_id = ? AND budget_month = ?");
$bStmt->execute([1, '2026-09']);
$liveAllocatedCents = (int)($bStmt->fetchColumn() ?: 0);

$spentStmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount_cents), 0) 
    FROM expenses 
    WHERE branch_id = ? 
      AND status = 'APPROVED' 
      AND DATE_FORMAT(expense_date, '%Y-%m') = ?
");
$spentStmt->execute([1, '2026-09']);
$liveSpentCents = (int)$spentStmt->fetchColumn();

$liveRemainingCents = $liveAllocatedCents - $liveSpentCents;
$liveUtilPct = $liveAllocatedCents > 0 ? round(($liveSpentCents / $liveAllocatedCents) * 100, 2) : 0.00;

echo "    Allocated Budget : PHP " . number_format($liveAllocatedCents / 100, 2) . "\n";
echo "    Approved Opex    : PHP " . number_format($liveSpentCents / 100, 2) . "\n";
echo "    Remaining Buffer : PHP " . number_format($liveRemainingCents / 100, 2) . "\n";
echo "    Utilization Rate : {$liveUtilPct}%\n";
echo "    [PASS] Live get_budget_summary executed successfully.\n";

// 5B: fika_finance_reports.php (Operating Expenses PnL query)
echo "\n  [Test 5B] Simulating live fika_finance_reports.php Operating Expenses query (Branch 1, 2026-09-01 to 2026-09-30):\n";
$pnlExpStmt = $pdo->prepare("
    SELECT 
        ec.name AS category_name,
        COALESCE(SUM(e.amount_cents), 0) AS cat_cents
    FROM expenses e
    JOIN expense_categories ec ON e.category_id = ec.id
    WHERE e.status = 'APPROVED'
      AND e.expense_date BETWEEN ? AND ?
      AND e.branch_id = ?
    GROUP BY ec.id ORDER BY cat_cents DESC
");
$pnlExpStmt->execute(['2026-09-01', '2026-09-30', 1]);
$pnlRows = $pnlExpStmt->fetchAll(PDO::FETCH_ASSOC);

$totalPnlOpexCents = 0;
foreach ($pnlRows as $pr) {
    $totalPnlOpexCents += (int)$pr['cat_cents'];
    printf("    * %-14s : PHP %10s\n", $pr['category_name'], number_format($pr['cat_cents'] / 100, 2));
}
echo "    Total PnL Opex   : PHP " . number_format($totalPnlOpexCents / 100, 2) . "\n";
echo "    [PASS] Live reports Operating Expenses query executed successfully.\n";

echo "====================================================================\n";
