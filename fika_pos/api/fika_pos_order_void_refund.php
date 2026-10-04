<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

Auth::requireLogin();
Csrf::requireValid();
Rbac::require_permission('pos.access');

$input = json_decode(file_get_contents('php://input'), true);
$orderId = (int) ($input['order_id'] ?? 0);
$supervisorPin = $input['supervisor_pin'] ?? '';
$reason = $input['reason'] ?? '';
$action = $input['action'] ?? 'VOID'; // VOID or REFUND

$user = Auth::user();
$branchId = $user['branch_id'];

if (!$orderId || empty($supervisorPin) || empty($reason)) {
    http_response_code(400);
    exit(json_encode(["error" => "Order ID, Supervisor PIN, and Reason are required."]));
}
if (!in_array($action, ['VOID', 'REFUND'])) {
    http_response_code(400);
    exit(json_encode(["error" => "Invalid action."]));
}

global $pdo;

// Validate Supervisor PIN (Must have pos.void_order or pos.refund_order)
$requiredPerm = $action === 'VOID' ? 'pos.void_order' : 'pos.refund_order';
$stmt = $pdo->prepare("
    SELECT u.id, u.password 
    FROM users u
    JOIN role_permissions rp ON rp.role_id = u.role_id
    WHERE u.branch_id = ? AND u.deleted_at IS NULL AND rp.permission_name = ?
");
$stmt->execute([$branchId, $requiredPerm]);
$authManagerId = null;
while ($mgr = $stmt->fetch()) {
    if (password_verify($supervisorPin, $mgr['password'])) {
        $authManagerId = $mgr['id'];
        break;
    }
}

if (!$authManagerId) {
    http_response_code(403);
    Audit::log('POS_OVERRIDE_FAILED', "Failed override attempt for {$action} on Order {$orderId}");
    exit(json_encode(["error" => "Invalid Supervisor PIN or insufficient privileges."]));
}

try {
    $pdo->beginTransaction();
    
    // Lock Order
    $orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? FOR UPDATE");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch();

    if (!$order) {
        throw new Exception("Order not found.");
    }
    
    if ($action === 'VOID' && $order['order_status'] !== 'PENDING') {
        throw new Exception("Only PENDING orders can be VOIDED.");
    }
    if ($action === 'REFUND' && $order['payment_status'] !== 'PAID') {
        throw new Exception("Only PAID orders can be REFUNDED.");
    }

    $newState = $action === 'VOID' ? 'VOID' : 'REFUNDED';
    $newPayState = $action === 'VOID' ? 'UNPAID' : 'REFUNDED';

    // Update Order
    $pdo->prepare("UPDATE orders SET order_status = ?, payment_status = ? WHERE id = ?")
        ->execute([$newState, $newPayState, $orderId]);
        
    // Insert Audit
    $pdo->prepare("INSERT INTO order_audit (order_id, action, reason, authorized_by, created_at) VALUES (?, ?, ?, ?, NOW())")
        ->execute([$orderId, $action, $reason, $authManagerId]);

    // If order was PAID (meaning stock was deducted), we restock it.
    // If it was VOID (never paid), stock was never deducted, so no ledger update needed.
    // Wait, Sub-Phase 4B says: "write VOID_RESTOCK to inventory_ledger."
    // Let's assume if it's REFUNDED or VOIDED after stock deduction we restock. 
    // Actually, in 4A I didn't deduct stock on creation, I deducted stock on Payment! 
    // So PENDING orders have NOT deducted stock yet!
    // So if action == REFUND, stock was deducted -> needs restock.
    if ($action === 'REFUND') {
        $ledgerStmt = $pdo->prepare("
            INSERT INTO inventory_ledger (branch_id, inventory_id, type, quantity, unit_cost_snapshot, user_id, remarks, created_at)
            VALUES (?, ?, 'Adjustment', ?, ?, ?, ?, NOW())
        ");
        
        $stockAddStmt = $pdo->prepare("
            UPDATE inventory_stock SET current_stock = current_stock + ? 
            WHERE branch_id = ? AND inventory_id = ?
        ");
        
        // Fetch all items + their recipes
        $itemsStmt = $pdo->prepare("
            SELECT oi.quantity as order_qty, r.inventory_id, r.quantity_required, ii.unit_cost
            FROM order_items oi
            JOIN recipes r ON r.product_id = oi.product_id
            JOIN inventory_items ii ON ii.id = r.inventory_id
            WHERE oi.order_id = ?
        ");
        $itemsStmt->execute([$orderId]);
        
        foreach ($itemsStmt->fetchAll() as $restock) {
            $totalQty = $restock['order_qty'] * $restock['quantity_required'];
            $ledgerStmt->execute([
                $order['branch_id'], $restock['inventory_id'], $totalQty, $restock['unit_cost'], 
                $authManagerId, "{$action} Restock Order #{$order['order_number']}"
            ]);
            $stockAddStmt->execute([$totalQty, $order['branch_id'], $restock['inventory_id']]);
        }
        
        // Modifiers
        $modItemsStmt = $pdo->prepare("
            SELECT oi.quantity as order_qty, r.inventory_id, r.quantity_required, ii.unit_cost
            FROM order_item_modifiers oim
            JOIN order_items oi ON oi.id = oim.order_item_id
            JOIN recipes r ON r.modifier_id = oim.modifier_id
            JOIN inventory_items ii ON ii.id = r.inventory_id
            WHERE oi.order_id = ?
        ");
        $modItemsStmt->execute([$orderId]);
        foreach ($modItemsStmt->fetchAll() as $restock) {
            $totalQty = $restock['order_qty'] * $restock['quantity_required'];
            $ledgerStmt->execute([
                $order['branch_id'], $restock['inventory_id'], $totalQty, $restock['unit_cost'], 
                $authManagerId, "{$action} Restock Order #{$order['order_number']} (Modifier)"
            ]);
            $stockAddStmt->execute([$totalQty, $order['branch_id'], $restock['inventory_id']]);
        }
    }
    
    Audit::log("ORDER_{$action}", "Order ID {$orderId} {$action} by manager {$authManagerId}. Reason: {$reason}");
    $pdo->commit();

    echo json_encode(["success" => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    exit(json_encode(["error" => $e->getMessage()]));
}
