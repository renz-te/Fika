<?php
/**
 * POS Trust Boundaries and Authorization Test Suite (H5)
 * Verifies server-side trust boundaries, unauthenticated restrictions,
 * cross-branch 403 protections, server-calculated totals, and exception masking.
 */

declare(strict_types=1);

require_once __DIR__ . '/../cafe_pos/database.php';

$baseUrl = getenv('FIKA_BASE_URL') ?: 'http://localhost/Fika/Fika';

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

// Global Tracking
$failures = 0;
$createdOrderIds = [];
$createdSessionIds = [];
$createdCashSessionIds = [];
$createdUserIds = [];
$createdSessionFiles = [];
$errorResponses = [];

echo "====================================================================\n";
echo "POS Trust Boundaries Test Suite (H5)\n";
echo "Target Base URL : {$baseUrl}\n";
echo "====================================================================\n\n";

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

function recordResult(int $caseNum, string $description, bool $passed, ?array $debug = null): bool {
    global $failures;
    if ($passed) {
        echo "PASS [Case {$caseNum}]: {$description}\n";
        return true;
    }
    echo "FAIL [Case {$caseNum}]: {$description}\n";
    if ($debug) {
        echo "       Debug info: " . json_encode($debug) . "\n";
    }
    $failures++;
    return false;
}

try {
    $ordersUrl = rtrim($baseUrl, '/') . '/cafe_pos/api/orders.php';
    $ordersPayUrl = rtrim($baseUrl, '/') . '/cafe_pos/api/orders_pay.php';
    $ordersVoidRefundUrl = rtrim($baseUrl, '/') . '/cafe_pos/api/orders_void_refund.php';
    $ordersCancelUrl = rtrim($baseUrl, '/') . '/cafe_pos/api/orders_cancel.php';
    $ordersCompleteUrl = rtrim($baseUrl, '/') . '/cafe_pos/api/orders_complete.php';
    $posSessionsUrl = rtrim($baseUrl, '/') . '/cafe_pos/api/pos_sessions.php';
    $paymongoCheckoutUrl = rtrim($baseUrl, '/') . '/cafe_pos/api/paymongo_checkout.php';

    // 1. Ensure Branch 2 exists in hrms.branches
    $createdBranchIds = [];
    $stmtCheckB2 = $pdo->query("SELECT id FROM hrms.branches WHERE id = 2");
    if (!$stmtCheckB2->fetch()) {
        $pdo->exec("INSERT INTO hrms.branches (id, name, address, status) VALUES (2, 'Branch 2 Test', '456 Test Street', 'Active')");
        $createdBranchIds[] = 2;
    }

    // 2. Setup Test Supervisors in hrms.users
    $supPinBranch1 = '112233';
    $supHashB1 = password_hash($supPinBranch1, PASSWORD_DEFAULT);
    $pdo->prepare("
        INSERT INTO hrms.users (name, username, email, password, role_id, branch_id, verified, created_at)
        VALUES ('Test Supervisor B1', 'sup_b1', 'sup_b1@test.local', ?, 5, 1, 1, NOW())
    ")->execute([$supHashB1]);
    $supB1Id = (int)$pdo->lastInsertId();
    $createdUserIds[] = $supB1Id;

    $supPinBranch2 = '445566';
    $supHashB2 = password_hash($supPinBranch2, PASSWORD_DEFAULT);
    $pdo->prepare("
        INSERT INTO hrms.users (name, username, email, password, role_id, branch_id, verified, created_at)
        VALUES ('Test Supervisor B2', 'sup_b2', 'sup_b2@test.local', ?, 5, 2, 1, NOW())
    ")->execute([$supHashB2]);
    $supB2Id = (int)$pdo->lastInsertId();
    $createdUserIds[] = $supB2Id;

    // 2. Setup Real Staff Role Sessions
    $cashierB1Cookie = "PHPSESSID=" . createSessionWithUser([
        'id' => 1001,
        'username' => 'cashier_b1',
        'name' => 'Cashier Branch 1',
        'role' => 'Cashier',
        'branch_id' => 1
    ]);

    $cashierB2Cookie = "PHPSESSID=" . createSessionWithUser([
        'id' => 1002,
        'username' => 'cashier_b2',
        'name' => 'Cashier Branch 2',
        'role' => 'Cashier',
        'branch_id' => 2
    ]);

    $headBaristaB1Cookie = "PHPSESSID=" . createSessionWithUser([
        'id' => $supB1Id,
        'username' => 'sup_b1',
        'name' => 'Head Barista B1',
        'role' => 'Head Barista',
        'branch_id' => 1
    ]);

    $headBaristaB2Cookie = "PHPSESSID=" . createSessionWithUser([
        'id' => $supB2Id,
        'username' => 'sup_b2',
        'name' => 'Head Barista B2',
        'role' => 'Head Barista',
        'branch_id' => 2
    ]);

    $baristaCookie = "PHPSESSID=" . createSessionWithUser([
        'id' => 1003,
        'username' => 'barista_user',
        'name' => 'Barista User',
        'role' => 'Barista',
        'branch_id' => 1
    ]);

    // Helper to create order in database directly
    $createCustomOrder = function(int $branchId, string $payStatus = 'UNPAID', string $orderStatus = 'PENDING', float $total = 150.00) use ($pdo, &$createdOrderIds): int {
        $orderNum = 'TEST-' . bin2hex(random_bytes(6));
        $stmt = $pdo->prepare("
            INSERT INTO orders (order_number, branch_id, total_price, vat_amount, discount_amount, payment_status, payment_method, order_status, is_test)
            VALUES (?, ?, ?, 16.07, 0.00, ?, 'CASH', ?, 1)
        ");
        $stmt->execute([$orderNum, $branchId, $total, $payStatus, $orderStatus]);
        $id = (int)$pdo->lastInsertId();
        $createdOrderIds[] = $id;

        // Insert standard item
        $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, subtotal) VALUES (?, 1, 1, ?)")->execute([$id, $total]);
        return $id;
    };

    // -------------------------------------------------------------------------
    // CASE 1: Unauthenticated orders.php with payment_status=PAID is forced to UNPAID
    // -------------------------------------------------------------------------
    $c1Payload = json_encode([
        'cart' => [
            ['product_id' => 1, 'quantity' => 1, 'modifier_ids' => [2]] // 120 + 30 = 150
        ],
        'payment_status' => 'PAID',
        'payment_method' => 'CASH',
        'branch_id' => 1
    ]);
    $resC1 = sendHttpRequest($ordersUrl, 'POST', $c1Payload, ['Content-Type: application/json']);
    $c1OrderId = (int)($resC1['json']['order_id'] ?? 0);
    if ($c1OrderId > 0) $createdOrderIds[] = $c1OrderId;

    $stmtC1 = $pdo->prepare("SELECT payment_status FROM orders WHERE id = ?");
    $stmtC1->execute([$c1OrderId]);
    $c1DbStatus = $stmtC1->fetchColumn();

    $c1Pass = ($resC1['code'] === 200) && ($c1DbStatus === 'UNPAID') && ($resC1['json']['payment_status'] === 'UNPAID');
    recordResult(1, "unauthenticated orders.php with payment_status=PAID forced to UNPAID", $c1Pass, ['resp' => $resC1]);

    // -------------------------------------------------------------------------
    // CASE 2: Unauthenticated orders.php with spoofed cashier_id ignores spoofed cashier
    // -------------------------------------------------------------------------
    $c2Payload = json_encode([
        'cart' => [
            ['product_id' => 1, 'quantity' => 1]
        ],
        'cashier_id' => 1, // Spoofed admin user id
        'branch_id' => 1
    ]);
    $resC2 = sendHttpRequest($ordersUrl, 'POST', $c2Payload, ['Content-Type: application/json']);
    $c2OrderId = (int)($resC2['json']['order_id'] ?? 0);
    if ($c2OrderId > 0) $createdOrderIds[] = $c2OrderId;

    $stmtC2 = $pdo->prepare("SELECT cashier_id, cashier_name FROM orders WHERE id = ?");
    $stmtC2->execute([$c2OrderId]);
    $c2DbRow = $stmtC2->fetch();

    $c2Pass = ($resC2['code'] === 200) && ($c2DbRow['cashier_id'] === null) && ($c2DbRow['cashier_name'] === 'Self-Service Kiosk');
    recordResult(2, "unauthenticated orders.php ignores spoofed cashier_id (NULL stored)", $c2Pass, ['row' => $c2DbRow]);

    // -------------------------------------------------------------------------
    // CASE 3: Constraint 1: Unauthenticated kiosk cannot apply discounts (forced discount_amount=0, discount_type=null)
    // -------------------------------------------------------------------------
    $c3Payload = json_encode([
        'cart' => [
            ['product_id' => 1, 'quantity' => 1, 'modifier_ids' => [2]] // 120 + 30 = 150
        ],
        'discount_type' => 'SENIOR',
        'discount_amount' => 30.00,
        'branch_id' => 1
    ]);
    $resC3 = sendHttpRequest($ordersUrl, 'POST', $c3Payload, ['Content-Type: application/json']);
    $c3OrderId = (int)($resC3['json']['order_id'] ?? 0);
    if ($c3OrderId > 0) $createdOrderIds[] = $c3OrderId;

    $stmtC3 = $pdo->prepare("SELECT total_price, discount_amount FROM orders WHERE id = ?");
    $stmtC3->execute([$c3OrderId]);
    $c3DbRow = $stmtC3->fetch();

    $c3Pass = ($resC3['code'] === 200) 
        && ((float)$c3DbRow['discount_amount'] === 0.0)
        && ($resC3['json']['discount_type'] === null)
        && ((float)$resC3['json']['discount_amount'] === 0.0)
        && ((float)$c3DbRow['total_price'] === 150.00);
    recordResult(3, "Constraint 1: kiosk caller discount forced to 0.00 and discount_type to NULL", $c3Pass, ['row' => $c3DbRow, 'resp' => $resC3['json']]);

    // -------------------------------------------------------------------------
    // CASE 4: Client-sent total_price & unit price tampering is ignored (server computes from DB)
    // -------------------------------------------------------------------------
    $c4Payload = json_encode([
        'cart' => [
            ['product_id' => 1, 'quantity' => 2, 'price' => 1.00] // Product 1 is 120.00; 2 * 120 = 240.00
        ],
        'total_price' => 2.00, // Client tries to spoof total to 2.00
        'branch_id' => 1
    ]);
    $resC4 = sendHttpRequest($ordersUrl, 'POST', $c4Payload, ['Content-Type: application/json']);
    $c4OrderId = (int)($resC4['json']['order_id'] ?? 0);
    if ($c4OrderId > 0) $createdOrderIds[] = $c4OrderId;

    $stmtC4 = $pdo->prepare("SELECT total_price FROM orders WHERE id = ?");
    $stmtC4->execute([$c4OrderId]);
    $c4DbTotal = (float)$stmtC4->fetchColumn();

    $c4Pass = ($resC4['code'] === 200) && ($c4DbTotal === 240.00) && ((float)$resC4['json']['total_price'] === 240.00);
    recordResult(4, "client-sent total_price tampering ignored (server calculates DB sum 240.00)", $c4Pass, ['db_total' => $c4DbTotal]);

    // -------------------------------------------------------------------------
    // CASE 5: Client-sent is_test is ignored (server derives is_test from Config)
    // -------------------------------------------------------------------------
    $serverIsTest = (Config::get('paymongo.is_test') === true) ? 1 : 0;
    $spoofedIsTest = $serverIsTest === 1 ? 0 : 1;
    $c5Payload = json_encode([
        'cart' => [
            ['product_id' => 1, 'quantity' => 1]
        ],
        'is_test' => $spoofedIsTest,
        'branch_id' => 1
    ]);
    $resC5 = sendHttpRequest($ordersUrl, 'POST', $c5Payload, ['Content-Type: application/json']);
    $c5OrderId = (int)($resC5['json']['order_id'] ?? 0);
    if ($c5OrderId > 0) $createdOrderIds[] = $c5OrderId;

    $stmtC5 = $pdo->prepare("SELECT is_test FROM orders WHERE id = ?");
    $stmtC5->execute([$c5OrderId]);
    $c5DbIsTest = (int)$stmtC5->fetchColumn();

    $c5Pass = ($resC5['code'] === 200) && ($c5DbIsTest === $serverIsTest);
    recordResult(5, "client-sent is_test ignored (stored matches server Config: {$serverIsTest})", $c5Pass, ['db_is_test' => $c5DbIsTest]);

    // -------------------------------------------------------------------------
    // CASE 6: orders_pay.php returns 401 when unauthenticated
    // -------------------------------------------------------------------------
    $c6Order = $createCustomOrder(1, 'UNPAID', 'PENDING');
    $c6Payload = json_encode(['order_id' => $c6Order, 'cart' => [['product_id' => 1, 'quantity' => 1]]]);
    $resC6 = sendHttpRequest($ordersPayUrl, 'POST', $c6Payload, ['Content-Type: application/json']);
    $errorResponses[] = $resC6['body'];

    $c6Pass = ($resC6['code'] === 401);
    recordResult(6, "orders_pay.php unauthenticated POST returns 401", $c6Pass, ['code' => $resC6['code']]);

    // -------------------------------------------------------------------------
    // CASE 7: Cashier for Branch 1 attempting orders_pay.php on Branch 2 order returns 403
    // -------------------------------------------------------------------------
    $c7Order = $createCustomOrder(2, 'UNPAID', 'PENDING');
    $c7Payload = json_encode(['order_id' => $c7Order, 'cart' => [['product_id' => 1, 'quantity' => 1]]]);
    $resC7 = sendHttpRequest($ordersPayUrl, 'POST', $c7Payload, ['Content-Type: application/json'], $cashierB1Cookie);
    $errorResponses[] = $resC7['body'];

    $c7Pass = ($resC7['code'] === 403);
    recordResult(7, "Cashier B1 attempting orders_pay.php on Branch 2 order returns 403", $c7Pass, ['code' => $resC7['code']]);

    // -------------------------------------------------------------------------
    // CASE 8: Constraint 3 & Trap 3: orders_pay.php on already-PAID order returns 409 + audit log outside tx
    // -------------------------------------------------------------------------
    $c8Order = $createCustomOrder(1, 'PAID', 'COMPLETED');
    $c8Payload = json_encode(['order_id' => $c8Order, 'cart' => [['product_id' => 1, 'quantity' => 1]]]);
    $resC8 = sendHttpRequest($ordersPayUrl, 'POST', $c8Payload, ['Content-Type: application/json'], $cashierB1Cookie);
    $errorResponses[] = $resC8['body'];

    $stmtC8Audit = $pdo->prepare("SELECT id, action FROM order_audit_logs WHERE order_id = ? AND action = 'PAYMENT_REJECTED'");
    $stmtC8Audit->execute([$c8Order]);
    $c8AuditRow = $stmtC8Audit->fetch();

    $c8Pass = ($resC8['code'] === 409) && !empty($c8AuditRow);
    recordResult(8, "Constraint 3 & Trap 3: orders_pay.php on already-PAID order returns 409 + PAYMENT_REJECTED audit log", $c8Pass, ['code' => $resC8['code']]);

    // -------------------------------------------------------------------------
    // CASE 9: Constraint 3: orders_pay.php on VOID order returns 409
    // -------------------------------------------------------------------------
    $c9Order = $createCustomOrder(1, 'UNPAID', 'VOID');
    $c9Payload = json_encode(['order_id' => $c9Order, 'cart' => [['product_id' => 1, 'quantity' => 1]]]);
    $resC9 = sendHttpRequest($ordersPayUrl, 'POST', $c9Payload, ['Content-Type: application/json'], $cashierB1Cookie);
    $errorResponses[] = $resC9['body'];

    $c9Pass = ($resC9['code'] === 409);
    recordResult(9, "Constraint 3: orders_pay.php on VOID order returns 409", $c9Pass, ['code' => $resC9['code']]);

    // -------------------------------------------------------------------------
    // CASE 10: Authorized Cashier on Branch 1 pays Branch 1 order -> 200, PAID, recomputed total
    // -------------------------------------------------------------------------
    $c10Order = $createCustomOrder(1, 'UNPAID', 'PENDING');
    $c10Payload = json_encode([
        'order_id' => $c10Order,
        'cart' => [
            ['product_id' => 1, 'quantity' => 1, 'modifier_ids' => [2]] // 120 + 30 = 150
        ],
        'total_price' => 5.00, // Tampered price ignored
        'payment_method' => 'CASH'
    ]);
    $resC10 = sendHttpRequest($ordersPayUrl, 'POST', $c10Payload, ['Content-Type: application/json'], $cashierB1Cookie);

    $stmtC10Order = $pdo->prepare("SELECT payment_status, total_price, cashier_id FROM orders WHERE id = ?");
    $stmtC10Order->execute([$c10Order]);
    $c10DbOrder = $stmtC10Order->fetch();

    $c10Pass = ($resC10['code'] === 200) 
        && ($c10DbOrder['payment_status'] === 'PAID')
        && ((float)$c10DbOrder['total_price'] === 150.00)
        && ((int)$c10DbOrder['cashier_id'] === 1001);
    recordResult(10, "Cashier B1 paying Branch 1 order succeeds, marked PAID with recomputed total 150.00", $c10Pass, ['db_order' => $c10DbOrder]);

    // -------------------------------------------------------------------------
    // CASE 11: orders_void_refund.php returns 401 when unauthenticated (no PIN, no session)
    // -------------------------------------------------------------------------
    $c11Order = $createCustomOrder(1, 'PAID', 'PENDING');
    $c11Payload = json_encode(['order_id' => $c11Order, 'action' => 'VOID', 'reason' => 'Customer cancel']);
    $resC11 = sendHttpRequest($ordersVoidRefundUrl, 'POST', $c11Payload, ['Content-Type: application/json']);
    $errorResponses[] = $resC11['body'];

    $c11Pass = ($resC11['code'] === 401);
    recordResult(11, "orders_void_refund.php unauthenticated POST returns 401", $c11Pass, ['code' => $resC11['code']]);

    // -------------------------------------------------------------------------
    // CASE 12: Supervisor for Branch 1 attempting orders_void_refund.php on Branch 2 order returns 403
    // -------------------------------------------------------------------------
    $c12Order = $createCustomOrder(2, 'PAID', 'PENDING');
    $c12Payload = json_encode([
        'order_id' => $c12Order,
        'action' => 'VOID',
        'pin' => $supPinBranch1, // PIN belongs to Branch 1 supervisor
        'reason' => 'Cross-branch test'
    ]);
    $resC12 = sendHttpRequest($ordersVoidRefundUrl, 'POST', $c12Payload, ['Content-Type: application/json']);
    $errorResponses[] = $resC12['body'];

    $c12Pass = ($resC12['code'] === 403);
    recordResult(12, "Supervisor B1 attempting orders_void_refund.php on Branch 2 order returns 403", $c12Pass, ['code' => $resC12['code']]);

    // -------------------------------------------------------------------------
    // CASE 13: Supervisor for Branch 1 successfully voids Branch 1 order
    // -------------------------------------------------------------------------
    $c13Order = $createCustomOrder(1, 'PAID', 'PENDING');
    $c13Payload = json_encode([
        'order_id' => $c13Order,
        'action' => 'VOID',
        'pin' => $supPinBranch1,
        'reason' => 'Accidental duplicate entry'
    ]);
    $resC13 = sendHttpRequest($ordersVoidRefundUrl, 'POST', $c13Payload, ['Content-Type: application/json']);

    $stmtC13 = $pdo->prepare("SELECT order_status FROM orders WHERE id = ?");
    $stmtC13->execute([$c13Order]);
    $c13Status = $stmtC13->fetchColumn();

    $c13Pass = ($resC13['code'] === 200) && ($c13Status === 'VOID');
    recordResult(13, "Supervisor B1 successfully voids Branch 1 order (status=VOID)", $c13Pass, ['status' => $c13Status]);

    // -------------------------------------------------------------------------
    // CASE 14: orders_cancel.php returns 401 when unauthenticated
    // -------------------------------------------------------------------------
    $c14Order = $createCustomOrder(1, 'UNPAID', 'PENDING');
    $c14Payload = json_encode(['order_id' => $c14Order]);
    $resC14 = sendHttpRequest($ordersCancelUrl, 'POST', $c14Payload, ['Content-Type: application/json']);
    $errorResponses[] = $resC14['body'];

    $c14Pass = ($resC14['code'] === 401);
    recordResult(14, "orders_cancel.php unauthenticated POST returns 401", $c14Pass, ['code' => $resC14['code']]);

    // -------------------------------------------------------------------------
    // CASE 15: Cashier for Branch 1 attempting orders_cancel.php on Branch 2 order returns 403
    // -------------------------------------------------------------------------
    $c15Order = $createCustomOrder(2, 'UNPAID', 'PENDING');
    $c15Payload = json_encode(['order_id' => $c15Order]);
    $resC15 = sendHttpRequest($ordersCancelUrl, 'POST', $c15Payload, ['Content-Type: application/json'], $cashierB1Cookie);
    $errorResponses[] = $resC15['body'];

    $c15Pass = ($resC15['code'] === 403);
    recordResult(15, "Cashier B1 attempting orders_cancel.php on Branch 2 order returns 403", $c15Pass, ['code' => $resC15['code']]);

    // -------------------------------------------------------------------------
    // CASE 16: Cashier for Branch 1 cancelling Branch 1 order succeeds
    // -------------------------------------------------------------------------
    $c16Order = $createCustomOrder(1, 'UNPAID', 'PENDING');
    $c16Payload = json_encode(['order_id' => $c16Order]);
    $resC16 = sendHttpRequest($ordersCancelUrl, 'POST', $c16Payload, ['Content-Type: application/json'], $cashierB1Cookie);

    $stmtC16 = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE id = ?");
    $stmtC16->execute([$c16Order]);
    $c16Count = (int)$stmtC16->fetchColumn();

    $stmtC16Audit = $pdo->prepare("SELECT id FROM order_audit_logs WHERE order_id = ? AND action = 'ORDER_CANCELLED'");
    $stmtC16Audit->execute([$c16Order]);
    $c16AuditExists = (bool)$stmtC16Audit->fetch();

    $c16Pass = ($resC16['code'] === 200) && ($c16Count === 0) && $c16AuditExists;
    recordResult(16, "Cashier B1 cancelling Branch 1 order succeeds (deleted queue + ORDER_CANCELLED audit)", $c16Pass, ['audit' => $c16AuditExists]);

    // -------------------------------------------------------------------------
    // CASE 17: orders_complete.php returns 401 when unauthenticated
    // -------------------------------------------------------------------------
    $c17Order = $createCustomOrder(1, 'PAID', 'PENDING');
    $c17Payload = json_encode(['order_id' => $c17Order]);
    $resC17 = sendHttpRequest($ordersCompleteUrl, 'POST', $c17Payload, ['Content-Type: application/json']);
    $errorResponses[] = $resC17['body'];

    $c17Pass = ($resC17['code'] === 401);
    recordResult(17, "orders_complete.php unauthenticated POST returns 401", $c17Pass, ['code' => $resC17['code']]);

    // -------------------------------------------------------------------------
    // CASE 18: Cashier for Branch 1 attempting orders_complete.php on Branch 2 order returns 403
    // -------------------------------------------------------------------------
    $c18Order = $createCustomOrder(2, 'PAID', 'PENDING');
    $c18Payload = json_encode(['order_id' => $c18Order]);
    $resC18 = sendHttpRequest($ordersCompleteUrl, 'POST', $c18Payload, ['Content-Type: application/json'], $cashierB1Cookie);
    $errorResponses[] = $resC18['body'];

    $c18Pass = ($resC18['code'] === 403);
    recordResult(18, "Cashier B1 attempting orders_complete.php on Branch 2 order returns 403", $c18Pass, ['code' => $resC18['code']]);

    // -------------------------------------------------------------------------
    // CASE 19: Cashier for Branch 1 completing Branch 1 order succeeds
    // -------------------------------------------------------------------------
    $c19Order = $createCustomOrder(1, 'PAID', 'PENDING');
    $c19Payload = json_encode(['order_id' => $c19Order]);
    $resC19 = sendHttpRequest($ordersCompleteUrl, 'POST', $c19Payload, ['Content-Type: application/json'], $cashierB1Cookie);

    $stmtC19 = $pdo->prepare("SELECT order_status FROM orders WHERE id = ?");
    $stmtC19->execute([$c19Order]);
    $c19Status = $stmtC19->fetchColumn();

    $c19Pass = ($resC19['code'] === 200) && ($c19Status === 'COMPLETED');
    recordResult(19, "Cashier B1 completing Branch 1 order succeeds (status=COMPLETED)", $c19Pass, ['status' => $c19Status]);

    // -------------------------------------------------------------------------
    // CASE 20 & 21: pos_sessions.php returns 401 when unauthenticated
    // -------------------------------------------------------------------------
    $resC20 = sendHttpRequest("{$posSessionsUrl}?action=open_shift", 'POST', json_encode(['starting_cash' => 1000]), ['Content-Type: application/json']);
    $resC21 = sendHttpRequest("{$posSessionsUrl}?action=close_shift", 'POST', json_encode(['session_id' => 1, 'actual_cash' => 1000]), ['Content-Type: application/json']);
    $errorResponses[] = $resC20['body'];
    $errorResponses[] = $resC21['body'];

    $c20Pass = ($resC20['code'] === 401) && ($resC21['code'] === 401);
    recordResult(20, "pos_sessions.php open_shift & close_shift return 401 when unauthenticated", $c20Pass, ['open' => $resC20['code'], 'close' => $resC21['code']]);

    // -------------------------------------------------------------------------
    // CASE 22: Head Barista for Branch 1 attempting open_shift for Branch 2 returns 403
    // -------------------------------------------------------------------------
    $c22Payload = json_encode(['branch_id' => 2, 'starting_cash' => 500]);
    $resC22 = sendHttpRequest("{$posSessionsUrl}?action=open_shift", 'POST', $c22Payload, ['Content-Type: application/json'], $headBaristaB1Cookie);
    $errorResponses[] = $resC22['body'];

    $c22Pass = ($resC22['code'] === 403);
    recordResult(22, "Head Barista B1 attempting open_shift for Branch 2 returns 403", $c22Pass, ['code' => $resC22['code']]);

    // -------------------------------------------------------------------------
    // CASE 23: Head Barista for Branch 1 attempting close_shift on Branch 2 session returns 403
    // -------------------------------------------------------------------------
    // Create an open shift directly for Branch 2
    $pdo->prepare("INSERT INTO cash_sessions (branch_id, cashier_id, opening_float, status) VALUES (2, 1002, 500.00, 'OPEN')")->execute();
    $b2SessionId = (int)$pdo->lastInsertId();
    $createdCashSessionIds[] = $b2SessionId;

    $c23Payload = json_encode(['session_id' => $b2SessionId, 'actual_cash' => 500]);
    $resC23 = sendHttpRequest("{$posSessionsUrl}?action=close_shift", 'POST', $c23Payload, ['Content-Type: application/json'], $headBaristaB1Cookie);
    $errorResponses[] = $resC23['body'];

    $c23Pass = ($resC23['code'] === 403);
    recordResult(23, "Head Barista B1 attempting close_shift on Branch 2 session returns 403", $c23Pass, ['code' => $resC23['code']]);

    // -------------------------------------------------------------------------
    // CASE 24: Head Barista for Branch 1 successfully opens shift and closes shift for Branch 1
    // -------------------------------------------------------------------------
    // Ensure no prior open shift for Branch 1
    $pdo->prepare("UPDATE cash_sessions SET status = 'CLOSED' WHERE branch_id = 1 AND status = 'OPEN'")->execute();

    $c24OpenRes = sendHttpRequest("{$posSessionsUrl}?action=open_shift", 'POST', json_encode(['starting_cash' => 1200]), ['Content-Type: application/json'], $headBaristaB1Cookie);
    $c24SessionId = (int)($c24OpenRes['json']['session_id'] ?? 0);
    if ($c24SessionId > 0) $createdCashSessionIds[] = $c24SessionId;

    $c24CloseRes = sendHttpRequest("{$posSessionsUrl}?action=close_shift", 'POST', json_encode(['session_id' => $c24SessionId, 'actual_cash' => 1200]), ['Content-Type: application/json'], $headBaristaB1Cookie);

    $stmtC24 = $pdo->prepare("SELECT status, expected_cash, closing_cash FROM cash_sessions WHERE id = ?");
    $stmtC24->execute([$c24SessionId]);
    $c24SessionDb = $stmtC24->fetch();

    $c24Pass = ($c24OpenRes['code'] === 200) 
        && ($c24CloseRes['code'] === 200)
        && ($c24SessionDb['status'] === 'CLOSED')
        && ((float)$c24SessionDb['closing_cash'] === 1200.0);
    recordResult(24, "Head Barista B1 opens and closes shift for Branch 1 (status=CLOSED)", $c24Pass, ['db' => $c24SessionDb]);

    // -------------------------------------------------------------------------
    // CASE 25: Constraint 2: paymongo_checkout.php returns 401 when unauthenticated
    // -------------------------------------------------------------------------
    $c25Order = $createCustomOrder(1, 'UNPAID', 'PENDING');
    $c25Payload = json_encode(['order_id' => $c25Order]);
    $resC25 = sendHttpRequest($paymongoCheckoutUrl, 'POST', $c25Payload, ['Content-Type: application/json']);
    $errorResponses[] = $resC25['body'];

    $c25Pass = ($resC25['code'] === 401);
    recordResult(25, "Constraint 2: paymongo_checkout.php unauthenticated POST returns 401", $c25Pass, ['code' => $resC25['code']]);

    // -------------------------------------------------------------------------
    // CASE 26: Constraint 2: Cashier for Branch 1 calling paymongo_checkout.php on Branch 2 order returns 403
    // -------------------------------------------------------------------------
    $c26Order = $createCustomOrder(2, 'UNPAID', 'PENDING');
    $c26Payload = json_encode(['order_id' => $c26Order]);
    $resC26 = sendHttpRequest($paymongoCheckoutUrl, 'POST', $c26Payload, ['Content-Type: application/json'], $cashierB1Cookie);
    $errorResponses[] = $resC26['body'];

    $c26Pass = ($resC26['code'] === 403);
    recordResult(26, "Constraint 2: Cashier B1 calling paymongo_checkout.php on Branch 2 order returns 403", $c26Pass, ['code' => $resC26['code']]);

    // -------------------------------------------------------------------------
    // CASE 27: Constraint 2: Calling paymongo_checkout.php on already-PAID order returns 409
    // -------------------------------------------------------------------------
    $c27Order = $createCustomOrder(1, 'PAID', 'COMPLETED');
    $c27Payload = json_encode(['order_id' => $c27Order]);
    $resC27 = sendHttpRequest($paymongoCheckoutUrl, 'POST', $c27Payload, ['Content-Type: application/json'], $cashierB1Cookie);
    $errorResponses[] = $resC27['body'];

    $c27Pass = ($resC27['code'] === 409);
    recordResult(27, "Constraint 2: paymongo_checkout.php on already-PAID order returns 409", $c27Pass, ['code' => $resC27['code']]);

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

} finally {
    // Teardown Test Data
    if (!empty($createdOrderIds)) {
        $inOrders = implode(',', array_map('intval', $createdOrderIds));
        $pdo->query("DELETE FROM order_items WHERE order_id IN ({$inOrders})");
        $pdo->query("DELETE FROM order_kiosk_tokens WHERE order_id IN ({$inOrders})");
        $pdo->query("DELETE FROM order_audit_logs WHERE order_id IN ({$inOrders})");
        $pdo->query("DELETE FROM orders WHERE id IN ({$inOrders})");
    }

    if (!empty($createdCashSessionIds)) {
        $inCash = implode(',', array_map('intval', $createdCashSessionIds));
        $pdo->query("DELETE FROM cash_sessions WHERE id IN ({$inCash})");
    }

    if (!empty($createdUserIds)) {
        $inUsers = implode(',', array_map('intval', $createdUserIds));
        $pdo->query("DELETE FROM hrms.users WHERE id IN ({$inUsers})");
    }

    if (!empty($createdBranchIds)) {
        $inBranches = implode(',', array_map('intval', $createdBranchIds));
        $pdo->query("DELETE FROM hrms.branches WHERE id IN ({$inBranches})");
    }

    foreach ($createdSessionFiles as $file) {
        if (file_exists($file)) {
            @unlink($file);
        }
    }
}

echo "\n--------------------------------------------------------------------\n";
if ($failures === 0) {
    echo "All 28 POS Trust Boundary test cases PASSED successfully!\n";
    exit(0);
} else {
    echo "Completed with {$failures} failure(s).\n";
    exit(1);
}
