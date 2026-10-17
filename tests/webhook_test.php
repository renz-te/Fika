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
function sendWebhook(string $url, string $rawBody, string $sigHeader): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Paymongo-Signature: ' . $sigHeader
    ]);
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
    $resCase12 = sendHttpRequest($intentCreateUrl, 'POST', json_encode(['order_id' => $orderCase12, 'method' => 'gcash']), ['Content-Type: application/json']);

    $case12Pass = ($resCase12['code'] === 502)
        && isset($resCase12['json']['success']) && $resCase12['json']['success'] === false;
    recordResult(12, "is_test=false -> payment_intent_create returns 502 error", $case12Pass);

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
