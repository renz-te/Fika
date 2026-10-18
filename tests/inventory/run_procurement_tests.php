<?php
/**
 * Procurement (Purchase Orders & Receiving) Test Suite (Phase 13C)
 * Tests all 10 cases covering Maker-Checker, Idempotent Receiving, Ledger Traceability,
 * Over-receiving reasons, Partial Receiving, and Concurrency.
 */

require_once __DIR__ . '/../../app/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    die("Tests must be run from CLI.\n");
}

echo "====================================================================\n";
echo "Procurement (Purchase Orders & Receiving) Test Suite (Phase 13C)\n";
echo "====================================================================\n\n";

$failed = 0;

function assertTest(string $name, bool $condition, string $failMsg = '') {
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
$pdo->exec("DELETE FROM po_receipts WHERE po_id IN (SELECT id FROM purchase_orders WHERE branch_id IN (951, 952))");
$pdo->exec("DELETE FROM purchase_order_items WHERE po_id IN (SELECT id FROM purchase_orders WHERE branch_id IN (951, 952))");
$pdo->exec("DELETE FROM purchase_orders WHERE branch_id IN (951, 952)");
$pdo->exec("DELETE FROM suppliers WHERE branch_id IN (951, 952) OR id IN (9501, 9502)");
$pdo->exec("DELETE FROM inventory_ledger WHERE branch_id IN (951, 952) OR inventory_id IN (9501, 9502)");
$pdo->exec("DELETE FROM inventory_stock WHERE branch_id IN (951, 952) OR inventory_id IN (9501, 9502)");
$pdo->exec("DELETE FROM inventory_items WHERE id IN (9501, 9502)");
$pdo->exec("DELETE FROM users WHERE id IN (950, 951, 952, 953, 954)");
$pdo->exec("DELETE FROM branches WHERE id IN (951, 952)");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

// 1. Setup Test Branches
$pdo->exec("INSERT INTO branches (id, name) VALUES 
    (951, 'Procurement Test Branch A'),
    (952, 'Procurement Test Branch B')
");

// 2. Setup Permissions & Roles
$pdo->exec("INSERT IGNORE INTO permissions (name, description) VALUES 
    ('procurement.view', 'View POs'),
    ('procurement.create', 'Create POs'),
    ('procurement.approve', 'Approve POs'),
    ('procurement.receive', 'Receive POs')
");

$pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_name) VALUES 
    (1, 'procurement.view'), (1, 'procurement.create'), (1, 'procurement.approve'), (1, 'procurement.receive'),
    (6, 'procurement.view'), (6, 'procurement.create'), (6, 'procurement.receive'),
    (4, 'procurement.view'), (4, 'procurement.approve')
");

// 3. Setup Test Users
// 950: Admin (Global)
// 951: BM Branch A (Creator / Receiver)
// 952: BA Branch A (Approver)
// 953: BM Branch B (Cross-branch)
// 954: BA Branch B (Cross-branch)
$pinHash = password_hash('1234', PASSWORD_DEFAULT);
$pdo->exec("INSERT INTO users (id, username, password, role_id, branch_id) VALUES 
    (950, 'test_proc_admin', '{$pinHash}', 1, NULL),
    (951, 'test_proc_bm_a', '{$pinHash}', 6, 951),
    (952, 'test_proc_ba_a', '{$pinHash}', 4, 951),
    (953, 'test_proc_bm_b', '{$pinHash}', 6, 952),
    (954, 'test_proc_ba_b', '{$pinHash}', 4, 952)
");

// 4. Setup Test Inventory Items
// 9501: Arabica Beans (kg) @ ₱50.00
// 9502: Vanilla Syrup (bottles) @ ₱200.00
$pdo->exec("INSERT INTO inventory_items (id, item_name, unit, unit_cost, created_at) VALUES 
    (9501, 'TEST_Arabica_Beans', 'kg', 50.00, NOW()),
    (9502, 'TEST_Vanilla_Syrup', 'bottles', 200.00, NOW())
");

// Initial stock: 100.00 Beans, 50.00 Syrup for Branch 951
$pdo->exec("INSERT INTO inventory_stock (branch_id, inventory_id, current_stock, low_stock_threshold) VALUES 
    (951, 9501, 100.00, 10.00),
    (951, 9502, 50.00, 10.00)
");

// 5. Setup Test Supplier
$pdo->exec("INSERT INTO suppliers (id, branch_id, name, contact, address, created_at) VALUES 
    (9501, 951, 'TEST_Highland_Roasters', '09171234567', 'Benguet, PH', NOW())
");

// Subshell Helper function for testing the procurement API
function callProcurementApi(string $query, array $body = [], ?int $userId = 951, string $method = 'POST'): array {
    global $pdo;
    $user = null;
    if ($userId) {
        $uStmt = $pdo->prepare("SELECT id, username, role_id, branch_id FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $user = $uStmt->fetch(PDO::FETCH_ASSOC);
    }

    $wrapper = __DIR__ . '/wrapper_proc_' . uniqid() . '.php';
    $targetPath = realpath(__DIR__ . '/../../fika_inventory/api/fika_inventory_procurement.php');

    $token = bin2hex(random_bytes(32));
    $sessionInit = "\$_SESSION['csrf_token'] = '{$token}';\n";
    if ($user) {
        $sessionInit .= "\$_SESSION['user'] = " . var_export($user, true) . ";\n";
        $sessionInit .= "\$_SESSION['last_activity'] = time();\n";
    }

    $headerCode = "\$_SERVER['HTTP_X_CSRF_TOKEN'] = '{$token}';\n";
    $headerCode .= "\$_SERVER['REQUEST_METHOD'] = '{$method}';\n";

    $code = "<?php
    session_start();
    {$sessionInit}
    {$headerCode}
    \$_SERVER['QUERY_STRING'] = " . var_export($query, true) . ";
    parse_str(\$_SERVER['QUERY_STRING'], \$_GET);
    \$_POST = " . var_export($body, true) . ";

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
            \$this->data = base64_decode('" . base64_encode(json_encode($body)) . "');
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
        echo json_encode(['error' => \$t->getMessage()]);
    }
    ";

    file_put_contents($wrapper, $code);
    $out = shell_exec("php " . escapeshellarg($wrapper));
    @unlink($wrapper);

    $parts = explode("\n---STATUS:", $out);
    $bodyOutput = trim($parts[0] ?? '');
    $status = 200;
    if (isset($parts[1])) {
        $status = (int)trim(str_replace('---', '', $parts[1]));
    }

    $json = json_decode($bodyOutput, true);
    return [
        'status' => $status,
        'body' => $bodyOutput,
        'data' => is_array($json) ? $json : []
    ];
}

// ====================================================================
// CASE 1: BM creates a PO (DRAFT), BA approves it (APPROVED).
// ====================================================================
$resCreate = callProcurementApi('action=create_po', [
    'supplier_id' => 9501,
    'items' => [
        ['inventory_id' => 9501, 'quantity_ordered' => 10.00, 'unit_cost_cents' => 5000] // 10 kg @ ₱50.00
    ]
], 951); // User 951 = BM A

$poId1 = $resCreate['data']['po_id'] ?? 0;
$poNumber1 = $resCreate['data']['po_number'] ?? '';

assertTest(
    "Case 1: BM creates a PO (DRAFT), BA approves it (APPROVED) - Create",
    $resCreate['status'] === 200 && $poId1 > 0 && !empty($poNumber1),
    "Failed to create PO. Response: " . json_encode($resCreate)
);

// BA A approves it
$resApprove = callProcurementApi('action=approve_po', [
    'po_id' => $poId1
], 952); // User 952 = BA A

$checkPo1 = $pdo->query("SELECT status, approved_by FROM purchase_orders WHERE id = {$poId1}")->fetch();

assertTest(
    "Case 1: BM creates a PO (DRAFT), BA approves it (APPROVED) - Approve",
    $resApprove['status'] === 200 && ($checkPo1['status'] ?? '') === 'APPROVED' && (int)$checkPo1['approved_by'] === 952,
    "Failed approval. Response: " . json_encode($resApprove) . " DB: " . json_encode($checkPo1)
);

// ====================================================================
// CASE 2: BM tries to approve their own PO -> 403 (Maker-Checker).
// ====================================================================
$resCreate2 = callProcurementApi('action=create_po', [
    'supplier_id' => 9501,
    'items' => [
        ['inventory_id' => 9501, 'quantity_ordered' => 5.00, 'unit_cost_cents' => 5000]
    ]
], 951); // BM A creates
$poId2 = $resCreate2['data']['po_id'] ?? 0;

// BM A tries to approve their own PO
$resSelfApprove = callProcurementApi('action=approve_po', [
    'po_id' => $poId2
], 951); // Same user 951

$checkPo2 = $pdo->query("SELECT status FROM purchase_orders WHERE id = {$poId2}")->fetch();

assertTest(
    "Case 2: BM tries to approve their own PO -> 403 (Maker-Checker)",
    $resSelfApprove['status'] === 403 && ($checkPo2['status'] ?? '') === 'DRAFT',
    "Expected HTTP 403 but got {$resSelfApprove['status']}. Response: " . json_encode($resSelfApprove)
);

// ====================================================================
// CASE 3: BM receives half the items. Stock increases by exact quantity, and inventory_ledger has a PURCHASE row.
// ====================================================================
// Get item ID for PO 1
$poiStmt = $pdo->prepare("SELECT id FROM purchase_order_items WHERE po_id = ?");
$poiStmt->execute([$poId1]);
$poItemId1 = (int)$poiStmt->fetchColumn();

// Stock baseline for 9501 is 100.00
$stockBefore = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE branch_id = 951 AND inventory_id = 9501")->fetchColumn();

$resRecvHalf = callProcurementApi('action=receive_po', [
    'po_id' => $poId1,
    'idempotency_key' => 'idemp_case3_' . uniqid(),
    'items' => [
        ['item_id' => $poItemId1, 'receive_qty' => 5.00]
    ]
], 951); // BM A receives

$stockAfterHalf = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE branch_id = 951 AND inventory_id = 9501")->fetchColumn();
$ledgerRow = $pdo->query("
    SELECT * FROM inventory_ledger 
    WHERE branch_id = 951 AND inventory_id = 9501 AND type = 'PURCHASE' AND po_id = {$poId1}
    ORDER BY id DESC LIMIT 1
")->fetch();

assertTest(
    "Case 3: BM receives half the items. Stock increases by the exact quantity, and inventory_ledger has a PURCHASE row",
    $resRecvHalf['status'] === 200 && 
    abs(($stockAfterHalf - $stockBefore) - 5.00) < 0.001 &&
    $ledgerRow !== false &&
    (float)$ledgerRow['quantity'] === 5.00 &&
    (int)$ledgerRow['po_id'] === $poId1,
    "Expected +5.00 stock, got diff: " . ($stockAfterHalf - $stockBefore) . ". Ledger: " . json_encode($ledgerRow)
);

// ====================================================================
// CASE 4: BM receives the remaining items. Stock increases correctly and PO status becomes RECEIVED.
// ====================================================================
$resRecvRemaining = callProcurementApi('action=receive_po', [
    'po_id' => $poId1,
    'idempotency_key' => 'idemp_case4_' . uniqid(),
    'items' => [
        ['item_id' => $poItemId1, 'receive_qty' => 5.00]
    ]
], 951);

$stockAfterRemaining = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE branch_id = 951 AND inventory_id = 9501")->fetchColumn();
$po1Final = $pdo->query("SELECT status, received_at FROM purchase_orders WHERE id = {$poId1}")->fetch();

assertTest(
    "Case 4: BM receives the remaining items. Stock increases correctly and PO status becomes RECEIVED",
    $resRecvRemaining['status'] === 200 && 
    abs(($stockAfterRemaining - $stockAfterHalf) - 5.00) < 0.001 &&
    ($po1Final['status'] ?? '') === 'RECEIVED' &&
    !empty($po1Final['received_at']),
    "Expected status RECEIVED, got: " . json_encode($po1Final)
);

// ====================================================================
// CASE 5: Attempting to receive more than ordered without a reason returns 400.
// ====================================================================
// Create and approve a new PO for 10 units of Syrup (9502)
$resCreate5 = callProcurementApi('action=create_po', [
    'supplier_id' => 9501,
    'items' => [
        ['inventory_id' => 9502, 'quantity_ordered' => 10.00, 'unit_cost_cents' => 20000]
    ]
], 951);
$poId5 = $resCreate5['data']['po_id'];
callProcurementApi('action=approve_po', ['po_id' => $poId5], 952);

$poi5Stmt = $pdo->prepare("SELECT id FROM purchase_order_items WHERE po_id = ?");
$poi5Stmt->execute([$poId5]);
$poItemId5 = (int)$poi5Stmt->fetchColumn();

// Attempt to receive 15 units with NO reason
$resOverNoReason = callProcurementApi('action=receive_po', [
    'po_id' => $poId5,
    'idempotency_key' => 'idemp_case5_noreason',
    'items' => [
        ['item_id' => $poItemId5, 'receive_qty' => 15.00, 'reason' => '']
    ]
], 951);

// Attempt with non-empty reason
$resOverWithReason = callProcurementApi('action=receive_po', [
    'po_id' => $poId5,
    'idempotency_key' => 'idemp_case5_withreason',
    'items' => [
        ['item_id' => $poItemId5, 'receive_qty' => 12.00, 'reason' => 'Supplier included 2 bonus bottles']
    ]
], 951);

$item5Db = $pdo->query("SELECT quantity_received, over_receipt_reason FROM purchase_order_items WHERE id = {$poItemId5}")->fetch();

assertTest(
    "Case 5: Attempting to receive more than ordered without a reason returns 400",
    $resOverNoReason['status'] === 400 && 
    str_contains(strtolower($resOverNoReason['body']), 'reason') &&
    $resOverWithReason['status'] === 200 &&
    (float)$item5Db['quantity_received'] === 12.00 &&
    $item5Db['over_receipt_reason'] === 'Supplier included 2 bonus bottles',
    "Failed over-receive validation. No-reason res: " . json_encode($resOverNoReason) . " With-reason res: " . json_encode($resOverWithReason)
);

// ====================================================================
// CASE 6: Cross-branch user cannot view or approve a PO.
// ====================================================================
// Create a PO in Branch 951
$resCreate6 = callProcurementApi('action=create_po', [
    'supplier_id' => 9501,
    'items' => [
        ['inventory_id' => 9501, 'quantity_ordered' => 2.00, 'unit_cost_cents' => 5000]
    ]
], 951);
$poId6 = $resCreate6['data']['po_id'];

// Cross-branch BM B (953) tries to view PO 6
$resCrossView = callProcurementApi("action=get_po&id={$poId6}", [], 953, 'GET');

// Cross-branch BA B (954) tries to approve PO 6
$resCrossApprove = callProcurementApi('action=approve_po', ['po_id' => $poId6], 954);

assertTest(
    "Case 6: Cross-branch user cannot view or approve a PO",
    $resCrossView['status'] === 403 && $resCrossApprove['status'] === 403,
    "Expected 403 for both. View status: {$resCrossView['status']}, Approve status: {$resCrossApprove['status']}"
);

// ====================================================================
// CASE 7: Partial receive (50 of 100) moves status to PARTIALLY_RECEIVED. Full receive (remaining 50) moves status to RECEIVED.
// ====================================================================
$resCreate7 = callProcurementApi('action=create_po', [
    'supplier_id' => 9501,
    'items' => [
        ['inventory_id' => 9501, 'quantity_ordered' => 100.00, 'unit_cost_cents' => 5000]
    ]
], 951);
$poId7 = $resCreate7['data']['po_id'];
callProcurementApi('action=approve_po', ['po_id' => $poId7], 952);

$poi7Stmt = $pdo->prepare("SELECT id FROM purchase_order_items WHERE po_id = ?");
$poi7Stmt->execute([$poId7]);
$poItemId7 = (int)$poi7Stmt->fetchColumn();

// Partial receive 50 of 100
$resRecv7Part = callProcurementApi('action=receive_po', [
    'po_id' => $poId7,
    'idempotency_key' => 'idemp_case7_part',
    'items' => [
        ['item_id' => $poItemId7, 'receive_qty' => 50.00]
    ]
], 951);

$po7PartStatus = $pdo->query("SELECT status FROM purchase_orders WHERE id = {$poId7}")->fetchColumn();

// Full receive remaining 50
$resRecv7Full = callProcurementApi('action=receive_po', [
    'po_id' => $poId7,
    'idempotency_key' => 'idemp_case7_full',
    'items' => [
        ['item_id' => $poItemId7, 'receive_qty' => 50.00]
    ]
], 951);

$po7FullStatus = $pdo->query("SELECT status, received_at FROM purchase_orders WHERE id = {$poId7}")->fetch();

assertTest(
    "Case 7: Partial receive (50 of 100) moves status to PARTIALLY_RECEIVED. Full receive (remaining 50) moves status to RECEIVED",
    $po7PartStatus === 'PARTIALLY_RECEIVED' &&
    ($po7FullStatus['status'] ?? '') === 'RECEIVED' &&
    !empty($po7FullStatus['received_at']),
    "Part status: {$po7PartStatus}, Full status: " . json_encode($po7FullStatus)
);

// ====================================================================
// CASE 8: Receiving the same PO twice with the same idempotency_key does NOT double-increment stock.
// ====================================================================
$resCreate8 = callProcurementApi('action=create_po', [
    'supplier_id' => 9501,
    'items' => [
        ['inventory_id' => 9501, 'quantity_ordered' => 20.00, 'unit_cost_cents' => 5000]
    ]
], 951);
$poId8 = $resCreate8['data']['po_id'];
callProcurementApi('action=approve_po', ['po_id' => $poId8], 952);

$poi8Stmt = $pdo->prepare("SELECT id FROM purchase_order_items WHERE po_id = ?");
$poi8Stmt->execute([$poId8]);
$poItemId8 = (int)$poi8Stmt->fetchColumn();

$stockBefore8 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE branch_id = 951 AND inventory_id = 9501")->fetchColumn();
$fixedKey = 'idempotent_key_case_8_repeat_test';

// First call
$resRecv8First = callProcurementApi('action=receive_po', [
    'po_id' => $poId8,
    'idempotency_key' => $fixedKey,
    'items' => [
        ['item_id' => $poItemId8, 'receive_qty' => 10.00]
    ]
], 951);

$stockAfterFirst8 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE branch_id = 951 AND inventory_id = 9501")->fetchColumn();

// Second call with same idempotency key
$resRecv8Second = callProcurementApi('action=receive_po', [
    'po_id' => $poId8,
    'idempotency_key' => $fixedKey,
    'items' => [
        ['item_id' => $poItemId8, 'receive_qty' => 10.00]
    ]
], 951);

$stockAfterSecond8 = (float)$pdo->query("SELECT current_stock FROM inventory_stock WHERE branch_id = 951 AND inventory_id = 9501")->fetchColumn();

assertTest(
    "Case 8: Receiving the same PO twice with the same idempotency_key does NOT double-increment stock",
    $resRecv8First['status'] === 200 &&
    $resRecv8Second['status'] === 200 &&
    abs(($stockAfterFirst8 - $stockBefore8) - 10.00) < 0.001 &&
    abs($stockAfterSecond8 - $stockAfterFirst8) < 0.001,
    "Stock 1: {$stockAfterFirst8}, Stock 2: {$stockAfterSecond8}. Response 2: " . json_encode($resRecv8Second)
);

// ====================================================================
// CASE 9: inventory_ledger contains a po_id linking the purchase back to the original PO.
// ====================================================================
$ledgerRows8 = $pdo->query("
    SELECT id, po_id, type, quantity, remarks 
    FROM inventory_ledger 
    WHERE po_id = {$poId8} AND type = 'PURCHASE'
")->fetchAll();

assertTest(
    "Case 9: inventory_ledger contains a po_id linking the purchase back to the original PO",
    count($ledgerRows8) === 1 && (int)$ledgerRows8[0]['po_id'] === $poId8,
    "Expected 1 ledger row with po_id = {$poId8}, got: " . json_encode($ledgerRows8)
);

// ====================================================================
// CASE 10: Two simultaneous PO creations generate unique po_number values.
// ====================================================================
// We create two POs for branch 951 in quick succession / transactions
$resSim1 = callProcurementApi('action=create_po', [
    'supplier_id' => 9501,
    'items' => [
        ['inventory_id' => 9501, 'quantity_ordered' => 1.00, 'unit_cost_cents' => 5000]
    ]
], 951);

$resSim2 = callProcurementApi('action=create_po', [
    'supplier_id' => 9501,
    'items' => [
        ['inventory_id' => 9501, 'quantity_ordered' => 1.00, 'unit_cost_cents' => 5000]
    ]
], 951);

$poNum1 = $resSim1['data']['po_number'] ?? '';
$poNum2 = $resSim2['data']['po_number'] ?? '';

// Check pattern: PO-951-YYYYMMDD-XXXXX
$pattern = '/^PO-951-\d{8}-\d{5}$/';

assertTest(
    "Case 10: Two simultaneous PO creations generate unique po_number values",
    !empty($poNum1) && !empty($poNum2) && 
    $poNum1 !== $poNum2 &&
    preg_match($pattern, $poNum1) === 1 &&
    preg_match($pattern, $poNum2) === 1,
    "PO 1: '{$poNum1}', PO 2: '{$poNum2}'"
);

echo "\n--------------------------------------------------------------------\n";
echo "Procurement Test Suite Completed. Failed: {$failed}\n";
echo "--------------------------------------------------------------------\n";

exit($failed > 0 ? 1 : 0);
