<?php
require_once __DIR__ . '/../../app/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    die("Tests must be run from CLI.\n");
}

echo "Running Self-Service Kiosk Ordering & Token Tests...\n\n";
$failed = 0;

function assertTest($name, $condition, $failMsg = '') {
    global $failed;
    if ($condition) {
        echo "[PASS] {$name}\n";
    } else {
        echo "[FAIL] {$name}: {$failMsg}\n";
        $failed++;
    }
}

global $pdo;

// Cleanup previous test fixtures
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM order_kiosk_tokens WHERE order_id IN (SELECT id FROM orders WHERE branch_id IN (981, 982))");
$pdo->exec("DELETE FROM payments WHERE order_id IN (SELECT id FROM orders WHERE branch_id IN (981, 982))");
$pdo->exec("DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE branch_id IN (981, 982))");
$pdo->exec("DELETE FROM orders WHERE branch_id IN (981, 982)");
$pdo->exec("DELETE FROM branch_products WHERE branch_id IN (981, 982) OR product_id IN (SELECT id FROM products WHERE name LIKE 'TEST_KIOSK_%')");
$pdo->exec("DELETE FROM products WHERE name LIKE 'TEST_KIOSK_%'");
$pdo->exec("DELETE FROM devices WHERE branch_id IN (981, 982) OR device_token LIKE 'test_token_%'");
$pdo->exec("DELETE FROM cash_sessions WHERE branch_id IN (981, 982)");
$pdo->exec("DELETE FROM users WHERE id IN (971, 972)");
$pdo->exec("DELETE FROM branches WHERE id IN (981, 982)");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

// Setup Test Branches
$pdo->exec("INSERT INTO branches (id, name) VALUES (981, 'Kiosk Branch Alpha'), (982, 'Kiosk Branch Beta')");

// Setup Products
$pdo->exec("
    INSERT INTO products (id, name, category, price, price_cents, is_active, created_at, updated_at) VALUES 
    (9801, 'TEST_KIOSK_Americano', 'Coffee', 120.00, 12000, 1, NOW(), NOW()),
    (9802, 'TEST_KIOSK_Cheesecake', 'Pastry', 180.00, 18000, 1, NOW(), NOW())
");
$pdo->exec("
    INSERT INTO branch_products (branch_id, product_id, is_active) VALUES 
    (981, 9801, 1),
    (981, 9802, 1),
    (982, 9801, 1),
    (982, 9802, 1)
");

// Setup Test Devices with secure tokens (Constraint 1)
$deviceTokenAlpha = 'test_token_alpha_' . bin2hex(random_bytes(16));
$deviceTokenBeta  = 'test_token_beta_' . bin2hex(random_bytes(16));
$macAlpha = '00:11:22:33:AA:01';
$macBeta  = '00:11:22:33:BB:02';

$pdo->prepare("
    INSERT INTO devices (id, branch_id, device_name, mac_address, device_token, status) 
    VALUES (9811, 981, 'Kiosk Alpha 1', ?, ?, 'Active'),
           (9821, 982, 'Kiosk Beta 1', ?, ?, 'Active')
")->execute([$macAlpha, $deviceTokenAlpha, $macBeta, $deviceTokenBeta]);

// Setup Cashier User & Active Register Session for Branch Alpha
$pdo->exec("INSERT INTO users (id, username, password, role_id, branch_id) VALUES (971, 'cashier_alpha', 'hash', 7, 981)");
$pdo->exec("
    INSERT INTO cash_sessions (id, branch_id, cashier_id, starting_cash, expected_cash, status, opened_at) 
    VALUES (9711, 981, 971, 1000.00, 1000.00, 'OPEN', NOW())
");

// Subshell execution helper for Kiosk APIs
function callKioskApi(string $script, array $payload = [], array $headers = [], ?string $queryString = null) {
    $wrapper = __DIR__ . '/wrapper_kiosk_' . uniqid() . '.php';
    $targetPath = realpath(__DIR__ . '/../../fika_pos/api/' . $script);

    $headerCode = "";
    foreach ($headers as $k => $v) {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $k));
        $headerCode .= "\$_SERVER['{$serverKey}'] = " . var_export($v, true) . ";\n";
    }

    $code = "<?php
    \$_SERVER['REQUEST_METHOD'] = 'POST';
    {$headerCode}
    \$_POST = " . var_export($payload, true) . ";
    ";

    if ($queryString) {
        $code .= "\$_SERVER['QUERY_STRING'] = " . var_export($queryString, true) . ";\n";
        $code .= "parse_str(\$_SERVER['QUERY_STRING'], \$_GET);\n";
    }

    $code .= "
    register_shutdown_function(function() {
        echo \"\\n---STATUS:\" . http_response_code() . \"---\\n\";
    });

    stream_wrapper_unregister('php');
    stream_wrapper_register('php', 'KioskMockStream');
    class KioskMockStream {
        public \$context;
        private \$pos = 0;
        private \$data = '';
        public function stream_open(\$path, \$mode, \$options, &\$opened) {
            \$this->data = base64_decode('" . base64_encode(json_encode($payload)) . "');
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
        http_response_code(400);
        echo json_encode(['error' => \$t->getMessage()]);
    }
    ";

    file_put_contents($wrapper, $code);
    $raw = shell_exec(PHP_BINARY . ' ' . escapeshellarg($wrapper));
    if (file_exists($wrapper)) {
        unlink($wrapper);
    }

    $status = 200;
    if (preg_match('/---STATUS:(\d+)---/', $raw, $m)) {
        $status = (int)$m[1];
    }
    $cleanRaw = preg_replace('/---STATUS:\d+---/', '', $raw);
    $cleanRaw = trim($cleanRaw);

    $json = null;
    if (preg_match('/\{[\s\S]*\}/', $cleanRaw, $mJson)) {
        $json = json_decode($mJson[0], true);
    }

    return [
        'status' => $status,
        'body' => $json,
        'raw' => $cleanRaw
    ];
}

// Subshell execution helper for Cashier APIs (with session and CSRF)
function callCashierApi(string $script, array $payload, int $userId, int $sessionId) {
    global $pdo;

    $token = bin2hex(random_bytes(32));
    $payload['csrf_token'] = $token;

    $uStmt = $pdo->prepare("SELECT id, username, role_id, branch_id FROM users WHERE id = ?");
    $uStmt->execute([$userId]);
    $user = $uStmt->fetch();

    $wrapper = __DIR__ . '/wrapper_cashier_' . uniqid() . '.php';
    $targetPath = realpath(__DIR__ . '/../../fika_pos/api/' . $script);

    $code = "<?php
    session_start();
    \$_SESSION['csrf_token'] = " . var_export($token, true) . ";
    \$_SESSION['user'] = " . var_export($user, true) . ";
    \$_SESSION['pos_session_id'] = {$sessionId};
    \$_SERVER['REQUEST_METHOD'] = 'POST';
    \$_SERVER['HTTP_X_CSRF_TOKEN'] = " . var_export($token, true) . ";
    \$_POST = " . var_export($payload, true) . ";

    register_shutdown_function(function() {
        echo \"\\n---STATUS:\" . http_response_code() . \"---\\n\";
    });

    stream_wrapper_unregister('php');
    stream_wrapper_register('php', 'CashierMockStream');
    class CashierMockStream {
        public \$context;
        private \$pos = 0;
        private \$data = '';
        public function stream_open(\$path, \$mode, \$options, &\$opened) {
            \$this->data = base64_decode('" . base64_encode(json_encode($payload)) . "');
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
        http_response_code(400);
        echo json_encode(['error' => \$t->getMessage()]);
    }
    ";

    file_put_contents($wrapper, $code);
    $raw = shell_exec(PHP_BINARY . ' ' . escapeshellarg($wrapper));
    if (file_exists($wrapper)) {
        unlink($wrapper);
    }

    $status = 200;
    if (preg_match('/---STATUS:(\d+)---/', $raw, $m)) {
        $status = (int)$m[1];
    }
    $cleanRaw = preg_replace('/---STATUS:\d+---/', '', $raw);
    $cleanRaw = trim($cleanRaw);

    $json = null;
    if (preg_match('/\{[\s\S]*\}/', $cleanRaw, $mJson)) {
        $json = json_decode($mJson[0], true);
    }

    return [
        'status' => $status,
        'body' => $json,
        'raw' => $cleanRaw
    ];
}

try {
    // =========================================================================
    // 1. DEVICE AUTHENTICATION TESTS (Constraints 1 & 4)
    // =========================================================================

    // Case 1: Unauthenticated request (no X-Device-Token) -> 401
    $resNoAuth = callKioskApi('fika_pos_kiosk_order.php', [
        'items' => [['id' => 9801, 'qty' => 1]]
    ]);
    assertTest(
        "Unauthenticated call (no device token) gets 401",
        $resNoAuth['status'] === 401,
        "Status: {$resNoAuth['status']}"
    );

    // Case 2: Spoofed MAC address with no token gets 401 (Constraint 4)
    $resSpoofedMac = callKioskApi('fika_pos_kiosk_order.php', [
        'mac_address' => $macAlpha,
        'mac' => $macAlpha,
        'items' => [['id' => 9801, 'qty' => 1]]
    ], [
        'X-Mac-Address' => $macAlpha
    ]);
    assertTest(
        "Constraint 4: Spoofed MAC address with no device token gets 401",
        $resSpoofedMac['status'] === 401,
        "Status: {$resSpoofedMac['status']}"
    );

    // Case 3: Invalid random device token gets 401
    $resBadToken = callKioskApi('fika_pos_kiosk_order.php', [
        'items' => [['id' => 9801, 'qty' => 1]]
    ], [
        'X-Device-Token' => 'invalid_token_1234567890'
    ]);
    assertTest(
        "Invalid device token gets 401",
        $resBadToken['status'] === 401,
        "Status: {$resBadToken['status']}"
    );

    // =========================================================================
    // 2. KIOSK ORDER CREATION & TAMPERING PREVENTION (Constraint 2)
    // =========================================================================

    // Case 4: Kiosk creates order with valid device token
    // Client attempts to tamper with branch_id = 982, unit price = 5.00, total = 5.00
    $tamperedPayload = [
        'branch_id' => 982, // Spoofed branch (device belongs to 981)
        'total' => '5.00',
        'final_amount' => '5.00',
        'items' => [
            ['id' => 9801, 'qty' => 2, 'price' => '1.00'], // 2x 120 = 240
            ['id' => 9802, 'qty' => 1, 'price' => '3.00']  // 1x 180 = 180 (Total = 420.00)
        ]
    ];
    $resOrder1 = callKioskApi('fika_pos_kiosk_order.php', $tamperedPayload, [
        'X-Device-Token' => $deviceTokenAlpha
    ]);

    $order1Id = (int)($resOrder1['body']['order_id'] ?? 0);
    $order1Token = $resOrder1['body']['kiosk_token'] ?? '';

    assertTest(
        "Kiosk order created successfully with valid device token",
        $resOrder1['status'] === 200 && $order1Id > 0 && !empty($order1Token),
        "Status: {$resOrder1['status']}, Raw: {$resOrder1['raw']}"
    );

    // Verify DB integrity of created order
    $dbOrd1 = $pdo->query("SELECT * FROM orders WHERE id = {$order1Id}")->fetch();
    
    assertTest(
        "Client-sent branch_id is ignored (derived strictly from registered device: 981)",
        (int)$dbOrd1['branch_id'] === 981,
        "DB branch_id: {$dbOrd1['branch_id']} vs Expected: 981"
    );

    assertTest(
        "Client-sent totals & prices ignored (server computed 420.00 from DB prices)",
        (float)$dbOrd1['final_amount'] === 420.00 && (float)$dbOrd1['total_amount'] === 420.00,
        "DB final_amount: {$dbOrd1['final_amount']} vs Expected: 420.00"
    );

    // Constraint 2: Order-Shift Separation
    assertTest(
        "Constraint 2: Kiosk order has cashier_id = NULL and pos_session_id = NULL",
        $dbOrd1['cashier_id'] === null && $dbOrd1['pos_session_id'] === null,
        "cashier_id: " . var_export($dbOrd1['cashier_id'], true) . ", pos_session_id: " . var_export($dbOrd1['pos_session_id'], true)
    );

    assertTest(
        "Kiosk order status is PENDING and payment_status is UNPAID",
        $dbOrd1['order_status'] === 'PENDING' && $dbOrd1['payment_status'] === 'UNPAID',
        "Status: {$dbOrd1['order_status']}, Payment: {$dbOrd1['payment_status']}"
    );

    // Verify Token Hash in order_kiosk_tokens
    $dbTok1 = $pdo->query("SELECT * FROM order_kiosk_tokens WHERE order_id = {$order1Id}")->fetch();
    assertTest(
        "Order-bound token SHA-256 hash stored in order_kiosk_tokens",
        $dbTok1 && hash_equals($dbTok1['token_hash'], hash('sha256', $order1Token)),
        "Token hash mismatch or not found"
    );

    // Create Order 2 on Branch Beta
    $resOrder2 = callKioskApi('fika_pos_kiosk_order.php', [
        'items' => [['id' => 9801, 'qty' => 1]] // 120.00
    ], [
        'X-Device-Token' => $deviceTokenBeta
    ]);
    $order2Id = (int)($resOrder2['body']['order_id'] ?? 0);
    $order2Token = $resOrder2['body']['kiosk_token'] ?? '';

    assertTest(
        "Kiosk Order 2 created on Branch Beta (982)",
        $resOrder2['status'] === 200 && $order2Id > 0 && !empty($order2Token),
        "Status: {$resOrder2['status']}, Raw: {$resOrder2['raw']}"
    );

    // =========================================================================
    // 3. BRANCH & TOKEN ISOLATION TESTS
    // =========================================================================

    // Case 5: Kiosk of Branch A cannot read status of an order created in Branch B -> 403
    $resCrossBranch = callKioskApi('fika_pos_kiosk_status.php', [], [
        'X-Kiosk-Token' => $order2Token,
        'X-Device-Token' => $deviceTokenAlpha // Kiosk Alpha trying to check Order 2 (Branch Beta)
    ], "order_id={$order2Id}");
    assertTest(
        "Kiosk of branch A cannot read orders of branch B -> 403",
        $resCrossBranch['status'] === 403,
        "Status: {$resCrossBranch['status']}, Body: " . json_encode($resCrossBranch['body'])
    );

    // Case 6: Token of Order 1 cannot read Order 2 -> 403
    $resCrossToken = callKioskApi('fika_pos_kiosk_status.php', [], [
        'X-Kiosk-Token' => $order1Token, // Order 1's token
        'X-Device-Token' => $deviceTokenBeta
    ], "order_id={$order2Id}"); // Checking Order 2
    assertTest(
        "Token of order 1 cannot read order 2 -> 403",
        $resCrossToken['status'] === 403,
        "Status: {$resCrossToken['status']}, Body: " . json_encode($resCrossToken['body'])
    );

    // Case 7: Valid token polls order status successfully (Pending / Unpaid)
    $resStatusValid = callKioskApi('fika_pos_kiosk_status.php', [], [
        'X-Kiosk-Token' => $order1Token,
        'X-Device-Token' => $deviceTokenAlpha
    ], "order_id={$order1Id}");
    assertTest(
        "Valid order token reads status successfully (is_paid = false)",
        $resStatusValid['status'] === 200 && isset($resStatusValid['body']['is_paid']) && $resStatusValid['body']['is_paid'] === false,
        "Status: {$resStatusValid['status']}, Body: " . json_encode($resStatusValid['body'])
    );

    // Case 8: Expired token (> 30 min) is rejected -> 403
    $pdo->prepare("UPDATE order_kiosk_tokens SET expires_at = (NOW() - INTERVAL 5 MINUTE) WHERE order_id = ?")->execute([$order1Id]);
    $resExpired = callKioskApi('fika_pos_kiosk_status.php', [], [
        'X-Kiosk-Token' => $order1Token,
        'X-Device-Token' => $deviceTokenAlpha
    ], "order_id={$order1Id}");
    assertTest(
        "Expired token (> 30 min) is rejected with 403",
        $resExpired['status'] === 403,
        "Status: {$resExpired['status']}, Body: " . json_encode($resExpired['body'])
    );

    // Restore token expiration for subsequent verification
    $pdo->prepare("UPDATE order_kiosk_tokens SET expires_at = (NOW() + INTERVAL 30 MINUTE) WHERE order_id = ?")->execute([$order1Id]);

    // =========================================================================
    // 4. CASHIER PAYMENT & ORDER-SHIFT BINDING (Constraint 2)
    // =========================================================================

    // Case 9: Cashier processes payment for Kiosk Order 1
    $payPayload = [
        'order_id' => $order1Id,
        'amount_paid' => '500.00',
        'idempotency_key' => 'TEST_PAY_' . $order1Id
    ];
    $resPay = callCashierApi('fika_pos_order_pay_cash.php', $payPayload, 971, 9711);

    assertTest(
        "Cashier processes cash payment for kiosk order (Success, change: 80.00)",
        $resPay['status'] === 200 && isset($resPay['body']['change']) && (float)$resPay['body']['change'] === 80.00,
        "Status: {$resPay['status']}, Raw: {$resPay['raw']}"
    );

    // Verify DB binding (Constraint 2)
    $dbOrd1Paid = $pdo->query("SELECT * FROM orders WHERE id = {$order1Id}")->fetch();
    assertTest(
        "Constraint 2: Payment binds cashier_id (971) and active pos_session_id (9711)",
        (int)$dbOrd1Paid['cashier_id'] === 971 && (int)$dbOrd1Paid['pos_session_id'] === 9711,
        "cashier_id: {$dbOrd1Paid['cashier_id']}, pos_session_id: {$dbOrd1Paid['pos_session_id']}"
    );

    assertTest(
        "Order status updated to PAID and payment_status to PAID",
        $dbOrd1Paid['order_status'] === 'PAID' && $dbOrd1Paid['payment_status'] === 'PAID',
        "Status: {$dbOrd1Paid['order_status']}, Payment: {$dbOrd1Paid['payment_status']}"
    );

    // Case 10: Kiosk polling after cashier payment returns is_paid = true
    $resStatusPaid = callKioskApi('fika_pos_kiosk_status.php', [], [
        'X-Kiosk-Token' => $order1Token,
        'X-Device-Token' => $deviceTokenAlpha
    ], "order_id={$order1Id}");
    assertTest(
        "Kiosk status polling reflects is_paid = true after cashier payment",
        $resStatusPaid['status'] === 200 && !empty($resStatusPaid['body']['is_paid']),
        "Status: {$resStatusPaid['status']}, Body: " . json_encode($resStatusPaid['body'])
    );

    // =========================================================================
    // 5. AGE LIMIT SETTINGS & DASHBOARD FILTER VERIFICATION (Constraint 3)
    // =========================================================================

    // Case 11: Setting seeded in settings table via migration
    $stmtSet = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'kiosk_order_age_limit_minutes'");
    $settingVal = $stmtSet->fetchColumn();
    assertTest(
        "Constraint 3: kiosk_order_age_limit_minutes setting seeded (default 60)",
        $settingVal !== false && (int)$settingVal === 60,
        "Setting value: " . var_export($settingVal, true)
    );

    // Case 12: Age filtering query logic
    // Create an artificial aged order (created 90 minutes ago)
    $pdo->prepare("
        INSERT INTO orders (order_number, branch_id, pos_session_id, cashier_id, order_status, payment_status, total_amount, final_amount, created_at, updated_at) 
        VALUES ('TEST-AGED-001', 981, NULL, NULL, 'PENDING', 'UNPAID', 150.00, 150.00, (NOW() - INTERVAL 90 MINUTE), NOW())
    ")->execute();
    $agedOrderId = (int)$pdo->lastInsertId();

    // Query active vs aged
    $ageThreshold = (int)$settingVal;
    $activeStmt = $pdo->prepare("
        SELECT id, TIMESTAMPDIFF(MINUTE, created_at, NOW()) AS age_minutes 
        FROM orders 
        WHERE branch_id = 981 AND payment_status = 'UNPAID' AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) <= ?
    ");
    $activeStmt->execute([$ageThreshold]);
    $activeRows = $activeStmt->fetchAll();

    $agedStmt = $pdo->prepare("
        SELECT id, TIMESTAMPDIFF(MINUTE, created_at, NOW()) AS age_minutes 
        FROM orders 
        WHERE branch_id = 981 AND payment_status = 'UNPAID' AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) > ?
    ");
    $agedStmt->execute([$ageThreshold]);
    $agedRows = $agedStmt->fetchAll();

    $agedIds = array_column($agedRows, 'id');
    assertTest(
        "Dashboard age query correctly places 90-min-old order into aged list (> 60 mins)",
        in_array($agedOrderId, $agedIds),
        "Aged IDs: " . implode(',', $agedIds)
    );

    assertTest(
        "Dashboard age query excludes aged order from active list (<= 60 mins)",
        !in_array($agedOrderId, array_column($activeRows, 'id')),
        "Active rows contained aged order!"
    );

} finally {
    // Teardown test records
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("DELETE FROM order_kiosk_tokens WHERE order_id IN (SELECT id FROM orders WHERE branch_id IN (981, 982))");
    $pdo->exec("DELETE FROM payments WHERE order_id IN (SELECT id FROM orders WHERE branch_id IN (981, 982))");
    $pdo->exec("DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE branch_id IN (981, 982))");
    $pdo->exec("DELETE FROM orders WHERE branch_id IN (981, 982)");
    $pdo->exec("DELETE FROM branch_products WHERE branch_id IN (981, 982) OR product_id IN (9801, 9802)");
    $pdo->exec("DELETE FROM products WHERE id IN (9801, 9802)");
    $pdo->exec("DELETE FROM devices WHERE branch_id IN (981, 982) OR device_token LIKE 'test_token_%'");
    $pdo->exec("DELETE FROM cash_sessions WHERE branch_id IN (981, 982)");
    $pdo->exec("DELETE FROM users WHERE id IN (971, 972)");
    $pdo->exec("DELETE FROM branches WHERE id IN (981, 982)");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
}

echo "\n=======================================================\n";
if ($failed === 0) {
    echo "ALL KIOSK TESTS PASSED!\n";
    exit(0);
} else {
    echo "{$failed} TEST(S) FAILED.\n";
    exit(1);
}
