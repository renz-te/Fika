<?php
require_once __DIR__ . '/../../app/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    die("Tests must be run from CLI.\n");
}

echo "====================================================================\n";
echo "Recipes & Idempotent Stock Deduction Test Suite (Phase 13B)\n";
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

// 0. Cleanup Old Test Fixtures
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM order_ledger_deductions WHERE branch_id = 961 OR order_id IN (SELECT id FROM orders WHERE branch_id = 961)");
$pdo->exec("DELETE FROM order_audit WHERE order_id IN (SELECT id FROM orders WHERE branch_id = 961)");
$pdo->exec("DELETE FROM payments WHERE order_id IN (SELECT id FROM orders WHERE branch_id = 961)");
$pdo->exec("DELETE FROM order_item_modifiers WHERE order_item_id IN (SELECT id FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE branch_id = 961))");
$pdo->exec("DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE branch_id = 961)");
$pdo->exec("DELETE FROM orders WHERE branch_id = 961");
$pdo->exec("DELETE FROM recipes WHERE product_id = 9601 OR modifier_id = 9601 OR inventory_id IN (9601, 9602, 9603)");
$pdo->exec("DELETE FROM inventory_ledger WHERE branch_id = 961 OR inventory_id IN (9601, 9602, 9603)");
$pdo->exec("DELETE FROM inventory_stock WHERE branch_id = 961 OR inventory_id IN (9601, 9602, 9603)");
$pdo->exec("DELETE FROM branch_products WHERE branch_id = 961 OR product_id = 9601");
$pdo->exec("DELETE FROM products WHERE id = 9601");
$pdo->exec("DELETE FROM modifiers WHERE id = 9601");
$pdo->exec("DELETE FROM inventory_items WHERE id IN (9601, 9602, 9603)");
$pdo->exec("DELETE FROM cash_sessions WHERE branch_id = 961");
$pdo->exec("DELETE FROM users WHERE id IN (961, 962)");
$pdo->exec("DELETE FROM branches WHERE id = 961");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

// 1. Setup Test Branch & Users
$pdo->exec("INSERT INTO branches (id, name) VALUES (961, 'Recipe Test Branch')");

// Role 6 = BM with pos.access, pos.order_create, pos.override, inventory.view, inventory.adjust
$pdo->exec("INSERT IGNORE INTO permissions (name, description) VALUES 
    ('pos.access', 'Access POS'),
    ('pos.order_create', 'Create Orders'),
    ('pos.void_order', 'Void orders'),
    ('pos.refund_order', 'Refund orders'),
    ('inventory.view', 'View inventory'),
    ('inventory.adjust', 'Adjust inventory')
");
$pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_name) VALUES 
    (6, 'pos.access'), (6, 'pos.order_create'), (6, 'pos.void_order'), (6, 'pos.refund_order'), (6, 'inventory.view'), (6, 'inventory.adjust')
");

$pinHash = password_hash('1234', PASSWORD_DEFAULT);
$pdo->exec("INSERT INTO users (id, username, password, role_id, branch_id) VALUES 
    (961, 'recipe_cashier', '{$pinHash}', 6, 961),
    (962, 'recipe_manager', '{$pinHash}', 6, 961)
");

// Cash session for POS orders
$pdo->exec("INSERT INTO cash_sessions (id, branch_id, cashier_id, starting_cash, status, opened_at) 
    VALUES (9610, 961, 961, 1000.00, 'OPEN', NOW())
");

// 2. Setup Test Inventory Items
// Item 9601: Coffee Beans (g) @ ₱1.50
// Item 9602: Fresh Milk (ml) @ ₱0.10
// Item 9603: Oat Milk (ml) @ ₱0.20
$pdo->exec("INSERT INTO inventory_items (id, item_name, unit, unit_cost, created_at) VALUES 
    (9601, 'TEST_Coffee_Beans', 'g', 1.50, NOW()),
    (9602, 'TEST_Fresh_Milk', 'ml', 0.10, NOW()),
    (9603, 'TEST_Oat_Milk', 'ml', 0.20, NOW())
");

// 3. Setup Test Products & Modifiers
$pdo->exec("INSERT INTO products (id, name, category, price, price_cents, is_active, created_at) VALUES 
    (9601, 'TEST_Hot_Latte', 'Coffee', 150.00, 15000, 1, NOW())
");
$pdo->exec("INSERT INTO branch_products (branch_id, product_id, is_active) VALUES (961, 9601, 1)");

$pdo->exec("INSERT INTO modifiers (id, name, price, is_active, created_at) VALUES 
    (9601, 'TEST_Oat_Milk_Mod', 40.00, 1, NOW())
");

// 4. Subshell Helper
function callApi(string $relativePath, array $params = [], ?int $userId = 961, string $method = 'POST'): array {
    global $pdo;
    $user = null;
    if ($userId) {
        $uStmt = $pdo->prepare("SELECT id, username, role_id, branch_id FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $user = $uStmt->fetch(PDO::FETCH_ASSOC);
    }

    $wrapper = __DIR__ . '/wrapper_test_' . uniqid() . '.php';
    $targetPath = realpath(__DIR__ . '/../../' . $relativePath);

    $token = bin2hex(random_bytes(32));
    $sessionInit = "\$_SESSION['csrf_token'] = '{$token}';\n";
    $sessionInit .= "\$_SESSION['pos_session_id'] = 9610;\n";
    if ($user) {
        $sessionInit .= "\$_SESSION['user'] = " . var_export($user, true) . ";\n";
        $sessionInit .= "\$_SESSION['last_activity'] = time();\n";
    }

    $headerCode = "\$_SERVER['HTTP_X_CSRF_TOKEN'] = '{$token}';\n";
    $headerCode .= "\$_SERVER['REQUEST_METHOD'] = '{$method}';\n";

    if ($method === 'GET') {
        $queryOrBody = "\$_GET = " . var_export($params, true) . ";\n";
    } else {
        $queryOrBody = "\$_POST = " . var_export($params, true) . ";\n";
    }

    $code = "<?php
    session_start();
    {$sessionInit}
    {$headerCode}
    {$queryOrBody}

    register_shutdown_function(function() {
        \$c = http_response_code();
        if (\$c === false || \$c === 0 || \$c === null) {
            \$c = 200;
        }
        echo \"\\n---STATUS:\" . \$c . \"---\\n\";
    });

    // Mock php://input
    stream_wrapper_unregister('php');
    stream_wrapper_register('php', 'MockStream');
    class MockStream {
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

    ob_start();
    try {
        require " . var_export($targetPath, true) . ";
    } catch (\\Throwable \$t) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => \$t->getMessage()]);
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
    $body = trim(preg_replace('/---STATUS:.*?---\s*$/s', '', $raw));
    $json = json_decode($body, true);

    return ['status' => $statusCode, 'body' => $body, 'json' => $json];
}

// ====================================================================
// CASE 1: Mapping a recipe (Latte -> 20g Coffee, 250ml Milk) saves correctly
// ====================================================================
// 1. Map Coffee Beans (20.00g) to Product 9601
$res1_a = callApi('fika_inventory/api/fika_inventory_recipe.php', [
    'action' => 'save_ingredient',
    'type' => 'product',
    'target_id' => 9601,
    'inventory_id' => 9601,
    'quantity' => 20.00
], 961, 'POST');

// 2. Map Fresh Milk (250.00ml) to Product 9601
$res1_b = callApi('fika_inventory/api/fika_inventory_recipe.php', [
    'action' => 'save_ingredient',
    'type' => 'product',
    'target_id' => 9601,
    'inventory_id' => 9602,
    'quantity' => 250.00
], 961, 'POST');

// 3. Map Oat Milk (250.00ml) to Modifier 9601
$res1_c = callApi('fika_inventory/api/fika_inventory_recipe.php', [
    'action' => 'save_ingredient',
    'type' => 'modifier',
    'target_id' => 9601,
    'inventory_id' => 9603,
    'quantity' => 250.00
], 961, 'POST');

// Verify via list API
$res1_list = callApi('fika_inventory/api/fika_inventory_recipe.php', [
    'action' => 'list'
], 961, 'GET');

$savedProd = null;
foreach ($res1_list['json']['products'] ?? [] as $p) {
    if ((int)$p['id'] === 9601) {
        $savedProd = $p;
        break;
    }
}

$prodIngCount = count($savedProd['ingredients'] ?? []);
// COGS should be: 20g * 1.50 (30.00) + 250ml * 0.10 (25.00) = 55.00
$cogs = (float)($savedProd['cogs'] ?? 0);

assertTest(
    "Case 1: Mapping a recipe (Latte -> 20g Coffee, 250ml Milk) saves correctly with accurate COGS",
    $res1_a['status'] === 200 &&
    $res1_b['status'] === 200 &&
    $res1_c['status'] === 200 &&
    $prodIngCount === 2 &&
    $cogs === 55.00,
    "Expected 2 ingredients with 55.00 COGS, got ingCount={$prodIngCount}, cogs={$cogs}, json=" . json_encode($savedProd)
);

// ====================================================================
// CASE 2: Placing an order for 2 Lattes deducts exactly 40g coffee and 500ml milk
// ====================================================================
// Seed initial stock
$pdo->exec("INSERT INTO inventory_stock (branch_id, inventory_id, current_stock) VALUES 
    (961, 9601, 1000.00),
    (961, 9602, 5000.00),
    (961, 9603, 2000.00)
    ON DUPLICATE KEY UPDATE current_stock = VALUES(current_stock)
");

$res2 = callApi('fika_pos/api/fika_pos_order_create.php', [
    'items' => [
        ['id' => 9601, 'qty' => 2]
    ]
], 961, 'POST');

$orderId2 = (int)($res2['json']['order_id'] ?? 0);

$stockCoffee2 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE branch_id = 961 AND inventory_id = 9601")->fetchColumn();
$stockMilk2 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE branch_id = 961 AND inventory_id = 9602")->fetchColumn();

// Verify negative rows in inventory_ledger with type = 'SALE' and order_id
$ledgerRows2 = $pdo->query("
    SELECT inventory_id, type, quantity, order_id 
    FROM inventory_ledger 
    WHERE order_id = {$orderId2} AND type = 'SALE'
")->fetchAll(PDO::FETCH_ASSOC);

$coffeeLedger = null;
$milkLedger = null;
foreach ($ledgerRows2 as $lr) {
    if ((int)$lr['inventory_id'] === 9601) $coffeeLedger = (float)$lr['quantity'];
    if ((int)$lr['inventory_id'] === 9602) $milkLedger = (float)$lr['quantity'];
}

// Verify order_ledger_deductions row exists
$hasDeductionRecord2 = (bool)$pdo->query("SELECT id FROM order_ledger_deductions WHERE order_id = {$orderId2}")->fetchColumn();

assertTest(
    "Case 2: Placing an order for 2 Lattes deducts exactly 40g coffee and 500ml milk from stock",
    $res2['status'] === 200 &&
    $orderId2 > 0 &&
    $stockCoffee2 === 960.00 &&
    $stockMilk2 === 4500.00 &&
    $coffeeLedger === -40.00 &&
    $milkLedger === -500.00 &&
    $hasDeductionRecord2,
    "Expected stock 960g/4500ml and ledger -40/-500, got coffee={$stockCoffee2}, milk={$stockMilk2}, ledgerRows=" . json_encode($ledgerRows2)
);

// ====================================================================
// CASE 3: Voiding that order restores exactly 40g coffee and 500ml milk
// ====================================================================
$res3 = callApi('fika_pos/api/fika_pos_order_void_refund.php', [
    'order_id' => $orderId2,
    'action' => 'VOID',
    'supervisor_pin' => '1234',
    'reason' => 'Customer changed order before brewing'
], 962, 'POST');

$stockCoffee3 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE branch_id = 961 AND inventory_id = 9601")->fetchColumn();
$stockMilk3 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE branch_id = 961 AND inventory_id = 9602")->fetchColumn();

// Check VOID_RESTOCK ledger entries
$restockLedger = $pdo->query("
    SELECT inventory_id, type, quantity, order_id 
    FROM inventory_ledger 
    WHERE order_id = {$orderId2} AND type = 'VOID_RESTOCK'
")->fetchAll(PDO::FETCH_ASSOC);

$restockCoffee = null;
$restockMilk = null;
foreach ($restockLedger as $rl) {
    if ((int)$rl['inventory_id'] === 9601) $restockCoffee = (float)$rl['quantity'];
    if ((int)$rl['inventory_id'] === 9602) $restockMilk = (float)$rl['quantity'];
}

// Deduction record should be cleared to prevent double-restoration
$hasDeductionRecord3 = (bool)$pdo->query("SELECT id FROM order_ledger_deductions WHERE order_id = {$orderId2}")->fetchColumn();

assertTest(
    "Case 3: Voiding that order restores exactly 40g coffee and 500ml milk back to stock",
    $res3['status'] === 200 &&
    $stockCoffee3 === 1000.00 &&
    $stockMilk3 === 5000.00 &&
    $restockCoffee === 40.00 &&
    $restockMilk === 500.00 &&
    !$hasDeductionRecord3,
    "Expected restored stock 1000g/5000ml, got coffee={$stockCoffee3}, milk={$stockMilk3}, restock=" . json_encode($restockLedger)
);

// ====================================================================
// CASE 4: Replaying the same order does NOT deduct stock twice (idempotency)
// ====================================================================
// Place a new order for 1 Latte (requires 20g coffee, 250ml milk)
$res4_create = callApi('fika_pos/api/fika_pos_order_create.php', [
    'items' => [
        ['id' => 9601, 'qty' => 1]
    ]
], 961, 'POST');

$orderId4 = (int)($res4_create['json']['order_id'] ?? 0);
$stockCoffee4_afterFirst = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE branch_id = 961 AND inventory_id = 9601")->fetchColumn();
$stockMilk4_afterFirst = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE branch_id = 961 AND inventory_id = 9602")->fetchColumn();

// Expected after 1st deduction: 1000 - 20 = 980g, 5000 - 250 = 4750ml
// Replay deduction on the same orderId4
$res4_replay = callApi('fika_pos/api/fika_pos_order_create.php', [
    'order_id' => $orderId4,
    'items' => [
        ['id' => 9601, 'qty' => 1]
    ]
], 961, 'POST');

$stockCoffee4_afterReplay = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE branch_id = 961 AND inventory_id = 9601")->fetchColumn();
$stockMilk4_afterReplay = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE branch_id = 961 AND inventory_id = 9602")->fetchColumn();

// Count of 'SALE' rows for orderId4 in inventory_ledger should remain exactly 2
$saleRowsCount = (int)$pdo->query("SELECT COUNT(*) FROM inventory_ledger WHERE order_id = {$orderId4} AND type = 'SALE'")->fetchColumn();

assertTest(
    "Case 4: Replaying the same order (same order ID) does NOT deduct stock twice (idempotency)",
    $res4_replay['status'] === 200 &&
    $stockCoffee4_afterFirst === 980.00 &&
    $stockMilk4_afterFirst === 4750.00 &&
    $stockCoffee4_afterReplay === 980.00 &&
    $stockMilk4_afterReplay === 4750.00 &&
    $saleRowsCount === 2,
    "Expected stock unchanged at 980g/4750ml and 2 ledger rows, got coffee={$stockCoffee4_afterReplay}, milk={$stockMilk4_afterReplay}, saleRows={$saleRowsCount}"
);

// ====================================================================
// CASE 5: Attempting to order with insufficient ingredient stock fails with 400
// ====================================================================
// Set coffee stock to 10.00g (less than 20.00g needed for 1 Latte)
$pdo->exec("UPDATE inventory_stock SET current_stock = 10.00 WHERE branch_id = 961 AND inventory_id = 9601");

$res5 = callApi('fika_pos/api/fika_pos_order_create.php', [
    'items' => [
        ['id' => 9601, 'qty' => 1]
    ]
], 961, 'POST');

$stockCoffee5 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE branch_id = 961 AND inventory_id = 9601")->fetchColumn();
$stockMilk5 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE branch_id = 961 AND inventory_id = 9602")->fetchColumn();

assertTest(
    "Case 5: Attempting to order with insufficient ingredient stock fails with 400 and does NOT deduct stock",
    $res5['status'] === 400 &&
    strpos($res5['body'], 'Insufficient stock') !== false &&
    $stockCoffee5 === 10.00 &&
    $stockMilk5 === 4750.00,
    "Expected HTTP 400 with unchanged stock, got status={$res5['status']}, body={$res5['body']}, coffee={$stockCoffee5}"
);

// ====================================================================
// CASE 6: Order for Latte with 'Oat Milk' modifier deducts BOTH base recipe & modifier recipe
// ====================================================================
// Reset stocks
$pdo->exec("UPDATE inventory_stock SET current_stock = 1000.00 WHERE branch_id = 961 AND inventory_id = 9601");
$pdo->exec("UPDATE inventory_stock SET current_stock = 5000.00 WHERE branch_id = 961 AND inventory_id = 9602");
$pdo->exec("UPDATE inventory_stock SET current_stock = 2000.00 WHERE branch_id = 961 AND inventory_id = 9603");

// Place order for 2 Lattes with Oat Milk modifier (Mod 9601)
// Expected deductions for 2 items:
// Coffee: 2 * 20g = 40g
// Fresh Milk: 2 * 250ml = 500ml
// Oat Milk: 2 * 250ml = 500ml
$res6 = callApi('fika_pos/api/fika_pos_order_create.php', [
    'items' => [
        [
            'id' => 9601,
            'qty' => 2,
            'modifiers' => [9601]
        ]
    ]
], 961, 'POST');

$orderId6 = (int)($res6['json']['order_id'] ?? 0);

$stockCoffee6 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE branch_id = 961 AND inventory_id = 9601")->fetchColumn();
$stockMilk6 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE branch_id = 961 AND inventory_id = 9602")->fetchColumn();
$stockOat6 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE branch_id = 961 AND inventory_id = 9603")->fetchColumn();

$ledgerRows6 = $pdo->query("
    SELECT inventory_id, quantity 
    FROM inventory_ledger 
    WHERE order_id = {$orderId6} AND type = 'SALE'
")->fetchAll(PDO::FETCH_KEY_PAIR);

assertTest(
    "Case 6: Order for Latte with 'Oat Milk' modifier deducts BOTH base recipe and modifier recipe",
    $res6['status'] === 200 &&
    $orderId6 > 0 &&
    $stockCoffee6 === 960.00 &&
    $stockMilk6 === 4500.00 &&
    $stockOat6 === 1500.00 &&
    isset($ledgerRows6[9601]) && (float)$ledgerRows6[9601] === -40.00 &&
    isset($ledgerRows6[9602]) && (float)$ledgerRows6[9602] === -500.00 &&
    isset($ledgerRows6[9603]) && (float)$ledgerRows6[9603] === -500.00,
    "Expected coffee=960, milk=4500, oat=1500; got coffee={$stockCoffee6}, milk={$stockMilk6}, oat={$stockOat6}, ledger=" . json_encode($ledgerRows6)
);

// 9. Cleanup Test Fixtures
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM order_ledger_deductions WHERE branch_id = 961 OR order_id IN (SELECT id FROM orders WHERE branch_id = 961)");
$pdo->exec("DELETE FROM order_audit WHERE order_id IN (SELECT id FROM orders WHERE branch_id = 961)");
$pdo->exec("DELETE FROM payments WHERE order_id IN (SELECT id FROM orders WHERE branch_id = 961)");
$pdo->exec("DELETE FROM order_item_modifiers WHERE order_item_id IN (SELECT id FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE branch_id = 961))");
$pdo->exec("DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE branch_id = 961)");
$pdo->exec("DELETE FROM orders WHERE branch_id = 961");
$pdo->exec("DELETE FROM recipes WHERE product_id = 9601 OR modifier_id = 9601 OR inventory_id IN (9601, 9602, 9603)");
$pdo->exec("DELETE FROM inventory_ledger WHERE branch_id = 961 OR inventory_id IN (9601, 9602, 9603)");
$pdo->exec("DELETE FROM inventory_stock WHERE branch_id = 961 OR inventory_id IN (9601, 9602, 9603)");
$pdo->exec("DELETE FROM branch_products WHERE branch_id = 961 OR product_id = 9601");
$pdo->exec("DELETE FROM products WHERE id = 9601");
$pdo->exec("DELETE FROM modifiers WHERE id = 9601");
$pdo->exec("DELETE FROM inventory_items WHERE id IN (9601, 9602, 9603)");
$pdo->exec("DELETE FROM cash_sessions WHERE branch_id = 961");
$pdo->exec("DELETE FROM users WHERE id IN (961, 962)");
$pdo->exec("DELETE FROM branches WHERE id = 961");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

echo "\n--------------------------------------------------------------------\n";
echo "Recipe & Stock Deduction Test Suite Completed. Failed: {$failed}\n";
echo "--------------------------------------------------------------------\n";

exit($failed > 0 ? 1 : 0);
