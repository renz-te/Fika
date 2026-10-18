<?php
/**
 * Finance Expenses and Budgets Test Suite (Phase 14A)
 * Tests all 8 cases covering Auto-Approval threshold, Maker-Checker,
 * Voiding with reasons, Cross-branch restrictions, and Cent-accurate Budget Math.
 */

require_once __DIR__ . '/../../app/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    die("Tests must be run from CLI.\n");
}

echo "====================================================================\n";
echo "Finance Expenses & Budgets Test Suite (Phase 14A)\n";
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
$pdo->exec("DELETE FROM expenses WHERE branch_id IN (941, 942) OR category_id = 9401");
$pdo->exec("DELETE FROM branch_budgets WHERE branch_id IN (941, 942)");
$pdo->exec("DELETE FROM expense_categories WHERE id = 9401");
$pdo->exec("DELETE FROM users WHERE id IN (940, 941, 942, 943, 944)");
$pdo->exec("DELETE FROM branches WHERE id IN (941, 942)");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

// 1. Setup Test Branches
$pdo->exec("INSERT INTO branches (id, name) VALUES 
    (941, 'Finance Test Branch A'),
    (942, 'Finance Test Branch B')
");

// 2. Setup Permissions & Roles
$pdo->exec("INSERT IGNORE INTO permissions (name, description) VALUES 
    ('finance.view', 'View finances'),
    ('finance.expense.create', 'Create expenses'),
    ('finance.expense.approve', 'Approve expenses'),
    ('finance.budget.manage', 'Manage budgets')
");

$pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_name) VALUES 
    (1, 'finance.view'), (1, 'finance.expense.create'), (1, 'finance.expense.approve'), (1, 'finance.budget.manage'),
    (3, 'finance.view'), (3, 'finance.expense.create'), (3, 'finance.expense.approve'), (3, 'finance.budget.manage'),
    (4, 'finance.view'), (4, 'finance.expense.create'), (4, 'finance.expense.approve'),
    (6, 'finance.view'), (6, 'finance.expense.create')
");

// 3. Setup Test Users
$pinHash = password_hash('1234', PASSWORD_DEFAULT);
$pdo->exec("INSERT INTO users (id, username, password, role_id, branch_id) VALUES 
    (940, 'test_fin_admin', '{$pinHash}', 1, NULL),
    (941, 'test_fin_bm_a', '{$pinHash}', 6, 941),
    (942, 'test_fin_ba_a', '{$pinHash}', 4, 941),
    (943, 'test_fin_bm_b', '{$pinHash}', 6, 942),
    (944, 'test_fin_ga', '{$pinHash}', 3, NULL)
");

// 4. Setup Test Category
$pdo->exec("INSERT INTO expense_categories (id, name) VALUES (9401, 'TEST_Office_Supplies')");

// 5. Ensure Approval Threshold Setting
$pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES 
    ('finance_expense_approval_threshold_cents', '100000')
    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
");

// Helper function to call the finance API in subshell
function callFinanceApi(string $query, array $body = [], ?int $userId = 941, string $method = 'POST'): array {
    global $pdo;
    $user = null;
    if ($userId) {
        $uStmt = $pdo->prepare("SELECT id, username, role_id, branch_id FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $user = $uStmt->fetch(PDO::FETCH_ASSOC);
    }

    $wrapper = __DIR__ . '/wrapper_fin_' . uniqid() . '.php';
    $targetPath = realpath(__DIR__ . '/../../fika_finance/api/fika_finance_expenses.php');

    $token = bin2hex(random_bytes(32));
    $sessionInit = "\$_SESSION['csrf_token'] = '{$token}';\n";
    if ($user) {
        $sessionInit .= "\$_SESSION['user'] = " . var_export($user, true) . ";\n";
        $sessionInit .= "\$_SESSION['last_activity'] = time();\n";
    }

    $headerCode = "\$_SERVER['HTTP_X_CSRF_TOKEN'] = '{$token}';\n";
    $headerCode .= "\$_SERVER['REQUEST_METHOD'] = '{$method}';\n";

    $code = "<?php
    session_start();
    {$sessionInit}
    {$headerCode}
    \$_SERVER['QUERY_STRING'] = " . var_export($query, true) . ";
    parse_str(\$_SERVER['QUERY_STRING'], \$_GET);
    \$_POST = " . var_export($body, true) . ";

    register_shutdown_function(function() {
        \$c = http_response_code();
        if (\$c === false || \$c === 0 || \$c === null) {
            \$c = 200;
        }
        echo \"\\n---STATUS:\" . \$c . \"---\\n\";
    });

    // Mock php://input
    stream_wrapper_unregister('php');
    stream_wrapper_register('php', 'MockStream');
    class MockStream {
        public \$context;
        private \$pos = 0;
        private \$data = '';
        public function stream_open(\$path, \$mode, \$options, &\$opened) {
            \$this->data = base64_decode('" . base64_encode(json_encode($body)) . "');
            return true;
        }
        public function stream_read(\$count) {
            \$ret = substr(\$this->data, \$this->pos, \$count);
            \$this->pos += strlen(\$ret);
            return \$ret;
        }
        public function stream_eof() { return \$this->pos >= strlen(\$this->data); }
        public function stream_stat() { return []; }
    }

    ob_start();
    try {
        require " . var_export($targetPath, true) . ";
    } catch (\\Throwable \$t) {
        http_response_code(500);
        echo json_encode(['error' => \$t->getMessage()]);
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

    $json = json_decode($bodyOutput, true);
    return [
        'status' => $status,
        'body' => $bodyOutput,
        'data' => is_array($json) ? $json : []
    ];
}

$testMonth = date('Y-m');

// ====================================================================
// CASE 1: Create expense <= ₱1000 -> auto-approved.
// ====================================================================
// ₱800.00 = 80,000 cents <= 100,000 threshold
$res1 = callFinanceApi('action=save_expense', [
    'branch_id' => 941,
    'category_id' => 9401,
    'amount_cents' => 80000,
    'payee' => 'National Book Store',
    'expense_date' => date('Y-m-d'),
    'notes' => 'Pens and marker supplies'
], 941);

$expId1 = $res1['data']['expense_id'] ?? 0;
$expDb1 = $pdo->query("SELECT status, approved_by, created_by FROM expenses WHERE id = {$expId1}")->fetch();

assertTest(
    "Case 1: Create expense <= ₱1000 -> auto-approved",
    $res1['status'] === 200 &&
    ($res1['data']['status'] ?? '') === 'APPROVED' &&
    ($res1['data']['auto_approved'] ?? false) === true &&
    ($expDb1['status'] ?? '') === 'APPROVED' &&
    (int)$expDb1['approved_by'] === 941 &&
    (int)$expDb1['created_by'] === 941,
    "Expected auto-approved status. Res: " . json_encode($res1) . " DB: " . json_encode($expDb1)
);

// ====================================================================
// CASE 2: Create expense > ₱1000 -> status DRAFT.
// ====================================================================
// ₱2,500.00 = 250,000 cents > 100,000 threshold
$res2 = callFinanceApi('action=save_expense', [
    'branch_id' => 941,
    'category_id' => 9401,
    'amount_cents' => 250000,
    'payee' => 'Office Warehouse',
    'expense_date' => date('Y-m-d'),
    'notes' => 'Whiteboard and heavy equipment'
], 941);

$expId2 = $res2['data']['expense_id'] ?? 0;
$expDb2 = $pdo->query("SELECT status, approved_by, created_by FROM expenses WHERE id = {$expId2}")->fetch();

assertTest(
    "Case 2: Create expense > ₱1000 -> status DRAFT",
    $res2['status'] === 200 &&
    ($res2['data']['status'] ?? '') === 'DRAFT' &&
    ($res2['data']['auto_approved'] ?? true) === false &&
    ($expDb2['status'] ?? '') === 'DRAFT' &&
    $expDb2['approved_by'] === null,
    "Expected DRAFT status. Res: " . json_encode($res2) . " DB: " . json_encode($expDb2)
);

// ====================================================================
// CASE 3: Maker cannot approve their own DRAFT expense (403 Maker-Checker).
// ====================================================================
// User 941 (BM A who submitted expId2) attempts to approve expId2
$res3 = callFinanceApi('action=approve_expense', [
    'expense_id' => $expId2
], 941);

$expDb3 = $pdo->query("SELECT status FROM expenses WHERE id = {$expId2}")->fetch();

assertTest(
    "Case 3: Maker cannot approve their own DRAFT expense (403)",
    $res3['status'] === 403 &&
    ($expDb3['status'] ?? '') === 'DRAFT' &&
    str_contains(strtolower($res3['body']), 'maker-checker'),
    "Expected 403 Maker-Checker error. Status: {$res3['status']}, Body: {$res3['body']}"
);

// ====================================================================
// CASE 4: Accountant approves the DRAFT expense -> status APPROVED.
// ====================================================================
// User 942 (BA A) approves expId2
$res4 = callFinanceApi('action=approve_expense', [
    'expense_id' => $expId2
], 942);

$expDb4 = $pdo->query("SELECT status, approved_by FROM expenses WHERE id = {$expId2}")->fetch();

assertTest(
    "Case 4: Accountant approves the DRAFT expense -> status APPROVED",
    $res4['status'] === 200 &&
    ($expDb4['status'] ?? '') === 'APPROVED' &&
    (int)$expDb4['approved_by'] === 942,
    "Expected status APPROVED with approver 942. Res: " . json_encode($res4) . " DB: " . json_encode($expDb4)
);

// ====================================================================
// CASE 5: Voiding an expense requires a reason and sets status to VOIDED.
// ====================================================================
// Attempt to void without reason
$resVoidNoReason = callFinanceApi('action=void_expense', [
    'expense_id' => $expId2,
    'void_reason' => ''
], 942);

// Void with valid reason
$resVoidWithReason = callFinanceApi('action=void_expense', [
    'expense_id' => $expId2,
    'void_reason' => 'Duplicate receipt submitted in error'
], 942);

$expDb5 = $pdo->query("SELECT status, void_reason FROM expenses WHERE id = {$expId2}")->fetch();

assertTest(
    "Case 5: Voiding an expense requires a reason and sets status to VOIDED",
    $resVoidNoReason['status'] === 400 &&
    $resVoidWithReason['status'] === 200 &&
    ($expDb5['status'] ?? '') === 'VOIDED' &&
    $expDb5['void_reason'] === 'Duplicate receipt submitted in error',
    "Void no-reason: {$resVoidNoReason['status']}, Void with-reason: {$resVoidWithReason['status']}, DB: " . json_encode($expDb5)
);

// ====================================================================
// CASE 6: Cross-branch user cannot view or create expenses for another branch.
// ====================================================================
// User 943 (BM Branch B) tries to create expense for Branch 941 (Branch A)
$resCrossCreate = callFinanceApi('action=save_expense', [
    'branch_id' => 941,
    'category_id' => 9401,
    'amount_cents' => 50000,
    'payee' => 'Cross-branch Intruder',
    'expense_date' => date('Y-m-d')
], 943);

// User 943 tries to get budget summary for Branch 941
$resCrossBudget = callFinanceApi("action=get_budget_summary&branch_id=941&budget_month={$testMonth}", [], 943, 'GET');

assertTest(
    "Case 6: Cross-branch user cannot view or create expenses for another branch",
    $resCrossCreate['status'] === 403 &&
    $resCrossBudget['status'] === 403,
    "Create status: {$resCrossCreate['status']}, Budget status: {$resCrossBudget['status']}"
);

// ====================================================================
// CASE 7: Budget calculation math is correct in cents.
// ====================================================================
// Set budget for Branch 941: ₱20,000 = 2,000,000 cents (User 940 = Admin)
$resSetBudget = callFinanceApi('action=save_budget', [
    'branch_id' => 941,
    'budget_month' => $testMonth,
    'allocated_amount_cents' => 2000000
], 940);

// Ensure known approved expenses:
// Clean test expenses for branch 941 for this month
$pdo->exec("DELETE FROM expenses WHERE branch_id = 941");

// Create approved expense 1: ₱5,000 (500,000 cents)
$pdo->exec("INSERT INTO expenses (branch_id, category_id, amount_cents, expense_date, payee, status, created_by, approved_by, created_at)
    VALUES (941, 9401, 500000, '{$testMonth}-10', 'Vendor 1', 'APPROVED', 941, 942, NOW())
");

// Create approved expense 2: ₱3,500 (350,000 cents)
$pdo->exec("INSERT INTO expenses (branch_id, category_id, amount_cents, expense_date, payee, status, created_by, approved_by, created_at)
    VALUES (941, 9401, 350000, '{$testMonth}-15', 'Vendor 2', 'APPROVED', 941, 942, NOW())
");

// Create DRAFT expense (must NOT deduct from budget): ₱4,000 (400,000 cents)
$pdo->exec("INSERT INTO expenses (branch_id, category_id, amount_cents, expense_date, payee, status, created_by, approved_by, created_at)
    VALUES (941, 9401, 400000, '{$testMonth}-20', 'Draft Vendor', 'DRAFT', 941, NULL, NOW())
");

// Fetch budget summary
$resBudgetSummary = callFinanceApi("action=get_budget_summary&branch_id=941&budget_month={$testMonth}", [], 941, 'GET');
$bData = $resBudgetSummary['data']['data'] ?? [];

$allocatedCheck = (int)($bData['allocated_amount_cents'] ?? 0);
$spentCheck = (int)($bData['spent_cents'] ?? 0);
$remainingCheck = (int)($bData['remaining_cents'] ?? 0);
$utilCheck = (float)($bData['utilization_percent'] ?? 0);

// Expected:
// Allocated: 2,000,000 cents
// Spent: 500,000 + 350,000 = 850,000 cents (DRAFT 400,000 excluded)
// Remaining: 2,000,000 - 850,000 = 1,150,000 cents
// Utilization: (850,000 / 2,000,000) * 100 = 42.50%
assertTest(
    "Case 7: Budget calculation math is correct in cents",
    $resBudgetSummary['status'] === 200 &&
    $allocatedCheck === 2000000 &&
    $spentCheck === 850000 &&
    $remainingCheck === 1150000 &&
    abs($utilCheck - 42.50) < 0.01,
    "Allocated: {$allocatedCheck}, Spent: {$spentCheck}, Remaining: {$remainingCheck}, Util: {$utilCheck}%. Data: " . json_encode($bData)
);

// ====================================================================
// CASE 8: Approved expense drops budget, voiding it restores the budget.
// ====================================================================
// Step A: Baseline before new expense: Spent = 850,000, Remaining = 1,150,000
// Add an approved expense of ₱5,000 (500,000 cents)
$pdo->exec("INSERT INTO expenses (id, branch_id, category_id, amount_cents, expense_date, payee, status, created_by, approved_by, created_at)
    VALUES (9499, 941, 9401, 500000, '{$testMonth}-22', 'Equipment Maintenance', 'APPROVED', 941, 942, NOW())
");

// Check summary after adding ₱5,000 approved expense
$resSummaryAfterAdd = callFinanceApi("action=get_budget_summary&branch_id=941&budget_month={$testMonth}", [], 941, 'GET');
$dataAfterAdd = $resSummaryAfterAdd['data']['data'] ?? [];
$spentAfterAdd = (int)($dataAfterAdd['spent_cents'] ?? 0);
$remainingAfterAdd = (int)($dataAfterAdd['remaining_cents'] ?? 0);

// Spent should be 850,000 + 500,000 = 1,350,000. Remaining: 650,000
$stepAPass = ($spentAfterAdd === 1350000 && $remainingAfterAdd === 650000);

// Step B: Void that same expense
$resVoid8 = callFinanceApi('action=void_expense', [
    'expense_id' => 9499,
    'void_reason' => 'Maintenance was cancelled and refunded'
], 942);

// Check summary after voiding
$resSummaryAfterVoid = callFinanceApi("action=get_budget_summary&branch_id=941&budget_month={$testMonth}", [], 941, 'GET');
$dataAfterVoid = $resSummaryAfterVoid['data']['data'] ?? [];
$spentAfterVoid = (int)($dataAfterVoid['spent_cents'] ?? 0);
$remainingAfterVoid = (int)($dataAfterVoid['remaining_cents'] ?? 0);

// Spent should restore back to 850,000 and remaining back to 1,150,000
$stepBPass = ($spentAfterVoid === 850000 && $remainingAfterVoid === 1150000);

assertTest(
    "Case 8: Approved expense drops budget, voiding it restores the budget",
    $stepAPass && $resVoid8['status'] === 200 && $stepBPass,
    "Step A (spent=1350000, rem=650000): " . ($stepAPass ? 'PASS' : 'FAIL') . " (got spent={$spentAfterAdd}, rem={$remainingAfterAdd}). " .
    "Step B (spent=850000, rem=1150000): " . ($stepBPass ? 'PASS' : 'FAIL') . " (got spent={$spentAfterVoid}, rem={$remainingAfterVoid})."
);

echo "\n--------------------------------------------------------------------\n";
echo "Finance Test Suite Completed. Failed: {$failed}\n";
echo "--------------------------------------------------------------------\n";

exit($failed > 0 ? 1 : 0);
