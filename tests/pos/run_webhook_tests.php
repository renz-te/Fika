<?php
/**
 * Hardened PayMongo Webhook & Checkout Test Suite (Phase 12C)
 * Port of all 12 H6 test cases + Production mock protection test case
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    die("Tests must be run from CLI.\n");
}

echo "====================================================================\n";
echo "PayMongo Webhook & Checkout Test Suite (Phase 12C)\n";
echo "====================================================================\n\n";

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

$baseUrl = getenv('FIKA_BASE_URL') ?: 'http://localhost/Fika/Fika';
$webhookUrl = rtrim($baseUrl, '/') . '/fika_pos/api/fika_pos_payment_webhook.php';
$fixturesDir = __DIR__ . '/../fixtures';
$envFilePath = dirname(__DIR__, 2) . '/.env';

$webhookSecret = Config::get('paymongo.webhook_secret') ?: getenv('PAYMONGO_WEBHOOK_SECRET') ?: 'whk_test_phase12c_secret';

// Ensure test environment has webhook secret set
putenv("PAYMONGO_WEBHOOK_SECRET={$webhookSecret}");
putenv("PAYMONGO_IS_TEST=true");

function computeSignature(string $rawBody, string $secret, ?int $timestamp = null): string {
    $ts = $timestamp ?? time();
    $hash = hash_hmac('sha256', $ts . '.' . $rawBody, $secret);
    return "t={$ts},te={$hash}";
}

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
        curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running > 0);

    $results = [];
    foreach ($handles as $ch) {
        $results[] = [
            'code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'body' => curl_multi_getcontent($ch)
        ];
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $results;
}

function loadFixture(string $fixturesDir, string $file, int $orderId, string $intentId, int $shiftId): string {
    $path = $fixturesDir . '/' . $file;
    if (!file_exists($path)) {
        throw new RuntimeException("Fixture file {$path} not found.");
    }
    $content = file_get_contents($path);
    $content = str_replace('{{ORDER_ID}}', (string)$orderId, $content);
    $content = str_replace('{{INTENT_ID}}', $intentId, $content);
    $content = str_replace('{{SHIFT_ID}}', (string)$shiftId, $content);
    return $content;
}

// -----------------------------------------------------------------------------
// Database Test Setup
// -----------------------------------------------------------------------------
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM payments WHERE order_id IN (SELECT id FROM orders WHERE branch_id = 991)");
$pdo->exec("DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE branch_id = 991)");
$pdo->exec("DELETE FROM orders WHERE branch_id = 991");
$pdo->exec("DELETE FROM recipes WHERE product_id = 9901");
$pdo->exec("DELETE FROM branch_products WHERE branch_id = 991 OR product_id = 9901");
$pdo->exec("DELETE FROM products WHERE id = 9901");
$pdo->exec("DELETE FROM inventory_ledger WHERE branch_id = 991");
$pdo->exec("DELETE FROM inventory_stock WHERE branch_id = 991");
$pdo->exec("DELETE FROM inventory_items WHERE id = 9901");
$pdo->exec("DELETE FROM cash_sessions WHERE branch_id = 991");
$pdo->exec("DELETE FROM users WHERE id = 9910");
$pdo->exec("DELETE FROM branches WHERE id = 991");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

$pdo->exec("INSERT INTO branches (id, name) VALUES (991, 'Webhook Test Branch')");
$pdo->exec("INSERT INTO users (id, username, password, role_id, branch_id) VALUES (9910, 'webhook_tester', 'x', 7, 991)");
$pdo->exec("INSERT INTO inventory_items (id, item_name, unit, unit_cost) VALUES (9901, 'Coffee Beans', 'g', 0.50)");
$pdo->exec("INSERT INTO inventory_stock (branch_id, inventory_id, current_stock) VALUES (991, 9901, 1000.00)");

$pdo->exec("
    INSERT INTO products (id, name, category, price, price_cents, is_active, created_at, updated_at) 
    VALUES (9901, 'Webhook Latte', 'Coffee', 150.00, 15000, 1, NOW(), NOW())
");
$pdo->exec("INSERT INTO branch_products (branch_id, product_id, is_active) VALUES (991, 9901, 1)");
$pdo->exec("INSERT INTO recipes (product_id, inventory_id, quantity_required) VALUES (9901, 9901, 18.00)");

$pdo->exec("
    INSERT INTO cash_sessions (id, branch_id, cashier_id, starting_cash, expected_cash, status, opened_at) 
    VALUES (9991, 991, 9910, 1000.00, 1000.00, 'OPEN', NOW())
");

$testShiftId = 9991;

$createTestOrder = function(string $intentId, float $totalDecimal = 150.00, string $orderStatus = 'PENDING', string $paymentStatus = 'UNPAID', string $method = 'GCASH') use ($pdo) {
    $orderNumber = 'TEST-ORD-' . uniqid();
    $stmt = $pdo->prepare("
        INSERT INTO orders (order_number, branch_id, pos_session_id, cashier_id, order_status, payment_status, total_amount, final_amount, payment_method, payment_reference, is_test, created_at, updated_at)
        VALUES (?, 991, 9991, 9910, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())
    ");
    $stmt->execute([$orderNumber, $orderStatus, $paymentStatus, $totalDecimal, $totalDecimal, $method, $intentId]);
    $orderId = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, quantity, price_snapshot, price_snapshot_cents, subtotal, created_at)
        VALUES (?, 9901, 1, ?, ?, ?, NOW())
    ");
    $cents = Money::toCentavos($totalDecimal);
    $itemStmt->execute([$orderId, $totalDecimal, $cents, $totalDecimal]);

    return $orderId;
};

try {
    // -------------------------------------------------------------------------
    // CASE 1 (H6.1): Replay — Same signed payment.paid delivered twice
    // -------------------------------------------------------------------------
    $intent1 = 'pi_whk_c1_' . uniqid();
    $order1 = $createTestOrder($intent1, 150.00);
    $payload1 = loadFixture($fixturesDir, 'payment_paid.json', $order1, $intent1, $testShiftId);
    $sig1 = computeSignature($payload1, $webhookSecret);

    $stockBefore1 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE inventory_id = 9901 AND branch_id = 991")->fetchColumn();

    $res1A = sendWebhook($webhookUrl, $payload1, $sig1);
    $stockAfter1A = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE inventory_id = 9901 AND branch_id = 991")->fetchColumn();

    $res1B = sendWebhook($webhookUrl, $payload1, $sig1); // Replay
    $stockAfter1B = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE inventory_id = 9901 AND branch_id = 991")->fetchColumn();

    $payCount1 = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE order_id = {$order1} AND status = 'paid'")->fetchColumn();
    $ordStatus1 = $pdo->query("SELECT payment_status FROM orders WHERE id = {$order1}")->fetchColumn();

    $pass1 = ($res1A['code'] === 200)
        && ($res1B['code'] === 200)
        && ($ordStatus1 === 'PAID')
        && ($payCount1 === 1)
        && (abs($stockBefore1 - $stockAfter1A - 18.00) < 0.01)
        && (abs($stockAfter1A - $stockAfter1B) < 0.001);

    assertTest("H6.1 Replay: delivered twice -> order PAID once, 1 deduction, 1 payment row paid", $pass1, "ResA: {$res1A['code']}, ResB: {$res1B['code']}, PayCount: {$payCount1}, Stock: {$stockAfter1A} vs {$stockAfter1B}");

    // -------------------------------------------------------------------------
    // CASE 2 (H6.2): Concurrency — Simultaneous delivery via curl_multi
    // -------------------------------------------------------------------------
    $intent2 = 'pi_whk_c2_' . uniqid();
    $order2 = $createTestOrder($intent2, 150.00);
    $payload2 = loadFixture($fixturesDir, 'payment_paid.json', $order2, $intent2, $testShiftId);
    $sig2 = computeSignature($payload2, $webhookSecret);

    $stockBefore2 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE inventory_id = 9901 AND branch_id = 991")->fetchColumn();

    $concRes2 = sendConcurrentWebhooks($webhookUrl, $payload2, $sig2);
    $stockAfter2 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE inventory_id = 9901 AND branch_id = 991")->fetchColumn();

    $payCount2 = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE order_id = {$order2} AND status = 'paid'")->fetchColumn();
    $ordStatus2 = $pdo->query("SELECT payment_status FROM orders WHERE id = {$order2}")->fetchColumn();

    $pass2 = ($concRes2[0]['code'] === 200)
        && ($concRes2[1]['code'] === 200)
        && ($ordStatus2 === 'PAID')
        && ($payCount2 === 1)
        && (abs($stockBefore2 - $stockAfter2 - 18.00) < 0.01);

    assertTest("H6.2 Concurrency: simultaneous delivery -> 1 deduction, 1 payment row paid, no race/deadlock", $pass2, "StockBefore: {$stockBefore2}, StockAfter: {$stockAfter2}, PayCount: {$payCount2}");

    // -------------------------------------------------------------------------
    // CASE 3 (H6.3): payment.failed after payment.paid -> stays PAID
    // -------------------------------------------------------------------------
    $intent3 = 'pi_whk_c3_' . uniqid();
    $order3 = $createTestOrder($intent3, 150.00);
    $payload3Paid = loadFixture($fixturesDir, 'payment_paid.json', $order3, $intent3, $testShiftId);
    $sig3Paid = computeSignature($payload3Paid, $webhookSecret);
    sendWebhook($webhookUrl, $payload3Paid, $sig3Paid);

    $stockBefore3Fail = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE inventory_id = 9901 AND branch_id = 991")->fetchColumn();

    $payload3Fail = loadFixture($fixturesDir, 'payment_failed.json', $order3, $intent3, $testShiftId);
    $sig3Fail = computeSignature($payload3Fail, $webhookSecret);
    $res3Fail = sendWebhook($webhookUrl, $payload3Fail, $sig3Fail);

    $stockAfter3Fail = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE inventory_id = 9901 AND branch_id = 991")->fetchColumn();
    $ordStatus3 = $pdo->query("SELECT payment_status FROM orders WHERE id = {$order3}")->fetchColumn();
    $payStatus3 = $pdo->query("SELECT status FROM payments WHERE order_id = {$order3} ORDER BY id DESC LIMIT 1")->fetchColumn();

    $pass3 = ($res3Fail['code'] === 200)
        && ($ordStatus3 === 'PAID')
        && ($payStatus3 === 'paid')
        && (abs($stockBefore3Fail - $stockAfter3Fail) < 0.001);

    assertTest("H6.3 payment.failed after payment.paid: order stays PAID, payment stays paid, inventory not refunded", $pass3, "Code: {$res3Fail['code']}, Status: {$ordStatus3}, PayStatus: {$payStatus3}");

    // -------------------------------------------------------------------------
    // CASE 4 (H6.4): payment.failed alone -> status=failed, order NOT paid, 0 deduction
    // -------------------------------------------------------------------------
    $intent4 = 'pi_whk_c4_' . uniqid();
    $order4 = $createTestOrder($intent4, 150.00);
    $payload4 = loadFixture($fixturesDir, 'payment_failed.json', $order4, $intent4, $testShiftId);
    $sig4 = computeSignature($payload4, $webhookSecret);

    $stockBefore4 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE inventory_id = 9901 AND branch_id = 991")->fetchColumn();
    $res4 = sendWebhook($webhookUrl, $payload4, $sig4);
    $stockAfter4 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE inventory_id = 9901 AND branch_id = 991")->fetchColumn();

    $ordStatus4 = $pdo->query("SELECT payment_status FROM orders WHERE id = {$order4}")->fetchColumn();
    $payStatus4 = $pdo->query("SELECT status FROM payments WHERE order_id = {$order4} ORDER BY id DESC LIMIT 1")->fetchColumn();

    $pass4 = ($res4['code'] === 200)
        && ($ordStatus4 === 'UNPAID')
        && ($payStatus4 === 'failed')
        && (abs($stockBefore4 - $stockAfter4) < 0.001);

    assertTest("H6.4 payment.failed alone: status=failed, order NOT paid, 0 inventory deduction", $pass4, "Code: {$res4['code']}, OrdStatus: {$ordStatus4}, PayStatus: {$payStatus4}");

    // -------------------------------------------------------------------------
    // CASE 5 (H6.5): payment.paid for VOIDED order -> 409, NOT paid, 0 deduction, audit row
    // -------------------------------------------------------------------------
    $intent5 = 'pi_whk_c5_' . uniqid();
    $order5 = $createTestOrder($intent5, 150.00, 'VOID', 'UNPAID');
    $payload5 = loadFixture($fixturesDir, 'payment_paid.json', $order5, $intent5, $testShiftId);
    $sig5 = computeSignature($payload5, $webhookSecret);

    $stockBefore5 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE inventory_id = 9901 AND branch_id = 991")->fetchColumn();
    $res5 = sendWebhook($webhookUrl, $payload5, $sig5);
    $stockAfter5 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE inventory_id = 9901 AND branch_id = 991")->fetchColumn();

    $ordStatus5 = $pdo->query("SELECT payment_status, order_status FROM orders WHERE id = {$order5}")->fetch();
    $auditCount5 = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'VOID_ORDER_PAYMENT_REJECTED'")->fetchColumn();

    $pass5 = ($res5['code'] === 409)
        && ($ordStatus5['payment_status'] === 'UNPAID')
        && (abs($stockBefore5 - $stockAfter5) < 0.001)
        && ($auditCount5 > 0);

    assertTest("H6.5 payment.paid for VOID order: 409, NOT paid, 0 deduction, audit row written", $pass5, "Code: {$res5['code']}, AuditCount: {$auditCount5}");

    // -------------------------------------------------------------------------
    // CASE 6 (H6.6): payment.paid for CASH order -> 409, 0 deduction, audit row
    // -------------------------------------------------------------------------
    $intent6 = 'pi_whk_c6_' . uniqid();
    $order6 = $createTestOrder($intent6, 150.00, 'PAID', 'PAID', 'CASH');
    $payload6 = loadFixture($fixturesDir, 'payment_paid.json', $order6, $intent6, $testShiftId);
    $sig6 = computeSignature($payload6, $webhookSecret);

    $stockBefore6 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE inventory_id = 9901 AND branch_id = 991")->fetchColumn();
    $res6 = sendWebhook($webhookUrl, $payload6, $sig6);
    $stockAfter6 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE inventory_id = 9901 AND branch_id = 991")->fetchColumn();

    $auditCount6 = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'CASH_ORDER_PAYMENT_REJECTED'")->fetchColumn();

    $pass6 = ($res6['code'] === 409)
        && (abs($stockBefore6 - $stockAfter6) < 0.001)
        && ($auditCount6 > 0);

    assertTest("H6.6 payment.paid for CASH order: 409, 0 deduction, no paid payment row, audit row written", $pass6, "Code: {$res6['code']}, AuditCount: {$auditCount6}");

    // -------------------------------------------------------------------------
    // CASE 7 (H6.7): Amount mismatch -> 400, NOT paid, audit row
    // -------------------------------------------------------------------------
    $intent7 = 'pi_whk_c7_' . uniqid();
    $order7 = $createTestOrder($intent7, 150.00); // 15000 cents expected, payload sends 9999
    $payload7 = loadFixture($fixturesDir, 'paid_amount_mismatch.json', $order7, $intent7, $testShiftId);
    $sig7 = computeSignature($payload7, $webhookSecret);

    $stockBefore7 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE inventory_id = 9901 AND branch_id = 991")->fetchColumn();
    $res7 = sendWebhook($webhookUrl, $payload7, $sig7);
    $stockAfter7 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE inventory_id = 9901 AND branch_id = 991")->fetchColumn();

    $ordStatus7 = $pdo->query("SELECT payment_status FROM orders WHERE id = {$order7}")->fetchColumn();
    $auditCount7 = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'PAYMENT_MISMATCH'")->fetchColumn();

    $pass7 = ($res7['code'] === 400)
        && ($ordStatus7 === 'UNPAID')
        && (abs($stockBefore7 - $stockAfter7) < 0.001)
        && ($auditCount7 > 0);

    assertTest("H6.7 Amount mismatch: 400, NOT paid, payment_status unchanged, audit row written", $pass7, "Code: {$res7['code']}, Status: {$ordStatus7}, AuditCount: {$auditCount7}");

    // -------------------------------------------------------------------------
    // CASE 8 (H6.8): Missing amount -> 400, NOT paid, 0 deduction, audit row
    // -------------------------------------------------------------------------
    $intent8 = 'pi_whk_c8_' . uniqid();
    $order8 = $createTestOrder($intent8, 150.00);
    $payload8 = loadFixture($fixturesDir, 'paid_no_amount.json', $order8, $intent8, $testShiftId);
    $sig8 = computeSignature($payload8, $webhookSecret);

    $stockBefore8 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE inventory_id = 9901 AND branch_id = 991")->fetchColumn();
    $res8 = sendWebhook($webhookUrl, $payload8, $sig8);
    $stockAfter8 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE inventory_id = 9901 AND branch_id = 991")->fetchColumn();

    $ordStatus8 = $pdo->query("SELECT payment_status FROM orders WHERE id = {$order8}")->fetchColumn();
    $auditCount8 = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'PAYMENT_AMOUNT_INVALID'")->fetchColumn();

    $pass8 = ($res8['code'] === 400)
        && ($ordStatus8 === 'UNPAID')
        && (abs($stockBefore8 - $stockAfter8) < 0.001)
        && ($auditCount8 > 0);

    assertTest("H6.8 Missing amount: 400, NOT paid, 0 deduction, PAYMENT_AMOUNT_INVALID audit row", $pass8, "Code: {$res8['code']}, Status: {$ordStatus8}, AuditCount: {$auditCount8}");

    // -------------------------------------------------------------------------
    // CASE 9 (H6.9): Bad signature, missing header, malformed header -> all 401
    // -------------------------------------------------------------------------
    $intent9 = 'pi_whk_c9_' . uniqid();
    $order9 = $createTestOrder($intent9, 150.00);
    $payload9 = loadFixture($fixturesDir, 'payment_paid.json', $order9, $intent9, $testShiftId);

    $stockBefore9 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE inventory_id = 9901 AND branch_id = 991")->fetchColumn();

    $res9Missing = sendWebhook($webhookUrl, $payload9, null);
    $res9Malformed = sendWebhook($webhookUrl, $payload9, 'bad_malformed_header');
    $res9BadSig = sendWebhook($webhookUrl, $payload9, 't=' . time() . ',te=invalid_signature_hash_123');

    $stockAfter9 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE inventory_id = 9901 AND branch_id = 991")->fetchColumn();
    $ordStatus9 = $pdo->query("SELECT payment_status FROM orders WHERE id = {$order9}")->fetchColumn();

    $pass9 = ($res9Missing['code'] === 401)
        && ($res9Malformed['code'] === 401)
        && ($res9BadSig['code'] === 401)
        && ($ordStatus9 === 'UNPAID')
        && (abs($stockBefore9 - $stockAfter9) < 0.001);

    assertTest("H6.9 Bad signature, missing header, malformed header: all 401, no DB change", $pass9, "Missing: {$res9Missing['code']}, Malformed: {$res9Malformed['code']}, BadSig: {$res9BadSig['code']}");

    // -------------------------------------------------------------------------
    // CASE 10 (H6.10): Missing webhook secret in config -> 503
    // -------------------------------------------------------------------------
    $intent10 = 'pi_whk_c10_' . uniqid();
    $order10 = $createTestOrder($intent10, 150.00);
    $payload10 = loadFixture($fixturesDir, 'payment_paid.json', $order10, $intent10, $testShiftId);
    $sig10 = computeSignature($payload10, $webhookSecret);

    $stockBefore10 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE inventory_id = 9901 AND branch_id = 991")->fetchColumn();

    $envBackup = file_exists($envFilePath) ? file_get_contents($envFilePath) : '';
    try {
        $envLines = explode("\n", $envBackup);
        $filtered = array_filter($envLines, fn($l) => !str_starts_with(trim($l), 'PAYMONGO_WEBHOOK_SECRET='));
        $filtered[] = 'PAYMONGO_WEBHOOK_SECRET=';
        file_put_contents($envFilePath, implode("\n", $filtered));
        putenv("PAYMONGO_WEBHOOK_SECRET=");

        $res10 = sendWebhook($webhookUrl, $payload10, $sig10);
    } finally {
        file_put_contents($envFilePath, $envBackup);
        putenv("PAYMONGO_WEBHOOK_SECRET={$webhookSecret}");
    }

    $stockAfter10 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE inventory_id = 9901 AND branch_id = 991")->fetchColumn();
    $ordStatus10 = $pdo->query("SELECT payment_status FROM orders WHERE id = {$order10}")->fetchColumn();

    $pass10 = ($res10['code'] === 503)
        && ($ordStatus10 === 'UNPAID')
        && (abs($stockBefore10 - $stockAfter10) < 0.001);

    assertTest("H6.10 Missing webhook secret in config: 503, no database change", $pass10, "Code: {$res10['code']}, Status: {$ordStatus10}");

    // -------------------------------------------------------------------------
    // CASE 11 (H6.11): Timestamp tolerance — > 300s past / future skew -> 401
    // -------------------------------------------------------------------------
    $intent11 = 'pi_whk_c11_' . uniqid();
    $order11 = $createTestOrder($intent11, 150.00);
    $payload11 = loadFixture($fixturesDir, 'payment_paid.json', $order11, $intent11, $testShiftId);

    $stockBefore11 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE inventory_id = 9901 AND branch_id = 991")->fetchColumn();

    // Expired timestamp (305 seconds ago)
    $sigOld = computeSignature($payload11, $webhookSecret, time() - 305);
    $res11Old = sendWebhook($webhookUrl, $payload11, $sigOld);

    // Future timestamp skew (+305 seconds)
    $sigFuture = computeSignature($payload11, $webhookSecret, time() + 305);
    $res11Future = sendWebhook($webhookUrl, $payload11, $sigFuture);

    $stockAfter11 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE inventory_id = 9901 AND branch_id = 991")->fetchColumn();
    $ordStatus11 = $pdo->query("SELECT payment_status FROM orders WHERE id = {$order11}")->fetchColumn();

    $pass11 = ($res11Old['code'] === 401)
        && ($res11Future['code'] === 401)
        && ($ordStatus11 === 'UNPAID')
        && (abs($stockBefore11 - $stockAfter11) < 0.001);

    assertTest("H6.11 Timestamp tolerance: >300s past (401) & future skew (401), DB unchanged", $pass11, "Old: {$res11Old['code']}, Future: {$res11Future['code']}");

    // -------------------------------------------------------------------------
    // CASE 12 (H6.12): payment.paid for REFUNDED order -> 409, audit row
    // -------------------------------------------------------------------------
    $intent12 = 'pi_whk_c12_' . uniqid();
    $order12 = $createTestOrder($intent12, 150.00, 'REFUNDED', 'UNPAID');
    $payload12 = loadFixture($fixturesDir, 'payment_paid.json', $order12, $intent12, $testShiftId);
    $sig12 = computeSignature($payload12, $webhookSecret);

    $stockBefore12 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE inventory_id = 9901 AND branch_id = 991")->fetchColumn();
    $res12 = sendWebhook($webhookUrl, $payload12, $sig12);
    $stockAfter12 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE inventory_id = 9901 AND branch_id = 991")->fetchColumn();

    $ordStatus12 = $pdo->query("SELECT payment_status FROM orders WHERE id = {$order12}")->fetchColumn();
    $auditCount12 = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'REFUNDED_ORDER_PAYMENT_REJECTED'")->fetchColumn();

    $pass12 = ($res12['code'] === 409)
        && ($ordStatus12 === 'UNPAID')
        && (abs($stockBefore12 - $stockAfter12) < 0.001)
        && ($auditCount12 > 0);

    assertTest("H6.12 payment.paid for REFUNDED order: 409, NOT paid, 0 deduction, audit row written", $pass12, "Code: {$res12['code']}, AuditCount: {$auditCount12}");

    // -------------------------------------------------------------------------
    // CASE 13 (Mock Protection): In production mode, mock IDs rejected with 400 + audit row
    // -------------------------------------------------------------------------
    $intent13 = 'cs_test_mock_' . uniqid();
    $order13 = $createTestOrder($intent13, 150.00);
    $payload13 = loadFixture($fixturesDir, 'payment_paid.json', $order13, $intent13, $testShiftId);
    $sig13 = computeSignature($payload13, $webhookSecret);

    $stockBefore13 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE inventory_id = 9901 AND branch_id = 991")->fetchColumn();

    // Set production mode (is_test = false)
    putenv("PAYMONGO_IS_TEST=false");
    $envBackup13 = file_exists($envFilePath) ? file_get_contents($envFilePath) : '';
    try {
        $envLines = explode("\n", $envBackup13);
        $filtered = array_filter($envLines, fn($l) => !str_starts_with(trim($l), 'PAYMONGO_IS_TEST='));
        $filtered[] = 'PAYMONGO_IS_TEST=false';
        file_put_contents($envFilePath, implode("\n", $filtered));

        $res13 = sendWebhook($webhookUrl, $payload13, $sig13);
    } finally {
        file_put_contents($envFilePath, $envBackup13);
        putenv("PAYMONGO_IS_TEST=true");
    }

    $stockAfter13 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE inventory_id = 9901 AND branch_id = 991")->fetchColumn();
    $ordStatus13 = $pdo->query("SELECT payment_status FROM orders WHERE id = {$order13}")->fetchColumn();
    $auditCount13 = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'MOCK_PAYMENT_REJECTED'")->fetchColumn();

    $pass13 = ($res13['code'] === 400)
        && ($ordStatus13 === 'UNPAID')
        && (abs($stockBefore13 - $stockAfter13) < 0.001)
        && ($auditCount13 > 0);

    assertTest("Mock checkout rejected outside test mode: 400, NOT paid, MOCK_PAYMENT_REJECTED audit row", $pass13, "Code: {$res13['code']}, Status: {$ordStatus13}, AuditCount: {$auditCount13}");

} finally {
    // Teardown Test Data
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("DELETE FROM payments WHERE order_id IN (SELECT id FROM orders WHERE branch_id = 991)");
    $pdo->exec("DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE branch_id = 991)");
    $pdo->exec("DELETE FROM orders WHERE branch_id = 991");
    $pdo->exec("DELETE FROM recipes WHERE product_id = 9901");
    $pdo->exec("DELETE FROM branch_products WHERE branch_id = 991 OR product_id = 9901");
    $pdo->exec("DELETE FROM products WHERE id = 9901");
    $pdo->exec("DELETE FROM inventory_ledger WHERE branch_id = 991");
    $pdo->exec("DELETE FROM inventory_stock WHERE branch_id = 991");
    $pdo->exec("DELETE FROM inventory_items WHERE id = 9901");
    $pdo->exec("DELETE FROM cash_sessions WHERE branch_id = 991");
    $pdo->exec("DELETE FROM users WHERE id = 9910");
    $pdo->exec("DELETE FROM branches WHERE id = 991");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
}

echo "\n=======================================================\n";
if ($failed === 0) {
    echo "ALL 13 PAYMONGO WEBHOOK & CHECKOUT TESTS PASSED!\n";
    exit(0);
} else {
    echo "{$failed} TEST(S) FAILED.\n";
    exit(1);
}
