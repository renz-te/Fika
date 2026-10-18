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

    // Branch ownership check
    if (!empty($user['branch_id']) && (int)$order['branch_id'] !== (int)$user['branch_id']) {
        $pdo->rollBack();
        http_response_code(403);
        exit(json_encode(["error" => "Forbidden: wrong branch."]));
    }

    if ($order['payment_status'] === 'PAID') {
        $pdo->rollBack();
        Audit::log('PAYMENT_REJECTED', "Payment rejected: Order {$orderId} is already PAID", $user['id']);
        http_response_code(409);
        exit(json_encode(["error" => "Order is already paid."]));
    }

    // Constraint 4: Verify order_status === 'PENDING' inside FOR UPDATE lock
    if ($order['order_status'] !== 'PENDING' || $order['payment_status'] !== 'UNPAID') {
        $pdo->rollBack();
        Audit::log('PAYMENT_REJECTED', "Payment rejected: Order {$orderId} status is {$order['order_status']}", $user['id']);
        http_response_code(409);
        exit(json_encode(["error" => "Order is not pending payment."]));
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

    // 5. Update Order Status and bind to active cashier shift (Order-Shift Separation)
    $sessionId = $_SESSION['pos_session_id'] ?? null;
    if (!$sessionId && !empty($user['branch_id'])) {
        $sessStmt = $pdo->prepare("SELECT id FROM cash_sessions WHERE branch_id = ? AND status = 'OPEN' ORDER BY id DESC LIMIT 1");
        $sessStmt->execute([$user['branch_id']]);
        $sessionId = $sessStmt->fetchColumn() ?: null;
    }

    $pdo->prepare("
        UPDATE orders 
        SET order_status = 'PAID', 
            payment_status = 'PAID', 
            cashier_id = COALESCE(cashier_id, ?), 
            pos_session_id = COALESCE(pos_session_id, ?) 
        WHERE id = ?
    ")->execute([$user['id'], $sessionId, $orderId]);

    // 6. Write to Inventory Ledger (Recipes deduction with idempotency)
    InventoryDeduction::deductOrder($orderId, (int)$order['branch_id'], $user['id'], $order['order_number']);

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
