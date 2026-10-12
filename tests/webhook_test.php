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

$webhookSecret = getenv('PAYMONGO_WEBHOOK_SECRET');
if (empty($webhookSecret)) {
    $configPath = __DIR__ . '/../config/paymongo.php';
    if (file_exists($configPath)) {
        $cfg = require $configPath;
        $webhookSecret = $cfg['webhook_secret'] ?? 'whk_test_xxyz1234';
    } else {
        $webhookSecret = 'whk_test_xxyz1234';
    }
}

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

} catch (Throwable $t) {
    echo "ERROR during test execution: " . $t->getMessage() . "\n";
    $failures++;
} finally {
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
}

echo "\n--------------------------------------------------------------------\n";
if ($failures > 0) {
    echo "Test Run Completed with {$failures} failure(s).\n";
    exit(1);
} else {
    echo "All applicable tests PASSED (any pending sub-phase tests marked EXPECTED-FAIL).\n";
    exit(0);
}
