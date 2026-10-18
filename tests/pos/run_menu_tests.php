<?php
require_once __DIR__ . '/../../app/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    die("Tests must be run from CLI.\n");
}

echo "Running POS Menu & Product Management Tests...\n\n";
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

// Cleanup previous test artifacts if any
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE branch_id IN (991, 992))");
$pdo->exec("DELETE FROM orders WHERE branch_id IN (991, 992)");
$pdo->exec("DELETE FROM branch_products WHERE branch_id IN (991, 992) OR product_id IN (SELECT id FROM products WHERE name LIKE 'TEST_MENU_%')");
$pdo->exec("DELETE FROM products WHERE name LIKE 'TEST_MENU_%'");
$pdo->exec("DELETE FROM users WHERE id IN (980, 981, 982, 983)");
$pdo->exec("DELETE FROM branches WHERE id IN (991, 992)");
$pdo->exec("DELETE FROM doc_counters WHERE document_type = 'ORDER'");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

// Setup Test Branches
$pdo->exec("INSERT INTO branches (id, name) VALUES (991, 'Menu Test Branch A'), (992, 'Menu Test Branch B')");

// Setup Real Role Users (1 = ADMIN, 6 = BM, 7 = STAFF / Cashier)
$pdo->exec("INSERT INTO users (id, username, password, role_id, branch_id) VALUES 
    (980, 'test_admin', 'x', 1, NULL),
    (981, 'test_bm_a', 'x', 6, 991),
    (982, 'test_bm_b', 'x', 6, 992),
    (983, 'test_cashier_a', 'x', 7, 991),
    (984, 'test_cashier_b', 'x', 7, 992)
");

// Setup Cash Sessions for testing POS orders
$pdo->exec("INSERT INTO cash_sessions (id, branch_id, cashier_id, starting_cash, status) VALUES (999, 991, 983, 1000.00, 'OPEN'), (998, 992, 984, 1000.00, 'OPEN')");

// Helper to execute POS API in an isolated subshell
function callPosApi(string $apiScript, array $payload, ?int $userId = null) {
    global $pdo;

    $token = bin2hex(random_bytes(32));
    $payload['csrf_token'] = $token;

    $user = null;
    $posSessionId = 999;
    if ($userId) {
        $uStmt = $pdo->prepare("SELECT id, username, role_id, branch_id FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $user = $uStmt->fetch();
        if ($user && (int)$user['branch_id'] === 992) {
            $posSessionId = 998;
        }
    }

    $wrapper = __DIR__ . '/wrapper_menu_' . uniqid() . '.php';
    $targetPath = realpath(__DIR__ . '/../../fika_pos/api/' . $apiScript);

    $code = "<?php
    session_start();
    \$_SESSION['csrf_token'] = " . var_export($token, true) . ";
    \$_SESSION['pos_session_id'] = {$posSessionId};
    ";
    if ($user) {
        $code .= "\$_SESSION['user'] = " . var_export($user, true) . ";\n";
    }
    $code .= "
    \$_SERVER['REQUEST_METHOD'] = 'POST';
    \$_SERVER['HTTP_X_CSRF_TOKEN'] = " . var_export($token, true) . ";
    \$_POST = " . var_export($payload, true) . ";

    register_shutdown_function(function() {
        echo \"\\n---STATUS:\" . http_response_code() . \"---\\n\";
    });

    // Mock php://input
    stream_wrapper_unregister('php');
    stream_wrapper_register('php', 'MockStream');
    class MockStream {
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
    // 1. ROLE PERMISSIONS & BRANCH SCOPE TESTS
    // =========================================================================

    // Case 1A: BM edits own-branch menu (Success)
    $res1A = callPosApi('fika_pos_menu_save.php', [
        'name' => 'TEST_MENU_Latte',
        'category' => 'Coffee',
        'price' => '145.50',
        'branch_id' => 991
    ], 981); // User 981 is BM for Branch 991

    assertTest(
        "BM edits own-branch menu (Success)",
        $res1A['status'] === 200 && !empty($res1A['body']['success']) && !empty($res1A['body']['id']),
        "Status: {$res1A['status']}, Raw: {$res1A['raw']}"
    );
    $latteId = (int)($res1A['body']['id'] ?? 0);

    // Case 1B: BM cannot edit other branch (403)
    $res1B = callPosApi('fika_pos_menu_save.php', [
        'name' => 'TEST_MENU_OtherBranch',
        'category' => 'Coffee',
        'price' => '120.00',
        'branch_id' => 992
    ], 981); // BM for 991 tries to edit branch 992

    assertTest(
        "BM cannot edit other branch (403)",
        $res1B['status'] === 403 && strpos($res1B['raw'], 'Branch scope mismatch') !== false,
        "Status: {$res1B['status']}, Raw: {$res1B['raw']}"
    );

    // Case 1C: Cashier cannot edit (403)
    $res1C = callPosApi('fika_pos_menu_save.php', [
        'name' => 'TEST_MENU_CashierAttempt',
        'category' => 'Coffee',
        'price' => '100.00',
        'branch_id' => 991
    ], 983); // User 983 is Cashier (Role 7)

    assertTest(
        "Cashier cannot edit (403)",
        $res1C['status'] === 403 && strpos($res1C['raw'], 'pos.menu.manage') !== false,
        "Status: {$res1C['status']}, Raw: {$res1C['raw']}"
    );

    // =========================================================================
    // 2. PRICE IS STORED CORRECTLY IN CENTAVOS
    // =========================================================================

    // Case 2A: Verify price_cents in database
    $stmt = $pdo->prepare("SELECT price_cents, price FROM products WHERE id = ?");
    $stmt->execute([$latteId]);
    $prodDb = $stmt->fetch();

    $expectedCents = Money::toCentavos('145.50'); // 14550
    assertTest(
        "Price is stored correctly in centavos",
        (int)$prodDb['price_cents'] === $expectedCents && (float)$prodDb['price'] === 145.50,
        "Expected price_cents 14550, got {$prodDb['price_cents']} (price: {$prodDb['price']})"
    );

    // Case 2B: Constraint 1: Automatically insert rows into branch_products for ALL active branches
    $bpStmt = $pdo->prepare("SELECT branch_id, is_active FROM branch_products WHERE product_id = ? ORDER BY branch_id ASC");
    $bpStmt->execute([$latteId]);
    $bpRows = $bpStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $allActiveBranchesHaveRow = isset($bpRows[991]) && (int)$bpRows[991] === 1
                             && isset($bpRows[992]) && (int)$bpRows[992] === 1;
    assertTest(
        "New product automatically populates branch_products for all active branches (is_active=1)",
        $allActiveBranchesHaveRow,
        "branch_products rows: " . json_encode($bpRows)
    );

    // =========================================================================
    // 3. UPLOAD CLASS SECURITY (RENAME .PHP TO .JPG REJECTED)
    // =========================================================================

    $dummyPhpFile = sys_get_temp_dir() . '/fake_image_' . uniqid() . '.jpg';
    file_put_contents($dummyPhpFile, "<?php echo 'malicious backdoor'; ?>");

    $fakeUploadInfo = [
        'name' => 'backdoor.jpg',
        'type' => 'image/jpeg',
        'tmp_name' => $dummyPhpFile,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($dummyPhpFile)
    ];

    $uploadDir = APP_ROOT . '/uploads/menu/';
    $uploadResult = Upload::handle($fakeUploadInfo, $uploadDir);
    if (file_exists($dummyPhpFile)) {
        unlink($dummyPhpFile);
    }

    assertTest(
        "A .php file renamed to .jpg is rejected by the Upload class",
        $uploadResult === null,
        "Upload::handle accepted disguised PHP file: " . ($uploadResult ?? '')
    );

    // Verify .htaccess exists in uploads/menu/
    $htaccessPath = APP_ROOT . '/uploads/menu/.htaccess';
    $htaccessExists = file_exists($htaccessPath) && strpos(file_get_contents($htaccessPath), 'Require all denied') !== false;
    assertTest("uploads/menu/.htaccess protects against script execution", $htaccessExists, "Missing or invalid .htaccess");

    // =========================================================================
    // 4. ORDER LINE KEEPS PRICE SNAPSHOT AFTER A MENU PRICE CHANGE
    // =========================================================================

    // Create a product with initial price 100.00
    $resProd = callPosApi('fika_pos_menu_save.php', [
        'name' => 'TEST_MENU_Cappuccino',
        'category' => 'Coffee',
        'price' => '100.00',
        'branch_id' => 991
    ], 980);
    $cappuccinoId = (int)$resProd['body']['id'];

    // Cashier places order for 2 Cappuccinos (Initial price: 100.00 each)
    $resOrder = callPosApi('fika_pos_order_create.php', [
        'items' => [
            ['id' => $cappuccinoId, 'qty' => 2]
        ]
    ], 983); // Cashier A (Branch 991)

    assertTest("Cashier creates initial order successfully", $resOrder['status'] === 200 && !empty($resOrder['body']['order_id']), $resOrder['raw']);
    $orderId = (int)$resOrder['body']['order_id'];

    // Check order_items snapshot
    $oiStmt = $pdo->prepare("SELECT price_snapshot, price_snapshot_cents, subtotal FROM order_items WHERE order_id = ? AND product_id = ?");
    $oiStmt->execute([$orderId, $cappuccinoId]);
    $itemBefore = $oiStmt->fetch();

    assertTest(
        "Order item records price_snapshot_cents and price_snapshot at creation",
        (int)$itemBefore['price_snapshot_cents'] === 10000 && (float)$itemBefore['price_snapshot'] === 100.00,
        "Snapshot: " . json_encode($itemBefore)
    );

    // Now update menu price from 100.00 to 180.00
    $resPriceUpdate = callPosApi('fika_pos_menu_save.php', [
        'id' => $cappuccinoId,
        'name' => 'TEST_MENU_Cappuccino',
        'category' => 'Coffee',
        'price' => '180.00',
        'branch_id' => 991
    ], 981); // BM updates price

    assertTest("Price update succeeds", $resPriceUpdate['status'] === 200 && (int)$resPriceUpdate['body']['price_cents'] === 18000, $resPriceUpdate['raw']);

    // Check product in DB has new price
    $newProdStmt = $pdo->prepare("SELECT price_cents, price FROM products WHERE id = ?");
    $newProdStmt->execute([$cappuccinoId]);
    $prodAfter = $newProdStmt->fetch();
    assertTest("Product in DB updated to 18000 centavos", (int)$prodAfter['price_cents'] === 18000, json_encode($prodAfter));

    // Verify historical order line snapshot remains exactly 100.00 (10000 centavos)
    $oiStmt->execute([$orderId, $cappuccinoId]);
    $itemAfter = $oiStmt->fetch();

    $snapshotPreserved = (int)$itemAfter['price_snapshot_cents'] === 10000 
                      && (float)$itemAfter['price_snapshot'] === 100.00
                      && (float)$itemAfter['subtotal'] === 200.00;
    assertTest(
        "An order line keeps its price snapshot after a menu price change",
        $snapshotPreserved,
        "Historical line changed: " . json_encode($itemAfter)
    );

    // Verify Audit::log() recorded the price change
    $auditStmt = $pdo->prepare("SELECT * FROM audit_logs WHERE action = 'PRODUCT_PRICE_CHANGED' AND details LIKE ? ORDER BY id DESC LIMIT 1");
    $auditStmt->execute(["%{$cappuccinoId}%"]);
    $auditRow = $auditStmt->fetch();

    assertTest(
        "Audit::log() recorded the price change with old and new values",
        !empty($auditRow) && strpos($auditRow['details'], '100.00') !== false && strpos($auditRow['details'], '180.00') !== false,
        "Audit log missing or mismatch: " . json_encode($auditRow)
    );

    // =========================================================================
    // 5. DEACTIVATED PRODUCT CANNOT BE ORDERED
    // =========================================================================

    // Create a product for testing deactivation
    $resProdDec = callPosApi('fika_pos_menu_save.php', [
        'name' => 'TEST_MENU_Seasonal_Croissant',
        'category' => 'Pastry',
        'price' => '95.00',
        'branch_id' => 991
    ], 980);
    $croissantId = (int)$resProdDec['body']['id'];

    // 5A: Deactivate specifically for Branch 991 (Per-branch availability)
    $resToggle = callPosApi('fika_pos_menu_toggle_status.php', [
        'product_id' => $croissantId,
        'branch_id' => 991,
        'is_active' => 0
    ], 981); // BM for 991 marks it inactive for 991
    assertTest("Deactivate product for Branch 991 succeeds", $resToggle['status'] === 200 && (int)$resToggle['body']['is_active'] === 0, $resToggle['raw']);

    // Attempt to order from Branch 991 -> MUST FAIL
    $resOrderBranchFail = callPosApi('fika_pos_order_create.php', [
        'items' => [
            ['id' => $croissantId, 'qty' => 1]
        ]
    ], 983); // Cashier for 991
    assertTest(
        "Deactivated product for branch cannot be ordered by that branch (400)",
        $resOrderBranchFail['status'] === 400 && strpos($resOrderBranchFail['raw'], 'inactive') !== false,
        "Status: {$resOrderBranchFail['status']}, Raw: {$resOrderBranchFail['raw']}"
    );

    // Meanwhile, Branch 992 should still be able to order it (isolated per-branch availability)
    $resOrderBranchB = callPosApi('fika_pos_order_create.php', [
        'items' => [
            ['id' => $croissantId, 'qty' => 1]
        ]
    ], 984);
    assertTest(
        "Product remaining active in Branch 992 can still be ordered by Branch 992",
        $resOrderBranchB['status'] === 200 && !empty($resOrderBranchB['body']['order_id']),
        "Status: {$resOrderBranchB['status']}, Raw: {$resOrderBranchB['raw']}"
    );

    // 5B: Reactivate branch, then globally deactivate product
    callPosApi('fika_pos_menu_toggle_status.php', [
        'product_id' => $croissantId,
        'branch_id' => 991,
        'is_active' => 1
    ], 981);

    $resGlobalToggle = callPosApi('fika_pos_menu_toggle_status.php', [
        'product_id' => $croissantId,
        'is_active' => 0
    ], 980); // Admin globally deactivates
    assertTest("Globally deactivate product succeeds", $resGlobalToggle['status'] === 200, $resGlobalToggle['raw']);

    $resOrderGlobalFail = callPosApi('fika_pos_order_create.php', [
        'items' => [
            ['id' => $croissantId, 'qty' => 1]
        ]
    ], 983);
    assertTest(
        "Globally deactivated product cannot be ordered (400)",
        $resOrderGlobalFail['status'] === 400 && strpos($resOrderGlobalFail['raw'], 'inactive') !== false,
        "Status: {$resOrderGlobalFail['status']}, Raw: {$resOrderGlobalFail['raw']}"
    );

    // 5C: Soft-delete product (Constraint 3)
    $resDelete = callPosApi('fika_pos_menu_delete.php', [
        'id' => $croissantId,
        'branch_id' => 991
    ], 981);
    assertTest("Soft-delete product succeeds", $resDelete['status'] === 200 && !empty($resDelete['body']['success']), $resDelete['raw']);

    $delCheckStmt = $pdo->prepare("SELECT deleted_at, is_active FROM products WHERE id = ?");
    $delCheckStmt->execute([$croissantId]);
    $delCheck = $delCheckStmt->fetch();
    assertTest("Soft-deleted product has deleted_at set", !empty($delCheck['deleted_at']) && (int)$delCheck['is_active'] === 0, json_encode($delCheck));

    $resOrderDeletedFail = callPosApi('fika_pos_order_create.php', [
        'items' => [
            ['id' => $croissantId, 'qty' => 1]
        ]
    ], 983);
    assertTest(
        "Soft-deleted product cannot be ordered (400)",
        $resOrderDeletedFail['status'] === 400 && strpos($resOrderDeletedFail['raw'], 'inactive') !== false,
        "Status: {$resOrderDeletedFail['status']}, Raw: {$resOrderDeletedFail['raw']}"
    );

} finally {
    // Teardown Test Data
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE branch_id IN (991, 992))");
    $pdo->exec("DELETE FROM orders WHERE branch_id IN (991, 992)");
    $pdo->exec("DELETE FROM branch_products WHERE branch_id IN (991, 992) OR product_id IN (SELECT id FROM products WHERE name LIKE 'TEST_MENU_%')");
    $pdo->exec("DELETE FROM products WHERE name LIKE 'TEST_MENU_%'");
    $pdo->exec("DELETE FROM users WHERE id IN (980, 981, 982, 983, 984)");
    $pdo->exec("DELETE FROM branches WHERE id IN (991, 992)");
    $pdo->exec("DELETE FROM cash_sessions WHERE id IN (998, 999)");
    $pdo->exec("DELETE FROM doc_counters WHERE document_type = 'ORDER'");
    $pdo->exec("DELETE FROM audit_logs WHERE details LIKE '%TEST_MENU_%'");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
}

echo "\nTests Completed. Failed: {$failed}\n";
if ($failed > 0) {
    exit(1);
}
