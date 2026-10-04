<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

Auth::requireLogin();
Csrf::requireValid();
Rbac::require_permission('pos.access');

$input = json_decode(file_get_contents('php://input'), true);
$orderId = (int) ($input['order_id'] ?? 0);
$amountPaidRaw = $input['amount_paid'] ?? 0;
$idempotencyKey = $input['idempotency_key'] ?? '';
$user = Auth::user();

if (!$orderId || !$idempotencyKey) {
    http_response_code(400);
    exit(json_encode(["error" => "Order ID and Idempotency Key are required."]));
}

global $pdo;

try {
    $pdo->beginTransaction();
    
    // 1. Check Idempotency (prevent duplicate payment execution)
    $chkStmt = $pdo->prepare("SELECT id FROM payments WHERE reference_number = ? LIMIT 1");
    $chkStmt->execute([$idempotencyKey]);
    if ($chkStmt->fetch()) {
        $pdo->rollBack();
        echo json_encode(["success" => true, "message" => "Payment already processed."]);
        exit;
    }

    // 2. Lock Order row
    $orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? FOR UPDATE");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch();

    if (!$order) {
        throw new Exception("Order not found.");
    }
    if ($order['payment_status'] !== 'UNPAID') {
        throw new Exception("Order is already paid or cancelled.");
    }

    // 3. Financials
    $finalAmountCentavos = Money::toCentavos($order['final_amount']);
    $amountPaidCentavos = Money::toCentavos($amountPaidRaw);

    if ($amountPaidCentavos < $finalAmountCentavos) {
        throw new Exception("Insufficient payment amount.");
    }

    $changeCentavos = $amountPaidCentavos - $finalAmountCentavos;

    // 4. Record Payment
    $payStmt = $pdo->prepare("
        INSERT INTO payments (order_id, payment_method, amount_paid, change_amount, reference_number, created_at)
        VALUES (?, 'CASH', ?, ?, ?, NOW())
    ");
    $payStmt->execute([
        $orderId, 
        Money::toDecimal($amountPaidCentavos), 
        Money::toDecimal($changeCentavos),
        $idempotencyKey
    ]);

    // 5. Update Order Status
    $pdo->prepare("UPDATE orders SET order_status = 'PAID', payment_status = 'PAID' WHERE id = ?")->execute([$orderId]);

    // 6. Write to Inventory Ledger (Recipes deduction)
    $ledgerStmt = $pdo->prepare("
        INSERT INTO inventory_ledger (branch_id, inventory_id, type, quantity, unit_cost_snapshot, user_id, remarks, created_at)
        VALUES (?, ?, 'Sold', ?, ?, ?, ?, NOW())
    ");
    
    $stockDeductStmt = $pdo->prepare("
        UPDATE inventory_stock SET current_stock = current_stock - ? 
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
    $deductions = $itemsStmt->fetchAll();

    foreach ($deductions as $deduction) {
        $totalQtyUsed = $deduction['order_qty'] * $deduction['quantity_required'];
        
        $ledgerStmt->execute([
            $order['branch_id'],
            $deduction['inventory_id'],
            $totalQtyUsed,
            $deduction['unit_cost'],
            $user['id'],
            "Order #{$order['order_number']}"
        ]);
        
        $stockDeductStmt->execute([$totalQtyUsed, $order['branch_id'], $deduction['inventory_id']]);
    }

    // Do the same for modifiers
    $modItemsStmt = $pdo->prepare("
        SELECT oi.quantity as order_qty, r.inventory_id, r.quantity_required, ii.unit_cost
        FROM order_item_modifiers oim
        JOIN order_items oi ON oi.id = oim.order_item_id
        JOIN recipes r ON r.modifier_id = oim.modifier_id
        JOIN inventory_items ii ON ii.id = r.inventory_id
        WHERE oi.order_id = ?
    ");
    $modItemsStmt->execute([$orderId]);
    $modDeductions = $modItemsStmt->fetchAll();

    foreach ($modDeductions as $deduction) {
        $totalQtyUsed = $deduction['order_qty'] * $deduction['quantity_required'];
        
        $ledgerStmt->execute([
            $order['branch_id'],
            $deduction['inventory_id'],
            $totalQtyUsed,
            $deduction['unit_cost'],
            $user['id'],
            "Order #{$order['order_number']} (Modifier)"
        ]);
        
        $stockDeductStmt->execute([$totalQtyUsed, $order['branch_id'], $deduction['inventory_id']]);
    }

    Audit::log('ORDER_PAID', "Order ID {$orderId} paid in cash. Reference: {$idempotencyKey}");
    $pdo->commit();

    echo json_encode([
        "success" => true,
        "change" => Money::toDecimal($changeCentavos)
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    exit(json_encode(["error" => $e->getMessage()]));
}
