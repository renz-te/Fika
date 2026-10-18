<?php
require_once __DIR__ . '/../../app/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    die("Tests must be run from CLI.\n");
}

echo "====================================================================\n";
echo "58mm Thermal Receipt & Reprint Audit Test Suite (Phase 12D)\n";
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

// Cleanup any old test fixtures
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM order_kiosk_tokens WHERE order_id IN (SELECT id FROM orders WHERE branch_id IN (961, 962))");
$pdo->exec("DELETE FROM order_audit WHERE order_id IN (SELECT id FROM orders WHERE branch_id IN (961, 962))");
$pdo->exec("DELETE FROM payments WHERE order_id IN (SELECT id FROM orders WHERE branch_id IN (961, 962))");
$pdo->exec("DELETE FROM order_item_modifiers WHERE order_item_id IN (SELECT id FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE branch_id IN (961, 962)))");
$pdo->exec("DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE branch_id IN (961, 962))");
$pdo->exec("DELETE FROM orders WHERE branch_id IN (961, 962)");
$pdo->exec("DELETE FROM branch_products WHERE branch_id IN (961, 962) OR product_id IN (SELECT id FROM products WHERE name LIKE 'TEST_RECEIPT_%')");
$pdo->exec("DELETE FROM products WHERE name LIKE 'TEST_RECEIPT_%'");
$pdo->exec("DELETE FROM users WHERE id IN (951, 952)");
$pdo->exec("DELETE FROM branches WHERE id IN (961, 962)");
$pdo->exec("DELETE FROM audit_logs WHERE action = 'RECEIPT_PRINTED' AND details LIKE '%Order ID: 960%'");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

// 1. Setup Test Branches
$pdo->exec("INSERT INTO branches (id, name, address, contact_number) VALUES 
    (961, 'Fika Branch 1', '101 Coffee Lane, Branch 1', '0917-111-2222'),
    (962, 'Fika Branch 2', '202 Espresso Rd, Branch 2', '0917-333-4444')
");

// 2. Setup Cashier Users (Role 7 = Staff/Cashier with pos.access)
$pdo->exec("INSERT INTO users (id, username, password, role_id, branch_id) VALUES 
    (951, 'cashier_b1', 'hash', 7, 961),
    (952, 'cashier_b2', 'hash', 7, 962)
");

// Ensure role 7 has pos.access
$pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_name) VALUES (7, 'pos.access'), (7, 'pos.order_create')");

// Setup Cash Sessions
$pdo->exec("DELETE FROM cash_sessions WHERE id IN (9610, 9620)");
$pdo->exec("
    INSERT INTO cash_sessions (id, branch_id, cashier_id, starting_cash, status, opened_at) 
    VALUES (9610, 961, 951, 1000.00, 'OPEN', NOW()),
           (9620, 962, 952, 1000.00, 'OPEN', NOW())
");

// 3. Setup Test Products
$pdo->exec("
    INSERT INTO products (id, name, category, price, price_cents, is_active, created_at, updated_at) VALUES 
    (9601, 'TEST_RECEIPT_Original_Item', 'Coffee', 100.00, 10000, 1, NOW(), NOW()),
    (9602, 'TEST_RECEIPT_XSS_<script>alert(1)</script>_Latte', 'Coffee', 150.00, 15000, 1, NOW(), NOW())
");
$pdo->exec("
    INSERT INTO branch_products (branch_id, product_id, is_active) VALUES 
    (961, 9601, 1), (961, 9602, 1),
    (962, 9601, 1), (962, 9602, 1)
");

// 4. Setup Test Orders
// Order 9601: Normal paid order in Branch 961
$pdo->exec("
    INSERT INTO orders (id, order_number, branch_id, cashier_id, pos_session_id, total_amount, discount_amount, vat_amount, final_amount, order_status, payment_status, created_at)
    VALUES (9601, 'ORD-9601-TEST', 961, 951, 9610, 100.00, 0.00, 10.71, 100.00, 'PAID', 'PAID', NOW())
");
$pdo->exec("
    INSERT INTO order_items (id, order_id, product_id, quantity, price_snapshot, price_snapshot_cents, subtotal)
    VALUES (9611, 9601, 9601, 1, 100.00, 10000, 100.00)
");
$pdo->exec("
    INSERT INTO payments (order_id, payment_method, amount_paid, change_amount, created_at)
    VALUES (9601, 'CASH', 100.00, 0.00, NOW())
");

// Order 9602: Kiosk order in Branch 961 with order-bound token
$kioskToken9602 = 'kiosk_token_secret_9602_' . bin2hex(random_bytes(16));
$tokenHash9602 = hash('sha256', $kioskToken9602);
$pdo->exec("
    INSERT INTO orders (id, order_number, branch_id, cashier_id, pos_session_id, total_amount, discount_amount, vat_amount, final_amount, order_status, payment_status, created_at)
    VALUES (9602, 'ORD-9602-KIOSK', 961, NULL, NULL, 150.00, 0.00, 16.07, 150.00, 'PAID', 'PAID', NOW())
");
$pdo->exec("
    INSERT INTO order_kiosk_tokens (order_id, token_hash, expires_at, created_at)
    VALUES (9602, '{$tokenHash9602}', DATE_ADD(NOW(), INTERVAL 2 HOUR), NOW())
");

// Order 9603: XSS test order
$pdo->exec("
    INSERT INTO orders (id, order_number, branch_id, cashier_id, pos_session_id, total_amount, discount_amount, vat_amount, final_amount, order_status, payment_status, created_at)
    VALUES (9603, 'ORD-9603-XSS', 961, 951, 9610, 150.00, 0.00, 16.07, 150.00, 'PAID', 'PAID', NOW())
");
$pdo->exec("
    INSERT INTO order_items (id, order_id, product_id, quantity, price_snapshot, price_snapshot_cents, subtotal)
    VALUES (9613, 9603, 9602, 1, 150.00, 15000, 150.00)
");

// Order 9604: Voided Order
$pdo->exec("
    INSERT INTO orders (id, order_number, branch_id, cashier_id, pos_session_id, total_amount, discount_amount, vat_amount, final_amount, order_status, payment_status, created_at)
    VALUES (9604, 'ORD-9604-VOID', 961, 951, 9610, 100.00, 0.00, 0.00, 100.00, 'VOID', 'UNPAID', NOW())
");
$pdo->exec("
    INSERT INTO order_items (id, order_id, product_id, quantity, price_snapshot, price_snapshot_cents, subtotal)
    VALUES (9614, 9604, 9601, 1, 100.00, 10000, 100.00)
");
$pdo->exec("
    INSERT INTO order_audit (order_id, action, reason, authorized_by, created_at)
    VALUES (9604, 'VOID', 'Customer changed mind', 951, NOW())
");

// Subshell execution helper for the receipt page
function callReceiptPage(array $getParams, ?int $userId = null, array $headers = []) {
    global $pdo;
    $user = null;
    if ($userId) {
        $uStmt = $pdo->prepare("SELECT id, username, role_id, branch_id FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $user = $uStmt->fetch();
    }
    
    $wrapper = __DIR__ . '/wrapper_receipt_' . uniqid() . '.php';
    $targetPath = realpath(__DIR__ . '/../../fika_pos/fika_pos_receipt.php');
    
    $headerCode = "";
    foreach ($headers as $k => $v) {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $k));
        $headerCode .= "\$_SERVER['{$serverKey}'] = " . var_export($v, true) . ";\n";
    }

    $code = "<?php
    session_start();
    ";
    if ($user) {
        $code .= "\$_SESSION['user'] = " . var_export($user, true) . ";\n";
    }
    $code .= "
    \$_SERVER['REQUEST_METHOD'] = 'GET';
    {$headerCode}
    \$_GET = " . var_export($getParams, true) . ";

    register_shutdown_function(function() {
        echo \"\\n---STATUS:\" . http_response_code() . \"---\\n\";
    });

    ob_start();
    try {
        require " . var_export($targetPath, true) . ";
    } catch (Throwable \$e) {
        echo 'Exception: ' . \$e->getMessage();
    }
    \$output = ob_get_clean();
    echo \$output;
    ";

    file_put_contents($wrapper, $code);
    $cmd = 'php ' . escapeshellarg($wrapper);
    $raw = shell_exec($cmd) ?? '';
    @unlink($wrapper);

    $statusCode = 200;
    if (preg_match('/---STATUS:(\d+)---/', $raw, $m)) {
        $statusCode = (int)$m[1];
    }
    $body = preg_replace('/---STATUS:\d+---/', '', $raw);

    return ['status' => $statusCode, 'body' => $body];
}

// Subshell execution helper for the receipt log API
function callReceiptLogApi(array $payload, ?int $userId = null, array $headers = []) {
    global $pdo;
    $user = null;
    if ($userId) {
        $uStmt = $pdo->prepare("SELECT id, username, role_id, branch_id FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $user = $uStmt->fetch();
    }
    
    $wrapper = __DIR__ . '/wrapper_log_' . uniqid() . '.php';
    $targetPath = realpath(__DIR__ . '/../../fika_pos/api/fika_pos_receipt_log.php');
    
    $headerCode = "";
    foreach ($headers as $k => $v) {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $k));
        $headerCode .= "\$_SERVER['{$serverKey}'] = " . var_export($v, true) . ";\n";
    }

    $code = "<?php
    session_start();
    ";
    if ($user) {
        $code .= "\$_SESSION['user'] = " . var_export($user, true) . ";\n";
    }
    $code .= "
    \$_SERVER['REQUEST_METHOD'] = 'POST';
    {$headerCode}
    \$_POST = " . var_export($payload, true) . ";

    register_shutdown_function(function() {
        echo \"\\n---STATUS:\" . http_response_code() . \"---\\n\";
    });

    stream_wrapper_unregister('php');
    stream_wrapper_register('php', 'LogMockStream');
    class LogMockStream {
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
    } catch (Throwable \$e) {
        echo 'Exception: ' . \$e->getMessage();
    }
    \$output = ob_get_clean();
    echo \$output;
    ";

    file_put_contents($wrapper, $code);
    $cmd = 'php ' . escapeshellarg($wrapper);
    $raw = shell_exec($cmd) ?? '';
    @unlink($wrapper);

    $statusCode = 200;
    if (preg_match('/---STATUS:(\d+)---/', $raw, $m)) {
        $statusCode = (int)$m[1];
    }
    $body = preg_replace('/---STATUS:\d+---/', '', $raw);

    return ['status' => $statusCode, 'body' => $body];
}

// ====================================================================
// Test 1: Cashier from another branch gets 403
// ====================================================================
echo "\n--- Test 1: Cross-Branch Cashier Authorization ---\n";
// Order 9601 belongs to Branch 961. Cashier 952 belongs to Branch 962.
$res1 = callReceiptPage(['order_id' => 9601], 952);
assertTest("Cashier from another branch gets 403", $res1['status'] === 403, "Expected 403, got {$res1['status']}");

// Cashier from same branch gets 200
$res1b = callReceiptPage(['order_id' => 9601], 951);
assertTest("Cashier from same branch gets 200", $res1b['status'] === 200, "Expected 200, got {$res1b['status']}");


// ====================================================================
// Test 2: Kiosk token for the wrong order gets 403
// ====================================================================
echo "\n--- Test 2: Kiosk Token Authorization ---\n";
// Order 9602 has token $kioskToken9602.
// Wrong token on Order 9602
$res2a = callReceiptPage(['order_id' => 9602, 'kiosk_token' => 'completely_wrong_token']);
assertTest("Wrong kiosk token gets 403", $res2a['status'] === 403, "Expected 403, got {$res2a['status']}");

// Valid token on wrong order (Order 9601 has no kiosk token)
$res2b = callReceiptPage(['order_id' => 9601, 'kiosk_token' => $kioskToken9602]);
assertTest("Valid kiosk token on wrong order gets 403", $res2b['status'] === 403, "Expected 403, got {$res2b['status']}");

// Correct kiosk token on Order 9602 gets 200
$res2c = callReceiptPage(['order_id' => 9602, 'kiosk_token' => $kioskToken9602]);
assertTest("Correct kiosk token gets 200", $res2c['status'] === 200, "Expected 200, got {$res2c['status']}");


// ====================================================================
// Test 3: Reprint detection & RECEIPT_PRINTED audit log
// ====================================================================
echo "\n--- Test 3: Reprint Audit & Marker ---\n";
// First check: no audit log row yet for Order 9601
$auditChkStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE action = 'RECEIPT_PRINTED' AND details LIKE '%Order ID: 9601,%'");
$auditChkStmt->execute();
$countBefore = (int)$auditChkStmt->fetchColumn();
assertTest("Initial print audit count is 0", $countBefore === 0, "Expected 0, got {$countBefore}");

// Initial receipt render: should NOT contain *** REPRINT ***
$res3a = callReceiptPage(['order_id' => 9601], 951);
assertTest("Initial receipt does not contain *** REPRINT ***", strpos($res3a['body'], '*** REPRINT ***') === false, "Found reprint marker on first view");

// Cashier clicks Print button -> calls API
$logRes = callReceiptLogApi(['order_id' => 9601], 951);
assertTest("Receipt print log API returns 200", $logRes['status'] === 200, "Expected 200, got {$logRes['status']}");

// Verify audit row was created
$auditChkStmt->execute();
$countAfter1 = (int)$auditChkStmt->fetchColumn();
assertTest("Audit log row created for RECEIPT_PRINTED", $countAfter1 === 1, "Expected 1 row, got {$countAfter1}");

// Second receipt render: now it IS a reprint, so *** REPRINT *** must appear
$res3b = callReceiptPage(['order_id' => 9601], 951);
assertTest("Subsequent receipt render contains *** REPRINT ***", strpos($res3b['body'], '*** REPRINT ***') !== false, "Reprint marker missing on reprint view");

// Second print action: logs again
$logRes2 = callReceiptLogApi(['order_id' => 9601], 951);
assertTest("Reprint log call returns 200", $logRes2['status'] === 200, "Expected 200, got {$logRes2['status']}");

$auditChkStmt->execute();
$countAfter2 = (int)$auditChkStmt->fetchColumn();
assertTest("Audit log row count incremented to 2 for reprint", $countAfter2 === 2, "Expected 2 rows, got {$countAfter2}");


// ====================================================================
// Test 4: HTML Injection in Product Name is Escaped (Constraint 2)
// ====================================================================
echo "\n--- Test 4: XSS Escaping in Product Name ---\n";
// Order 9603 has product 'TEST_RECEIPT_XSS_<script>alert(1)</script>_Latte'
$res4 = callReceiptPage(['order_id' => 9603], 951);
$body4 = $res4['body'];

$hasEscapedTag = strpos($body4, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false;
$hasRawScriptTag = strpos($body4, '<script>alert(1)</script>') !== false;

assertTest("Product name HTML injection is escaped as &lt;script&gt;alert(1)&lt;/script&gt;", $hasEscapedTag, "Escaped tag not found in receipt body");
assertTest("Raw <script>alert(1)</script> does NOT exist in body", !$hasRawScriptTag, "Raw script tag was found unescaped in receipt body");


// ====================================================================
// Test 5: Amounts match price_snapshot_cents (Snapshot Integrity)
// ====================================================================
echo "\n--- Test 5: Price Snapshot Centavos Guarantee ---\n";
// Order 9601 was created when product 9601 was ₱100.00 (snapshot = 10000 cents).
// Now we maliciously or legitimately update live product price in `products` to ₱180.00 (18000 cents).
$pdo->exec("UPDATE products SET price = 180.00, price_cents = 18000 WHERE id = 9601");

$res5 = callReceiptPage(['order_id' => 9601], 951);
$body5 = $res5['body'];

$shows100 = strpos($body5, '100.00') !== false;
$shows180 = strpos($body5, '180.00') !== false;

assertTest("Receipt displays price snapshot ₱100.00", $shows100, "Snapshot amount 100.00 missing from receipt");
assertTest("Receipt does NOT display updated catalog price ₱180.00", !$shows180, "Updated catalog price 180.00 leaked into historical receipt!");


// ====================================================================
// Test 6: BIR Fields appear when settings.bir_registered = 1 and toggle off
// ====================================================================
echo "\n--- Test 6: BIR Statutory Notice & VAT Breakdown Toggling ---\n";
// 6a. Enabled (bir_registered = 1)
$pdo->exec("UPDATE settings SET setting_value = '1' WHERE setting_key IN ('bir_registered', 'receipt_bir_registered')");

$res6a = callReceiptPage(['order_id' => 9601], 951);
$body6a = $res6a['body'];

$hasOfficialNotice = strpos($body6a, 'THIS SERVES AS YOUR OFFICIAL RECEIPT') !== false;
$hasVatable = strpos($body6a, 'VATable Sales') !== false;
$hasVat12 = strpos($body6a, 'VAT (12%)') !== false;
$hasVatExempt = strpos($body6a, 'VAT-Exempt Sales') !== false;

assertTest("BIR official notice appears when registered", $hasOfficialNotice, "Notice missing when bir_registered = 1");
assertTest("VATable Sales appears when registered", $hasVatable, "VATable sales missing when bir_registered = 1");
assertTest("VAT (12%) breakdown appears when registered", $hasVat12, "VAT 12% missing when bir_registered = 1");
assertTest("VAT-Exempt Sales appears when registered", $hasVatExempt, "VAT-Exempt missing when bir_registered = 1");

// 6b. Disabled (bir_registered = 0)
$pdo->exec("UPDATE settings SET setting_value = '0' WHERE setting_key IN ('bir_registered', 'receipt_bir_registered')");

$res6b = callReceiptPage(['order_id' => 9601], 951);
$body6b = $res6b['body'];

$hasOfficialNoticeOff = strpos($body6b, 'THIS SERVES AS YOUR OFFICIAL RECEIPT') !== false;
$hasVatableOff = strpos($body6b, 'VATable Sales') !== false;

assertTest("BIR official notice hidden when bir_registered = 0", !$hasOfficialNoticeOff, "Notice shown when bir_registered = 0");
assertTest("VATable Sales hidden when bir_registered = 0", !$hasVatableOff, "VATable sales shown when bir_registered = 0");

// Restore BIR setting to 1
$pdo->exec("UPDATE settings SET setting_value = '1' WHERE setting_key IN ('bir_registered', 'receipt_bir_registered')");


// ====================================================================
// Test 7: Voided Order Receipt Handling
// ====================================================================
echo "\n--- Test 7: Voided Order Handling ---\n";
$res7 = callReceiptPage(['order_id' => 9604], 951);
$body7 = $res7['body'];

assertTest("Voided order displays *** VOID ***", strpos($body7, '*** VOID ***') !== false, "VOID marker missing");
assertTest("Voided order displays original order number ORD-9604-VOID", strpos($body7, 'ORD-9604-VOID') !== false, "Order number missing in void details");
assertTest("Voided order displays void reason", strpos($body7, 'Customer changed mind') !== false, "Void reason missing");


// ====================================================================
// Test 8: 58mm CSS Body Class Check (Constraint 4)
// ====================================================================
echo "\n--- Test 8: Print CSS & Body Class ---\n";
assertTest("Receipt page has receipt-body class", strpos($res1b['body'], 'class="receipt-body"') !== false, "receipt-body class missing from body tag");
$cssContent = file_get_contents(__DIR__ . '/../../fika_pos/assets/css/receipt.css');
assertTest("receipt.css specifies @page { size: 58mm auto; margin: 2mm; }", strpos($cssContent, 'size: 58mm auto;') !== false && strpos($cssContent, 'margin: 2mm;') !== false, "Exact @page declaration missing from receipt.css");
assertTest("receipt.css styles body.receipt-body for 58mm in print", strpos($cssContent, 'body.receipt-body') !== false, "body.receipt-body print rule missing in receipt.css");


// ====================================================================
// Summary
// ====================================================================
echo "\n====================================================================\n";
if ($failed === 0) {
    echo "ALL TESTS PASSED!\n";
    echo "====================================================================\n";
    exit(0);
} else {
    echo "FAILED: {$failed} test(s) failed.\n";
    echo "====================================================================\n";
    exit(1);
}
