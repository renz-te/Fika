<?php
/**
 * Financial Reports and P&L Test Suite (Phase 14B)
 * Tests all 6 cases:
 * 1. Orders revenue: PAID counted, UNPAID ignored.
 * 2. Refund handling: Seeded refunds reduce revenue.
 * 3. Opex and Payroll: Only APPROVED expenses and RELEASED payroll runs deducted.
 * 4. Branch Isolation: BA for Branch 1 attempting to query or export Branch 2 gets HTTP 403.
 * 5. CSV Sanitization: Cells starting with =, +, -, @, \t, \r are prepended with '.
 * 6. Cent-Accurate Net Profit Math: Exact centavos verification (Gross - Refunds - COGS - Opex - Payroll).
 */

require_once __DIR__ . '/../../app/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    die("Tests must be run from CLI.\n");
}

echo "====================================================================\n";
echo "Financial Reports & P&L Test Suite (Phase 14B)\n";
echo "====================================================================\n\n";

$failed = 0;

function assertTest(string $name, bool $condition, string $failMsg = '') {
    global $failed;
    if ($condition) {
        echo "[PASS] {$name}\n";
    } else {
        echo "[FAIL] {$name}: {$failMsg}\n";
        $failed++;
    }
}

global $pdo;

// 0. Cleanup Old Test Data
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM inventory_ledger WHERE branch_id IN (941, 942)");
$pdo->exec("DELETE FROM orders WHERE branch_id IN (941, 942)");
$pdo->exec("DELETE FROM expenses WHERE branch_id IN (941, 942)");
$pdo->exec("DELETE FROM payroll_items WHERE payroll_run_id IN (SELECT id FROM payroll_runs WHERE branch_id IN (941, 942))");
$pdo->exec("DELETE FROM payroll_runs WHERE branch_id IN (941, 942)");
$pdo->exec("DELETE FROM cash_sessions WHERE branch_id IN (941, 942)");
$pdo->exec("DELETE FROM inventory_stock WHERE branch_id IN (941, 942)");
$pdo->exec("DELETE FROM inventory_items WHERE id IN (9401, 9402)");
$pdo->exec("DELETE FROM expense_categories WHERE id = 9401");
$pdo->exec("DELETE FROM employees WHERE id IN (942, 943)");
$pdo->exec("DELETE FROM users WHERE id IN (940, 941, 942, 943, 944)");
$pdo->exec("DELETE FROM branches WHERE id IN (941, 942)");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

// 1. Setup Test Branches
$pdo->exec("INSERT INTO branches (id, name) VALUES 
    (941, 'Finance Test Branch 1'),
    (942, 'Finance Test Branch 2')
");

// 1b. Setup Test Employee
$pdo->exec("INSERT INTO employees (id, employee_code, branch_id, first_name, last_name, email, position, department, employment_type, status, date_hired) VALUES 
    (942, 'EMP-942', 941, 'Report', 'Employee', 'test_emp_942@fikacafe.local', 'Barista', 'Operations', 'REGULAR', 'ACTIVE', '2026-01-01'),
    (943, 'EMP-943', 942, 'Report', 'Employee 2', 'test_emp_943@fikacafe.local', 'Barista', 'Operations', 'REGULAR', 'ACTIVE', '2026-01-01')
");

// 2. Setup Permissions & Roles
$pdo->exec("INSERT IGNORE INTO permissions (name, description) VALUES 
    ('finance.view', 'View finances'),
    ('finance.expense.create', 'Create expenses'),
    ('finance.expense.approve', 'Approve expenses')
");

$pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_name) VALUES 
    (1, 'finance.view'), (1, 'finance.expense.create'), (1, 'finance.expense.approve'),
    (3, 'finance.view'), (3, 'finance.expense.create'), (3, 'finance.expense.approve'),
    (4, 'finance.view'), (4, 'finance.expense.create'), (4, 'finance.expense.approve')
");

// 3. Setup Test Users
$pinHash = password_hash('1234', PASSWORD_DEFAULT);
$pdo->exec("INSERT INTO users (id, username, password, role_id, branch_id) VALUES 
    (940, 'test_rep_admin', '{$pinHash}', 1, NULL),
    (942, 'test_rep_ba_1', '{$pinHash}', 4, 941),
    (943, 'test_rep_ba_2', '{$pinHash}', 4, 942),
    (944, 'test_rep_ga', '{$pinHash}', 3, NULL)
");

// 4. Setup Test Category
$pdo->exec("INSERT INTO expense_categories (id, name) VALUES (9401, 'TEST_Utilities')");

// 5. Setup Test Inventory Item
$pdo->exec("INSERT INTO inventory_items (id, item_name, unit, unit_cost) VALUES 
    (9401, 'Test Coffee Beans', 'kg', 25.50)
");

/**
 * Helper to call export API via subshell
 */
function callExportApi(array $body, ?int $userId = 940): array {
    global $pdo;
    $user = null;
    if ($userId) {
        $uStmt = $pdo->prepare("SELECT id, username, role_id, branch_id FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $user = $uStmt->fetch(PDO::FETCH_ASSOC);
    }

    $wrapper = __DIR__ . '/wrapper_exp_' . uniqid() . '.php';
    $targetPath = realpath(__DIR__ . '/../../fika_finance/api/fika_finance_export.php');

    $token = bin2hex(random_bytes(32));
    $sessionInit = "\$_SESSION['csrf_token'] = '{$token}';\n";
    if ($user) {
        $sessionInit .= "\$_SESSION['user'] = " . var_export($user, true) . ";\n";
        $sessionInit .= "\$_SESSION['last_activity'] = time();\n";
    }

    $headerCode = "\$_SERVER['HTTP_X_CSRF_TOKEN'] = '{$token}';\n";
    $headerCode .= "\$_SERVER['REQUEST_METHOD'] = 'POST';\n";

    $code = "<?php
    session_start();
    {$sessionInit}
    {$headerCode}
    \$_POST = " . var_export($body, true) . ";

    register_shutdown_function(function() {
        \$c = http_response_code();
        if (\$c === false || \$c === 0 || \$c === null) {
            \$c = 200;
        }
        echo \"\\n---STATUS:\" . \$c . \"---\\n\";
    });

    ob_start();
    try {
        require " . var_export($targetPath, true) . ";
    } catch (\\Throwable \$t) {
        http_response_code(500);
        echo \$t->getMessage();
    }
    ";

    file_put_contents($wrapper, $code);
    $out = shell_exec("php " . escapeshellarg($wrapper));
    @unlink($wrapper);

    $parts = explode("\n---STATUS:", $out);
    $bodyOutput = trim($parts[0] ?? '');
    $status = 200;
    if (isset($parts[1])) {
        $status = (int)trim(str_replace('---', '', $parts[1]));
    }

    return ['status' => $status, 'body' => $bodyOutput];
}

/**
 * Helper to call reports UI via subshell
 */
function callReportsPage(array $params, ?int $userId = 940): array {
    global $pdo;
    $user = null;
    if ($userId) {
        $uStmt = $pdo->prepare("SELECT id, username, role_id, branch_id FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $user = $uStmt->fetch(PDO::FETCH_ASSOC);
    }

    $wrapper = __DIR__ . '/wrapper_rep_' . uniqid() . '.php';
    $targetPath = realpath(__DIR__ . '/../../fika_finance/fika_finance_reports.php');

    $token = bin2hex(random_bytes(32));
    $sessionInit = "\$_SESSION['csrf_token'] = '{$token}';\n";
    if ($user) {
        $sessionInit .= "\$_SESSION['user'] = " . var_export($user, true) . ";\n";
        $sessionInit .= "\$_SESSION['last_activity'] = time();\n";
    }

    $headerCode = "\$_SERVER['REQUEST_METHOD'] = 'GET';\n";

    $code = "<?php
    session_start();
    {$sessionInit}
    {$headerCode}
    \$_GET = " . var_export($params, true) . ";

    register_shutdown_function(function() {
        \$c = http_response_code();
        if (\$c === false || \$c === 0 || \$c === null) {
            \$c = 200;
        }
        echo \"\\n---STATUS:\" . \$c . \"---\\n\";
    });

    ob_start();
    try {
        require " . var_export($targetPath, true) . ";
    } catch (\\Throwable \$t) {
        http_response_code(500);
        echo \$t->getMessage();
    }
    ";

    file_put_contents($wrapper, $code);
    $out = shell_exec("php " . escapeshellarg($wrapper));
    @unlink($wrapper);

    $parts = explode("\n---STATUS:", $out);
    $bodyOutput = trim($parts[0] ?? '');
    $status = 200;
    if (isset($parts[1])) {
        $status = (int)trim(str_replace('---', '', $parts[1]));
    }

    return ['status' => $status, 'body' => $bodyOutput];
}

$today = date('Y-m-d');
$now = date('Y-m-d H:i:s');

// ====================================================================
// CASE 1: Revenue calculation counts PAID orders and excludes UNPAID
// ====================================================================
echo "--- Running Case 1: Revenue Calculation (PAID vs UNPAID) ---\n";

$pdo->exec("DELETE FROM orders WHERE branch_id = 941");
$pdo->exec("INSERT INTO orders (id, order_number, branch_id, total_amount, discount_amount, vat_amount, final_amount, order_status, payment_status, payment_method, is_test, created_at) VALUES 
    (94101, 'ORD-C1-01', 941, 1500.00, 0, 0, 1500.00, 'COMPLETED', 'PAID', 'CASH', 0, '{$now}'),
    (94102, 'ORD-C1-02', 941, 800.00, 0, 0, 800.00, 'PENDING', 'UNPAID', 'CASH', 0, '{$now}')
");

$expC1 = callExportApi([
    'report_type' => 'sales',
    'start_date' => $today,
    'end_date' => $today,
    'branch_id' => 941
], 940);

assertTest("Case 1 - Sales Export returns 200", $expC1['status'] === 200, "Expected 200, got {$expC1['status']}");
assertTest("Case 1 - Sales Export includes 1,500.00 PAID revenue", strpos($expC1['body'], '1,500.00') !== false, "Output does not contain 1,500.00");
assertTest("Case 1 - Sales Export excludes 800.00 UNPAID revenue", strpos($expC1['body'], '800.00') === false, "Output unexpectedly contains UNPAID 800.00");

// ====================================================================
// CASE 2: Seeded refunds reduce revenue (Gross ₱2,000, Refund ₱500 -> Net ₱1,500)
// ====================================================================
echo "\n--- Running Case 2: Refund Handling (Gross 2,000, Refund 500 -> Net 1,500) ---\n";

$pdo->exec("DELETE FROM orders WHERE branch_id = 941");
$pdo->exec("INSERT INTO orders (id, order_number, branch_id, total_amount, discount_amount, vat_amount, final_amount, order_status, payment_status, payment_method, is_test, created_at) VALUES 
    (94103, 'ORD-C2-01', 941, 2000.00, 0, 0, 2000.00, 'COMPLETED', 'PAID', 'GCASH', 0, '{$now}'),
    (94104, 'ORD-C2-02', 941, 500.00, 0, 0, 500.00, 'REFUNDED', 'REFUNDED', 'GCASH', 0, '{$now}')
");

$expC2 = callExportApi([
    'report_type' => 'sales',
    'start_date' => $today,
    'end_date' => $today,
    'branch_id' => 941
], 940);

assertTest("Case 2 - Sales Export returns 200", $expC2['status'] === 200, "Expected 200, got {$expC2['status']}");
assertTest("Case 2 - Gross revenue reflects 2,000.00", strpos($expC2['body'], '2,000.00') !== false, "Output missing 2,000.00 gross");
assertTest("Case 2 - Refund deduction reflects 500.00", strpos($expC2['body'], '500.00') !== false, "Output missing 500.00 refund");
assertTest("Case 2 - Net revenue reflects 1,500.00", strpos($expC2['body'], '1,500.00') !== false, "Output missing 1,500.00 net");

// ====================================================================
// CASE 3: Only APPROVED expenses and RELEASED payroll runs are deducted
// ====================================================================
echo "\n--- Running Case 3: Expense & Payroll Deductions (APPROVED/RELEASED only) ---\n";

$pdo->exec("DELETE FROM orders WHERE branch_id IN (941, 942)");
$pdo->exec("DELETE FROM expenses WHERE branch_id = 941");
$pdo->exec("INSERT INTO expenses (id, branch_id, category_id, amount_cents, expense_date, payee, notes, status, created_by, approved_by) VALUES 
    (9411, 941, 9401, 100000, '{$today}', 'Approved Supplier', 'Valid operational expense', 'APPROVED', 940, 940),
    (9412, 941, 9401, 200000, '{$today}', 'Draft Supplier', 'Draft pending review', 'DRAFT', 940, NULL)
");

$pdo->exec("DELETE FROM payroll_items WHERE payroll_run_id IN (SELECT id FROM payroll_runs WHERE branch_id = 941)");
$pdo->exec("DELETE FROM payroll_runs WHERE branch_id = 941");

$pdo->exec("INSERT INTO payroll_runs (id, scope, branch_id, period_start, period_end, status, processed_by) VALUES 
    (94101, 'BRANCH', 941, '{$today}', '{$today}', 'RELEASED', 940),
    (94102, 'BRANCH', 941, '{$today}', '{$today}', 'DRAFT', 940)
");

$pdo->exec("INSERT INTO payroll_items (payroll_run_id, employee_id, gross_pay, basic_pay, overtime_pay, bonus_pay, employer_sss, employer_philhealth, employer_pagibig, net_pay) VALUES 
    (94101, 942, 5000.00, 5000.00, 0, 0, 0, 0, 0, 5000.00),
    (94102, 942, 3000.00, 3000.00, 0, 0, 0, 0, 0, 3000.00)
");

$expC3 = callExportApi([
    'report_type' => 'pnl',
    'start_date' => $today,
    'end_date' => $today,
    'branch_id' => 941
], 940);

assertTest("Case 3 - P&L Export returns 200", $expC3['status'] === 200, "Expected 200, got {$expC3['status']}");
assertTest("Case 3 - Deducts APPROVED expense ₱1,000.00 (100000 cents)", strpos($expC3['body'], '1,000.00') !== false, "Approved expense 1,000.00 not in output");
assertTest("Case 3 - Excludes DRAFT expense ₱2,000.00 (200000 cents)", strpos($expC3['body'], '2,000.00') === false, "Draft expense 2,000.00 unexpectedly in output");
assertTest("Case 3 - Deducts RELEASED payroll ₱5,000.00 (500000 cents)", strpos($expC3['body'], '5,000.00') !== false, "Released payroll 5,000.00 not in output");
assertTest("Case 3 - Excludes DRAFT payroll ₱3,000.00 (300000 cents)", strpos($expC3['body'], '3,000.00') === false, "Draft payroll 3,000.00 unexpectedly in output");

// ====================================================================
// CASE 4: Branch Isolation: BA for Branch 1 attempting Branch 2 gets HTTP 403
// ====================================================================
echo "\n--- Running Case 4: Branch Isolation (Constraint 3) ---\n";

// User 942 is BA for Branch 941
$expC4_cross = callExportApi([
    'report_type' => 'pnl',
    'start_date' => $today,
    'end_date' => $today,
    'branch_id' => 942 // Attempting to access Branch 2!
], 942);

assertTest("Case 4 - BA attempting cross-branch export receives HTTP 403", $expC4_cross['status'] === 403, "Expected 403, got {$expC4_cross['status']}");

$repC4_cross = callReportsPage([
    'tab' => 'pnl',
    'start_date' => $today,
    'end_date' => $today,
    'branch_id' => 942 // Attempting to query Branch 2 via GET!
], 942);

assertTest("Case 4 - BA attempting cross-branch UI query receives HTTP 403", $repC4_cross['status'] === 403, "Expected 403, got {$repC4_cross['status']}");

$expC4_own = callExportApi([
    'report_type' => 'pnl',
    'start_date' => $today,
    'end_date' => $today,
    'branch_id' => 941 // Own branch
], 942);

assertTest("Case 4 - BA accessing own branch export succeeds with HTTP 200", $expC4_own['status'] === 200, "Expected 200, got {$expC4_own['status']}");

// ====================================================================
// CASE 5: CSV Export Sanitization (Formula Injection Neutralization)
// ====================================================================
echo "\n--- Running Case 5: CSV Sanitization (=, +, -, @, \\t, \\r) ---\n";

if (!defined('FIKA_CSV_EXPORT_TESTING')) {
    define('FIKA_CSV_EXPORT_TESTING', true);
}
require_once __DIR__ . '/../../fika_finance/api/fika_finance_export.php';

// Directly test sanitizeCsvValue
$calcInjection = "=cmd|' /C calc'!A0";
$plusInjection = "+1234567890";
$minusInjection = "-5+5";
$atInjection = "@SUM(A1:A10)";
$tabInjection = "\tTAB_DATA";
$crInjection = "\rCR_DATA";
$safeString = "Normal Safe Sales";

assertTest("Case 5 - Sanitizes '=' prefix", sanitizeCsvValue($calcInjection) === "'=cmd|' /C calc'!A0", "Failed to sanitize '='");
assertTest("Case 5 - Sanitizes '+' prefix", sanitizeCsvValue($plusInjection) === "'+1234567890", "Failed to sanitize '+'");
assertTest("Case 5 - Sanitizes '-' prefix", sanitizeCsvValue($minusInjection) === "'-5+5", "Failed to sanitize '-'");
assertTest("Case 5 - Sanitizes '@' prefix", sanitizeCsvValue($atInjection) === "'@SUM(A1:A10)", "Failed to sanitize '@'");
assertTest("Case 5 - Sanitizes '\\t' prefix", sanitizeCsvValue($tabInjection) === "'\tTAB_DATA", "Failed to sanitize '\\t'");
assertTest("Case 5 - Sanitizes '\\r' prefix", sanitizeCsvValue($crInjection) === "'\rCR_DATA", "Failed to sanitize '\\r'");
assertTest("Case 5 - Preserves normal strings without single quote prefix", sanitizeCsvValue($safeString) === "Normal Safe Sales", "Altered normal string");

// Test with malicious category name in CSV export
$pdo->exec("INSERT INTO expense_categories (id, name) VALUES (9402, '=cmd|\' /C calc\'!A0') ON DUPLICATE KEY UPDATE name = '=cmd|\' /C calc\'!A0'");
$pdo->exec("INSERT INTO expenses (id, branch_id, category_id, amount_cents, expense_date, payee, notes, status, created_by, approved_by) VALUES 
    (9413, 941, 9402, 50000, '{$today}', 'Injected Payee', 'Formula test', 'APPROVED', 940, 940)
");

$expC5 = callExportApi([
    'report_type' => 'pnl',
    'start_date' => $today,
    'end_date' => $today,
    'branch_id' => 941
], 940);

assertTest("Case 5 - CSV export disarms injection in row content", strpos($expC5['body'], "'=cmd|' /C calc'!A0") !== false, "Did not find disarmed formula in CSV output");

// ====================================================================
// CASE 6: Exact Centavos Net Profit Math (Constraint 1, 2, 5)
// Gross - Refunds - COGS - Opex - Payroll
// ====================================================================
echo "\n--- Running Case 6: Exact Centavos Net Profit Math (Constraint 1, 2, 5) ---\n";

// Clean and prepare deterministic test fixtures
$pdo->exec("DELETE FROM inventory_ledger WHERE branch_id = 941");
$pdo->exec("DELETE FROM orders WHERE branch_id = 941");
$pdo->exec("DELETE FROM expenses WHERE branch_id = 941");
$pdo->exec("DELETE FROM payroll_items WHERE payroll_run_id IN (SELECT id FROM payroll_runs WHERE branch_id = 941)");
$pdo->exec("DELETE FROM payroll_runs WHERE branch_id = 941");

// 1. Seed PAID order: ₱10,000.00 (1,000,000 cents)
$orderIdPaid = 94105;
$pdo->exec("INSERT INTO orders (id, order_number, branch_id, total_amount, discount_amount, vat_amount, final_amount, order_status, payment_status, payment_method, is_test, created_at) VALUES 
    ({$orderIdPaid}, 'ORD-C6-PAID', 941, 10000.00, 0, 0, 10000.00, 'COMPLETED', 'PAID', 'CASH', 0, '{$now}')
");

// 2. Seed REFUNDED order: ₱1,000.00 (100,000 cents)
$orderIdRefund = 94106;
$pdo->exec("INSERT INTO orders (id, order_number, branch_id, total_amount, discount_amount, vat_amount, final_amount, order_status, payment_status, payment_method, is_test, created_at) VALUES 
    ({$orderIdRefund}, 'ORD-C6-REF', 941, 1000.00, 0, 0, 1000.00, 'REFUNDED', 'REFUNDED', 'CASH', 0, '{$now}')
");

// 3. Seed COGS in inventory_ledger (Constraint 1: SUM(ABS(quantity) * unit_cost_snapshot) WHERE type = 'SALE' and order_id belongs to PAID order)
// Quantity = -100.00 kg, unit_cost_snapshot = 25.50 -> 100 * 25.50 = ₱2,550.00 (255,000 cents)
$pdo->exec("INSERT INTO inventory_ledger (id, branch_id, inventory_id, type, quantity, unit_cost_snapshot, user_id, order_id, remarks, created_at) VALUES 
    (94101, 941, 9401, 'SALE', -100.00, 25.50, 940, {$orderIdPaid}, 'Sale COGS ingredient deduction', '{$now}')
");

// Seed another inventory_ledger row with type = 'Spoilage' (should NOT be counted in COGS per Constraint 1)
$pdo->exec("INSERT INTO inventory_ledger (id, branch_id, inventory_id, type, quantity, unit_cost_snapshot, user_id, remarks, created_at) VALUES 
    (94102, 941, 9401, 'Spoilage', -10.00, 25.50, 940, 'Damaged stock', '{$now}')
");

// 4. Seed APPROVED expense: ₱1,500.00 (150,000 cents)
$pdo->exec("INSERT INTO expenses (id, branch_id, category_id, amount_cents, expense_date, payee, notes, status, created_by, approved_by) VALUES 
    (9414, 941, 9401, 150000, '{$today}', 'Clean Power Co', 'Monthly electric utility', 'APPROVED', 940, 940)
");

// 5. Seed RELEASED payroll run with gross_pay + employer contributions (Constraint 2)
// gross_pay = 2,000.00, employer_sss = 150.00, employer_philhealth = 75.00, employer_pagibig = 50.00
// Total Payroll Expense = 2,000 + 150 + 75 + 50 = ₱2,275.00 (227,500 cents)
$payrollRunId = 94103;
$pdo->exec("INSERT INTO payroll_runs (id, scope, branch_id, period_start, period_end, status, processed_by) VALUES 
    ({$payrollRunId}, 'BRANCH', 941, '{$today}', '{$today}', 'RELEASED', 940)
");
$pdo->exec("INSERT INTO payroll_items (payroll_run_id, employee_id, gross_pay, basic_pay, overtime_pay, bonus_pay, employer_sss, employer_philhealth, employer_pagibig, net_pay) VALUES 
    ({$payrollRunId}, 942, 2000.00, 2000.00, 0, 0, 150.00, 75.00, 50.00, 1800.00)
");

// MATH BREAKDOWN (in exact centavos):
// Gross Revenue:     +1,000,000 cents (₱10,000.00)
// Refunds:             -100,000 cents (₱1,000.00)
// Net Revenue:         +900,000 cents (₱9,000.00)
// COGS:                -255,000 cents (₱2,550.00)
// Gross Profit:        +645,000 cents (₱6,450.00)
// Opex (Approved):     -150,000 cents (₱1,500.00)
// Payroll (Released):  -227,500 cents (₱2,275.00)
// ------------------------------------------------
// Net Profit:           267,500 cents (₱2,675.00)

$expectedGrossCents = 1000000;
$expectedRefundCents = 100000;
$expectedNetRevCents = 900000;
$expectedCogsCents = 255000;
$expectedGrossProfitCents = 645000;
$expectedOpexCents = 150000;
$expectedPayrollCents = 227500;
$expectedNetProfitCents = 267500; // ₱2,675.00

$expC6 = callExportApi([
    'report_type' => 'pnl',
    'start_date' => $today,
    'end_date' => $today,
    'branch_id' => 941
], 940);

assertTest("Case 6 - P&L Export returns 200", $expC6['status'] === 200, "Expected 200, got {$expC6['status']}");
assertTest("Case 6 - Gross Sales matches exact ₱10,000.00", strpos($expC6['body'], '10,000.00') !== false, "Gross sales 10,000.00 not found");
assertTest("Case 6 - Refunds matches exact ₱1,000.00", strpos($expC6['body'], '1,000.00') !== false, "Refund 1,000.00 not found");
assertTest("Case 6 - Net Revenue matches exact ₱9,000.00", strpos($expC6['body'], '9,000.00') !== false, "Net revenue 9,000.00 not found");
assertTest("Case 6 - COGS matches exact ₱2,550.00 (255000 cents)", strpos($expC6['body'], '2,550.00') !== false, "COGS 2,550.00 not found");
assertTest("Case 6 - Gross Profit matches exact ₱6,450.00 (645000 cents)", strpos($expC6['body'], '6,450.00') !== false, "Gross profit 6,450.00 not found");
assertTest("Case 6 - Operating Expenses matches exact ₱1,500.00", strpos($expC6['body'], '1,500.00') !== false, "Opex 1,500.00 not found");
assertTest("Case 6 - Payroll Expenses matches exact ₱2,275.00 (227500 cents)", strpos($expC6['body'], '2,275.00') !== false, "Payroll 2,275.00 not found");
assertTest("Case 6 - Net Profit matches exact centavos ₱2,675.00 (267500 cents)", strpos($expC6['body'], '2,675.00') !== false && strpos($expC6['body'], '267500') !== false, "Net profit 2,675.00 / 267500 cents not found");

$repC6 = callReportsPage([
    'tab' => 'pnl',
    'start_date' => $today,
    'end_date' => $today,
    'branch_id' => 941
], 940);

assertTest("Case 6 - Reports UI renders Net Profit ₱2,675.00 accurately", strpos($repC6['body'], '2,675.00') !== false, "Reports UI missing exact 2,675.00");

// ====================================================================
// Cleanup
// ====================================================================
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM inventory_ledger WHERE branch_id IN (941, 942)");
$pdo->exec("DELETE FROM orders WHERE branch_id IN (941, 942)");
$pdo->exec("DELETE FROM expenses WHERE branch_id IN (941, 942)");
$pdo->exec("DELETE FROM payroll_items WHERE payroll_run_id IN (SELECT id FROM payroll_runs WHERE branch_id IN (941, 942))");
$pdo->exec("DELETE FROM payroll_runs WHERE branch_id IN (941, 942)");
$pdo->exec("DELETE FROM cash_sessions WHERE branch_id IN (941, 942)");
$pdo->exec("DELETE FROM inventory_stock WHERE branch_id IN (941, 942)");
$pdo->exec("DELETE FROM inventory_items WHERE id IN (9401, 9402)");
$pdo->exec("DELETE FROM expense_categories WHERE id IN (9401, 9402)");
$pdo->exec("DELETE FROM employees WHERE id IN (942, 943)");
$pdo->exec("DELETE FROM users WHERE id IN (940, 941, 942, 943, 944)");
$pdo->exec("DELETE FROM branches WHERE id IN (941, 942)");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

echo "\n====================================================================\n";
if ($failed === 0) {
    echo "ALL 6 TEST CASES PASSED SUCCESSFULLY!\n";
    echo "====================================================================\n";
    exit(0);
} else {
    echo "FAILED: {$failed} test(s) failed.\n";
    echo "====================================================================\n";
    exit(1);
}
