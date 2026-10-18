<?php
/**
 * Payment Reconciliation Test Suite (Phase 14C)
 * Tests all 5 cases:
 * 1. Detects PAID order with no payments row, and verifies resolution without payment_id.
 * 2. Detects paid payment row whose order is still UNPAID.
 * 3. Detects amount mismatch between payments.gross_amount and orders.final_amount.
 * 4. Detects stuck pending payment older than 30 minutes (paid_at IS NULL).
 * 5. Resolving anomaly requires note, enforces Maker-Checker (403 for order creator), and saves resolved_at/resolved_by.
 */

require_once __DIR__ . '/../../app/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    die("Tests must be run from CLI.\n");
}

echo "====================================================================\n";
echo "Payment Reconciliation Test Suite (Phase 14C)\n";
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

// 0. Cleanup Test Data
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM reconciliation_logs WHERE order_id IN (95101, 95102, 95103, 95104, 95105, 95106)");
$pdo->exec("DELETE FROM payments WHERE order_id IN (95101, 95102, 95103, 95104, 95105, 95106) OR id IN (9501, 9502, 9503, 9504, 9505)");
$pdo->exec("DELETE FROM orders WHERE branch_id IN (951, 952) OR id IN (95101, 95102, 95103, 95104, 95105, 95106)");
$pdo->exec("DELETE FROM users WHERE id IN (950, 951, 952, 953)");
$pdo->exec("DELETE FROM branches WHERE id IN (951, 952)");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

// 1. Setup Test Branches
$pdo->exec("INSERT INTO branches (id, name) VALUES 
    (951, 'Recon Test Branch 1'),
    (952, 'Recon Test Branch 2')
");

// 2. Setup Permissions & Roles
$pdo->exec("INSERT IGNORE INTO permissions (name, description) VALUES 
    ('finance.view', 'View finances')
");

$pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_name) VALUES 
    (1, 'finance.view'),
    (3, 'finance.view'),
    (4, 'finance.view'),
    (5, 'finance.view')
");

// 3. Setup Test Users
$pinHash = password_hash('1234', PASSWORD_DEFAULT);
$pdo->exec("INSERT INTO users (id, username, password, role_id, branch_id) VALUES 
    (950, 'test_recon_admin', '{$pinHash}', 1, NULL),
    (951, 'test_recon_cashier', '{$pinHash}', 5, 951),
    (952, 'test_recon_accountant', '{$pinHash}', 4, 951),
    (953, 'test_recon_ba2', '{$pinHash}', 4, 952)
");

/**
 * Helper to call reconciliation API via subshell
 */
function callReconcileApi(array $params = [], ?int $userId = 950, string $method = 'GET'): array {
    global $pdo;
    $user = null;
    if ($userId) {
        $uStmt = $pdo->prepare("SELECT id, username, role_id, branch_id FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $user = $uStmt->fetch(PDO::FETCH_ASSOC);
    }

    $wrapper = __DIR__ . '/wrapper_recon_' . uniqid() . '.php';
    $targetPath = realpath(__DIR__ . '/../../fika_finance/api/fika_finance_reconcile.php');

    $token = bin2hex(random_bytes(32));
    $sessionInit = "\$_SESSION['csrf_token'] = '{$token}';\n";
    if ($user) {
        $sessionInit .= "\$_SESSION['user'] = " . var_export($user, true) . ";\n";
        $sessionInit .= "\$_SESSION['last_activity'] = time();\n";
    }

    $headerCode = "\$_SERVER['HTTP_X_CSRF_TOKEN'] = '{$token}';\n";
    $headerCode .= "\$_SERVER['REQUEST_METHOD'] = '{$method}';\n";

    if ($method === 'POST') {
        $params['csrf_token'] = $token;
    }

    $code = "<?php
    session_start();
    {$sessionInit}
    {$headerCode}
    if ('{$method}' === 'GET') {
        \$_GET = " . var_export($params, true) . ";
    } else {
        \$_POST = " . var_export($params, true) . ";
    }

    // Mock php://input
    stream_wrapper_unregister('php');
    stream_wrapper_register('php', 'MockReconStream');
    class MockReconStream {
        public \$context;
        private \$pos = 0;
        private \$data = '';
        public function stream_open(\$path, \$mode, \$options, &\$opened) {
            \$this->data = base64_decode('" . base64_encode(json_encode($params)) . "');
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
    return ['status' => $status, 'json' => $json, 'raw' => $bodyOutput];
}

$today = date('Y-m-d');
$now = date('Y-m-d H:i:s');

// ====================================================================
// CASE 1: Detects a PAID order with no payments row (and resolve works without payment_id)
// ====================================================================
echo "--- Running Case 1: Paid without Payment Row (Constraint 3) ---\n";

$orderId1 = 95101;
$pdo->exec("INSERT INTO orders (id, order_number, branch_id, cashier_id, total_amount, discount_amount, vat_amount, final_amount, order_status, payment_status, payment_method, is_test, created_at) VALUES 
    ({$orderId1}, 'ORD-REC-01', 951, 951, 350.00, 0, 0, 350.00, 'COMPLETED', 'PAID', 'GCASH', 0, '{$now}')
");

$res1 = callReconcileApi([
    'action' => 'list_anomalies',
    'start_date' => $today,
    'end_date' => $today,
    'branch_id' => 951
], 950);

assertTest("Case 1 - List anomalies returns 200", $res1['status'] === 200, "Expected 200, got {$res1['status']}");
$missingPayments = $res1['json']['anomalies']['paid_without_payment'] ?? [];
$found1 = false;
foreach ($missingPayments as $item) {
    if ((int)$item['order_id'] === $orderId1) {
        $found1 = true;
        break;
    }
}
assertTest("Case 1 - Detects PAID digital order with no payments row", $found1, "Order {$orderId1} not found in paid_without_payment");

// Constraint 3: Explicitly verify that resolving works without payment_id
$res1_resolve = callReconcileApi([
    'action' => 'resolve',
    'anomaly_type' => 'PAID_WITHOUT_PAYMENT',
    'order_id' => $orderId1,
    'payment_id' => null, // Explicitly NULL!
    'resolution_note' => 'Matched manually via PayMongo dashboard transaction #PM-8921'
], 952, 'POST'); // User 952 is accountant (different from cashier 951)

assertTest("Case 1 - Resolves anomaly without payment_id successfully (200)", $res1_resolve['status'] === 200, "Expected 200, got {$res1_resolve['status']}. Error: " . ($res1_resolve['json']['error'] ?? $res1_resolve['raw']));

// Verify row exists in reconciliation_logs
$checkLog1 = $pdo->prepare("SELECT * FROM reconciliation_logs WHERE order_id = ? AND anomaly_type = 'PAID_WITHOUT_PAYMENT'");
$checkLog1->execute([$orderId1]);
$logRow1 = $checkLog1->fetch(PDO::FETCH_ASSOC);
assertTest("Case 1 - reconciliation_logs row created with payment_id = NULL", $logRow1 && $logRow1['payment_id'] === null, "Log row missing or payment_id not null");
assertTest("Case 1 - reconciliation_logs records resolved_by user 952", (int)($logRow1['resolved_by'] ?? 0) === 952, "resolved_by is not 952");

// Verify that resolved anomaly is filtered out on subsequent list
$res1_after = callReconcileApi([
    'action' => 'list_anomalies',
    'start_date' => $today,
    'end_date' => $today,
    'branch_id' => 951
], 950);
$missingPaymentsAfter = $res1_after['json']['anomalies']['paid_without_payment'] ?? [];
$found1After = false;
foreach ($missingPaymentsAfter as $item) {
    if ((int)$item['order_id'] === $orderId1) {
        $found1After = true;
        break;
    }
}
assertTest("Case 1 - Resolved anomaly filtered out from pending list", !$found1After, "Resolved order {$orderId1} still appears in list");

// ====================================================================
// CASE 2: Detects a paid payment row whose order is still UNPAID
// ====================================================================
echo "\n--- Running Case 2: Payment Row without Paid Order ---\n";

$orderId2 = 95102;
$paymentId2 = 9502;
$pdo->exec("INSERT INTO orders (id, order_number, branch_id, cashier_id, total_amount, discount_amount, vat_amount, final_amount, order_status, payment_status, payment_method, is_test, created_at) VALUES 
    ({$orderId2}, 'ORD-REC-02', 951, 951, 420.00, 0, 0, 420.00, 'PENDING', 'UNPAID', 'MAYA', 0, '{$now}')
");
$pdo->exec("INSERT INTO payments (id, order_id, method, gross_amount, net_amount, status, payment_method, created_at) VALUES 
    ({$paymentId2}, {$orderId2}, 'MAYA', 420.00, 410.00, 'paid', 'MAYA', '{$now}')
");

$res2 = callReconcileApi([
    'action' => 'list_anomalies',
    'start_date' => $today,
    'end_date' => $today,
    'branch_id' => 951
], 950);

assertTest("Case 2 - List anomalies returns 200", $res2['status'] === 200, "Expected 200, got {$res2['status']}");
$unpaidOrders = $res2['json']['anomalies']['payment_without_order'] ?? [];
$found2 = false;
foreach ($unpaidOrders as $item) {
    if ((int)$item['order_id'] === $orderId2 && (int)$item['payment_id'] === $paymentId2) {
        $found2 = true;
        break;
    }
}
assertTest("Case 2 - Detects paid payment row whose order is still UNPAID", $found2, "Payment {$paymentId2} / Order {$orderId2} not detected");

// ====================================================================
// CASE 3: Detects an amount mismatch between payments.gross_amount and orders.final_amount
// ====================================================================
echo "\n--- Running Case 3: Amount Mismatch ---\n";

$orderId3 = 95103;
$paymentId3 = 9503;
$pdo->exec("INSERT INTO orders (id, order_number, branch_id, cashier_id, total_amount, discount_amount, vat_amount, final_amount, order_status, payment_status, payment_method, is_test, created_at) VALUES 
    ({$orderId3}, 'ORD-REC-03', 951, 951, 550.00, 0, 0, 550.00, 'COMPLETED', 'PAID', 'GCASH', 0, '{$now}')
");
// Gross amount in payment is 500.00 != order final_amount 550.00
$pdo->exec("INSERT INTO payments (id, order_id, method, gross_amount, net_amount, status, payment_method, created_at) VALUES 
    ({$paymentId3}, {$orderId3}, 'GCASH', 500.00, 485.00, 'paid', 'GCASH', '{$now}')
");

$res3 = callReconcileApi([
    'action' => 'list_anomalies',
    'start_date' => $today,
    'end_date' => $today,
    'branch_id' => 951
], 950);

assertTest("Case 3 - List anomalies returns 200", $res3['status'] === 200, "Expected 200, got {$res3['status']}");
$mismatches = $res3['json']['anomalies']['amount_mismatch'] ?? [];
$found3 = false;
foreach ($mismatches as $item) {
    if ((int)$item['order_id'] === $orderId3 && (int)$item['payment_id'] === $paymentId3) {
        $found3 = true;
        break;
    }
}
assertTest("Case 3 - Detects amount mismatch between payment (500) and order (550)", $found3, "Amount mismatch not detected for Order {$orderId3}");

// ====================================================================
// CASE 4: Detects a stuck pending payment (older than 30 mins)
// ====================================================================
echo "\n--- Running Case 4: Stuck Pending (Constraint 4) ---\n";

$orderId4 = 95104;
$paymentId4_old = 9504; // 45 minutes ago -> STUCK
$paymentId4_recent = 9505; // 10 minutes ago -> NOT stuck
$pdo->exec("INSERT INTO orders (id, order_number, branch_id, cashier_id, total_amount, discount_amount, vat_amount, final_amount, order_status, payment_status, payment_method, is_test, created_at) VALUES 
    ({$orderId4}, 'ORD-REC-04', 951, 951, 280.00, 0, 0, 280.00, 'PENDING', 'UNPAID', 'CARD', 0, '{$now}')
");

$timeOld = date('Y-m-d H:i:s', strtotime('-45 minutes'));
$timeRecent = date('Y-m-d H:i:s', strtotime('-10 minutes'));

$pdo->exec("INSERT INTO payments (id, order_id, method, gross_amount, net_amount, status, paid_at, payment_method, created_at) VALUES 
    ({$paymentId4_old}, {$orderId4}, 'CARD', 280.00, 270.00, 'pending', NULL, 'CARD', '{$timeOld}'),
    ({$paymentId4_recent}, {$orderId4}, 'CARD', 280.00, 270.00, 'pending', NULL, 'CARD', '{$timeRecent}')
");

$res4 = callReconcileApi([
    'action' => 'list_anomalies',
    'start_date' => $today,
    'end_date' => $today,
    'branch_id' => 951
], 950);

assertTest("Case 4 - List anomalies returns 200", $res4['status'] === 200, "Expected 200, got {$res4['status']}");
$stuckPayments = $res4['json']['anomalies']['stuck_pending'] ?? [];
$foundOld = false;
$foundRecent = false;
foreach ($stuckPayments as $item) {
    if ((int)$item['payment_id'] === $paymentId4_old) {
        $foundOld = true;
    }
    if ((int)$item['payment_id'] === $paymentId4_recent) {
        $foundRecent = true;
    }
}
assertTest("Case 4 - Detects pending payment created 45 mins ago as stuck", $foundOld, "Old pending payment {$paymentId4_old} not detected");
assertTest("Case 4 - Excludes pending payment created 10 mins ago (< 30 mins)", !$foundRecent, "Recent pending payment {$paymentId4_recent} unexpectedly detected");

// ====================================================================
// CASE 5: Resolving an anomaly requires a note, enforces Maker-Checker, and saves audit
// ====================================================================
echo "\n--- Running Case 5: Maker-Checker & Resolution Enforcement (Constraint 2) ---\n";

$orderId5 = 95105;
$paymentId5 = 9506;
// Order created by Cashier 951
$pdo->exec("INSERT INTO orders (id, order_number, branch_id, cashier_id, total_amount, discount_amount, vat_amount, final_amount, order_status, payment_status, payment_method, is_test, created_at) VALUES 
    ({$orderId5}, 'ORD-REC-05', 951, 951, 600.00, 0, 0, 600.00, 'COMPLETED', 'PAID', 'GCASH', 0, '{$now}')
");
$pdo->exec("INSERT INTO payments (id, order_id, method, gross_amount, net_amount, status, payment_method, created_at) VALUES 
    ({$paymentId5}, {$orderId5}, 'GCASH', 550.00, 535.00, 'paid', 'GCASH', '{$now}')
");

// 1. Maker-Checker check: Cashier 951 (order creator) attempts to resolve -> MUST return 403
$res5_maker = callReconcileApi([
    'action' => 'resolve',
    'anomaly_type' => 'AMOUNT_MISMATCH',
    'order_id' => $orderId5,
    'payment_id' => $paymentId5,
    'resolution_note' => 'I am the cashier and I am trying to resolve my own order'
], 951, 'POST'); // Cashier 951!

assertTest("Case 5 - Maker cannot resolve their own order anomaly (HTTP 403)", $res5_maker['status'] === 403, "Expected 403, got {$res5_maker['status']}");
assertTest("Case 5 - Maker-Checker error message returned", strpos($res5_maker['raw'], 'Maker-Checker') !== false, "Error message does not mention Maker-Checker");

// 2. Missing note check: Accountant 952 attempts to resolve without note -> MUST return 400
$res5_nonote = callReconcileApi([
    'action' => 'resolve',
    'anomaly_type' => 'AMOUNT_MISMATCH',
    'order_id' => $orderId5,
    'payment_id' => $paymentId5,
    'resolution_note' => '   ' // Empty whitespace
], 952, 'POST');

assertTest("Case 5 - Missing resolution note returns HTTP 400", $res5_nonote['status'] === 400, "Expected 400, got {$res5_nonote['status']}");

// 3. Cross-branch check: Accountant from Branch 2 (User 953) attempts to resolve Branch 1 order -> MUST return 403
$res5_cross = callReconcileApi([
    'action' => 'resolve',
    'anomaly_type' => 'AMOUNT_MISMATCH',
    'order_id' => $orderId5,
    'payment_id' => $paymentId5,
    'resolution_note' => 'Cross-branch attempt'
], 953, 'POST');

assertTest("Case 5 - Cross-branch resolution attempt returns HTTP 403", $res5_cross['status'] === 403, "Expected 403, got {$res5_cross['status']}");

// 4. Valid Resolution by Authorized Accountant (User 952)
$res5_valid = callReconcileApi([
    'action' => 'resolve',
    'anomaly_type' => 'AMOUNT_MISMATCH',
    'order_id' => $orderId5,
    'payment_id' => $paymentId5,
    'resolution_note' => 'Customer paid ₱500 via GCash and ₱50 balance in cash. Validated with shift supervisor.'
], 952, 'POST');

assertTest("Case 5 - Authorized resolution returns HTTP 200", $res5_valid['status'] === 200, "Expected 200, got {$res5_valid['status']}");

// Verify persistence in reconciliation_logs
$checkLog5 = $pdo->prepare("SELECT * FROM reconciliation_logs WHERE order_id = ? AND anomaly_type = 'AMOUNT_MISMATCH'");
$checkLog5->execute([$orderId5]);
$logRow5 = $checkLog5->fetch(PDO::FETCH_ASSOC);

assertTest("Case 5 - reconciliation_logs row saved", (bool)$logRow5, "Log record not found in database");
assertTest("Case 5 - resolved_by matches user 952", (int)($logRow5['resolved_by'] ?? 0) === 952, "resolved_by is not 952");
assertTest("Case 5 - resolved_at is set to current timestamp", !empty($logRow5['resolved_at']), "resolved_at is empty");
assertTest("Case 5 - resolution_note saved accurately", strpos($logRow5['resolution_note'] ?? '', 'Validated with shift supervisor') !== false, "resolution_note content mismatch");

// Verify anomaly no longer appears in pending list
$res5_after = callReconcileApi([
    'action' => 'list_anomalies',
    'start_date' => $today,
    'end_date' => $today,
    'branch_id' => 951
], 950);
$mismatchesAfter = $res5_after['json']['anomalies']['amount_mismatch'] ?? [];
$found5After = false;
foreach ($mismatchesAfter as $item) {
    if ((int)$item['order_id'] === $orderId5) {
        $found5After = true;
        break;
    }
}
assertTest("Case 5 - Resolved mismatch filtered out from pending anomalies", !$found5After, "Resolved mismatch still appears in list");

// ====================================================================
// Cleanup
// ====================================================================
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM reconciliation_logs WHERE order_id IN (95101, 95102, 95103, 95104, 95105, 95106)");
$pdo->exec("DELETE FROM payments WHERE order_id IN (95101, 95102, 95103, 95104, 95105, 95106) OR id IN (9501, 9502, 9503, 9504, 9505)");
$pdo->exec("DELETE FROM orders WHERE branch_id IN (951, 952) OR id IN (95101, 95102, 95103, 95104, 95105, 95106)");
$pdo->exec("DELETE FROM users WHERE id IN (950, 951, 952, 953)");
$pdo->exec("DELETE FROM branches WHERE id IN (951, 952)");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

echo "\n====================================================================\n";
if ($failed === 0) {
    echo "ALL 5 TEST CASES PASSED SUCCESSFULLY!\n";
    echo "====================================================================\n";
    exit(0);
} else {
    echo "FAILED: {$failed} test(s) failed.\n";
    echo "====================================================================\n";
    exit(1);
}
