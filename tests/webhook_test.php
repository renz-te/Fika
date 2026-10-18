<?php
/**
 * Webhook Test Harness for PayMongo Integration
 * Sub-Phase 4.0: Automated test suite for PayMongo webhook signature, idempotency,
 * amount integrity, failure handling, and inventory deductions.
 *
 * Payload Shape: Based on PayMongo API v1 Event Resource documentation
 * (type: payment.paid, payment.failed with inner payment resource data).
 */

declare(strict_types=1);

require_once __DIR__ . '/../cafe_pos/database.php';

// 1. Configuration & Environment
$baseUrl = getenv('FIKA_BASE_URL') ?: 'http://localhost/Fika/Fika';
$webhookUrl = rtrim($baseUrl, '/') . '/cafe_pos/api/payment_webhook.php';

if (!class_exists('Config')) {
    class Config {
        public static function get(string $key, mixed $default = null): mixed {
            $envFile = dirname(__DIR__) . '/.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#')) continue;
                    if (str_contains($line, '=')) {
                        [$k, $v] = explode('=', $line, 2);
                        $k = trim($k);
                        $v = trim($v, " \t\n\r\0\x0B\"'");
                        putenv("{$k}={$v}");
                        $_ENV[$k] = $v;
                        $_SERVER[$k] = $v;
                    }
                }
            }
            [$file, $item] = array_pad(explode('.', $key, 2), 2, null);
            $path = dirname(__DIR__) . '/config/' . $file . '.php';
            $config = file_exists($path) ? require $path : [];
            if ($item === null) return $config ?? $default;
            return $config[$item] ?? $default;
        }
    }
}

$webhookSecret = Config::get('paymongo.webhook_secret') ?: getenv('PAYMONGO_WEBHOOK_SECRET') ?: 'whk_test_dummy';

// 2. Global State & Tracking
$failures = 0;
$createdProductIds = [];
$createdInventoryIds = [];
$createdOrderIds = [];
$createdSessionIds = [];
$fixturesDir = __DIR__ . '/fixtures';

echo "====================================================================\n";
echo "PayMongo Webhook Test Suite (Sub-Phase 4.0)\n";
echo "Target Endpoint : {$webhookUrl}\n";
echo "Payload Schema  : PayMongo API v1 Event Resource (payment.paid / payment.failed)\n";
echo "====================================================================\n\n";

/**
 * Compute the Paymongo-Signature header value.
 * Format: "t=<timestamp>,te=<hmac_sha256(timestamp + '.' + body, secret)>"
 */
function computeSignature(string $rawBody, string $secret, ?int $timestamp = null): string {
    $ts = $timestamp ?? time();
    $hash = hash_hmac('sha256', $ts . '.' . $rawBody, $secret);
    return "t={$ts},te={$hash}";
}

/**
 * Perform HTTP POST request to webhook endpoint.
 */
function sendWebhook(string $url, string $rawBody, ?string $sigHeader): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
    $headers = ['Content-Type: application/json'];
    if ($sigHeader !== null) {
        $headers[] = 'Paymongo-Signature: ' . $sigHeader;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => $response,
        'json' => json_decode((string)$response, true),
        'error' => $curlError
    ];
}

/**
 * Perform concurrent HTTP POST requests to webhook endpoint using curl_multi.
 */
function sendConcurrentWebhooks(string $url, string $rawBody, string $sigHeader): array {
    $mh = curl_multi_init();
    $handles = [];
    for ($i = 0; $i < 2; $i++) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Paymongo-Signature: ' . $sigHeader
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_multi_add_handle($mh, $ch);
        $handles[] = $ch;
    }

    $running = null;
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running > 0) {
            curl_multi_select($mh, 0.05);
        }
    } while ($running > 0 && $status === CURLM_OK);

    $results = [];
    foreach ($handles as $ch) {
        $content = curl_multi_getcontent($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $results[] = [
            'code' => $httpCode,
            'body' => (string)$content,
            'json' => json_decode((string)$content, true)
        ];
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $results;
}

/**
 * Load fixture and replace template placeholders.
 */
function loadFixture(string $fixturesDir, string $fixtureName, int $orderId, string $intentId, int $shiftId): string {
    $path = $fixturesDir . '/' . $fixtureName;
    if (!file_exists($path)) {
        throw new RuntimeException("Fixture not found: {$path}");
    }
    $raw = file_get_contents($path);
    return str_replace(
        ['{{ORDER_ID}}', '{{INTENT_ID}}', '{{SHIFT_ID}}'],
        [(string)$orderId, $intentId, (string)$shiftId],
        $raw
    );
}

/**
 * Perform generic HTTP request.
 */
function sendHttpRequest(string $url, string $method = 'GET', array|string $postFields = [], array $headers = [], ?string $cookie = null): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    }
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    if ($cookie !== null) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => (string)$response,
        'json' => json_decode((string)$response, true),
        'error' => $curlError
    ];
}

$createdSessionFiles = [];

/**
 * Create a session in PHP session save path with given user payload.
 */
function createSessionWithUser(?array $userData): string {
    global $createdSessionFiles;
    $sessId = bin2hex(random_bytes(16));
    $savePath = session_save_path() ?: sys_get_temp_dir();
    $sessFile = rtrim($savePath, '/\\') . '/sess_' . $sessId;
    if ($userData !== null) {
        file_put_contents($sessFile, 'user|' . serialize($userData));
    } else {
        file_put_contents($sessFile, '');
    }
    $createdSessionFiles[] = $sessFile;
    return $sessId;
}

/**
 * Record test result.
 */
function recordResult(int $caseNum, string $description, bool $passed, bool $expectedFail = false, ?array $debug = null): bool {
    global $failures;
    if ($passed) {
        echo "PASS [Case {$caseNum}]: {$description}\n";
        return true;
    }

    if ($expectedFail) {
        echo "EXPECTED-FAIL [Case {$caseNum}]: {$description} (Pending remediation in subsequent sub-phase)\n";
        return false;
    }

    echo "FAIL [Case {$caseNum}]: {$description}\n";
    if ($debug) {
        echo "      Debug: " . json_encode($debug) . "\n";
    }
    $failures++;
    return false;
}

$envFilePath = dirname(__DIR__) . '/.env';

$setTestMode = function(?bool $isTest) use ($envFilePath) {
    $current = file_exists($envFilePath) ? file_get_contents($envFilePath) : '';
    $lines = explode("\n", $current);
    $filtered = array_values(array_filter($lines, fn($l) => !str_starts_with(trim($l), 'PAYMONGO_IS_TEST=')));
    if ($isTest !== null) {
        $filtered[] = 'PAYMONGO_IS_TEST=' . ($isTest ? 'true' : 'false');
    }
    file_put_contents($envFilePath, rtrim(implode("\n", $filtered)) . "\n");
    if ($isTest !== null) {
        putenv('PAYMONGO_IS_TEST=' . ($isTest ? 'true' : 'false'));
        $_ENV['PAYMONGO_IS_TEST'] = ($isTest ? 'true' : 'false');
        $_SERVER['PAYMONGO_IS_TEST'] = ($isTest ? 'true' : 'false');
    } else {
        putenv('PAYMONGO_IS_TEST');
        unset($_ENV['PAYMONGO_IS_TEST'], $_SERVER['PAYMONGO_IS_TEST']);
    }
};

try {
    // 3. Setup: Test Session, Inventory Item, Stock, Product & Recipe
    $testShiftId = 99100 + random_int(100, 999);
    $stmtSession = $pdo->prepare("INSERT INTO pos_sessions (id, branch_id, cashier_id, status, opening_float) VALUES (?, 1, 1, 'OPEN', 1000.00)");
    $stmtSession->execute([$testShiftId]);
    $createdSessionIds[] = $testShiftId;

    $stmtInv = $pdo->prepare("INSERT INTO inventory (item_name, unit, unit_cost) VALUES (?, 'g', 0.50)");
    $stmtInv->execute(['WHK-TEST-BEANS-' . uniqid()]);
    $testInvId = (int)$pdo->lastInsertId();
    $createdInventoryIds[] = $testInvId;

    $stmtStock = $pdo->prepare("INSERT INTO inventory_stock (inventory_id, branch_id, stock, low_stock_level) VALUES (?, 1, 1000.00, 100.00)");
    $stmtStock->execute([$testInvId]);

    $stmtProd = $pdo->prepare("INSERT INTO products (name, category, price, is_active) VALUES (?, 'Coffee', 150.00, 1)");
    $stmtProd->execute(['WHK-TEST-LATTE-' . uniqid()]);
    $testProdId = (int)$pdo->lastInsertId();
    $createdProductIds[] = $testProdId;

    $stmtRecipe = $pdo->prepare("INSERT INTO recipes (product_id, inventory_id, quantity) VALUES (?, ?, 20.00)");
    $stmtRecipe->execute([$testProdId, $testInvId]);

    /**
     * Helper to seed a test order with item and pending payment.
     */
    $createTestOrder = function(string $intentId, float $totalPrice = 150.00) use ($pdo, $testShiftId, $testProdId, &$createdOrderIds): int {
        $orderNum = 'WHK-' . random_int(100000, 999999);
        $stmtOrder = $pdo->prepare("
            INSERT INTO orders (
                branch_id, pos_session_id, order_number, table_source, 
                total_price, payment_status, payment_method, is_test
            ) VALUES (1, ?, ?, 'Table 1', ?, 'UNPAID', 'GCASH', 0)
        ");
        $stmtOrder->execute([$testShiftId, $orderNum, $totalPrice]);
        $orderId = (int)$pdo->lastInsertId();
        $createdOrderIds[] = $orderId;

        $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, subtotal) VALUES (?, ?, 1, ?)");
        $stmtItem->execute([$orderId, $testProdId, $totalPrice]);

        $stmtPay = $pdo->prepare("
            INSERT INTO payments (
                order_id, shift_id, intent_id, method, gross_amount, fee_amount, net_amount, status, created_at
            ) VALUES (?, ?, ?, 'gcash', ?, 3.75, ? - 3.75, 'pending', NOW())
        ");
        $stmtPay->execute([$orderId, $testShiftId, $intentId, $totalPrice, $totalPrice]);

        return $orderId;
    };

    // -------------------------------------------------------------------------
    // CASE 1: Valid payment.paid -> 200, order PAID, payments.status=paid
    // -------------------------------------------------------------------------
    $intentCase1 = 'pi_whk_c1_' . uniqid();
    $orderCase1 = $createTestOrder($intentCase1, 150.00);
    $payloadCase1 = loadFixture($fixturesDir, 'payment_paid.json', $orderCase1, $intentCase1, $testShiftId);
    $sigCase1 = computeSignature($payloadCase1, $webhookSecret);

    $resCase1 = sendWebhook($webhookUrl, $payloadCase1, $sigCase1);

    $stmtCheckOrder1 = $pdo->prepare("SELECT payment_status FROM orders WHERE id = ?");
    $stmtCheckOrder1->execute([$orderCase1]);
    $orderStatusCase1 = $stmtCheckOrder1->fetchColumn();

    $stmtCheckPay1 = $pdo->prepare("SELECT status FROM payments WHERE intent_id = ?");
    $stmtCheckPay1->execute([$intentCase1]);
    $payStatusCase1 = $stmtCheckPay1->fetchColumn();

    $stmtCheckStock1 = $pdo->prepare("SELECT stock FROM inventory_stock WHERE inventory_id = ? AND branch_id = 1");
    $stmtCheckStock1->execute([$testInvId]);
    $stockAfterCase1 = (float)$stmtCheckStock1->fetchColumn();

    $case1Pass = ($resCase1['code'] === 200) && ($orderStatusCase1 === 'PAID') && ($payStatusCase1 === 'paid');
    recordResult(1, "valid payment.paid -> 200, order PAID, payments.status=paid", $case1Pass);

    // -------------------------------------------------------------------------
    // CASE 2: Same payload replayed -> 200 noop, inventory_stock unchanged vs after case 1
    // -------------------------------------------------------------------------
    $resCase2 = sendWebhook($webhookUrl, $payloadCase1, $sigCase1);

    $stmtCheckStock2 = $pdo->prepare("SELECT stock FROM inventory_stock WHERE inventory_id = ? AND branch_id = 1");
    $stmtCheckStock2->execute([$testInvId]);
    $stockAfterCase2 = (float)$stmtCheckStock2->fetchColumn();

    $isNoop = ($resCase2['code'] === 200) && (isset($resCase2['json']['status']) && $resCase2['json']['status'] === 'noop');
    $stockUnchanged = ($stockAfterCase2 === $stockAfterCase1);
    $case2Pass = $isNoop && $stockUnchanged;
    recordResult(2, "same payload replayed -> 200 noop, inventory_stock unchanged vs after case 1", $case2Pass);

    // -------------------------------------------------------------------------
    // CASE 3: payment.failed -> order NOT paid, payments.status=failed
    // -------------------------------------------------------------------------
    $intentCase3 = 'pi_whk_c3_' . uniqid();
    $orderCase3 = $createTestOrder($intentCase3, 150.00);
    $payloadCase3 = loadFixture($fixturesDir, 'payment_failed.json', $orderCase3, $intentCase3, $testShiftId);
    $sigCase3 = computeSignature($payloadCase3, $webhookSecret);

    $resCase3 = sendWebhook($webhookUrl, $payloadCase3, $sigCase3);

    $stmtCheckOrder3 = $pdo->prepare("SELECT payment_status FROM orders WHERE id = ?");
    $stmtCheckOrder3->execute([$orderCase3]);
    $orderStatusCase3 = $stmtCheckOrder3->fetchColumn();

    $stmtCheckPay3 = $pdo->prepare("SELECT status FROM payments WHERE intent_id = ?");
    $stmtCheckPay3->execute([$intentCase3]);
    $payStatusCase3 = $stmtCheckPay3->fetchColumn();

    $case3Pass = ($orderStatusCase3 !== 'PAID') && ($payStatusCase3 === 'failed');
    recordResult(3, "payment.failed -> order NOT paid, payments.status=failed", $case3Pass, true);

    // -------------------------------------------------------------------------
    // CASE 4: Bad signature -> 401
    // -------------------------------------------------------------------------
    $intentCase4 = 'pi_whk_c4_' . uniqid();
    $orderCase4 = $createTestOrder($intentCase4, 150.00);
    $payloadCase4 = loadFixture($fixturesDir, 'payment_paid.json', $orderCase4, $intentCase4, $testShiftId);
    $badSig = "t=" . time() . ",te=invalid_signature_hash_000000";

    $resCase4 = sendWebhook($webhookUrl, $payloadCase4, $badSig);

    $stmtCheckOrder4 = $pdo->prepare("SELECT payment_status FROM orders WHERE id = ?");
    $stmtCheckOrder4->execute([$orderCase4]);
    $orderStatusCase4 = $stmtCheckOrder4->fetchColumn();

    $case4Pass = ($resCase4['code'] === 401) && ($orderStatusCase4 !== 'PAID');
    recordResult(4, "bad signature -> 401", $case4Pass);

    // -------------------------------------------------------------------------
    // CASE 5: Amount mismatch -> 400, order not paid, PAYMENT_MISMATCH audit row
    // -------------------------------------------------------------------------
    $intentCase5 = 'pi_whk_c5_' . uniqid();
    $orderCase5 = $createTestOrder($intentCase5, 150.00);
    $payloadCase5 = loadFixture($fixturesDir, 'paid_amount_mismatch.json', $orderCase5, $intentCase5, $testShiftId);
    $sigCase5 = computeSignature($payloadCase5, $webhookSecret);

    $resCase5 = sendWebhook($webhookUrl, $payloadCase5, $sigCase5);

    $stmtCheckOrder5 = $pdo->prepare("SELECT payment_status FROM orders WHERE id = ?");
    $stmtCheckOrder5->execute([$orderCase5]);
    $orderStatusCase5 = $stmtCheckOrder5->fetchColumn();

    $stmtCheckAudit5 = $pdo->prepare("SELECT COUNT(*) FROM order_audit_logs WHERE order_id = ? AND action = 'PAYMENT_MISMATCH'");
    $stmtCheckAudit5->execute([$orderCase5]);
    $auditRowCount = (int)$stmtCheckAudit5->fetchColumn();

    $case5Pass = ($resCase5['code'] === 400) && ($orderStatusCase5 !== 'PAID') && ($auditRowCount > 0);
    recordResult(5, "amount mismatch -> 400, order not paid, PAYMENT_MISMATCH audit row", $case5Pass);

    // -------------------------------------------------------------------------
    // CASE 6: Missing amount -> rejected, order not paid
    // -------------------------------------------------------------------------
    $intentCase6 = 'pi_whk_c6_' . uniqid();
    $orderCase6 = $createTestOrder($intentCase6, 150.00);
    $payloadCase6 = loadFixture($fixturesDir, 'paid_no_amount.json', $orderCase6, $intentCase6, $testShiftId);
    $sigCase6 = computeSignature($payloadCase6, $webhookSecret);

    $resCase6 = sendWebhook($webhookUrl, $payloadCase6, $sigCase6);

    $stmtCheckOrder6 = $pdo->prepare("SELECT payment_status FROM orders WHERE id = ?");
    $stmtCheckOrder6->execute([$orderCase6]);
    $orderStatusCase6 = $stmtCheckOrder6->fetchColumn();

    $case6Pass = ($resCase6['code'] >= 400) && ($orderStatusCase6 !== 'PAID');
    recordResult(6, "missing amount -> rejected, order not paid", $case6Pass, true);

    // =========================================================================
    // SUB-PHASE H1: Fake-Payment Hole Security Tests
    // =========================================================================

    // -------------------------------------------------------------------------
    // CASE 7: is_test=false -> GET to mock URL returns 404 (with & without cashier session)
    // -------------------------------------------------------------------------
    $setTestMode(false);
    $intentCase7 = 'pi_whk_c7_' . uniqid();
    $orderCase7 = $createTestOrder($intentCase7, 150.00);
    $mockUrlCase7 = rtrim($baseUrl, '/') . "/cafe_pos/kiosk.php?payment=mock_checkout&order_id={$orderCase7}&intent_id={$intentCase7}&method=gcash";

    $resCase7Unauth = sendHttpRequest($mockUrlCase7, 'GET');

    $cashierSessionId7 = createSessionWithUser([
        'id' => 1,
        'username' => 'cashier_test',
        'name' => 'Cashier Test User',
        'role' => 'Cashier',
        'branch_id' => 1
    ]);
    $cashierCookie7 = "PHPSESSID={$cashierSessionId7}";
    $resCase7Cashier = sendHttpRequest($mockUrlCase7, 'GET', [], [], $cashierCookie7);

    $case7Pass = ($resCase7Unauth['code'] === 404) && ($resCase7Cashier['code'] === 404);
    recordResult(7, "is_test=false -> GET to mock URL returns 404 (unauthenticated & cashier session)", $case7Pass);

    // -------------------------------------------------------------------------
    // CASE 8: is_test=false -> POST to mock URL returns 404, order remains UNPAID
    // -------------------------------------------------------------------------
    $resCase8Unauth = sendHttpRequest($mockUrlCase7, 'POST', ['authorize_mock_payment' => '1']);
    $resCase8Cashier = sendHttpRequest($mockUrlCase7, 'POST', ['authorize_mock_payment' => '1'], [], $cashierCookie7);

    $stmtCheckOrder8 = $pdo->prepare("SELECT payment_status FROM orders WHERE id = ?");
    $stmtCheckOrder8->execute([$orderCase7]);
    $orderStatusCase8 = $stmtCheckOrder8->fetchColumn();

    $case8Pass = ($resCase8Unauth['code'] === 404) && ($resCase8Cashier['code'] === 404) && ($orderStatusCase8 === 'UNPAID');
    recordResult(8, "is_test=false -> POST to mock URL returns 404, order remains UNPAID", $case8Pass);

    // -------------------------------------------------------------------------
    // CASE 9: is_test=false -> Webhook carrying mock ID rejected (400) + audit row
    // -------------------------------------------------------------------------
    $intentCase9 = 'cs_test_mock_intent_' . uniqid();
    $orderCase9 = $createTestOrder($intentCase9, 150.00);
    $mockEventId = 'evt_mock_' . bin2hex(random_bytes(12));
    $mockPayId = 'pay_mock_' . bin2hex(random_bytes(12));
    $mockSrcId = 'src_mock_' . bin2hex(random_bytes(10));

    $payloadCase9 = json_encode([
        'data' => [
            'id' => $mockEventId,
            'type' => 'event',
            'attributes' => [
                'type' => 'payment.paid',
                'livemode' => false,
                'data' => [
                    'id' => $mockPayId,
                    'type' => 'payment',
                    'attributes' => [
                        'amount' => 15000,
                        'fee' => 0,
                        'net_amount' => 15000,
                        'currency' => 'PHP',
                        'status' => 'paid',
                        'source' => [
                            'id' => $mockSrcId,
                            'type' => 'gcash'
                        ],
                        'payment_intent_id' => $intentCase9,
                        'metadata' => [
                            'order_id' => (string)$orderCase9,
                            'shift_id' => (string)$testShiftId,
                            'method' => 'gcash',
                            'branch_id' => '1'
                        ]
                    ]
                ]
            ]
        ]
    ]);
    $sigCase9 = computeSignature($payloadCase9, $webhookSecret);
    $resCase9 = sendWebhook($webhookUrl, $payloadCase9, $sigCase9);

    $stmtCheckOrder9 = $pdo->prepare("SELECT payment_status FROM orders WHERE id = ?");
    $stmtCheckOrder9->execute([$orderCase9]);
    $orderStatusCase9 = $stmtCheckOrder9->fetchColumn();

    $stmtCheckAudit9 = $pdo->prepare("SELECT COUNT(*) FROM order_audit_logs WHERE order_id = ? AND action = 'MOCK_PAYMENT_REJECTED'");
    $stmtCheckAudit9->execute([$orderCase9]);
    $auditRowCount9 = (int)$stmtCheckAudit9->fetchColumn();

    $case9Pass = ($resCase9['code'] === 400) && ($orderStatusCase9 !== 'PAID') && ($auditRowCount9 > 0);
    recordResult(9, "is_test=false -> validly signed webhook with mock id rejected (400) + MOCK_PAYMENT_REJECTED audit row", $case9Pass);

    // -------------------------------------------------------------------------
    // CASE 10: is_test=true WITHOUT cashier session or unauthorized role -> 404
    // -------------------------------------------------------------------------
    $setTestMode(true);
    $intentCase10 = 'cs_test_c10_' . uniqid();
    $orderCase10 = $createTestOrder($intentCase10, 150.00);
    $mockUrlCase10 = rtrim($baseUrl, '/') . "/cafe_pos/kiosk.php?payment=mock_checkout&order_id={$orderCase10}&intent_id={$intentCase10}&method=gcash";

    $resCase10NoSession = sendHttpRequest($mockUrlCase10, 'GET');

    $baristaSessionId = createSessionWithUser([
        'id' => 99,
        'username' => 'barista_test',
        'name' => 'Barista User',
        'role' => 'Barista',
        'branch_id' => 1
    ]);
    $baristaCookie = "PHPSESSID={$baristaSessionId}";
    $resCase10BaristaGet = sendHttpRequest($mockUrlCase10, 'GET', [], [], $baristaCookie);
    $resCase10BaristaPost = sendHttpRequest($mockUrlCase10, 'POST', ['authorize_mock_payment' => '1'], [], $baristaCookie);

    $stmtCheckOrder10 = $pdo->prepare("SELECT payment_status FROM orders WHERE id = ?");
    $stmtCheckOrder10->execute([$orderCase10]);
    $orderStatusCase10 = $stmtCheckOrder10->fetchColumn();

    $case10Pass = ($resCase10NoSession['code'] === 404)
        && ($resCase10BaristaGet['code'] === 404)
        && ($resCase10BaristaPost['code'] === 404)
        && ($orderStatusCase10 === 'UNPAID');
    recordResult(10, "is_test=true without cashier session or unauthorized role -> returns 404, order unpaid", $case10Pass);

    // -------------------------------------------------------------------------
    // CASE 11: is_test=true AND logged-in real Cashier session -> mock flow works, order PAID
    // -------------------------------------------------------------------------
    $intentCase11 = 'cs_test_c11_' . uniqid();
    $orderCase11 = $createTestOrder($intentCase11, 150.00);
    $mockUrlCase11 = rtrim($baseUrl, '/') . "/cafe_pos/kiosk.php?payment=mock_checkout&order_id={$orderCase11}&intent_id={$intentCase11}&method=gcash";

    $realCashierSessId = createSessionWithUser([
        'id' => 1,
        'username' => 'cashier_user',
        'name' => 'Real Cashier User',
        'role' => 'Cashier',
        'branch_id' => 1
    ]);
    $realCashierCookie = "PHPSESSID={$realCashierSessId}";

    // GET mock checkout page
    $resCase11Get = sendHttpRequest($mockUrlCase11, 'GET', [], [], $realCashierCookie);
    $getPageOk = ($resCase11Get['code'] === 200) && (str_contains($resCase11Get['body'], 'PayMongo Simulation') || str_contains($resCase11Get['body'], 'Test Sandbox'));

    // POST authorize_mock_payment
    $resCase11Post = sendHttpRequest($mockUrlCase11, 'POST', ['authorize_mock_payment' => '1'], [], $realCashierCookie);
    $postPageOk = ($resCase11Post['code'] === 200);

    $stmtCheckOrder11 = $pdo->prepare("SELECT payment_status FROM orders WHERE id = ?");
    $stmtCheckOrder11->execute([$orderCase11]);
    $orderStatusCase11 = $stmtCheckOrder11->fetchColumn();

    $stmtCheckPay11 = $pdo->prepare("SELECT status FROM payments WHERE order_id = ?");
    $stmtCheckPay11->execute([$orderCase11]);
    $payStatusCase11 = $stmtCheckPay11->fetchColumn();

    $case11Pass = $getPageOk && $postPageOk && ($orderStatusCase11 === 'PAID') && ($payStatusCase11 === 'paid');
    recordResult(11, "is_test=true AND real Cashier session -> mock flow works, 200 OK, order marked PAID", $case11Pass);

    // -------------------------------------------------------------------------
    // CASE 12: is_test=false -> payment_intent_create returns 502 error
    // -------------------------------------------------------------------------
    $setTestMode(false);
    $orderCase12 = $createTestOrder('pi_whk_c12_' . uniqid(), 150.00);
    $intentCreateUrl = rtrim($baseUrl, '/') . '/cafe_pos/api/payment_intent_create.php';
    $resCase12 = sendHttpRequest($intentCreateUrl, 'POST', json_encode(['order_id' => $orderCase12, 'method' => 'gcash']), ['Content-Type: application/json'], $realCashierCookie);

    $case12Pass = ($resCase12['code'] === 502)
        && isset($resCase12['json']['success']) && $resCase12['json']['success'] === false;
    recordResult(12, "is_test=false -> payment_intent_create returns 502 error", $case12Pass);

    // =========================================================================
    // SUB-PHASE H4: Authenticated Payment Endpoints Test Suite (Cases 13-22)
    // =========================================================================
    $setTestMode(true);
    $paymentStatusUrl = rtrim($baseUrl, '/') . '/cafe_pos/api/payment_status.php';

    // Helper to generate a kiosk token for an order
    $issueKioskToken = function(int $orderId) use ($pdo): string {
        $raw = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);
        $stmt = $pdo->prepare("INSERT INTO order_kiosk_tokens (order_id, token_hash) VALUES (?, ?)");
        $stmt->execute([$orderId, $hash]);
        return $raw;
    };

    // Helper to create test order with custom branch, payment_status, order_status
    $createCustomOrder = function(int $branchId, string $paymentStatus = 'UNPAID', string $orderStatus = 'PENDING') use ($pdo, &$createdOrderIds): int {
        $orderNum = 'ORD_H4_' . uniqid();
        $stmt = $pdo->prepare("
            INSERT INTO orders (order_number, total_price, payment_status, order_status, branch_id, is_test, created_at)
            VALUES (?, 120.00, ?, ?, ?, 1, NOW())
        ");
        $stmt->execute([$orderNum, $paymentStatus, $orderStatus, $branchId]);
        $id = (int)$pdo->lastInsertId();
        $createdOrderIds[] = $id;
        return $id;
    };

    // Ensure Branch 1 has an active OPEN pos_session
    $stmtB1Session = $pdo->query("SELECT id FROM pos_sessions WHERE branch_id = 1 AND status = 'OPEN' ORDER BY opened_at DESC LIMIT 1");
    $b1Shift = $stmtB1Session->fetch();
    if ($b1Shift) {
        $openShiftBranch1 = (int)$b1Shift['id'];
    } else {
        $pdo->exec("INSERT INTO pos_sessions (branch_id, cashier_id, status, opened_at) VALUES (1, 1, 'OPEN', NOW())");
        $openShiftBranch1 = (int)$pdo->lastInsertId();
        $createdSessionIds[] = $openShiftBranch1;
    }

    // Create Cashier session for Branch 1
    $cashierB1SessId = createSessionWithUser([
        'id' => 101,
        'username' => 'cashier_b1',
        'name' => 'Cashier Branch 1',
        'role' => 'Cashier',
        'branch_id' => 1
    ]);
    $cashierB1Cookie = "PHPSESSID={$cashierB1SessId}";

    // Create Cashier session for Branch 2
    $cashierB2SessId = createSessionWithUser([
        'id' => 102,
        'username' => 'cashier_b2',
        'name' => 'Cashier Branch 2',
        'role' => 'Cashier',
        'branch_id' => 2
    ]);
    $cashierB2Cookie = "PHPSESSID={$cashierB2SessId}";

    // Ensure Branch 2 has an open shift for branch-2 specific tests
    $stmtB2Session = $pdo->query("SELECT id FROM pos_sessions WHERE branch_id = 2 AND status = 'OPEN' ORDER BY opened_at DESC LIMIT 1");
    $b2Shift = $stmtB2Session->fetch();
    if ($b2Shift) {
        $openShiftBranch2 = (int)$b2Shift['id'];
    } else {
        $pdo->exec("INSERT INTO pos_sessions (branch_id, cashier_id, status, opened_at) VALUES (2, 102, 'OPEN', NOW())");
        $openShiftBranch2 = (int)$pdo->lastInsertId();
        $createdSessionIds[] = $openShiftBranch2;
    }

    // Error response text collector for checking information leakage (Case 22)
    $errorResponses = [];

    // -------------------------------------------------------------------------
    // CASE 13: No auth -> 401 on payment_intent_create & payment_status
    // -------------------------------------------------------------------------
    $orderCase13 = $createCustomOrder(1, 'UNPAID', 'PENDING');
    $res13Intent = sendHttpRequest($intentCreateUrl, 'POST', json_encode(['order_id' => $orderCase13, 'method' => 'gcash']), ['Content-Type: application/json']);
    $res13Status = sendHttpRequest("{$paymentStatusUrl}?order_id={$orderCase13}", 'GET');
    $errorResponses[] = $res13Intent['body'];
    $errorResponses[] = $res13Status['body'];

    $case13Pass = ($res13Intent['code'] === 401) && ($res13Status['code'] === 401);
    recordResult(13, "no auth -> 401", $case13Pass);

    // -------------------------------------------------------------------------
    // CASE 14: Wrong branch -> 403 on payment_intent_create & payment_status
    // -------------------------------------------------------------------------
    $orderCase14 = $createCustomOrder(2, 'UNPAID', 'PENDING');
    $res14Intent = sendHttpRequest($intentCreateUrl, 'POST', json_encode(['order_id' => $orderCase14, 'method' => 'gcash']), ['Content-Type: application/json'], $cashierB1Cookie);
    $res14Status = sendHttpRequest("{$paymentStatusUrl}?order_id={$orderCase14}", 'GET', [], [], $cashierB1Cookie);
    $errorResponses[] = $res14Intent['body'];
    $errorResponses[] = $res14Status['body'];

    $case14Pass = ($res14Intent['code'] === 403) && ($res14Status['code'] === 403);
    recordResult(14, "wrong branch -> 403", $case14Pass);

    // -------------------------------------------------------------------------
    // CASE 15: Already PAID -> 409 on payment_intent_create
    // -------------------------------------------------------------------------
    $orderCase15 = $createCustomOrder(1, 'PAID', 'COMPLETED');
    $res15 = sendHttpRequest($intentCreateUrl, 'POST', json_encode(['order_id' => $orderCase15, 'method' => 'gcash']), ['Content-Type: application/json'], $cashierB1Cookie);
    $errorResponses[] = $res15['body'];

    $case15Pass = ($res15['code'] === 409);
    recordResult(15, "PAID -> 409", $case15Pass);

    // -------------------------------------------------------------------------
    // CASE 16: VOIDED -> 409 on payment_intent_create
    // -------------------------------------------------------------------------
    $orderCase16 = $createCustomOrder(1, 'UNPAID', 'VOID');
    $res16 = sendHttpRequest($intentCreateUrl, 'POST', json_encode(['order_id' => $orderCase16, 'method' => 'gcash']), ['Content-Type: application/json'], $cashierB1Cookie);
    $errorResponses[] = $res16['body'];

    $case16Pass = ($res16['code'] === 409);
    recordResult(16, "VOIDED -> 409", $case16Pass);

    // -------------------------------------------------------------------------
    // CASE 17: No open shift in branch -> 409
    // -------------------------------------------------------------------------
    $orderCase17 = $createCustomOrder(999, 'UNPAID', 'PENDING');
    $cashierB999SessId = createSessionWithUser([
        'id' => 199,
        'username' => 'cashier_b999',
        'name' => 'Cashier Branch 999',
        'role' => 'Cashier',
        'branch_id' => 999
    ]);
    $cashierB999Cookie = "PHPSESSID={$cashierB999SessId}";
    $res17 = sendHttpRequest($intentCreateUrl, 'POST', json_encode(['order_id' => $orderCase17, 'method' => 'gcash']), ['Content-Type: application/json'], $cashierB999Cookie);
    $errorResponses[] = $res17['body'];

    $case17Pass = ($res17['code'] === 409);
    recordResult(17, "no open shift -> 409", $case17Pass);

    // -------------------------------------------------------------------------
    // CASE 18: Rate limiting -> 11th call in 10 minutes returns 429
    // -------------------------------------------------------------------------
    $orderCase18 = $createCustomOrder(1, 'UNPAID', 'PENDING');
    for ($i = 1; $i <= 10; $i++) {
        sendHttpRequest($intentCreateUrl, 'POST', json_encode(['order_id' => $orderCase18, 'method' => 'gcash']), ['Content-Type: application/json'], $cashierB1Cookie);
    }
    $res18Eleventh = sendHttpRequest($intentCreateUrl, 'POST', json_encode(['order_id' => $orderCase18, 'method' => 'gcash']), ['Content-Type: application/json'], $cashierB1Cookie);
    $errorResponses[] = $res18Eleventh['body'];

    $case18Pass = ($res18Eleventh['code'] === 429);
    recordResult(18, "11th call in 10 minutes -> 429", $case18Pass);

    // -------------------------------------------------------------------------
    // CASE 19: Client-supplied shift_id is ignored
    // -------------------------------------------------------------------------
    $orderCase19 = $createCustomOrder(1, 'UNPAID', 'PENDING');
    $fakeClientShiftId = 77777;
    $res19 = sendHttpRequest($intentCreateUrl, 'POST', json_encode([
        'order_id' => $orderCase19,
        'method'   => 'gcash',
        'shift_id' => $fakeClientShiftId
    ]), ['Content-Type: application/json'], $cashierB1Cookie);

    $stmtCheckShift19 = $pdo->prepare("SELECT shift_id FROM payments WHERE order_id = ?");
    $stmtCheckShift19->execute([$orderCase19]);
    $persistedShiftId19 = (int)$stmtCheckShift19->fetchColumn();

    $case19Pass = ($res19['code'] === 200)
        && ($persistedShiftId19 === $openShiftBranch1)
        && ($res19['json']['shift_id'] === $openShiftBranch1)
        && ($persistedShiftId19 !== $fakeClientShiftId);
    recordResult(19, "a client-supplied shift_id is ignored", $case19Pass);

    // -------------------------------------------------------------------------
    // CASE 20: Valid kiosk token works for its own order
    // -------------------------------------------------------------------------
    $orderCase20 = $createCustomOrder(1, 'UNPAID', 'PENDING');
    $kioskToken20 = $issueKioskToken($orderCase20);

    $res20Intent = sendHttpRequest($intentCreateUrl, 'POST', json_encode([
        'order_id'    => $orderCase20,
        'method'      => 'gcash',
        'kiosk_token' => $kioskToken20
    ]), ['Content-Type: application/json', "X-Kiosk-Token: {$kioskToken20}"]);

    $res20Status = sendHttpRequest("{$paymentStatusUrl}?order_id={$orderCase20}&kiosk_token={$kioskToken20}", 'GET', [], ["X-Kiosk-Token: {$kioskToken20}"]);

    $case20Pass = ($res20Intent['code'] === 200)
        && ($res20Status['code'] === 200)
        && ($res20Status['json']['order_id'] === $orderCase20);
    recordResult(20, "valid kiosk token works for its own order", $case20Pass);

    // -------------------------------------------------------------------------
    // CASE 21: Valid kiosk token for Order A fails (401/403) for Order B
    // -------------------------------------------------------------------------
    $orderCase21A = $createCustomOrder(1, 'UNPAID', 'PENDING');
    $kioskToken21A = $issueKioskToken($orderCase21A);

    $orderCase21B = $createCustomOrder(1, 'UNPAID', 'PENDING');
    $kioskToken21B = $issueKioskToken($orderCase21B);

    $res21CrossIntent = sendHttpRequest($intentCreateUrl, 'POST', json_encode([
        'order_id'    => $orderCase21B,
        'method'      => 'gcash',
        'kiosk_token' => $kioskToken21A
    ]), ['Content-Type: application/json', "X-Kiosk-Token: {$kioskToken21A}"]);

    $res21CrossStatus = sendHttpRequest("{$paymentStatusUrl}?order_id={$orderCase21B}&kiosk_token={$kioskToken21A}", 'GET', [], ["X-Kiosk-Token: {$kioskToken21A}"]);
    $errorResponses[] = $res21CrossIntent['body'];
    $errorResponses[] = $res21CrossStatus['body'];

    $case21Pass = in_array($res21CrossIntent['code'], [401, 403], true)
        && in_array($res21CrossStatus['code'], [401, 403], true);
    recordResult(21, "valid kiosk token fails (401/403) for another order", $case21Pass);

    // -------------------------------------------------------------------------
    // CASE 22: No exception text or SQL in any error response
    // -------------------------------------------------------------------------
    $res22BadOrder = sendHttpRequest($intentCreateUrl, 'POST', json_encode(['order_id' => -9999]), ['Content-Type: application/json'], $cashierB1Cookie);
    $errorResponses[] = $res22BadOrder['body'];

    $hasExceptionLeak = false;
    $leakPatterns = ['SQLSTATE', 'PDOException', 'Exception in', 'Fatal error', 'syntax error', 'Stack trace:', 'SELECT '];
    foreach ($errorResponses as $body) {
        foreach ($leakPatterns as $pattern) {
            if (stripos($body, $pattern) !== false) {
                $hasExceptionLeak = true;
                break 2;
            }
        }
    }
    $case22Pass = !$hasExceptionLeak;
    recordResult(22, "no exception text in any error response", $case22Pass);

    // =========================================================================
    // SUB-PHASE H6: Webhook and Payment Test Suite (12 Comprehensive Cases)
    // =========================================================================

    // -------------------------------------------------------------------------
    // CASE 23 (H6.1): Replay — same signed payment.paid delivered twice -> order PAID once,
    // exactly 1 inventory deduction per ingredient, 1 payment row paid
    // -------------------------------------------------------------------------
    $intentCase23 = 'pi_whk_c23_' . uniqid();
    $orderCase23 = $createTestOrder($intentCase23, 150.00);
    $payloadCase23 = loadFixture($fixturesDir, 'payment_paid.json', $orderCase23, $intentCase23, $testShiftId);
    $sigCase23 = computeSignature($payloadCase23, $webhookSecret);

    $stockBefore23 = (float)$pdo->query("SELECT stock FROM inventory_stock WHERE inventory_id = {$testInvId} AND branch_id = 1")->fetchColumn();
    $txCountBefore23 = (int)$pdo->query("SELECT COUNT(*) FROM inventory_transactions WHERE inventory_id = {$testInvId}")->fetchColumn();

    // 1st delivery
    $res23_1 = sendWebhook($webhookUrl, $payloadCase23, $sigCase23);
    // 2nd delivery (replay)
    $res23_2 = sendWebhook($webhookUrl, $payloadCase23, $sigCase23);

    $stockAfter23 = (float)$pdo->query("SELECT stock FROM inventory_stock WHERE inventory_id = {$testInvId} AND branch_id = 1")->fetchColumn();
    $txCountAfter23 = (int)$pdo->query("SELECT COUNT(*) FROM inventory_transactions WHERE inventory_id = {$testInvId}")->fetchColumn();

    $stmtCheckOrder23 = $pdo->prepare("SELECT payment_status, order_status FROM orders WHERE id = ?");
    $stmtCheckOrder23->execute([$orderCase23]);
    $orderRow23 = $stmtCheckOrder23->fetch();

    $stmtCheckPay23 = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE order_id = ? AND status = 'paid'");
    $stmtCheckPay23->execute([$orderCase23]);
    $paidPayCount23 = (int)$stmtCheckPay23->fetchColumn();

    $case23Pass = ($res23_1['code'] === 200)
        && ($res23_2['code'] === 200)
        && ($orderRow23['payment_status'] === 'PAID')
        && ($paidPayCount23 === 1)
        && (round($stockBefore23 - $stockAfter23, 2) === 20.00)
        && ($txCountAfter23 === $txCountBefore23 + 1);
    recordResult(23, "H6.1 Replay: delivered twice -> order PAID once, 1 deduction, 1 payment row paid", $case23Pass);

    // -------------------------------------------------------------------------
    // CASE 24 (H6.2): Concurrency — two identical payment.paid delivered simultaneously via curl_multi_init
    // -> exactly 1 deduction, 1 payment row paid, no race error, no deadlock
    // -------------------------------------------------------------------------
    $intentCase24 = 'pi_whk_c24_' . uniqid();
    $orderCase24 = $createTestOrder($intentCase24, 150.00);
    $payloadCase24 = loadFixture($fixturesDir, 'payment_paid.json', $orderCase24, $intentCase24, $testShiftId);
    $sigCase24 = computeSignature($payloadCase24, $webhookSecret);

    $stockBefore24 = (float)$pdo->query("SELECT stock FROM inventory_stock WHERE inventory_id = {$testInvId} AND branch_id = 1")->fetchColumn();
    $txCountBefore24 = (int)$pdo->query("SELECT COUNT(*) FROM inventory_transactions WHERE inventory_id = {$testInvId}")->fetchColumn();

    $concurrentResponses24 = sendConcurrentWebhooks($webhookUrl, $payloadCase24, $sigCase24);

    $stockAfter24 = (float)$pdo->query("SELECT stock FROM inventory_stock WHERE inventory_id = {$testInvId} AND branch_id = 1")->fetchColumn();
    $txCountAfter24 = (int)$pdo->query("SELECT COUNT(*) FROM inventory_transactions WHERE inventory_id = {$testInvId}")->fetchColumn();

    $stmtCheckOrder24 = $pdo->prepare("SELECT payment_status FROM orders WHERE id = ?");
    $stmtCheckOrder24->execute([$orderCase24]);
    $orderStatus24 = $stmtCheckOrder24->fetchColumn();

    $stmtCheckPay24 = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE order_id = ? AND status = 'paid'");
    $stmtCheckPay24->execute([$orderCase24]);
    $paidPayCount24 = (int)$stmtCheckPay24->fetchColumn();

    $allCodes200 = ($concurrentResponses24[0]['code'] === 200 && $concurrentResponses24[1]['code'] === 200);

    $case24Pass = $allCodes200
        && ($orderStatus24 === 'PAID')
        && ($paidPayCount24 === 1)
        && (round($stockBefore24 - $stockAfter24, 2) === 20.00)
        && ($txCountAfter24 === $txCountBefore24 + 1);
    recordResult(24, "H6.2 Concurrency: simultaneous delivery -> 1 deduction, 1 payment row paid, no race/deadlock", $case24Pass);

    // -------------------------------------------------------------------------
    // CASE 25 (H6.3): payment.failed after payment.paid — delivered for an already-PAID order
    // -> order stays PAID, payment stays paid, inventory not refunded
    // -------------------------------------------------------------------------
    $intentCase25 = 'pi_whk_c25_' . uniqid();
    $orderCase25 = $createTestOrder($intentCase25, 150.00);
    $payloadPaid25 = loadFixture($fixturesDir, 'payment_paid.json', $orderCase25, $intentCase25, $testShiftId);
    $sigPaid25 = computeSignature($payloadPaid25, $webhookSecret);

    // Pay order first
    $res25Paid = sendWebhook($webhookUrl, $payloadPaid25, $sigPaid25);

    $stockAfterPaid25 = (float)$pdo->query("SELECT stock FROM inventory_stock WHERE inventory_id = {$testInvId} AND branch_id = 1")->fetchColumn();

    // Now send payment.failed for the same order & intent
    $payloadFailed25 = loadFixture($fixturesDir, 'payment_failed.json', $orderCase25, $intentCase25, $testShiftId);
    $sigFailed25 = computeSignature($payloadFailed25, $webhookSecret);
    $res25Failed = sendWebhook($webhookUrl, $payloadFailed25, $sigFailed25);

    $stmtCheckOrder25 = $pdo->prepare("SELECT payment_status FROM orders WHERE id = ?");
    $stmtCheckOrder25->execute([$orderCase25]);
    $orderStatus25 = $stmtCheckOrder25->fetchColumn();

    $stmtCheckPay25 = $pdo->prepare("SELECT status FROM payments WHERE order_id = ? AND intent_id = ?");
    $stmtCheckPay25->execute([$orderCase25, $intentCase25]);
    $payStatus25 = $stmtCheckPay25->fetchColumn();

    $stockAfterFailed25 = (float)$pdo->query("SELECT stock FROM inventory_stock WHERE inventory_id = {$testInvId} AND branch_id = 1")->fetchColumn();

    $case25Pass = ($res25Paid['code'] === 200)
        && ($res25Failed['code'] === 200)
        && ($orderStatus25 === 'PAID')
        && ($payStatus25 === 'paid')
        && ($stockAfterFailed25 === $stockAfterPaid25);
    recordResult(25, "H6.3 payment.failed after payment.paid: order stays PAID, payment stays paid, inventory not refunded", $case25Pass);

    // -------------------------------------------------------------------------
    // CASE 26 (H6.4): payment.failed alone — payment row status=failed, order NOT paid, 0 inventory deduction
    // -------------------------------------------------------------------------
    $intentCase26 = 'pi_whk_c26_' . uniqid();
    $orderCase26 = $createTestOrder($intentCase26, 150.00);
    $payloadCase26 = loadFixture($fixturesDir, 'payment_failed.json', $orderCase26, $intentCase26, $testShiftId);
    $sigCase26 = computeSignature($payloadCase26, $webhookSecret);

    $stockBefore26 = (float)$pdo->query("SELECT stock FROM inventory_stock WHERE inventory_id = {$testInvId} AND branch_id = 1")->fetchColumn();

    $resCase26 = sendWebhook($webhookUrl, $payloadCase26, $sigCase26);

    $stockAfter26 = (float)$pdo->query("SELECT stock FROM inventory_stock WHERE inventory_id = {$testInvId} AND branch_id = 1")->fetchColumn();

    $stmtCheckOrder26 = $pdo->prepare("SELECT payment_status FROM orders WHERE id = ?");
    $stmtCheckOrder26->execute([$orderCase26]);
    $orderStatus26 = $stmtCheckOrder26->fetchColumn();

    $stmtCheckPay26 = $pdo->prepare("SELECT status FROM payments WHERE order_id = ? AND intent_id = ?");
    $stmtCheckPay26->execute([$orderCase26, $intentCase26]);
    $payStatus26 = $stmtCheckPay26->fetchColumn();

    $case26Pass = ($resCase26['code'] === 200)
        && ($orderStatus26 === 'UNPAID')
        && ($payStatus26 === 'failed')
        && ($stockAfter26 === $stockBefore26);
    recordResult(26, "H6.4 payment.failed alone: status=failed, order NOT paid, 0 inventory deduction", $case26Pass);

    // -------------------------------------------------------------------------
    // CASE 27 (H6.5): payment.paid for VOIDED order — NOT marked paid, 0 deduction, audit row written
    // -------------------------------------------------------------------------
    $intentCase27 = 'pi_whk_c27_' . uniqid();
    $orderCase27 = $createTestOrder($intentCase27, 150.00);
    $pdo->prepare("UPDATE orders SET order_status = 'VOID' WHERE id = ?")->execute([$orderCase27]);

    $payloadCase27 = loadFixture($fixturesDir, 'payment_paid.json', $orderCase27, $intentCase27, $testShiftId);
    $sigCase27 = computeSignature($payloadCase27, $webhookSecret);

    $stockBefore27 = (float)$pdo->query("SELECT stock FROM inventory_stock WHERE inventory_id = {$testInvId} AND branch_id = 1")->fetchColumn();

    $resCase27 = sendWebhook($webhookUrl, $payloadCase27, $sigCase27);

    $stockAfter27 = (float)$pdo->query("SELECT stock FROM inventory_stock WHERE inventory_id = {$testInvId} AND branch_id = 1")->fetchColumn();

    $stmtCheckOrder27 = $pdo->prepare("SELECT payment_status, order_status FROM orders WHERE id = ?");
    $stmtCheckOrder27->execute([$orderCase27]);
    $orderRow27 = $stmtCheckOrder27->fetch();

    $stmtCheckPay27 = $pdo->prepare("SELECT status FROM payments WHERE order_id = ? AND intent_id = ?");
    $stmtCheckPay27->execute([$orderCase27, $intentCase27]);
    $payStatus27 = $stmtCheckPay27->fetchColumn();

    $stmtCheckAudit27 = $pdo->prepare("SELECT COUNT(*) FROM order_audit_logs WHERE order_id = ? AND action = 'VOID_ORDER_PAYMENT_REJECTED'");
    $stmtCheckAudit27->execute([$orderCase27]);
    $auditCount27 = (int)$stmtCheckAudit27->fetchColumn();

    $case27Pass = ($resCase27['code'] === 409)
        && ($orderRow27['payment_status'] === 'UNPAID')
        && ($orderRow27['order_status'] === 'VOID')
        && ($payStatus27 !== 'paid')
        && ($stockAfter27 === $stockBefore27)
        && ($auditCount27 > 0);
    recordResult(27, "H6.5 payment.paid for VOID order: 409, NOT paid, 0 deduction, audit row written", $case27Pass);

    // -------------------------------------------------------------------------
    // CASE 28 (H6.6): payment.paid for order already paid by cash — no second deduction,
    // no digital payment row marked paid, audit row written outside transaction
    // -------------------------------------------------------------------------
    $intentCase28 = 'pi_whk_c28_' . uniqid();
    $orderCase28 = $createTestOrder($intentCase28, 150.00);
    $pdo->prepare("UPDATE orders SET payment_status = 'PAID', payment_method = 'CASH', order_status = 'COMPLETED' WHERE id = ?")->execute([$orderCase28]);
    $pdo->prepare("DELETE FROM payments WHERE order_id = ?")->execute([$orderCase28]);

    $payloadCase28 = loadFixture($fixturesDir, 'payment_paid.json', $orderCase28, $intentCase28, $testShiftId);
    $sigCase28 = computeSignature($payloadCase28, $webhookSecret);

    $stockBefore28 = (float)$pdo->query("SELECT stock FROM inventory_stock WHERE inventory_id = {$testInvId} AND branch_id = 1")->fetchColumn();

    $resCase28 = sendWebhook($webhookUrl, $payloadCase28, $sigCase28);

    $stockAfter28 = (float)$pdo->query("SELECT stock FROM inventory_stock WHERE inventory_id = {$testInvId} AND branch_id = 1")->fetchColumn();

    $stmtCheckOrder28 = $pdo->prepare("SELECT payment_status, payment_method FROM orders WHERE id = ?");
    $stmtCheckOrder28->execute([$orderCase28]);
    $orderRow28 = $stmtCheckOrder28->fetch();

    $stmtCheckPay28 = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE order_id = ? AND status = 'paid'");
    $stmtCheckPay28->execute([$orderCase28]);
    $paidPayCount28 = (int)$stmtCheckPay28->fetchColumn();

    $stmtCheckAudit28 = $pdo->prepare("SELECT COUNT(*) FROM order_audit_logs WHERE order_id = ? AND action = 'CASH_ORDER_WEBHOOK_REJECTED'");
    $stmtCheckAudit28->execute([$orderCase28]);
    $auditCount28 = (int)$stmtCheckAudit28->fetchColumn();

    $case28Pass = ($resCase28['code'] === 409)
        && ($orderRow28['payment_status'] === 'PAID')
        && ($orderRow28['payment_method'] === 'CASH')
        && ($paidPayCount28 === 0)
        && ($stockAfter28 === $stockBefore28)
        && ($auditCount28 > 0);
    recordResult(28, "H6.6 payment.paid for CASH order: 409, 0 deduction, no paid payment row, audit row written", $case28Pass);

    // -------------------------------------------------------------------------
    // CASE 29 (H6.7): Amount mismatch — centavos in webhook differ from order total_price*100
    // -> NOT paid, payment_status unchanged, audit row written outside transaction
    // -------------------------------------------------------------------------
    $intentCase29 = 'pi_whk_c29_' . uniqid();
    $orderCase29 = $createTestOrder($intentCase29, 150.00);
    $payloadCase29 = loadFixture($fixturesDir, 'paid_amount_mismatch.json', $orderCase29, $intentCase29, $testShiftId);
    $sigCase29 = computeSignature($payloadCase29, $webhookSecret);

    $stockBefore29 = (float)$pdo->query("SELECT stock FROM inventory_stock WHERE inventory_id = {$testInvId} AND branch_id = 1")->fetchColumn();

    $resCase29 = sendWebhook($webhookUrl, $payloadCase29, $sigCase29);

    $stockAfter29 = (float)$pdo->query("SELECT stock FROM inventory_stock WHERE inventory_id = {$testInvId} AND branch_id = 1")->fetchColumn();

    $stmtCheckOrder29 = $pdo->prepare("SELECT payment_status FROM orders WHERE id = ?");
    $stmtCheckOrder29->execute([$orderCase29]);
    $orderStatus29 = $stmtCheckOrder29->fetchColumn();

    $stmtCheckPay29 = $pdo->prepare("SELECT status FROM payments WHERE order_id = ? AND intent_id = ?");
    $stmtCheckPay29->execute([$orderCase29, $intentCase29]);
    $payStatus29 = $stmtCheckPay29->fetchColumn();

    $stmtCheckAudit29 = $pdo->prepare("SELECT COUNT(*) FROM order_audit_logs WHERE order_id = ? AND action = 'PAYMENT_MISMATCH'");
    $stmtCheckAudit29->execute([$orderCase29]);
    $auditCount29 = (int)$stmtCheckAudit29->fetchColumn();

    $case29Pass = ($resCase29['code'] === 400)
        && ($orderStatus29 === 'UNPAID')
        && ($payStatus29 !== 'paid')
        && ($stockAfter29 === $stockBefore29)
        && ($auditCount29 > 0);
    recordResult(29, "H6.7 Amount mismatch: 400, NOT paid, payment_status unchanged, audit row written", $case29Pass);

    // -------------------------------------------------------------------------
    // CASE 30 (H6.8): Missing amount — payload with no amount field -> NOT paid, audit row
    // -------------------------------------------------------------------------
    $intentCase30 = 'pi_whk_c30_' . uniqid();
    $orderCase30 = $createTestOrder($intentCase30, 150.00);
    $payloadCase30 = loadFixture($fixturesDir, 'paid_no_amount.json', $orderCase30, $intentCase30, $testShiftId);
    $sigCase30 = computeSignature($payloadCase30, $webhookSecret);

    $stockBefore30 = (float)$pdo->query("SELECT stock FROM inventory_stock WHERE inventory_id = {$testInvId} AND branch_id = 1")->fetchColumn();

    $resCase30 = sendWebhook($webhookUrl, $payloadCase30, $sigCase30);

    $stockAfter30 = (float)$pdo->query("SELECT stock FROM inventory_stock WHERE inventory_id = {$testInvId} AND branch_id = 1")->fetchColumn();

    $stmtCheckOrder30 = $pdo->prepare("SELECT payment_status FROM orders WHERE id = ?");
    $stmtCheckOrder30->execute([$orderCase30]);
    $orderStatus30 = $stmtCheckOrder30->fetchColumn();

    $stmtCheckPay30 = $pdo->prepare("SELECT status FROM payments WHERE order_id = ? AND intent_id = ?");
    $stmtCheckPay30->execute([$orderCase30, $intentCase30]);
    $payStatus30 = $stmtCheckPay30->fetchColumn();

    $stmtCheckAudit30 = $pdo->prepare("SELECT COUNT(*) FROM order_audit_logs WHERE order_id = ? AND action = 'PAYMENT_AMOUNT_INVALID'");
    $stmtCheckAudit30->execute([$orderCase30]);
    $auditCount30 = (int)$stmtCheckAudit30->fetchColumn();

    $case30Pass = ($resCase30['code'] === 400)
        && ($orderStatus30 === 'UNPAID')
        && ($payStatus30 !== 'paid')
        && ($stockAfter30 === $stockBefore30)
        && ($auditCount30 > 0);
    recordResult(30, "H6.8 Missing amount: 400, NOT paid, 0 deduction, PAYMENT_AMOUNT_INVALID audit row", $case30Pass);

    // -------------------------------------------------------------------------
    // CASE 31 (H6.9): Bad signature, missing header, malformed header -> 401, no DB change
    // -------------------------------------------------------------------------
    $intentCase31 = 'pi_whk_c31_' . uniqid();
    $orderCase31 = $createTestOrder($intentCase31, 150.00);
    $payloadCase31 = loadFixture($fixturesDir, 'payment_paid.json', $orderCase31, $intentCase31, $testShiftId);

    $stockBefore31 = (float)$pdo->query("SELECT stock FROM inventory_stock WHERE inventory_id = {$testInvId} AND branch_id = 1")->fetchColumn();

    // 1. Missing header
    $res31Missing = sendWebhook($webhookUrl, $payloadCase31, null);
    // 2. Malformed header (missing te / li)
    $res31Malformed = sendWebhook($webhookUrl, $payloadCase31, "t=" . time());
    // 3. Bad signature hash
    $res31BadSig = sendWebhook($webhookUrl, $payloadCase31, "t=" . time() . ",te=invalid_sig_hash_abcdef");

    $stockAfter31 = (float)$pdo->query("SELECT stock FROM inventory_stock WHERE inventory_id = {$testInvId} AND branch_id = 1")->fetchColumn();

    $stmtCheckOrder31 = $pdo->prepare("SELECT payment_status FROM orders WHERE id = ?");
    $stmtCheckOrder31->execute([$orderCase31]);
    $orderStatus31 = $stmtCheckOrder31->fetchColumn();

    $case31Pass = ($res31Missing['code'] === 401)
        && ($res31Malformed['code'] === 401)
        && ($res31BadSig['code'] === 401)
        && ($orderStatus31 === 'UNPAID')
        && ($stockAfter31 === $stockBefore31);
    recordResult(31, "H6.9 Bad signature, missing header, malformed header: all 401, no DB change", $case31Pass);

    // -------------------------------------------------------------------------
    // CASE 32 (H6.10): Missing webhook secret in config -> 503, no database change
    // -------------------------------------------------------------------------
    $intentCase32 = 'pi_whk_c32_' . uniqid();
    $orderCase32 = $createTestOrder($intentCase32, 150.00);
    $payloadCase32 = loadFixture($fixturesDir, 'payment_paid.json', $orderCase32, $intentCase32, $testShiftId);
    $sigCase32 = computeSignature($payloadCase32, $webhookSecret);

    $stockBefore32 = (float)$pdo->query("SELECT stock FROM inventory_stock WHERE inventory_id = {$testInvId} AND branch_id = 1")->fetchColumn();

    $envContentBefore32 = file_exists($envFilePath) ? file_get_contents($envFilePath) : '';
    try {
        $envLines = explode("\n", $envContentBefore32);
        $envLinesFiltered = array_filter($envLines, fn($l) => !str_starts_with(trim($l), 'PAYMONGO_WEBHOOK_SECRET='));
        $envLinesFiltered[] = 'PAYMONGO_WEBHOOK_SECRET=';
        file_put_contents($envFilePath, implode("\n", $envLinesFiltered));

        $res32 = sendWebhook($webhookUrl, $payloadCase32, $sigCase32);
    } finally {
        file_put_contents($envFilePath, $envContentBefore32);
    }

    $stockAfter32 = (float)$pdo->query("SELECT stock FROM inventory_stock WHERE inventory_id = {$testInvId} AND branch_id = 1")->fetchColumn();

    $stmtCheckOrder32 = $pdo->prepare("SELECT payment_status FROM orders WHERE id = ?");
    $stmtCheckOrder32->execute([$orderCase32]);
    $orderStatus32 = $stmtCheckOrder32->fetchColumn();

    $case32Pass = ($res32['code'] === 503)
        && ($orderStatus32 === 'UNPAID')
        && ($stockAfter32 === $stockBefore32);
    recordResult(32, "H6.10 Missing webhook secret in config: 503, no database change", $case32Pass);

    // -------------------------------------------------------------------------
    // CASE 33 (H6.11): Timestamp tolerance — signed with timestamp older than 300s -> 401;
    // future timestamp skew > 300s -> 401
    // -------------------------------------------------------------------------
    $intentCase33 = 'pi_whk_c33_' . uniqid();
    $orderCase33 = $createTestOrder($intentCase33, 150.00);
    $payloadCase33 = loadFixture($fixturesDir, 'payment_paid.json', $orderCase33, $intentCase33, $testShiftId);

    $stockBefore33 = (float)$pdo->query("SELECT stock FROM inventory_stock WHERE inventory_id = {$testInvId} AND branch_id = 1")->fetchColumn();

    // Expired timestamp (305s ago)
    $oldTimestamp = time() - 305;
    $sigOld = computeSignature($payloadCase33, $webhookSecret, $oldTimestamp);
    $res33Old = sendWebhook($webhookUrl, $payloadCase33, $sigOld);

    // Future timestamp skew (+305s)
    $futureTimestamp = time() + 305;
    $sigFuture = computeSignature($payloadCase33, $webhookSecret, $futureTimestamp);
    $res33Future = sendWebhook($webhookUrl, $payloadCase33, $sigFuture);

    $stockAfter33 = (float)$pdo->query("SELECT stock FROM inventory_stock WHERE inventory_id = {$testInvId} AND branch_id = 1")->fetchColumn();

    $stmtCheckOrder33 = $pdo->prepare("SELECT payment_status FROM orders WHERE id = ?");
    $stmtCheckOrder33->execute([$orderCase33]);
    $orderStatus33 = $stmtCheckOrder33->fetchColumn();

    $case33Pass = ($res33Old['code'] === 401)
        && ($res33Future['code'] === 401)
        && ($orderStatus33 === 'UNPAID')
        && ($stockAfter33 === $stockBefore33);
    recordResult(33, "H6.11 Timestamp tolerance: >300s past (401) & future skew (401), DB unchanged", $case33Pass);

    // -------------------------------------------------------------------------
    // CASE 34 (H6.12): payment.paid for REFUNDED order — order_status='REFUNDED'
    // -> not marked paid, 0 inventory deduction, audit row written
    // -------------------------------------------------------------------------
    $intentCase34 = 'pi_whk_c34_' . uniqid();
    $orderCase34 = $createTestOrder($intentCase34, 150.00);
    $pdo->prepare("UPDATE orders SET order_status = 'REFUNDED' WHERE id = ?")->execute([$orderCase34]);

    $payloadCase34 = loadFixture($fixturesDir, 'payment_paid.json', $orderCase34, $intentCase34, $testShiftId);
    $sigCase34 = computeSignature($payloadCase34, $webhookSecret);

    $stockBefore34 = (float)$pdo->query("SELECT stock FROM inventory_stock WHERE inventory_id = {$testInvId} AND branch_id = 1")->fetchColumn();

    $resCase34 = sendWebhook($webhookUrl, $payloadCase34, $sigCase34);

    $stockAfter34 = (float)$pdo->query("SELECT stock FROM inventory_stock WHERE inventory_id = {$testInvId} AND branch_id = 1")->fetchColumn();

    $stmtCheckOrder34 = $pdo->prepare("SELECT payment_status, order_status FROM orders WHERE id = ?");
    $stmtCheckOrder34->execute([$orderCase34]);
    $orderRow34 = $stmtCheckOrder34->fetch();

    $stmtCheckPay34 = $pdo->prepare("SELECT status FROM payments WHERE order_id = ? AND intent_id = ?");
    $stmtCheckPay34->execute([$orderCase34, $intentCase34]);
    $payStatus34 = $stmtCheckPay34->fetchColumn();

    $stmtCheckAudit34 = $pdo->prepare("SELECT COUNT(*) FROM order_audit_logs WHERE order_id = ? AND action = 'REFUNDED_ORDER_PAYMENT_REJECTED'");
    $stmtCheckAudit34->execute([$orderCase34]);
    $auditCount34 = (int)$stmtCheckAudit34->fetchColumn();

    $case34Pass = ($resCase34['code'] === 409)
        && ($orderRow34['payment_status'] === 'UNPAID')
        && ($orderRow34['order_status'] === 'REFUNDED')
        && ($payStatus34 !== 'paid')
        && ($stockAfter34 === $stockBefore34)
        && ($auditCount34 > 0);
    recordResult(34, "H6.12 payment.paid for REFUNDED order: 409, NOT paid, 0 deduction, audit row written", $case34Pass);

} catch (Throwable $t) {
    echo "ERROR during test execution: " . $t->getMessage() . "\n";
    $failures++;
} finally {
    // Restore environment
    $setTestMode(null);
    // 4. Cleanup: Remove all test seeded data
    if (!empty($createdOrderIds)) {
        $inOrders = implode(',', array_map('intval', $createdOrderIds));
        $pdo->exec("DELETE FROM order_audit_logs WHERE order_id IN ({$inOrders})");
        $pdo->exec("DELETE FROM order_kiosk_tokens WHERE order_id IN ({$inOrders})");
        $pdo->exec("DELETE FROM order_item_modifiers WHERE order_item_id IN (SELECT id FROM order_items WHERE order_id IN ({$inOrders}))");
        $pdo->exec("DELETE FROM order_items WHERE order_id IN ({$inOrders})");
        $pdo->exec("DELETE FROM payments WHERE order_id IN ({$inOrders})");
        $pdo->exec("DELETE FROM orders WHERE id IN ({$inOrders})");
    }

    if (!empty($createdProductIds)) {
        $inProds = implode(',', array_map('intval', $createdProductIds));
        $pdo->exec("DELETE FROM recipes WHERE product_id IN ({$inProds})");
        $pdo->exec("DELETE FROM products WHERE id IN ({$inProds})");
    }

    if (!empty($createdInventoryIds)) {
        $inInvs = implode(',', array_map('intval', $createdInventoryIds));
        $pdo->exec("DELETE FROM inventory_transactions WHERE inventory_id IN ({$inInvs})");
        $pdo->exec("DELETE FROM inventory_stock WHERE inventory_id IN ({$inInvs})");
        $pdo->exec("DELETE FROM inventory WHERE id IN ({$inInvs})");
    }

    if (!empty($createdSessionIds)) {
        $inSess = implode(',', array_map('intval', $createdSessionIds));
        $pdo->exec("DELETE FROM pos_sessions WHERE id IN ({$inSess})");
    }

    if (!empty($createdSessionFiles)) {
        foreach ($createdSessionFiles as $sf) {
            if (file_exists($sf)) {
                @unlink($sf);
            }
        }
    }
}

echo "\n--------------------------------------------------------------------\n";
if ($failures > 0) {
    echo "Test Run Completed with {$failures} failure(s).\n";
    exit(1);
} else {
    echo "All applicable tests PASSED (any pending sub-phase tests marked EXPECTED-FAIL).\n";
    exit(0);
}
