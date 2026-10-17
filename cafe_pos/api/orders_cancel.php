<?php
require_once __DIR__ . '/../database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Mandatory Staff Session Authentication
$isStaff = !empty($_SESSION['user']) && in_array(
    $_SESSION['user']['role'] ?? '',
    ['Cashier', 'Admin', 'Super Admin', 'Branch Manager', 'Head Barista', 'Branch Admin'],
    true
);

if (!$isStaff) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: authentication required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['order_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$orderId = (int)$input['order_id'];

// 2. Fetch Order outside transaction to verify existence and branch
$stmtOrder = $pdo->prepare("SELECT id, order_number, branch_id, is_test FROM orders WHERE id = ?");
$stmtOrder->execute([$orderId]);
$order = $stmtOrder->fetch();

if (!$order) {
    http_response_code(404);
    echo json_encode(['error' => 'Order not found']);
    exit;
}

// 3. Branch Ownership Enforcement
$staffBranch = (int)($_SESSION['user']['branch_id'] ?? 0);
if ($staffBranch > 0 && (int)$order['branch_id'] !== $staffBranch) {
    try {
        $pdo->prepare("
            INSERT INTO order_audit_logs (branch_id, order_id, action, supervisor_id, reason, created_at)
            VALUES (?, ?, 'AUTH_FAIL', ?, ?, NOW())
        ")->execute([
            (int)$order['branch_id'],
            $orderId,
            (int)$_SESSION['user']['id'],
            "Cross-branch order cancellation attempt denied"
        ]);
    } catch (\Throwable $t) {}

    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: wrong branch']);
    exit;
}

$branchId = (int)$order['branch_id'];

try {
    $pdo->beginTransaction();
    
    // Fetch items to restore stock
    $stmt = $pdo->prepare("SELECT id, product_id, quantity FROM order_items WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll();
    
    if (!$order['is_test']) {
        foreach ($items as $item) {
            $qty = $item['quantity'];
            
            // Restore product inventory
            $prodRec = $pdo->prepare("SELECT inventory_id, quantity FROM recipes WHERE product_id = ?");
            $prodRec->execute([$item['product_id']]);
            foreach ($prodRec->fetchAll() as $r) {
                $addAmount = (float)$r['quantity'] * $qty;
                $upd = $pdo->prepare("UPDATE inventory_stock SET stock = stock + ? WHERE inventory_id = ? AND branch_id = ?");
                $upd->execute([$addAmount, $r['inventory_id'], $branchId]);
            }
            
            // Restore modifier inventory
            $modStmt = $pdo->prepare("SELECT modifier_id FROM order_item_modifiers WHERE order_item_id = ?");
            $modStmt->execute([$item['id']]);
            $mods = $modStmt->fetchAll();
            
            foreach ($mods as $mod) {
                $modRec = $pdo->prepare("SELECT inventory_id, quantity FROM recipes WHERE modifier_id = ?");
                $modRec->execute([$mod['modifier_id']]);
                foreach ($modRec->fetchAll() as $mr) {
                    $addAmount = (float)$mr['quantity'] * $qty;
                    $upd = $pdo->prepare("UPDATE inventory_stock SET stock = stock + ? WHERE inventory_id = ? AND branch_id = ?");
                    $upd->execute([$addAmount, $mr['inventory_id'], $branchId]);
                }
            }
        }
    }

    // Delete the order entirely to clear the queue
    $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    
    // Audit Log on Order Cancellation
    $pdo->prepare("
        INSERT INTO order_audit_logs (branch_id, order_id, action, supervisor_id, reason, created_at)
        VALUES (?, ?, 'ORDER_CANCELLED', ?, ?, NOW())
    ")->execute([
        $branchId,
        $orderId,
        (int)$_SESSION['user']['id'],
        "Order #{$order['order_number']} cancelled by staff"
    ]);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    try {
        $pdo->prepare("
            INSERT INTO order_audit_logs (branch_id, order_id, action, supervisor_id, reason, created_at)
            VALUES (?, ?, 'CANCEL_FAILED', ?, ?, NOW())
        ")->execute([
            $branchId,
            $orderId,
            (int)$_SESSION['user']['id'],
            "Cancel failed: " . $e->getMessage()
        ]);
    } catch (\Throwable $t) {}

    error_log("Order cancel error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred while cancelling the order.']);
}
