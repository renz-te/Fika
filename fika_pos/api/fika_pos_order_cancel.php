<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method Not Allowed']));
}

Auth::requireLogin();
Csrf::requireValid();
Rbac::require_permission('pos.access');

$user = Auth::user();
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$orderId = (int)($input['order_id'] ?? $_POST['order_id'] ?? 0);

if ($orderId <= 0) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid order ID']));
}

global $pdo;

try {
    $pdo->beginTransaction();

    $stmtOrder = $pdo->prepare("SELECT * FROM orders WHERE id = ? FOR UPDATE");
    $stmtOrder->execute([$orderId]);
    $order = $stmtOrder->fetch();

    if (!$order) {
        $pdo->rollBack();
        http_response_code(404);
        exit(json_encode(['error' => 'Order not found']));
    }

    if (!empty($user['branch_id']) && (int)$order['branch_id'] !== (int)$user['branch_id']) {
        $pdo->rollBack();
        Audit::log('AUTH_FAIL', "Cross-branch order cancellation attempt denied for Order {$orderId}", $user['id']);
        http_response_code(403);
        exit(json_encode(['error' => 'Forbidden: wrong branch']));
    }

    $branchId = (int)$order['branch_id'];

    // If order was paid, restock recipe ingredients
    if ($order['payment_status'] === 'PAID') {
        $itemsStmt = $pdo->prepare("
            SELECT oi.quantity as order_qty, r.inventory_id, r.quantity_required, ii.unit_cost
            FROM order_items oi
            JOIN recipes r ON r.product_id = oi.product_id
            JOIN inventory_items ii ON ii.id = r.inventory_id
            WHERE oi.order_id = ?
        ");
        $itemsStmt->execute([$orderId]);
        $restockItems = $itemsStmt->fetchAll();

        $stockAddStmt = $pdo->prepare("
            UPDATE inventory_stock 
            SET current_stock = current_stock + ? 
            WHERE branch_id = ? AND inventory_id = ?
        ");

        $ledgerStmt = $pdo->prepare("
            INSERT INTO inventory_ledger (branch_id, inventory_id, type, quantity, unit_cost_snapshot, user_id, remarks, created_at)
            VALUES (?, ?, 'Adjustment', ?, ?, ?, ?, NOW())
        ");

        foreach ($restockItems as $ri) {
            $totalQty = (float)$ri['order_qty'] * (float)$ri['quantity_required'];
            $stockAddStmt->execute([$totalQty, $branchId, $ri['inventory_id']]);
            $ledgerStmt->execute([
                $branchId,
                $ri['inventory_id'],
                $totalQty,
                $ri['unit_cost'],
                $user['id'],
                "Cancelled order #{$order['order_number']} restock"
            ]);
        }
    }

    // Update order status to CANCELLED (or delete if pending unpaid test queue)
    $stmtCancel = $pdo->prepare("UPDATE orders SET order_status = 'CANCELLED' WHERE id = ?");
    $stmtCancel->execute([$orderId]);

    Audit::log('ORDER_CANCELLED', "Order #{$order['order_number']} cancelled by staff", $user['id']);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    Audit::log('CANCEL_FAILED', "Cancel failed for Order {$orderId}: " . $e->getMessage(), $user['id']);
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred while cancelling the order.']);
}
