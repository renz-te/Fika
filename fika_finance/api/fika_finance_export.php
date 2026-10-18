<?php
/**
 * Fika Rebuild Core - Financial Reports CSV Export Endpoint
 * Exports Daily Sales, Shift Settlement, and P&L Statements to CSV.
 * Strictly enforces authentication, finance.view permission, branch scoping,
 * and formula injection sanitization (=, +, -, @, \t, \r).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

/**
 * Neutralize CSV Formula Injection (Constraint 4)
 * Handles =, +, -, @, \t, \r at the start of any cell.
 */
if (!function_exists('sanitizeCsvValue')) {
    function sanitizeCsvValue(mixed $value): string {
        $str = (string)($value ?? '');
        $trimmed = ltrim($str, ' ');
        if (strlen($trimmed) > 0) {
            $firstChar = $trimmed[0];
            if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true)) {
                return "'" . $str;
            }
        }
        return $str;
    }
}

if (!function_exists('writeCsvRow')) {
    function writeCsvRow($handle, array $row): void {
        $sanitized = array_map('sanitizeCsvValue', $row);
        fputcsv($handle, $sanitized);
    }
}

if (defined('FIKA_CSV_EXPORT_TESTING')) {
    return;
}

Auth::requireLogin();
Csrf::requireValid();
Rbac::require_permission('finance.view');

$user = Auth::user();
$userId = (int)$user['id'];
$userBranchId = isset($user['branch_id']) ? (int)$user['branch_id'] : null;
$isAdminOrGa = in_array($user['role_name'] ?? '', ['ADMIN', 'Super Admin', 'GA', 'Global Accountant'], true) 
               || in_array((int)($user['role_id'] ?? 0), [1, 3], true);

// Parse input
$reportType = trim($_POST['report_type'] ?? '');
$startDate = trim($_POST['start_date'] ?? date('Y-m-01'));
$endDate = trim($_POST['end_date'] ?? date('Y-m-d'));
$requestedBranchId = (isset($_POST['branch_id']) && $_POST['branch_id'] !== '') ? (int)$_POST['branch_id'] : null;

// Enforce branch scoping (Constraint 3)
if (!$isAdminOrGa) {
    if ($requestedBranchId !== null && $requestedBranchId !== $userBranchId) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Forbidden: Cannot export data for another branch']);
        exit;
    }
    $branchId = $userBranchId;
} else {
    $branchId = $requestedBranchId;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $startDate = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $endDate = date('Y-m-d');
}

$startDateTime = $startDate . ' 00:00:00';
$endDateTime = $endDate . ' 23:59:59';

global $pdo;

// Prepare HTTP headers for CSV download
header('Content-Type: text/csv; charset=UTF-8');
$filename = sprintf('fika_%s_%s_to_%s.csv', $reportType, $startDate, $endDate);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');
// Output UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

switch ($reportType) {
    // =================================================================
    // DAILY SALES REPORT
    // =================================================================
    case 'sales': {
        writeCsvRow($output, ['FIKA DAILY SALES REPORT']);
        writeCsvRow($output, ['Period:', $startDate . ' to ' . $endDate]);
        writeCsvRow($output, ['Branch Scope:', $branchId ? 'Branch #' . $branchId : 'All Branches']);
        writeCsvRow($output, ['Exported At:', date('Y-m-d H:i:s')]);
        writeCsvRow($output, []);

        // 1. Payment Method Breakdown
        writeCsvRow($output, ['--- SALES BY PAYMENT METHOD ---']);
        writeCsvRow($output, ['Payment Method', 'Transactions', 'Total Amount (PHP)', 'Total Centavos']);

        $paySql = "
            SELECT 
                COALESCE(NULLIF(payment_method, ''), 'CASH') AS method,
                COUNT(*) AS tx_count,
                COALESCE(SUM(ROUND(final_amount * 100)), 0) AS total_cents
            FROM orders
            WHERE payment_status = 'PAID'
              AND is_test = 0
              AND created_at BETWEEN ? AND ?
        ";
        $payParams = [$startDateTime, $endDateTime];
        if ($branchId !== null) {
            $paySql .= " AND branch_id = ?";
            $payParams[] = $branchId;
        }
        $paySql .= " GROUP BY method ORDER BY total_cents DESC";

        $payStmt = $pdo->prepare($paySql);
        $payStmt->execute($payParams);
        $methods = $payStmt->fetchAll();

        $totalPaidCents = 0;
        foreach ($methods as $m) {
            $cents = (int)$m['total_cents'];
            $totalPaidCents += $cents;
            writeCsvRow($output, [
                strtoupper($m['method']),
                $m['tx_count'],
                number_format($cents / 100, 2),
                $cents
            ]);
        }
        writeCsvRow($output, ['TOTAL PAID SALES', '', number_format($totalPaidCents / 100, 2), $totalPaidCents]);
        writeCsvRow($output, []);

        // 2. Refunds & Voids
        $refSql = "
            SELECT 
                COALESCE(SUM(CASE WHEN payment_status = 'REFUNDED' THEN ROUND(final_amount * 100) ELSE 0 END), 0) AS refund_cents,
                COUNT(CASE WHEN payment_status = 'REFUNDED' THEN 1 END) AS refund_count,
                COALESCE(SUM(CASE WHEN order_status = 'VOID' THEN ROUND(final_amount * 100) ELSE 0 END), 0) AS void_cents,
                COUNT(CASE WHEN order_status = 'VOID' THEN 1 END) AS void_count
            FROM orders
            WHERE is_test = 0 AND created_at BETWEEN ? AND ?
        ";
        $refParams = [$startDateTime, $endDateTime];
        if ($branchId !== null) {
            $refSql .= " AND branch_id = ?";
            $refParams[] = $branchId;
        }
        $refStmt = $pdo->prepare($refSql);
        $refStmt->execute($refParams);
        $refSummary = $refStmt->fetch();

        $refundCents = (int)($refSummary['refund_cents'] ?? 0);
        $voidCents = (int)($refSummary['void_cents'] ?? 0);
        $netRevenueCents = $totalPaidCents - $refundCents;

        writeCsvRow($output, ['--- REVENUE RECONCILIATION ---']);
        writeCsvRow($output, ['Gross Revenue', number_format($totalPaidCents / 100, 2), $totalPaidCents]);
        writeCsvRow($output, ['Less Refunds (' . (int)$refSummary['refund_count'] . ' items)', '-' . number_format($refundCents / 100, 2), -$refundCents]);
        writeCsvRow($output, ['Net Revenue', number_format($netRevenueCents / 100, 2), $netRevenueCents]);
        writeCsvRow($output, ['Voids (Non-Revenue, ' . (int)$refSummary['void_count'] . ' items)', number_format($voidCents / 100, 2), $voidCents]);
        break;
    }

    // =================================================================
    // SHIFT SETTLEMENT REPORT
    // =================================================================
    case 'settlement': {
        writeCsvRow($output, ['FIKA REGISTER SHIFT SETTLEMENT REPORT']);
        writeCsvRow($output, ['Period:', $startDate . ' to ' . $endDate]);
        writeCsvRow($output, ['Branch Scope:', $branchId ? 'Branch #' . $branchId : 'All Branches']);
        writeCsvRow($output, ['Exported At:', date('Y-m-d H:i:s')]);
        writeCsvRow($output, []);

        writeCsvRow($output, [
            'Shift #', 'Branch', 'Cashier', 'Opened At', 'Closed At',
            'Opening Float (PHP)', 'Expected Cash (PHP)', 'Closing Cash (PHP)',
            'Variance (PHP)', 'Variance Cents', 'Status'
        ]);

        $csSql = "
            SELECT cs.*, b.name AS branch_name, u.username AS cashier_username
            FROM cash_sessions cs
            JOIN branches b ON cs.branch_id = b.id
            JOIN users u ON cs.cashier_id = u.id
            WHERE cs.status = 'CLOSED'
              AND cs.closed_at BETWEEN ? AND ?
        ";
        $csParams = [$startDateTime, $endDateTime];
        if ($branchId !== null) {
            $csSql .= " AND cs.branch_id = ?";
            $csParams[] = $branchId;
        }
        $csSql .= " ORDER BY cs.closed_at DESC";

        $csStmt = $pdo->prepare($csSql);
        $csStmt->execute($csParams);
        $sessions = $csStmt->fetchAll();

        foreach ($sessions as $cs) {
            $startFloat = (float)$cs['starting_cash'];
            $expCash = (float)$cs['expected_cash'];
            $closeCash = (float)($cs['ending_cash'] ?? 0);
            $var = $closeCash - $expCash;
            $varCents = (int)round($var * 100);

            writeCsvRow($output, [
                '#' . $cs['id'],
                $cs['branch_name'],
                $cs['cashier_username'],
                $cs['opened_at'],
                $cs['closed_at'],
                number_format($startFloat, 2),
                number_format($expCash, 2),
                number_format($closeCash, 2),
                ($var > 0 ? '+' : '') . number_format($var, 2),
                $varCents,
                $cs['status']
            ]);
        }
        break;
    }

    // =================================================================
    // PROFIT & LOSS (P&L) STATEMENT (Constraint 1 & 2)
    // =================================================================
    case 'pnl': {
        writeCsvRow($output, ['FIKA PROFIT & LOSS STATEMENT (EXECUTIVE P&L)']);
        writeCsvRow($output, ['Period:', $startDate . ' to ' . $endDate]);
        writeCsvRow($output, ['Branch Scope:', $branchId ? 'Branch #' . $branchId : 'All Branches']);
        writeCsvRow($output, ['Exported At:', date('Y-m-d H:i:s')]);
        writeCsvRow($output, []);

        // 1. Gross Revenue (PAID orders)
        $revSql = "
            SELECT 
                COALESCE(SUM(CASE WHEN payment_status = 'PAID' THEN ROUND(final_amount * 100) ELSE 0 END), 0) AS gross_cents,
                COALESCE(SUM(CASE WHEN payment_status = 'REFUNDED' THEN ROUND(final_amount * 100) ELSE 0 END), 0) AS refund_cents
            FROM orders
            WHERE is_test = 0
              AND created_at BETWEEN ? AND ?
        ";
        $revParams = [$startDateTime, $endDateTime];
        if ($branchId !== null) {
            $revSql .= " AND branch_id = ?";
            $revParams[] = $branchId;
        }
        $revStmt = $pdo->prepare($revSql);
        $revStmt->execute($revParams);
        $revRow = $revStmt->fetch();

        $grossRevCents = (int)$revRow['gross_cents'];
        $refundCents = (int)$revRow['refund_cents'];
        $netRevCents = $grossRevCents - $refundCents;

        // 2. Strict COGS Calculation (Constraint 1)
        // Formula: SUM(ABS(quantity) * unit_cost_snapshot) WHERE type = 'SALE' and order_id belongs to a PAID order in range
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
        if ($branchId !== null) {
            $cogsSql .= " AND o.branch_id = ?";
            $cogsParams[] = $branchId;
        }
        $cogsStmt = $pdo->prepare($cogsSql);
        $cogsStmt->execute($cogsParams);
        $cogsCents = (int)$cogsStmt->fetchColumn();

        $grossProfitCents = $netRevCents - $cogsCents;

        // 3. Operating Expenses (APPROVED only)
        $expSql = "
            SELECT 
                ec.name AS category_name,
                COALESCE(SUM(e.amount_cents), 0) AS cat_cents
            FROM expenses e
            JOIN expense_categories ec ON e.category_id = ec.id
            WHERE e.status = 'APPROVED'
              AND e.expense_date BETWEEN ? AND ?
        ";
        $expParams = [$startDate, $endDate];
        if ($branchId !== null) {
            $expSql .= " AND e.branch_id = ?";
            $expParams[] = $branchId;
        }
        $expSql .= " GROUP BY ec.id ORDER BY cat_cents DESC";

        $expStmt = $pdo->prepare($expSql);
        $expStmt->execute($expParams);
        $opexRows = $expStmt->fetchAll();

        $totalOpexCents = 0;
        foreach ($opexRows as $orow) {
            $totalOpexCents += (int)$orow['cat_cents'];
        }

        // 4. Strict Payroll Expense Calculation (Constraint 2)
        // Formula: gross_pay + employer_sss + employer_philhealth + employer_pagibig from payroll_items for RELEASED payroll runs
        $paySql = "
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
        $payParams = [$startDate, $endDate];
        if ($branchId !== null) {
            $paySql .= " AND pr.branch_id = ?";
            $payParams[] = $branchId;
        }
        $payStmt = $pdo->prepare($paySql);
        $payStmt->execute($payParams);
        $payrollCents = (int)$payStmt->fetchColumn();

        // 5. Net Profit (Loss)
        $netProfitCents = $grossProfitCents - $totalOpexCents - $payrollCents;

        writeCsvRow($output, ['Account / Financial Line Item', 'Amount (PHP)', 'Amount (Centavos)', '% of Net Revenue']);
        writeCsvRow($output, ['1. REVENUE']);
        writeCsvRow($output, ['Gross Sales Revenue (PAID)', number_format($grossRevCents / 100, 2), $grossRevCents, '']);
        writeCsvRow($output, ['Less: Refunds & Returns', '-' . number_format($refundCents / 100, 2), -$refundCents, '']);
        writeCsvRow($output, ['NET REVENUE', number_format($netRevCents / 100, 2), $netRevCents, '100.00%']);
        writeCsvRow($output, []);

        writeCsvRow($output, ['2. COST OF GOODS SOLD (COGS)']);
        writeCsvRow($output, ['Inventory Ingredients Consumed (SALE)', '-' . number_format($cogsCents / 100, 2), -$cogsCents, $netRevCents > 0 ? number_format(($cogsCents / $netRevCents) * 100, 2) . '%' : '0.00%']);
        writeCsvRow($output, ['GROSS PROFIT', number_format($grossProfitCents / 100, 2), $grossProfitCents, $netRevCents > 0 ? number_format(($grossProfitCents / $netRevCents) * 100, 2) . '%' : '0.00%']);
        writeCsvRow($output, []);

        writeCsvRow($output, ['3. OPERATING EXPENSES (APPROVED)']);
        foreach ($opexRows as $orow) {
            $c = (int)$orow['cat_cents'];
            writeCsvRow($output, [$orow['category_name'], number_format($c / 100, 2), $c, $netRevCents > 0 ? number_format(($c / $netRevCents) * 100, 2) . '%' : '0.00%']);
        }
        writeCsvRow($output, ['TOTAL OPERATING EXPENSES', '-' . number_format($totalOpexCents / 100, 2), -$totalOpexCents, $netRevCents > 0 ? number_format(($totalOpexCents / $netRevCents) * 100, 2) . '%' : '0.00%']);
        writeCsvRow($output, []);

        writeCsvRow($output, ['4. PAYROLL EXPENSES (RELEASED)']);
        writeCsvRow($output, ['Wages & Statutory Contributions', '-' . number_format($payrollCents / 100, 2), -$payrollCents, $netRevCents > 0 ? number_format(($payrollCents / $netRevCents) * 100, 2) . '%' : '0.00%']);
        writeCsvRow($output, []);

        writeCsvRow($output, ['5. BOTTOM LINE']);
        writeCsvRow($output, ['NET PROFIT / (LOSS)', ($netProfitCents >= 0 ? '' : '-') . number_format(abs($netProfitCents) / 100, 2), $netProfitCents, $netRevCents > 0 ? number_format(($netProfitCents / $netRevCents) * 100, 2) . '%' : '0.00%']);
        break;
    }

    default:
        writeCsvRow($output, ['Error: Invalid report type specified.']);
        break;
}

fclose($output);
exit;
