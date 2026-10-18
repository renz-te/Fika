<?php
require_once __DIR__ . '/../../app/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    die("Tests must be run from CLI.\n");
}

echo "====================================================================\n";
echo "Inventory Stock, Adjustments & Maker-Checker Test Suite (Phase 13A)\n";
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

// 0. Cleanup Old Test Data
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM inventory_adjustments WHERE branch_id IN (971, 972) OR inventory_id IN (9701, 9702)");
$pdo->exec("DELETE FROM inventory_ledger WHERE branch_id IN (971, 972) OR inventory_id IN (9701, 9702)");
$pdo->exec("DELETE FROM inventory_stock WHERE branch_id IN (971, 972) OR inventory_id IN (9701, 9702)");
$pdo->exec("DELETE FROM inventory_items WHERE id IN (9701, 9702)");
$pdo->exec("DELETE FROM users WHERE id IN (970, 971, 972, 973, 974)");
$pdo->exec("DELETE FROM branches WHERE id IN (971, 972)");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

// 1. Setup Test Branches
$pdo->exec("INSERT INTO branches (id, name) VALUES 
    (971, 'Stock Test Branch A'),
    (972, 'Stock Test Branch B')
");

// 2. Setup Test Roles & Permissions
// Ensure permissions exist
$pdo->exec("INSERT IGNORE INTO permissions (name, description) VALUES 
    ('inventory.view', 'View stock levels'),
    ('inventory.adjust', 'Submit adjustments'),
    ('inventory.approve', 'Approve adjustments')
");
// Ensure role permissions:
// Role 1 (ADMIN): all
// Role 6 (BM): inventory.view, inventory.adjust
// Role 4 (BA): inventory.view, inventory.approve
// Role 7 (STAFF): no inventory permissions
$pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_name) VALUES 
    (1, 'inventory.view'), (1, 'inventory.adjust'), (1, 'inventory.approve'),
    (6, 'inventory.view'), (6, 'inventory.adjust'),
    (4, 'inventory.view'), (4, 'inventory.approve')
");

// 3. Setup Test Users
$pdo->exec("INSERT INTO users (id, username, password, role_id, branch_id) VALUES 
    (970, 'test_inv_admin', 'hash', 1, NULL),
    (971, 'test_inv_bm_a', 'hash', 6, 971),
    (972, 'test_inv_ba_a', 'hash', 4, 971),
    (973, 'test_inv_bm_b', 'hash', 6, 972),
    (974, 'test_inv_staff', 'hash', 7, 971)
");

// 4. Setup Settings
$pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES 
    ('inventory_approval_threshold_cents', '50000'),
    ('inventory_allow_negative', '0')
    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
");

// 5. Setup Test Inventory Items
// Item 9701: Beans (₱50.00 / kg)
// Item 9702: Milk (₱80.00 / liter)
$pdo->exec("INSERT INTO inventory_items (id, item_name, unit, unit_cost, created_at) VALUES 
    (9701, 'TEST_INV_Beans', 'kg', 50.00, NOW()),
    (9702, 'TEST_INV_Milk', 'liters', 80.00, NOW())
");

// 6. Subshell Execution Helper
function callInventoryApi(array $params = [], ?int $userId = null, string $method = 'POST', array $headers = []): array {
    global $pdo;
    $user = null;
    if ($userId) {
        $uStmt = $pdo->prepare("SELECT id, username, role_id, branch_id FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $user = $uStmt->fetch(PDO::FETCH_ASSOC);
    }

    $wrapper = __DIR__ . '/wrapper_inv_' . uniqid() . '.php';
    $targetPath = realpath(__DIR__ . '/../../fika_inventory/api/fika_inventory_adjust.php');

    $headerCode = "";
    foreach ($headers as $k => $v) {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $k));
        $headerCode .= "\$_SERVER['{$serverKey}'] = " . var_export($v, true) . ";\n";
    }

    $sessionInit = "";
    $token = bin2hex(random_bytes(32));
    if ($user) {
        $sessionInit = "\$_SESSION['user'] = " . var_export($user, true) . ";\n";
        $sessionInit .= "\$_SESSION['last_activity'] = time();\n";
    }
    $sessionInit .= "\$_SESSION['csrf_token'] = " . var_export($token, true) . ";\n";

    if (!isset($headers['X-CSRF-Token']) && !isset($headers['x-csrf-token'])) {
        $headerCode .= "\$_SERVER['HTTP_X_CSRF_TOKEN'] = " . var_export($token, true) . ";\n";
    }

    $methodCode = "\$_SERVER['REQUEST_METHOD'] = '{$method}';\n";
    $queryOrBody = "";
    if ($method === 'GET') {
        $queryOrBody = "\$_GET = " . var_export($params, true) . ";\n";
    } else {
        $queryOrBody = "\$_POST = " . var_export($params, true) . ";\n";
    }

    $code = "<?php
    session_start();
    {$sessionInit}
    {$methodCode}
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
        http_response_code(500);
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
// CASE 1: Initial stock equals exact sum of inventory_ledger entries
// ====================================================================
// Seed initial ledger: Restock 15.00, Sold -5.00 => Net sum = 10.00
$pdo->exec("INSERT INTO inventory_ledger (branch_id, inventory_id, type, quantity, unit_cost_snapshot, user_id, remarks, created_at) VALUES 
    (971, 9701, 'Restock', 15.00, 50.00, 970, 'Initial delivery', NOW()),
    (971, 9701, 'Sold', -5.00, 50.00, 970, 'Opening sales', NOW())
");
// Sync inventory_stock
$pdo->exec("INSERT INTO inventory_stock (branch_id, inventory_id, current_stock, low_stock_threshold) VALUES 
    (971, 9701, 10.00, 10.00)
");

$res1 = callInventoryApi(['action' => 'list', 'branch_id' => 971], 971, 'GET');
$items1 = $res1['json']['data'] ?? [];
$item1 = null;
foreach ($items1 as $it) {
    if ((int)$it['id'] === 9701) {
        $item1 = $it;
        break;
    }
}

$sumLedger1 = (float)$pdo->query("SELECT SUM(quantity) FROM inventory_ledger WHERE branch_id = 971 AND inventory_id = 9701")->fetchColumn();
$stockRow1 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE branch_id = 971 AND inventory_id = 9701")->fetchColumn();

assertTest(
    "Case 1: Initial stock equals exact sum of inventory_ledger entries",
    $res1['status'] === 200 &&
    $item1 !== null &&
    (float)$item1['current_stock'] === 10.00 &&
    $sumLedger1 === 10.00 &&
    $stockRow1 === 10.00 &&
    !empty($item1['is_low_stock']),
    "Expected 10.00 in ledger and stock, got item=" . json_encode($item1) . ", body=" . $res1['body'] . ", ledger={$sumLedger1}, stock={$stockRow1}"
);

// ====================================================================
// CASE 2: Auto-approval: Adjustment <= ₱500 auto-approved
// ====================================================================
// Adjust +5.00 units @ ₱50.00 = ₱250.00 (25,000 cents <= 50,000 cents threshold)
$res2 = callInventoryApi([
    'action' => 'submit',
    'branch_id' => 971,
    'inventory_id' => 9701,
    'quantity' => 5.00,
    'reason' => 'Found unopened bag during count'
], 971, 'POST');

$adjId2 = $res2['json']['adjustment_id'] ?? 0;
$adjRow2 = $pdo->query("SELECT * FROM inventory_adjustments WHERE id = {$adjId2}")->fetch(PDO::FETCH_ASSOC);
$sumLedger2 = (float)$pdo->query("SELECT SUM(quantity) FROM inventory_ledger WHERE branch_id = 971 AND inventory_id = 9701")->fetchColumn();
$stockRow2 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE branch_id = 971 AND inventory_id = 9701")->fetchColumn();

assertTest(
    "Case 2: Adjustment <= ₱500 is auto-approved with audit row and stock sync",
    $res2['status'] === 200 &&
    ($res2['json']['status'] ?? '') === 'APPROVED' &&
    $adjRow2 &&
    $adjRow2['status'] === 'APPROVED' &&
    (int)$adjRow2['created_by'] === 971 &&
    (int)$adjRow2['approved_by'] === 971 &&
    (int)$adjRow2['total_cost_cents'] === 25000 &&
    $sumLedger2 === 15.00 &&
    $stockRow2 === 15.00,
    "Expected auto-approved status with 15.00 stock, got: " . json_encode($res2['json']) . " adjRow: " . json_encode($adjRow2)
);

// ====================================================================
// CASE 3: Threshold hold: Adjustment > ₱500 held in PENDING queue
// ====================================================================
// Adjust +15.00 units @ ₱50.00 = ₱750.00 (75,000 cents > 50,000 cents threshold)
$res3 = callInventoryApi([
    'action' => 'submit',
    'branch_id' => 971,
    'inventory_id' => 9701,
    'quantity' => 15.00,
    'reason' => 'Large supplier consignment batch'
], 971, 'POST');

$adjId3 = $res3['json']['adjustment_id'] ?? 0;
$adjRow3 = $pdo->query("SELECT * FROM inventory_adjustments WHERE id = {$adjId3}")->fetch(PDO::FETCH_ASSOC);
$sumLedger3 = (float)$pdo->query("SELECT SUM(quantity) FROM inventory_ledger WHERE branch_id = 971 AND inventory_id = 9701")->fetchColumn();
$stockRow3 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE branch_id = 971 AND inventory_id = 9701")->fetchColumn();

assertTest(
    "Case 3: Adjustment > ₱500 is held in PENDING Maker-Checker queue without stock movement",
    $res3['status'] === 200 &&
    ($res3['json']['status'] ?? '') === 'PENDING' &&
    $adjRow3 &&
    $adjRow3['status'] === 'PENDING' &&
    $adjRow3['approved_by'] === null &&
    (int)$adjRow3['total_cost_cents'] === 75000 &&
    $sumLedger3 === 15.00 &&
    $stockRow3 === 15.00,
    "Expected PENDING with no ledger/stock movement, got: " . json_encode($res3['json']) . " ledger={$sumLedger3}, stock={$stockRow3}"
);

// ====================================================================
// CASE 4: Maker-Checker constraint: Maker cannot approve own adjustment
// ====================================================================
// Part A: User 971 (BM who submitted adjId3) attempts to approve adjId3 -> rejected 403
$res4_a = callInventoryApi([
    'action' => 'approve',
    'adjustment_id' => $adjId3
], 971, 'POST');

// Part B: User 970 (Admin who possesses inventory.approve) submits an adjustment, then attempts to approve their own
$res4_sub = callInventoryApi([
    'action' => 'submit',
    'branch_id' => 971,
    'inventory_id' => 9701,
    'quantity' => 20.00,
    'reason' => 'Admin self-approval test'
], 970, 'POST');
$adjIdAdmin = $res4_sub['json']['adjustment_id'] ?? 0;

$res4_b = callInventoryApi([
    'action' => 'approve',
    'adjustment_id' => $adjIdAdmin
], 970, 'POST');

$adjRow4 = $pdo->query("SELECT status FROM inventory_adjustments WHERE id = {$adjId3}")->fetchColumn();
$adjRowAdmin = $pdo->query("SELECT status FROM inventory_adjustments WHERE id = {$adjIdAdmin}")->fetchColumn();

assertTest(
    "Case 4: Maker cannot approve their own adjustment (HTTP 403 enforced for submitter and checker)",
    $res4_a['status'] === 403 &&
    $res4_b['status'] === 403 &&
    strpos($res4_b['body'], 'Maker cannot approve their own adjustment') !== false &&
    $adjRow4 === 'PENDING' &&
    $adjRowAdmin === 'PENDING',
    "Expected HTTP 403 for self-approval, got res4_a={$res4_a['status']}, res4_b={$res4_b['status']} body: {$res4_b['body']}"
);

// ====================================================================
// CASE 5: Checker approval: Branch Admin (BA) approves pending adjustment
// ====================================================================
// User 972 (BA with inventory.approve, different user) approves adjId3
$res5 = callInventoryApi([
    'action' => 'approve',
    'adjustment_id' => $adjId3
], 972, 'POST');

$adjRow5 = $pdo->query("SELECT * FROM inventory_adjustments WHERE id = {$adjId3}")->fetch(PDO::FETCH_ASSOC);
$sumLedger5 = (float)$pdo->query("SELECT SUM(quantity) FROM inventory_ledger WHERE branch_id = 971 AND inventory_id = 9701")->fetchColumn();
$stockRow5 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE branch_id = 971 AND inventory_id = 9701")->fetchColumn();

assertTest(
    "Case 5: Checker approval by authorized user transitions to APPROVED and updates ledger/stock",
    $res5['status'] === 200 &&
    ($res5['json']['status'] ?? '') === 'APPROVED' &&
    $adjRow5 &&
    $adjRow5['status'] === 'APPROVED' &&
    (int)$adjRow5['approved_by'] === 972 &&
    $adjRow5['approved_at'] !== null &&
    $sumLedger5 === 30.00 &&
    $stockRow5 === 30.00,
    "Expected APPROVED by user 972 and new stock 30.00, got: " . json_encode($res5['json']) . " stock={$stockRow5}, ledger={$sumLedger5}"
);

// ====================================================================
// CASE 6: Cross-branch rejection: Branch 1 user accessing Branch 2 gets 403
// ====================================================================
// User 971 (scoped to Branch 971) tries to submit adjustment for Branch 972
$res6_submit = callInventoryApi([
    'action' => 'submit',
    'branch_id' => 972,
    'inventory_id' => 9701,
    'quantity' => 2.00,
    'reason' => 'Cross branch breach attempt'
], 971, 'POST');

// Setup pending adjustment in Branch 972 created by User 973
$pdo->exec("INSERT INTO inventory_adjustments (branch_id, inventory_id, quantity, unit_cost_snapshot, total_cost_cents, reason, status, created_by, created_at)
    VALUES (972, 9701, 20.00, 50.00, 100000, 'Branch 2 test pending', 'PENDING', 973, NOW())
");
$adjIdBranch2 = (int)$pdo->lastInsertId();

// User 972 (BA of Branch 971) tries to approve adjustment of Branch 972
$res6_approve = callInventoryApi([
    'action' => 'approve',
    'adjustment_id' => $adjIdBranch2
], 972, 'POST');

assertTest(
    "Case 6: Cross-branch adjustment submission and approval are strictly rejected (HTTP 403)",
    $res6_submit['status'] === 403 &&
    $res6_approve['status'] === 403,
    "Expected 403 for cross branch, got submit={$res6_submit['status']}, approve={$res6_approve['status']}"
);

// ====================================================================
// CASE 7: Negative stock refusal: Adjustment dropping stock below zero rejected
// ====================================================================
// Current stock is 30.00. Attempt to reduce by -35.00 => would result in -5.00
$res7 = callInventoryApi([
    'action' => 'submit',
    'branch_id' => 971,
    'inventory_id' => 9701,
    'quantity' => -35.00,
    'reason' => 'Excessive spoilage claim'
], 971, 'POST');

$sumLedger7 = (float)$pdo->query("SELECT SUM(quantity) FROM inventory_ledger WHERE branch_id = 971 AND inventory_id = 9701")->fetchColumn();
$stockRow7 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE branch_id = 971 AND inventory_id = 9701")->fetchColumn();

assertTest(
    "Case 7: Negative stock refusal rejects request with HTTP 400 when inventory_allow_negative=0",
    $res7['status'] === 400 &&
    $sumLedger7 === 30.00 &&
    $stockRow7 === 30.00,
    "Expected HTTP 400 with unchanged stock of 30.00, got status {$res7['status']} body: {$res7['body']}"
);

// ====================================================================
// CASE 8: Stock/Ledger sync assertion & Soft-deactivate test
// ====================================================================
// Constraint 3 explicit assertion: Execute multiple adjustments (auto-approved negative, checker-approved positive)
// 1. Auto-approved negative adjustment: -8.00 units @ ₱50 = ₱400 (<= ₱500)
$res8_neg = callInventoryApi([
    'action' => 'submit',
    'branch_id' => 971,
    'inventory_id' => 9701,
    'quantity' => -8.00,
    'reason' => 'Approved batch spoilage disposal'
], 971, 'POST');

// 2. High-value positive adjustment: +12.00 units @ ₱50 = ₱600 (> ₱500), then approved by BA 972
$res8_pos = callInventoryApi([
    'action' => 'submit',
    'branch_id' => 971,
    'inventory_id' => 9701,
    'quantity' => 12.00,
    'reason' => 'Stock audit replenishment'
], 971, 'POST');
$adjId8_pos = $res8_pos['json']['adjustment_id'] ?? 0;

$res8_pos_app = callInventoryApi([
    'action' => 'approve',
    'adjustment_id' => $adjId8_pos
], 972, 'POST');

// Check exact DB sync: inventory_stock.current_stock === SUM(quantity) FROM inventory_ledger
$finalLedgerSum = (float)$pdo->query("SELECT SUM(quantity) FROM inventory_ledger WHERE branch_id = 971 AND inventory_id = 9701")->fetchColumn();
$finalStockCurrent = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE branch_id = 971 AND inventory_id = 9701")->fetchColumn();
$expectedFinalStock = 30.00 - 8.00 + 12.00; // 34.00

$stockLedgerExactSync = (abs($finalStockCurrent - $finalLedgerSum) < 0.0001) && ($finalStockCurrent === $expectedFinalStock);

// Test soft-deactivate of Item 9702
$res8_deact = callInventoryApi([
    'action' => 'item_deactivate',
    'id' => 9702
], 971, 'POST');

$deletedAt9702 = $pdo->query("SELECT deleted_at FROM inventory_items WHERE id = 9702")->fetchColumn();

// Verify deactivated item does NOT appear in active stock list
$res8_list = callInventoryApi(['action' => 'list', 'branch_id' => 971], 971, 'GET');
$activeItems = $res8_list['json']['data'] ?? [];
$deactivatedFound = false;
foreach ($activeItems as $ai) {
    if ((int)$ai['id'] === 9702) {
        $deactivatedFound = true;
        break;
    }
}

assertTest(
    "Case 8: inventory_stock.current_stock strictly matches SELECT SUM(quantity) FROM inventory_ledger and soft-deactivate works",
    $stockLedgerExactSync &&
    $res8_deact['status'] === 200 &&
    $deletedAt9702 !== null &&
    !$deactivatedFound,
    "Sync error or soft-deactivate failure: stock={$finalStockCurrent}, ledger={$finalLedgerSum}, expected={$expectedFinalStock}, deactFound={$deactivatedFound}"
);

// 9. Cleanup Test Data
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM inventory_adjustments WHERE branch_id IN (971, 972) OR inventory_id IN (9701, 9702)");
$pdo->exec("DELETE FROM inventory_ledger WHERE branch_id IN (971, 972) OR inventory_id IN (9701, 9702)");
$pdo->exec("DELETE FROM inventory_stock WHERE branch_id IN (971, 972) OR inventory_id IN (9701, 9702)");
$pdo->exec("DELETE FROM inventory_items WHERE id IN (9701, 9702)");
$pdo->exec("DELETE FROM users WHERE id IN (970, 971, 972, 973, 974)");
$pdo->exec("DELETE FROM branches WHERE id IN (971, 972)");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

echo "\n--------------------------------------------------------------------\n";
echo "Inventory Stock Test Suite Completed. Failed: {$failed}\n";
echo "--------------------------------------------------------------------\n";

exit($failed > 0 ? 1 : 0);
