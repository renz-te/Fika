<?php
require_once __DIR__ . '/../../app/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    die("Tests must be run from CLI.\n");
}

echo "====================================================================\n";
echo "POS Regression & Concurrency Trust Suite (Phase 12E)\n";
echo "====================================================================\n\n";

$failures = 0;
$errorResponses = [];
$createdSessionFiles = [];
$createdOrderIds = [];
$createdCashSessionIds = [];
$createdUserIds = [];
$createdBranchIds = [];
$createdProductIds = [];
$createdInventoryIds = [];
$createdDeviceIds = [];

$baseUrl = 'http://127.0.0.1/Fika/Fika';

function sendHttpRequest(string $url, string $method = 'GET', $data = [], array $headers = [], ?string $cookie = null): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (is_string($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
    }
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    if ($cookie !== null) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
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

function executeParallelRequests(array $requests): array {
    $mh = curl_multi_init();
    $handles = [];
    foreach ($requests as $idx => $req) {
        $ch = curl_init($req['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if (($req['method'] ?? 'GET') === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $req['data'] ?? '');
        }
        if (!empty($req['headers'])) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $req['headers']);
        }
        if (!empty($req['cookie'])) {
            curl_setopt($ch, CURLOPT_COOKIE, $req['cookie']);
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_multi_add_handle($mh, $ch);
        $handles[$idx] = $ch;
    }

    $active = null;
    do {
        $mrc = curl_multi_exec($mh, $active);
        if ($active) {
            curl_multi_select($mh, 0.05);
        }
    } while ($active && $mrc == CURLM_OK);

    $results = [];
    foreach ($handles as $idx => $ch) {
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $body = (string)curl_multi_getcontent($ch);
        $json = json_decode($body, true);
        $curlError = curl_error($ch);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);

        $results[$idx] = [
            'code' => $code,
            'body' => $body,
            'json' => $json,
            'error' => $curlError
        ];
    }
    curl_multi_close($mh);
    return $results;
}

function createSessionWithUser(?array $userData, ?string $csrf = null, ?int $posSessionId = null): array {
    global $createdSessionFiles;
    $sessId = bin2hex(random_bytes(16));
    $csrfToken = $csrf ?: bin2hex(random_bytes(32));
    $savePath = session_save_path() ?: sys_get_temp_dir();
    $sessFile = rtrim($savePath, '/\\') . '/sess_' . $sessId;
    
    $data = [];
    if ($userData !== null) {
        $data['user'] = $userData;
    }
    if ($csrfToken !== null) {
        $data['csrf_token'] = $csrfToken;
    }
    if ($posSessionId !== null) {
        $data['pos_session_id'] = $posSessionId;
    }

    $serialized = '';
    foreach ($data as $k => $v) {
        $serialized .= $k . '|' . serialize($v);
    }
    file_put_contents($sessFile, $serialized);
    $createdSessionFiles[] = $sessFile;

    return [
        'sess_id' => $sessId,
        'cookie' => 'PHPSESSID=' . $sessId,
        'csrf' => $csrfToken
    ];
}

function recordResult(int $caseNum, string $description, bool $passed, ?array $debug = null): bool {
    global $failures;
    if ($passed) {
        echo "[PASS] Case {$caseNum}: {$description}\n";
        return true;
    }
    echo "[FAIL] Case {$caseNum}: {$description}\n";
    if ($debug) {
        echo "       Debug info: " . json_encode($debug) . "\n";
    }
    $failures++;
    return false;
}

global $pdo;

try {
    // Endpoints
    $kioskOrderUrl     = rtrim($baseUrl, '/') . '/fika_pos/api/fika_pos_kiosk_order.php';
    $orderCreateUrl    = rtrim($baseUrl, '/') . '/fika_pos/api/fika_pos_order_create.php';
    $orderPayCashUrl   = rtrim($baseUrl, '/') . '/fika_pos/api/fika_pos_order_pay_cash.php';
    $orderVoidRefundUrl= rtrim($baseUrl, '/') . '/fika_pos/api/fika_pos_order_void_refund.php';
    $orderCancelUrl    = rtrim($baseUrl, '/') . '/fika_pos/api/fika_pos_order_cancel.php';
    $orderCompleteUrl  = rtrim($baseUrl, '/') . '/fika_pos/api/fika_pos_order_complete.php';
    $registerOpenUrl   = rtrim($baseUrl, '/') . '/fika_pos/api/fika_pos_register_open.php';
    $registerCloseUrl  = rtrim($baseUrl, '/') . '/fika_pos/api/fika_pos_register_close.php';
    $paymentCreateUrl  = rtrim($baseUrl, '/') . '/fika_pos/api/fika_pos_payment_create.php';

    // 0. Pre-test cleanup
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("DELETE FROM cash_sessions WHERE id IN (99111, 99222)");
    $pdo->exec("DELETE FROM users WHERE id IN (1001, 1002, 1005, 1006)");
    $pdo->exec("DELETE FROM devices WHERE id = 9911");
    $pdo->exec("DELETE FROM recipes WHERE product_id IN (1, 9929) OR inventory_id IN (9929)");
    $pdo->exec("DELETE FROM branch_products WHERE product_id IN (1, 9929)");
    $pdo->exec("DELETE FROM products WHERE id IN (1, 9929)");
    $pdo->exec("DELETE FROM inventory_stock WHERE inventory_id IN (9929)");
    $pdo->exec("DELETE FROM inventory_items WHERE id IN (9929)");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // 1. Setup Branches
    $pdo->exec("INSERT INTO branches (id, name, address) VALUES (1, 'General Trias Branch', 'General Trias City, Cavite') ON DUPLICATE KEY UPDATE name=name");
    $pdo->exec("INSERT INTO branches (id, name, address) VALUES (2, 'Trece Martires Branch', 'Trece Martires City, Cavite') ON DUPLICATE KEY UPDATE name=name");


    // 2. Setup Products & Modifiers for Cases 1-5 & 10
    // Product 1: 120.00 (12000 cents), Modifier 2: 30.00 (3000 cents)
    $pdo->exec("
        INSERT INTO products (id, name, category, price, price_cents, is_active, created_at, updated_at) 
        VALUES (1, 'Americano Trust Test', 'Coffee', 120.00, 12000, 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE price = 120.00, price_cents = 12000, is_active = 1, deleted_at = NULL
    ");
    $createdProductIds[] = 1;

    $pdo->exec("
        INSERT INTO branch_products (branch_id, product_id, is_active) 
        VALUES (1, 1, 1), (2, 1, 1)
        ON DUPLICATE KEY UPDATE is_active = 1
    ");

    $pdo->exec("
        INSERT INTO modifiers (id, name, price, is_active) 
        VALUES (2, 'Extra Shot Test', 30.00, 1)
        ON DUPLICATE KEY UPDATE price = 30.00, is_active = 1
    ");

    // 3. Setup Kiosk Device for Branch 1
    $deviceTokenB1 = 'trust_kiosk_token_' . bin2hex(random_bytes(16));
    $pdo->prepare("
        INSERT INTO devices (id, branch_id, device_name, device_token, status)
        VALUES (9911, 1, 'Kiosk Trust Test', ?, 'Active')
        ON DUPLICATE KEY UPDATE device_token = ?, branch_id = 1, status = 'Active'
    ")->execute([$deviceTokenB1, $deviceTokenB1]);
    $createdDeviceIds[] = 9911;

    // 4. Setup Test Supervisors in users table (Role 6 = BM with pos.void_order & pos.refund_order)
    $supPinBranch1 = '112233';
    $supHashB1 = password_hash($supPinBranch1, PASSWORD_DEFAULT);
    $pdo->prepare("
        INSERT INTO users (id, username, password, role_id, branch_id, created_at)
        VALUES (1005, 'sup_trust_b1', ?, 6, 1, NOW())
        ON DUPLICATE KEY UPDATE password = ?, role_id = 6, branch_id = 1, deleted_at = NULL
    ")->execute([$supHashB1, $supHashB1]);
    $createdUserIds[] = 1005;

    $supPinBranch2 = '445566';
    $supHashB2 = password_hash($supPinBranch2, PASSWORD_DEFAULT);
    $pdo->prepare("
        INSERT INTO users (id, username, password, role_id, branch_id, created_at)
        VALUES (1006, 'sup_trust_b2', ?, 6, 2, NOW())
        ON DUPLICATE KEY UPDATE password = ?, role_id = 6, branch_id = 2, deleted_at = NULL
    ")->execute([$supHashB2, $supHashB2]);
    $createdUserIds[] = 1006;

    // Setup Cashier Users (Role 7 = STAFF with pos.access)
    $pdo->prepare("
        INSERT INTO users (id, username, password, role_id, branch_id, created_at)
        VALUES (1001, 'cashier_trust_b1', 'hash', 7, 1, NOW())
        ON DUPLICATE KEY UPDATE role_id = 7, branch_id = 1, deleted_at = NULL
    ")->execute();
    $createdUserIds[] = 1001;

    $pdo->prepare("
        INSERT INTO users (id, username, password, role_id, branch_id, created_at)
        VALUES (1002, 'cashier_trust_b2', 'hash', 7, 2, NOW())
        ON DUPLICATE KEY UPDATE role_id = 7, branch_id = 2, deleted_at = NULL
    ")->execute();
    $createdUserIds[] = 1002;

    // Setup Active Shift for Branch 1 Cashier (Session ID 99111)
    $pdo->exec("UPDATE cash_sessions SET status = 'CLOSED' WHERE branch_id IN (1, 2) AND status = 'OPEN'");
    $pdo->exec("
        INSERT INTO cash_sessions (id, branch_id, cashier_id, starting_cash, expected_cash, status, opened_at)
        VALUES (99111, 1, 1001, 1000.00, 1000.00, 'OPEN', NOW())
        ON DUPLICATE KEY UPDATE status = 'OPEN', expected_cash = 1000.00
    ");
    $createdCashSessionIds[] = 99111;

    // 5. Setup Real Staff Role Sessions
    $cashierB1 = createSessionWithUser([
        'id' => 1001,
        'username' => 'cashier_trust_b1',
        'name' => 'Cashier Branch 1',
        'role_id' => 7,
        'branch_id' => 1
    ], null, 99111);

    $cashierB2 = createSessionWithUser([
        'id' => 1002,
        'username' => 'cashier_trust_b2',
        'name' => 'Cashier Branch 2',
        'role_id' => 7,
        'branch_id' => 2
    ], null, 99222);

    $supervisorB1 = createSessionWithUser([
        'id' => 1005,
        'username' => 'sup_trust_b1',
        'name' => 'Test Supervisor B1',
        'role_id' => 6,
        'branch_id' => 1
    ], null, 99111);

    // Helper to create test orders directly in DB
    $createCustomOrder = function(int $branchId, string $payStatus = 'UNPAID', string $orderStatus = 'PENDING', float $total = 150.00) use ($pdo, &$createdOrderIds): int {
        $orderNum = 'ORD-' . strtoupper(bin2hex(random_bytes(4)));
        $stmt = $pdo->prepare("
            INSERT INTO orders (order_number, branch_id, total_amount, vat_amount, discount_amount, final_amount, payment_status, payment_method, order_status, is_test, created_at, updated_at)
            VALUES (?, ?, ?, 16.07, 0.00, ?, ?, 'CASH', ?, 1, NOW(), NOW())
        ");
        $stmt->execute([$orderNum, $branchId, $total, $total, $payStatus, $orderStatus]);
        $id = (int)$pdo->lastInsertId();
        $createdOrderIds[] = $id;

        $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, quantity, price_snapshot, price_snapshot_cents, subtotal, created_at)
            VALUES (?, 1, 1, ?, ?, ?, NOW())
        ")->execute([$id, $total, (int)($total * 100), $total]);

        return $id;
    };

    // -------------------------------------------------------------------------
    // CASE 1: Kiosk order creation with payment_status=PAID is forced to UNPAID
    // -------------------------------------------------------------------------
    $c1Payload = json_encode([
        'items' => [['id' => 1, 'qty' => 1]],
        'payment_status' => 'PAID',
        'payment_method' => 'CASH'
    ]);
    $resC1 = sendHttpRequest($kioskOrderUrl, 'POST', $c1Payload, [
        'Content-Type: application/json',
        'X-Device-Token: ' . $deviceTokenB1
    ]);
    $c1OrderId = (int)($resC1['json']['order_id'] ?? 0);
    if ($c1OrderId > 0) $createdOrderIds[] = $c1OrderId;

    $stmtC1 = $pdo->prepare("SELECT payment_status FROM orders WHERE id = ?");
    $stmtC1->execute([$c1OrderId]);
    $c1DbStatus = $stmtC1->fetchColumn();

    $c1Pass = ($resC1['code'] === 200) && ($c1DbStatus === 'UNPAID') && ($resC1['json']['payment_status'] === 'UNPAID');
    recordResult(1, "unauthenticated kiosk order with payment_status=PAID forced to UNPAID", $c1Pass, ['resp' => $resC1, 'db_status' => $c1DbStatus]);

    // -------------------------------------------------------------------------
    // CASE 2: Kiosk order creation with spoofed cashier_id ignores spoofed cashier
    // -------------------------------------------------------------------------
    $c2Payload = json_encode([
        'items' => [['id' => 1, 'qty' => 1]],
        'cashier_id' => 1
    ]);
    $resC2 = sendHttpRequest($kioskOrderUrl, 'POST', $c2Payload, [
        'Content-Type: application/json',
        'X-Device-Token: ' . $deviceTokenB1
    ]);
    $c2OrderId = (int)($resC2['json']['order_id'] ?? 0);
    if ($c2OrderId > 0) $createdOrderIds[] = $c2OrderId;

    $stmtC2 = $pdo->prepare("SELECT cashier_id FROM orders WHERE id = ?");
    $stmtC2->execute([$c2OrderId]);
    $c2DbRow = $stmtC2->fetch();

    $c2Pass = ($resC2['code'] === 200) && ($c2DbRow['cashier_id'] === null);
    recordResult(2, "unauthenticated kiosk order ignores spoofed cashier_id (NULL stored)", $c2Pass, ['row' => $c2DbRow]);

    // -------------------------------------------------------------------------
    // CASE 3: Constraint 1: Kiosk caller discount forced to 0.00
    // -------------------------------------------------------------------------
    $c3Payload = json_encode([
        'items' => [['id' => 1, 'qty' => 1]],
        'discount_type' => 'SENIOR',
        'discount_amount' => 30.00
    ]);
    $resC3 = sendHttpRequest($kioskOrderUrl, 'POST', $c3Payload, [
        'Content-Type: application/json',
        'X-Device-Token: ' . $deviceTokenB1
    ]);
    $c3OrderId = (int)($resC3['json']['order_id'] ?? 0);
    if ($c3OrderId > 0) $createdOrderIds[] = $c3OrderId;

    $stmtC3 = $pdo->prepare("SELECT total_amount, discount_amount FROM orders WHERE id = ?");
    $stmtC3->execute([$c3OrderId]);
    $c3DbRow = $stmtC3->fetch();

    $c3Pass = ($resC3['code'] === 200)
        && ((float)$c3DbRow['discount_amount'] === 0.0)
        && ($resC3['json']['discount_type'] === null)
        && ((float)$resC3['json']['discount_amount'] === 0.0)
        && ((float)$c3DbRow['total_amount'] === 120.00);
    recordResult(3, "Constraint 1: kiosk caller discount forced to 0.00 and discount_type to NULL", $c3Pass, ['row' => $c3DbRow, 'resp' => $resC3['json']]);

    // -------------------------------------------------------------------------
    // CASE 4: Client-sent total_price & unit price tampering is ignored
    // -------------------------------------------------------------------------
    $c4Payload = json_encode([
        'items' => [['id' => 1, 'qty' => 2, 'price' => 1.00]],
        'total' => 2.00,
        'total_amount' => 2.00,
        'final_amount' => 2.00
    ]);
    $resC4 = sendHttpRequest($kioskOrderUrl, 'POST', $c4Payload, [
        'Content-Type: application/json',
        'X-Device-Token: ' . $deviceTokenB1
    ]);
    $c4OrderId = (int)($resC4['json']['order_id'] ?? 0);
    if ($c4OrderId > 0) $createdOrderIds[] = $c4OrderId;

    $stmtC4 = $pdo->prepare("SELECT total_amount FROM orders WHERE id = ?");
    $stmtC4->execute([$c4OrderId]);
    $c4DbTotal = (float)$stmtC4->fetchColumn();

    $c4Pass = ($resC4['code'] === 200) && ($c4DbTotal === 240.00) && ((float)$resC4['json']['total_amount'] === 240.00);
    recordResult(4, "client-sent total_price tampering ignored (server calculates DB sum 240.00)", $c4Pass, ['db_total' => $c4DbTotal]);

    // -------------------------------------------------------------------------
    // CASE 5: Client-sent is_test is ignored
    // -------------------------------------------------------------------------
    $c5Payload = json_encode([
        'items' => [['id' => 1, 'qty' => 1]],
        'is_test' => 1
    ]);
    $resC5 = sendHttpRequest($kioskOrderUrl, 'POST', $c5Payload, [
        'Content-Type: application/json',
        'X-Device-Token: ' . $deviceTokenB1
    ]);
    $c5OrderId = (int)($resC5['json']['order_id'] ?? 0);
    if ($c5OrderId > 0) $createdOrderIds[] = $c5OrderId;

    $stmtC5 = $pdo->prepare("SELECT is_test FROM orders WHERE id = ?");
    $stmtC5->execute([$c5OrderId]);
    $c5DbIsTest = (int)$stmtC5->fetchColumn();

    $c5Pass = ($resC5['code'] === 200) && ($c5DbIsTest === 0);
    recordResult(5, "client-sent is_test ignored (stored matches server value 0)", $c5Pass, ['db_is_test' => $c5DbIsTest]);

    // -------------------------------------------------------------------------
    // CASE 6: fika_pos_order_pay_cash.php returns 401 when unauthenticated
    // -------------------------------------------------------------------------
    $c6Order = $createCustomOrder(1, 'UNPAID', 'PENDING');
    $c6Payload = json_encode(['order_id' => $c6Order, 'amount_paid' => 150.00, 'idempotency_key' => 'IDEMP_6']);
    $resC6 = sendHttpRequest($orderPayCashUrl, 'POST', $c6Payload, ['Content-Type: application/json']);
    $errorResponses[] = $resC6['body'];

    $c6Pass = ($resC6['code'] === 401);
    recordResult(6, "fika_pos_order_pay_cash.php unauthenticated POST returns 401", $c6Pass, ['code' => $resC6['code']]);

    // -------------------------------------------------------------------------
    // CASE 7: Cashier for Branch 1 attempting fika_pos_order_pay_cash.php on Branch 2 order returns 403
    // -------------------------------------------------------------------------
    $c7Order = $createCustomOrder(2, 'UNPAID', 'PENDING');
    $c7Payload = json_encode(['order_id' => $c7Order, 'amount_paid' => 150.00, 'idempotency_key' => 'IDEMP_7']);
    $resC7 = sendHttpRequest($orderPayCashUrl, 'POST', $c7Payload, [
        'Content-Type: application/json',
        'X-CSRF-Token: ' . $cashierB1['csrf']
    ], $cashierB1['cookie']);
    $errorResponses[] = $resC7['body'];

    $c7Pass = ($resC7['code'] === 403);
    recordResult(7, "Cashier B1 attempting fika_pos_order_pay_cash.php on Branch 2 order returns 403", $c7Pass, ['code' => $resC7['code']]);

    // -------------------------------------------------------------------------
    // CASE 8: Constraint 3: fika_pos_order_pay_cash.php on already-PAID order returns 409 + audit log
    // -------------------------------------------------------------------------
    $c8Order = $createCustomOrder(1, 'PAID', 'COMPLETED');
    $c8Payload = json_encode(['order_id' => $c8Order, 'amount_paid' => 150.00, 'idempotency_key' => 'IDEMP_8']);
    $resC8 = sendHttpRequest($orderPayCashUrl, 'POST', $c8Payload, [
        'Content-Type: application/json',
        'X-CSRF-Token: ' . $cashierB1['csrf']
    ], $cashierB1['cookie']);
    $errorResponses[] = $resC8['body'];

    $stmtC8Audit = $pdo->prepare("SELECT id FROM audit_logs WHERE action = 'PAYMENT_REJECTED' AND details LIKE ?");
    $stmtC8Audit->execute(["%Order {$c8Order}%"]);
    $c8AuditExists = (bool)$stmtC8Audit->fetch();

    $c8Pass = ($resC8['code'] === 409) && $c8AuditExists;
    recordResult(8, "Constraint 3: fika_pos_order_pay_cash.php on already-PAID order returns 409 + audit log", $c8Pass, ['code' => $resC8['code'], 'audit' => $c8AuditExists]);

    // -------------------------------------------------------------------------
    // CASE 9: fika_pos_order_pay_cash.php on VOID order returns 409
    // -------------------------------------------------------------------------
    $c9Order = $createCustomOrder(1, 'UNPAID', 'VOID');
    $c9Payload = json_encode(['order_id' => $c9Order, 'amount_paid' => 150.00, 'idempotency_key' => 'IDEMP_9']);
    $resC9 = sendHttpRequest($orderPayCashUrl, 'POST', $c9Payload, [
        'Content-Type: application/json',
        'X-CSRF-Token: ' . $cashierB1['csrf']
    ], $cashierB1['cookie']);
    $errorResponses[] = $resC9['body'];

    $c9Pass = ($resC9['code'] === 409);
    recordResult(9, "Constraint 4: fika_pos_order_pay_cash.php on VOID order returns 409", $c9Pass, ['code' => $resC9['code']]);

    // -------------------------------------------------------------------------
    // CASE 10: Authorized Cashier on Branch 1 pays Branch 1 order -> 200, marked PAID
    // -------------------------------------------------------------------------
    $c10Order = $createCustomOrder(1, 'UNPAID', 'PENDING');
    $c10Payload = json_encode([
        'order_id' => $c10Order,
        'amount_paid' => 150.00,
        'idempotency_key' => 'IDEMP_10_' . $c10Order
    ]);
    $resC10 = sendHttpRequest($orderPayCashUrl, 'POST', $c10Payload, [
        'Content-Type: application/json',
        'X-CSRF-Token: ' . $cashierB1['csrf']
    ], $cashierB1['cookie']);

    $stmtC10Order = $pdo->prepare("SELECT payment_status, total_amount, cashier_id FROM orders WHERE id = ?");
    $stmtC10Order->execute([$c10Order]);
    $c10DbOrder = $stmtC10Order->fetch();

    $c10Pass = ($resC10['code'] === 200)
        && ($c10DbOrder['payment_status'] === 'PAID')
        && ((float)$c10DbOrder['total_amount'] === 150.00)
        && ((int)$c10DbOrder['cashier_id'] === 1001);
    recordResult(10, "Cashier B1 paying Branch 1 order succeeds, marked PAID with cashier_id 1001", $c10Pass, ['db_order' => $c10DbOrder]);

    // -------------------------------------------------------------------------
    // CASE 11: fika_pos_order_void_refund.php returns 401 when unauthenticated
    // -------------------------------------------------------------------------
    $c11Order = $createCustomOrder(1, 'PAID', 'PENDING');
    $c11Payload = json_encode(['order_id' => $c11Order, 'action' => 'VOID', 'reason' => 'Customer cancel']);
    $resC11 = sendHttpRequest($orderVoidRefundUrl, 'POST', $c11Payload, ['Content-Type: application/json']);
    $errorResponses[] = $resC11['body'];

    $c11Pass = ($resC11['code'] === 401);
    recordResult(11, "fika_pos_order_void_refund.php unauthenticated POST returns 401", $c11Pass, ['code' => $resC11['code']]);

    // -------------------------------------------------------------------------
    // CASE 12: Supervisor for Branch 1 attempting fika_pos_order_void_refund.php on Branch 2 order returns 403
    // -------------------------------------------------------------------------
    $c12Order = $createCustomOrder(2, 'PAID', 'PENDING');
    $c12Payload = json_encode([
        'order_id' => $c12Order,
        'action' => 'VOID',
        'supervisor_pin' => $supPinBranch1,
        'reason' => 'Cross-branch test'
    ]);
    $resC12 = sendHttpRequest($orderVoidRefundUrl, 'POST', $c12Payload, [
        'Content-Type: application/json',
        'X-CSRF-Token: ' . $supervisorB1['csrf']
    ], $supervisorB1['cookie']);
    $errorResponses[] = $resC12['body'];

    $c12Pass = ($resC12['code'] === 403);
    recordResult(12, "Supervisor B1 attempting fika_pos_order_void_refund.php on Branch 2 order returns 403", $c12Pass, ['code' => $resC12['code']]);

    // -------------------------------------------------------------------------
    // CASE 13: Supervisor for Branch 1 successfully voids Branch 1 order
    // -------------------------------------------------------------------------
    $c13Order = $createCustomOrder(1, 'UNPAID', 'PENDING');
    $c13Payload = json_encode([
        'order_id' => $c13Order,
        'action' => 'VOID',
        'supervisor_pin' => $supPinBranch1,
        'reason' => 'Accidental duplicate entry'
    ]);
    $resC13 = sendHttpRequest($orderVoidRefundUrl, 'POST', $c13Payload, [
        'Content-Type: application/json',
        'X-CSRF-Token: ' . $supervisorB1['csrf']
    ], $supervisorB1['cookie']);

    $stmtC13 = $pdo->prepare("SELECT order_status FROM orders WHERE id = ?");
    $stmtC13->execute([$c13Order]);
    $c13Status = $stmtC13->fetchColumn();

    $c13Pass = ($resC13['code'] === 200) && ($c13Status === 'VOID');
    recordResult(13, "Supervisor B1 successfully voids Branch 1 order (status=VOID)", $c13Pass, ['status' => $c13Status]);

    // -------------------------------------------------------------------------
    // CASE 14: fika_pos_order_cancel.php returns 401 when unauthenticated
    // -------------------------------------------------------------------------
    $c14Order = $createCustomOrder(1, 'UNPAID', 'PENDING');
    $c14Payload = json_encode(['order_id' => $c14Order]);
    $resC14 = sendHttpRequest($orderCancelUrl, 'POST', $c14Payload, ['Content-Type: application/json']);
    $errorResponses[] = $resC14['body'];

    $c14Pass = ($resC14['code'] === 401);
    recordResult(14, "fika_pos_order_cancel.php unauthenticated POST returns 401", $c14Pass, ['code' => $resC14['code']]);

    // -------------------------------------------------------------------------
    // CASE 15: Cashier for Branch 1 attempting fika_pos_order_cancel.php on Branch 2 order returns 403
    // -------------------------------------------------------------------------
    $c15Order = $createCustomOrder(2, 'UNPAID', 'PENDING');
    $c15Payload = json_encode(['order_id' => $c15Order]);
    $resC15 = sendHttpRequest($orderCancelUrl, 'POST', $c15Payload, [
        'Content-Type: application/json',
        'X-CSRF-Token: ' . $cashierB1['csrf']
    ], $cashierB1['cookie']);
    $errorResponses[] = $resC15['body'];

    $c15Pass = ($resC15['code'] === 403);
    recordResult(15, "Cashier B1 attempting fika_pos_order_cancel.php on Branch 2 order returns 403", $c15Pass, ['code' => $resC15['code']]);

    // -------------------------------------------------------------------------
    // CASE 16: Cashier for Branch 1 cancelling Branch 1 order succeeds
    // -------------------------------------------------------------------------
    $c16Order = $createCustomOrder(1, 'UNPAID', 'PENDING');
    $c16Payload = json_encode(['order_id' => $c16Order]);
    $resC16 = sendHttpRequest($orderCancelUrl, 'POST', $c16Payload, [
        'Content-Type: application/json',
        'X-CSRF-Token: ' . $cashierB1['csrf']
    ], $cashierB1['cookie']);

    $stmtC16 = $pdo->prepare("SELECT order_status FROM orders WHERE id = ?");
    $stmtC16->execute([$c16Order]);
    $c16Status = $stmtC16->fetchColumn();

    $stmtC16Audit = $pdo->prepare("SELECT id FROM audit_logs WHERE action = 'ORDER_CANCELLED' AND details LIKE ?");
    $stmtC16Audit->execute(["%Order #%"]);
    $c16AuditExists = (bool)$stmtC16Audit->fetch();

    $c16Pass = ($resC16['code'] === 200) && ($c16Status === 'CANCELLED') && $c16AuditExists;
    recordResult(16, "Cashier B1 cancelling Branch 1 order succeeds (status=CANCELLED + ORDER_CANCELLED audit)", $c16Pass, ['status' => $c16Status, 'audit' => $c16AuditExists]);

    // -------------------------------------------------------------------------
    // CASE 17: fika_pos_order_complete.php returns 401 when unauthenticated
    // -------------------------------------------------------------------------
    $c17Order = $createCustomOrder(1, 'PAID', 'PENDING');
    $c17Payload = json_encode(['order_id' => $c17Order]);
    $resC17 = sendHttpRequest($orderCompleteUrl, 'POST', $c17Payload, ['Content-Type: application/json']);
    $errorResponses[] = $resC17['body'];

    $c17Pass = ($resC17['code'] === 401);
    recordResult(17, "fika_pos_order_complete.php unauthenticated POST returns 401", $c17Pass, ['code' => $resC17['code']]);

    // -------------------------------------------------------------------------
    // CASE 18: Cashier for Branch 1 attempting fika_pos_order_complete.php on Branch 2 order returns 403
    // -------------------------------------------------------------------------
    $c18Order = $createCustomOrder(2, 'PAID', 'PENDING');
    $c18Payload = json_encode(['order_id' => $c18Order]);
    $resC18 = sendHttpRequest($orderCompleteUrl, 'POST', $c18Payload, [
        'Content-Type: application/json',
        'X-CSRF-Token: ' . $cashierB1['csrf']
    ], $cashierB1['cookie']);
    $errorResponses[] = $resC18['body'];

    $c18Pass = ($resC18['code'] === 403);
    recordResult(18, "Cashier B1 attempting fika_pos_order_complete.php on Branch 2 order returns 403", $c18Pass, ['code' => $resC18['code']]);

    // -------------------------------------------------------------------------
    // CASE 19: Cashier for Branch 1 completing Branch 1 order succeeds
    // -------------------------------------------------------------------------
    $c19Order = $createCustomOrder(1, 'PAID', 'PENDING');
    $c19Payload = json_encode(['order_id' => $c19Order]);
    $resC19 = sendHttpRequest($orderCompleteUrl, 'POST', $c19Payload, [
        'Content-Type: application/json',
        'X-CSRF-Token: ' . $cashierB1['csrf']
    ], $cashierB1['cookie']);

    $stmtC19 = $pdo->prepare("SELECT order_status FROM orders WHERE id = ?");
    $stmtC19->execute([$c19Order]);
    $c19Status = $stmtC19->fetchColumn();

    $stmtC19Audit = $pdo->prepare("SELECT id FROM audit_logs WHERE action = 'ORDER_COMPLETED' AND details LIKE ?");
    $stmtC19Audit->execute(["%Order #%"]);
    $c19AuditExists = (bool)$stmtC19Audit->fetch();

    $c19Pass = ($resC19['code'] === 200) && ($c19Status === 'COMPLETED') && $c19AuditExists;
    recordResult(19, "Cashier B1 completing Branch 1 order succeeds (status=COMPLETED + ORDER_COMPLETED audit)", $c19Pass, ['status' => $c19Status, 'audit' => $c19AuditExists]);

    // -------------------------------------------------------------------------
    // CASE 20: fika_pos_register_open.php returns 401 when unauthenticated
    // -------------------------------------------------------------------------
    $resC20 = sendHttpRequest($registerOpenUrl, 'POST', json_encode(['starting_cash' => 500]), ['Content-Type: application/json']);
    $errorResponses[] = $resC20['body'];

    $c20Pass = ($resC20['code'] === 401);
    recordResult(20, "fika_pos_register_open.php returns 401 when unauthenticated", $c20Pass, ['code' => $resC20['code']]);

    // -------------------------------------------------------------------------
    // CASE 21: fika_pos_register_close.php returns 401 when unauthenticated
    // -------------------------------------------------------------------------
    $resC21 = sendHttpRequest($registerCloseUrl, 'POST', json_encode(['session_id' => 99111, 'actual_cash' => 1000]), ['Content-Type: application/json']);
    $errorResponses[] = $resC21['body'];

    $c21Pass = ($resC21['code'] === 401);
    recordResult(21, "fika_pos_register_close.php returns 401 when unauthenticated", $c21Pass, ['code' => $resC21['code']]);

    // -------------------------------------------------------------------------
    // CASE 22: Cashier for Branch 1 attempting register_open for Branch 2 returns 403
    // -------------------------------------------------------------------------
    $c22Payload = json_encode(['branch_id' => 2, 'starting_cash' => 500]);
    $resC22 = sendHttpRequest($registerOpenUrl, 'POST', $c22Payload, [
        'Content-Type: application/json',
        'X-CSRF-Token: ' . $cashierB1['csrf']
    ], $cashierB1['cookie']);
    $errorResponses[] = $resC22['body'];

    $c22Pass = ($resC22['code'] === 403);
    recordResult(22, "Cashier B1 attempting register_open for Branch 2 returns 403", $c22Pass, ['code' => $resC22['code']]);

    // -------------------------------------------------------------------------
    // CASE 23: Cashier for Branch 1 attempting register_close on Branch 2 session returns 403
    // -------------------------------------------------------------------------
    $pdo->prepare("
        INSERT INTO cash_sessions (id, branch_id, cashier_id, starting_cash, expected_cash, status, opened_at) 
        VALUES (99222, 2, 1002, 500.00, 500.00, 'OPEN', NOW())
        ON DUPLICATE KEY UPDATE status = 'OPEN', branch_id = 2
    ")->execute();
    $createdCashSessionIds[] = 99222;

    $c23Payload = json_encode(['session_id' => 99222, 'actual_cash' => 500]);
    $resC23 = sendHttpRequest($registerCloseUrl, 'POST', $c23Payload, [
        'Content-Type: application/json',
        'X-CSRF-Token: ' . $cashierB1['csrf']
    ], $cashierB1['cookie']);
    $errorResponses[] = $resC23['body'];

    $c23Pass = ($resC23['code'] === 403);
    recordResult(23, "Cashier B1 attempting register_close on Branch 2 session returns 403", $c23Pass, ['code' => $resC23['code']]);

    // -------------------------------------------------------------------------
    // CASE 24: Cashier for Branch 1 successfully opens shift and closes shift for Branch 1
    // -------------------------------------------------------------------------
    $pdo->prepare("UPDATE cash_sessions SET status = 'CLOSED' WHERE branch_id = 1 AND status = 'OPEN'")->execute();

    $c24OpenRes = sendHttpRequest($registerOpenUrl, 'POST', json_encode(['starting_cash' => 1200]), [
        'Content-Type: application/json',
        'X-CSRF-Token: ' . $cashierB1['csrf']
    ], $cashierB1['cookie']);
    $c24SessionId = (int)($c24OpenRes['json']['session_id'] ?? 0);
    if ($c24SessionId > 0) $createdCashSessionIds[] = $c24SessionId;

    $c24CloseRes = sendHttpRequest($registerCloseUrl, 'POST', json_encode(['session_id' => $c24SessionId, 'actual_cash' => 1200]), [
        'Content-Type: application/json',
        'X-CSRF-Token: ' . $cashierB1['csrf']
    ], $cashierB1['cookie']);

    $stmtC24 = $pdo->prepare("SELECT status, expected_cash, ending_cash FROM cash_sessions WHERE id = ?");
    $stmtC24->execute([$c24SessionId]);
    $c24SessionDb = $stmtC24->fetch();

    $c24Pass = ($c24OpenRes['code'] === 200) 
        && ($c24CloseRes['code'] === 200)
        && ($c24SessionDb['status'] === 'CLOSED')
        && ((float)$c24SessionDb['ending_cash'] === 1200.0);
    recordResult(24, "Cashier B1 opens and closes shift for Branch 1 (status=CLOSED, ending_cash=1200.00)", $c24Pass, ['db' => $c24SessionDb]);

    // Restore active session 99111 for subsequent cashier tests
    $pdo->prepare("UPDATE cash_sessions SET status = 'OPEN' WHERE id = 99111")->execute();

    // -------------------------------------------------------------------------
    // CASE 25: fika_pos_payment_create.php returns 401 when unauthenticated
    // -------------------------------------------------------------------------
    $c25Order = $createCustomOrder(1, 'UNPAID', 'PENDING');
    $c25Payload = json_encode(['order_id' => $c25Order]);
    $resC25 = sendHttpRequest($paymentCreateUrl, 'POST', $c25Payload, ['Content-Type: application/json']);
    $errorResponses[] = $resC25['body'];

    $c25Pass = ($resC25['code'] === 401);
    recordResult(25, "fika_pos_payment_create.php unauthenticated POST returns 401", $c25Pass, ['code' => $resC25['code']]);

    // -------------------------------------------------------------------------
    // CASE 26: Cashier for Branch 1 calling fika_pos_payment_create.php on Branch 2 order returns 403
    // -------------------------------------------------------------------------
    $c26Order = $createCustomOrder(2, 'UNPAID', 'PENDING');
    $c26Payload = json_encode(['order_id' => $c26Order]);
    $resC26 = sendHttpRequest($paymentCreateUrl, 'POST', $c26Payload, [
        'Content-Type: application/json',
        'X-CSRF-Token: ' . $cashierB1['csrf']
    ], $cashierB1['cookie']);
    $errorResponses[] = $resC26['body'];

    $c26Pass = ($resC26['code'] === 403);
    recordResult(26, "Cashier B1 calling fika_pos_payment_create.php on Branch 2 order returns 403", $c26Pass, ['code' => $resC26['code']]);

    // -------------------------------------------------------------------------
    // CASE 27: Calling fika_pos_payment_create.php on already-PAID order returns 409
    // -------------------------------------------------------------------------
    $c27Order = $createCustomOrder(1, 'PAID', 'COMPLETED');
    $c27Payload = json_encode(['order_id' => $c27Order]);
    $resC27 = sendHttpRequest($paymentCreateUrl, 'POST', $c27Payload, [
        'Content-Type: application/json',
        'X-CSRF-Token: ' . $cashierB1['csrf']
    ], $cashierB1['cookie']);
    $errorResponses[] = $resC27['body'];

    $c27Pass = ($resC27['code'] === 409);
    recordResult(27, "fika_pos_payment_create.php on already-PAID order returns 409", $c27Pass, ['code' => $resC27['code']]);

    // -------------------------------------------------------------------------
    // CASE 28: Trap 12: No raw exception or SQL statements leaked in any error responses
    // -------------------------------------------------------------------------
    $leakedPattern = false;
    foreach ($errorResponses as $body) {
        if (stripos($body, 'SQLSTATE') !== false 
            || stripos($body, 'Fatal error') !== false 
            || stripos($body, 'Uncaught Exception') !== false
            || stripos($body, 'Stack trace') !== false
            || stripos($body, 'SELECT * FROM') !== false) {
            $leakedPattern = true;
            break;
        }
    }
    $c28Pass = !$leakedPattern;
    recordResult(28, "Trap 12: no raw exception, SQLSTATE, or stack trace leaked in error responses", $c28Pass);

    // =========================================================================
    // CONCURRENCY TEST 29: Overselling Protection via curl_multi (Constraint 3)
    // =========================================================================
    // Setup Ingredient 9929 with stock = 10 for Branch 1
    $pdo->exec("
        INSERT INTO inventory_items (id, item_name, unit, unit_cost) 
        VALUES (9929, 'TEST_OVERSOLD_BEANS', 'kg', 10.00)
        ON DUPLICATE KEY UPDATE unit_cost = 10.00
    ");
    $createdInventoryIds[] = 9929;

    $pdo->exec("
        INSERT INTO inventory_stock (branch_id, inventory_id, current_stock)
        VALUES (1, 9929, 10.00)
        ON DUPLICATE KEY UPDATE current_stock = 10.00
    ");

    $pdo->exec("
        INSERT INTO products (id, name, category, price, price_cents, is_active)
        VALUES (9929, 'TEST_CONCURRENT_BREW', 'Coffee', 100.00, 10000, 1)
        ON DUPLICATE KEY UPDATE price = 100.00, price_cents = 10000, is_active = 1
    ");
    $createdProductIds[] = 9929;

    $pdo->exec("
        INSERT INTO branch_products (branch_id, product_id, is_active)
        VALUES (1, 9929, 1);
        INSERT INTO recipes (product_id, inventory_id, quantity_required)
        VALUES (9929, 9929, 1.00)
        ON DUPLICATE KEY UPDATE quantity_required = 1.00;
    ");

    // Ensure active shift 99111 exists and is OPEN in DB
    $pdo->prepare("UPDATE cash_sessions SET status = 'OPEN' WHERE id = 99111")->execute();

    // Re-create cashier session with pos_session_id = 99111
    $cashierB1 = createSessionWithUser([
        'id' => 1001,
        'username' => 'cashier_trust_b1',
        'name' => 'Cashier Branch 1',
        'role_id' => 7,
        'branch_id' => 1
    ], null, 99111);

    // Prepare 20 simultaneous order creation requests each ordering 1 unit
    $c29Requests = [];
    $orderCreatePayload = json_encode(['items' => [['id' => 9929, 'qty' => 1]]]);
    for ($i = 0; $i < 20; $i++) {
        $c29Requests[] = [
            'url' => $orderCreateUrl,
            'method' => 'POST',
            'data' => $orderCreatePayload,
            'headers' => [
                'Content-Type: application/json',
                'X-CSRF-Token: ' . $cashierB1['csrf']
            ],
            'cookie' => $cashierB1['cookie']
        ];
    }

    $c29Results = executeParallelRequests($c29Requests);

    $successCount29 = 0;
    $failureCount29 = 0;
    foreach ($c29Results as $res) {
        if ($res['code'] === 200) {
            $successCount29++;
            if (!empty($res['json']['order_id'])) {
                $createdOrderIds[] = (int)$res['json']['order_id'];
            }
        } elseif ($res['code'] === 400 && str_contains($res['body'], 'Insufficient stock')) {
            $failureCount29++;
        }
    }

    $stmtStock29 = $pdo->prepare("SELECT current_stock FROM inventory_stock WHERE branch_id = 1 AND inventory_id = 9929");
    $stmtStock29->execute();
    $endingStock29 = (float)$stmtStock29->fetchColumn();

    $c29Pass = ($successCount29 === 10) && ($failureCount29 === 10) && ($endingStock29 === 0.0);
    recordResult(29, "Constraint 3: Concurrency Test 29 (20 parallel requests for 10 units -> 10 succeed, 10 fail, stock is 0.00)", $c29Pass, [
        'successes' => $successCount29,
        'failures' => $failureCount29,
        'ending_stock' => $endingStock29
    ]);

    // =========================================================================
    // CONCURRENCY TEST 30: Pay vs Void Race Condition (Constraint 3 & 4)
    // =========================================================================
    // Setup single PENDING UNPAID order for Branch 1
    $c30Order = $createCustomOrder(1, 'UNPAID', 'PENDING', 100.00);

    // Request A: Cashier pay cash
    $payReq = [
        'url' => $orderPayCashUrl,
        'method' => 'POST',
        'data' => json_encode([
            'order_id' => $c30Order,
            'amount_paid' => 100.00,
            'idempotency_key' => 'RACE_PAY_' . $c30Order
        ]),
        'headers' => [
            'Content-Type: application/json',
            'X-CSRF-Token: ' . $cashierB1['csrf']
        ],
        'cookie' => $cashierB1['cookie']
    ];

    // Request B: Supervisor void order
    $voidReq = [
        'url' => $orderVoidRefundUrl,
        'method' => 'POST',
        'data' => json_encode([
            'order_id' => $c30Order,
            'action' => 'VOID',
            'supervisor_pin' => $supPinBranch1,
            'reason' => 'Race Condition Concurrency Test'
        ]),
        'headers' => [
            'Content-Type: application/json',
            'X-CSRF-Token: ' . $supervisorB1['csrf']
        ],
        'cookie' => $supervisorB1['cookie']
    ];

    $c30Results = executeParallelRequests([$payReq, $voidReq]);

    $c30Codes = array_column($c30Results, 'code');
    $c30Has200 = in_array(200, $c30Codes, true);
    $c30HasError = (in_array(400, $c30Codes, true) || in_array(409, $c30Codes, true));

    $stmtC30 = $pdo->prepare("SELECT order_status, payment_status FROM orders WHERE id = ?");
    $stmtC30->execute([$c30Order]);
    $c30OrderDb = $stmtC30->fetch();

    $consistentState = ($c30OrderDb['order_status'] === 'PAID' && $c30OrderDb['payment_status'] === 'PAID')
        || ($c30OrderDb['order_status'] === 'VOID' && $c30OrderDb['payment_status'] === 'UNPAID');

    $c30Pass = $c30Has200 && $c30HasError && $consistentState;
    recordResult(30, "Constraint 3 & 4: Concurrency Test 30 (Pay vs Void race -> exactly 1 winner, 1 rejected, consistent DB state)", $c30Pass, [
        'codes' => $c30Codes,
        'final_db_state' => $c30OrderDb
    ]);

} finally {
    // Teardown Test Data
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

    if (!empty($createdOrderIds)) {
        $inOrders = implode(',', array_map('intval', $createdOrderIds));
        $pdo->query("DELETE FROM order_items WHERE order_id IN ({$inOrders})");
        $pdo->query("DELETE FROM order_kiosk_tokens WHERE order_id IN ({$inOrders})");
        $pdo->query("DELETE FROM order_audit WHERE order_id IN ({$inOrders})");
        $pdo->query("DELETE FROM payments WHERE order_id IN ({$inOrders})");
        $pdo->query("DELETE FROM orders WHERE id IN ({$inOrders})");
    }

    if (!empty($createdInventoryIds)) {
        $inInv = implode(',', array_map('intval', $createdInventoryIds));
        $pdo->query("DELETE FROM inventory_ledger WHERE inventory_id IN ({$inInv})");
        $pdo->query("DELETE FROM inventory_stock WHERE inventory_id IN ({$inInv})");
        $pdo->query("DELETE FROM recipes WHERE inventory_id IN ({$inInv})");
        $pdo->query("DELETE FROM inventory_items WHERE id IN ({$inInv})");
    }

    if (!empty($createdProductIds)) {
        $inProd = implode(',', array_map('intval', $createdProductIds));
        $pdo->query("DELETE FROM branch_products WHERE product_id IN ({$inProd})");
        $pdo->query("DELETE FROM recipes WHERE product_id IN ({$inProd})");
        $pdo->query("DELETE FROM products WHERE id IN ({$inProd})");
    }

    if (!empty($createdCashSessionIds)) {
        $inCash = implode(',', array_map('intval', $createdCashSessionIds));
        $pdo->query("DELETE FROM cash_sessions WHERE id IN ({$inCash})");
    }

    if (!empty($createdDeviceIds)) {
        $inDev = implode(',', array_map('intval', $createdDeviceIds));
        $pdo->query("DELETE FROM devices WHERE id IN ({$inDev})");
    }

    if (!empty($createdUserIds)) {
        $inUsers = implode(',', array_map('intval', $createdUserIds));
        $pdo->query("DELETE FROM users WHERE id IN ({$inUsers})");
    }

    $safeBranchIds = array_diff($createdBranchIds, [1, 2, 3]);
    if (!empty($safeBranchIds)) {
        $inBranches = implode(',', array_map('intval', $safeBranchIds));
        $pdo->query("DELETE FROM branches WHERE id IN ({$inBranches})");
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    foreach ($createdSessionFiles as $file) {
        if (file_exists($file)) {
            @unlink($file);
        }
    }
}

echo "\n--------------------------------------------------------------------\n";
if ($failures === 0) {
    echo "All 30 POS Trust & Concurrency test cases PASSED successfully!\n";
    exit(0);
} else {
    echo "Completed with {$failures} failure(s).\n";
    exit(1);
}
