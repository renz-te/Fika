<?php
/**
 * tools/demo_seed/cleanup_test_branches.php
 *
 * Safely removes test branches left behind during test execution:
 *   - Finance Test Branch A (ID: 941)
 *   - Finance Test Branch B (ID: 942)
 *   - Fika Branch 1 (ID: 961)
 *   - Fika Branch 2 (ID: 962)
 *
 * Removes associated test users, test orders, audit logs, and test products.
 * Strictly preserves the 3 demo branches:
 *   - General Trias Branch (ID: 1)
 *   - Trece Martires Branch (ID: 2)
 *   - Imus Branch (ID: 3)
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once __DIR__ . '/../../app/bootstrap.php';

global $pdo;

$host = config('db.host', '127.0.0.1');
$dbName = config('db.name', 'fika_scratch');

echo "====================================================================\n";
echo "Fika Café — Cleanup Test Branch Artifacts\n";
echo "====================================================================\n";
echo "Database Host: {$host}\n";
echo "Database Name: {$dbName}\n";

// Rule 1: Assert Localhost / 127.0.0.1
if ($host !== '127.0.0.1' && strtolower($host) !== 'localhost') {
    fwrite(STDERR, "CRITICAL ERROR: cleanup_test_branches.php can only run on localhost/127.0.0.1. Current host: '{$host}'. Halting.\n");
    exit(1);
}

// 1. Identify target test branches
$targetNames = [
    'Finance Test Branch A',
    'Finance Test Branch B',
    'Fika Branch 1',
    'Fika Branch 2'
];

$placeholders = implode(',', array_fill(0, count($targetNames), '?'));
$findStmt = $pdo->prepare("SELECT id, name FROM branches WHERE name IN ($placeholders)");
$findStmt->execute($targetNames);
$testBranches = $findStmt->fetchAll(PDO::FETCH_ASSOC);

echo "\nTarget Test Branches Identified (" . count($testBranches) . "):\n";
foreach ($testBranches as $tb) {
    echo "  - Branch #{$tb['id']}: '{$tb['name']}'\n";
}

if (empty($testBranches)) {
    echo "\nNo test branches found to clean up. Only real branches exist.\n";
    print_real_branches($pdo);
    exit(0);
}

$testBranchIds = array_column($testBranches, 'id');
$inBranchIds = implode(',', $testBranchIds);

// Test user accounts associated with test suites
$testUserIds = [940, 941, 942, 943, 944, 951, 952];
$inUserIds = implode(',', $testUserIds);

// Test products created during test runs (e.g. receipt tests)
$testProductIds = [9601, 9602];
$inProductIds = implode(',', $testProductIds);

echo "\nExecuting cleanup using SET FOREIGN_KEY_CHECKS=0 transaction...\n";

$deletedCounts = [];

$pdo->beginTransaction();
try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0;");

    // 1. Order-related child tables
    $delTokens = $pdo->exec("DELETE FROM order_kiosk_tokens WHERE order_id IN (SELECT id FROM orders WHERE branch_id IN ($inBranchIds))");
    $deletedCounts['order_kiosk_tokens'] = $delTokens ?: 0;

    $delAudit = $pdo->exec("DELETE FROM order_audit WHERE order_id IN (SELECT id FROM orders WHERE branch_id IN ($inBranchIds))");
    $deletedCounts['order_audit'] = $delAudit ?: 0;

    $delDiscounts = $pdo->exec("DELETE FROM order_discounts WHERE order_id IN (SELECT id FROM orders WHERE branch_id IN ($inBranchIds))");
    $deletedCounts['order_discounts'] = $delDiscounts ?: 0;

    $delModItems = $pdo->exec("DELETE FROM order_item_modifiers WHERE order_item_id IN (SELECT id FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE branch_id IN ($inBranchIds)))");
    $deletedCounts['order_item_modifiers'] = $delModItems ?: 0;

    $delItems = $pdo->exec("DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE branch_id IN ($inBranchIds))");
    $deletedCounts['order_items'] = $delItems ?: 0;

    $delPayments = $pdo->exec("DELETE FROM payments WHERE order_id IN (SELECT id FROM orders WHERE branch_id IN ($inBranchIds))");
    $deletedCounts['payments'] = $delPayments ?: 0;

    $delDeductions = $pdo->exec("DELETE FROM order_ledger_deductions WHERE order_id IN (SELECT id FROM orders WHERE branch_id IN ($inBranchIds))");
    $deletedCounts['order_ledger_deductions'] = $delDeductions ?: 0;

    $delOrders = $pdo->exec("DELETE FROM orders WHERE branch_id IN ($inBranchIds)");
    $deletedCounts['orders'] = $delOrders ?: 0;

    // 2. POS Sessions, Budgets & Expenses
    $delSessions = $pdo->exec("DELETE FROM cash_sessions WHERE branch_id IN ($inBranchIds)");
    $deletedCounts['cash_sessions'] = $delSessions ?: 0;

    $delBudgets = $pdo->exec("DELETE FROM branch_budgets WHERE branch_id IN ($inBranchIds)");
    $deletedCounts['branch_budgets'] = $delBudgets ?: 0;

    $delExpenses = $pdo->exec("DELETE FROM expenses WHERE branch_id IN ($inBranchIds)");
    $deletedCounts['expenses'] = $delExpenses ?: 0;

    $delBranchProds = $pdo->exec("DELETE FROM branch_products WHERE branch_id IN ($inBranchIds)");
    $deletedCounts['branch_products'] = $delBranchProds ?: 0;

    // 3. HRMS, Roster & Devices
    $delDevices = $pdo->exec("DELETE FROM devices WHERE branch_id IN ($inBranchIds)");
    $deletedCounts['devices'] = $delDevices ?: 0;

    $delEmployees = $pdo->exec("DELETE FROM employees WHERE branch_id IN ($inBranchIds)");
    $deletedCounts['employees'] = $delEmployees ?: 0;

    $delShifts = $pdo->exec("DELETE FROM shifts WHERE branch_id IN ($inBranchIds)");
    $deletedCounts['shifts'] = $delShifts ?: 0;

    $delAttendance = $pdo->exec("DELETE FROM attendance_logs WHERE branch_id IN ($inBranchIds)");
    $deletedCounts['attendance_logs'] = $delAttendance ?: 0;

    $delCerts = $pdo->exec("DELETE FROM attendance_certifications WHERE branch_id IN ($inBranchIds)");
    $deletedCounts['attendance_certifications'] = $delCerts ?: 0;

    $delPayroll = $pdo->exec("DELETE FROM payroll_runs WHERE branch_id IN ($inBranchIds)");
    $deletedCounts['payroll_runs'] = $delPayroll ?: 0;

    // 4. Test Audit Logs & Login Attempts
    $delLogs = $pdo->exec("DELETE FROM audit_logs WHERE branch_id IN ($inBranchIds) OR user_id IN ($inUserIds)");
    $deletedCounts['audit_logs'] = $delLogs ?: 0;

    $delAttempts = $pdo->exec("DELETE FROM login_attempts WHERE username IN ('test_fin_admin', 'test_fin_bm_a', 'test_fin_ba_a', 'test_fin_bm_b', 'test_fin_ga', 'cashier_b1', 'cashier_b2')");
    $deletedCounts['login_attempts'] = $delAttempts ?: 0;

    // 5. Test Users
    $delUsers = $pdo->exec("DELETE FROM users WHERE id IN ($inUserIds) OR branch_id IN ($inBranchIds)");
    $deletedCounts['users'] = $delUsers ?: 0;

    // 6. Test Products
    $delProds = $pdo->exec("DELETE FROM products WHERE id IN ($inProductIds)");
    $deletedCounts['products'] = $delProds ?: 0;

    // 7. Delete Test Branches
    $delBranches = $pdo->exec("DELETE FROM branches WHERE id IN ($inBranchIds)");
    $deletedCounts['branches'] = $delBranches ?: 0;

    $pdo->commit();
    echo "Transaction committed successfully.\n\n";
} catch (\Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "CRITICAL ERROR: Failed to clean up test branch artifacts: " . $e->getMessage() . "\n");
    throw $e;
} finally {
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
}

echo "Summary of Deleted Test Artifacts:\n";
foreach ($deletedCounts as $tbl => $cnt) {
    if ($cnt > 0) {
        echo "  - {$tbl}: {$cnt} rows deleted\n";
    }
}

print_real_branches($pdo);

/**
 * Helper to display current branches and verify only 3 remain.
 */
function print_real_branches(PDO $pdo): void {
    echo "\n====================================================================\n";
    echo "CURRENT BRANCHES IN DATABASE (SELECT * FROM branches)\n";
    echo "====================================================================\n";
    $stmt = $pdo->query("SELECT * FROM branches ORDER BY id ASC");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo str_pad("ID", 6) . str_pad("Name", 28) . str_pad("Status", 12) . str_pad("Address", 35) . str_pad("Contact", 16) . "\n";
    echo str_repeat('-', 97) . "\n";

    foreach ($branches as $b) {
        echo str_pad((string)$b['id'], 6) .
             str_pad($b['name'], 28) .
             str_pad($b['status'], 12) .
             str_pad($b['address'] ?? '-', 35) .
             str_pad($b['contact_number'] ?? '-', 16) . "\n";
    }
    echo str_repeat('=', 97) . "\n";
    echo "Total Branches Remaining: " . count($branches) . "\n";

    if (count($branches) === 3) {
        echo ">>> VERIFIED: EXACTLY 3 REAL BRANCHES REMAIN IN DATABASE! <<<\n\n";
    } else {
        fwrite(STDERR, "WARNING: Expected 3 branches, found " . count($branches) . "!\n\n");
    }
}
