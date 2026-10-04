<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

Auth::requireLogin();
Csrf::requireValid();
Rbac::require_permission('pos.access');

$user = Auth::user();
$branchId = $user['branch_id'];
$sessionId = $_SESSION['pos_session_id'] ?? null;

if (!$branchId || !$sessionId) {
    http_response_code(400);
    exit(json_encode(["error" => "No active POS session."]));
}

// Input: {"items": [{"id": 1, "qty": 2, "modifiers": [3, 4]}]}
$input = json_decode(file_get_contents('php://input'), true);
if (empty($input['items']) || !is_array($input['items'])) {
    http_response_code(400);
    exit(json_encode(["error" => "Empty order."]));
}

global $pdo;

try {
    $pdo->beginTransaction();
    
    // 1. Generate Order Number
    $stmt = $pdo->query("SELECT current_count, prefix, padding FROM doc_counters WHERE document_type = 'ORDER' FOR UPDATE");
    $counter = $stmt->fetch();
    if (!$counter) {
        $pdo->exec("INSERT INTO doc_counters (document_type, current_count, prefix, padding) VALUES ('ORDER', 1, 'ORD-', 6)");
        $orderNumber = 'ORD-000001';
    } else {
        $next = $counter['current_count'] + 1;
        $pdo->prepare("UPDATE doc_counters SET current_count = ? WHERE document_type = 'ORDER'")->execute([$next]);
        $orderNumber = $counter['prefix'] . str_pad((string)$next, (int)$counter['padding'], '0', STR_PAD_LEFT);
    }
    
    // 2. Validate Items and Compute Totals
    $totalAmountCentavos = 0;
    
    // Create the order header first to get ID
    $orderStmt = $pdo->prepare("
        INSERT INTO orders (order_number, branch_id, pos_session_id, cashier_id, order_status, payment_status, total_amount, final_amount) 
        VALUES (?, ?, ?, ?, 'PENDING', 'UNPAID', 0, 0)
    ");
    $orderStmt->execute([$orderNumber, $branchId, $sessionId, $user['id']]);
    $orderId = $pdo->lastInsertId();
    
    $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price_snapshot, subtotal) VALUES (?, ?, ?, ?, ?)");
    $modStmt = $pdo->prepare("INSERT INTO order_item_modifiers (order_item_id, modifier_id, price_snapshot) VALUES (?, ?, ?)");
    
    $prodLookup = $pdo->prepare("SELECT price, is_active FROM products WHERE id = ?");
    $modLookup = $pdo->prepare("SELECT price, is_active FROM modifiers WHERE id = ?");
    
    foreach ($input['items'] as $item) {
        $prodId = (int) $item['id'];
        $qty = (int) ($item['qty'] ?? 1);
        if ($qty <= 0) continue;
        
        $prodLookup->execute([$prodId]);
        $product = $prodLookup->fetch();
        if (!$product || !$product['is_active']) {
            throw new Exception("Product ID {$prodId} is invalid or inactive.");
        }
        
        $basePriceCentavos = Money::toCentavos($product['price']);
        $itemTotalCentavos = $basePriceCentavos;
        
        $itemStmt->execute([$orderId, $prodId, $qty, Money::toDecimal($basePriceCentavos), 0]); // Subtotal updated later
        $orderItemId = $pdo->lastInsertId();
        
        // Process modifiers
        if (!empty($item['modifiers']) && is_array($item['modifiers'])) {
            foreach ($item['modifiers'] as $modId) {
                $modId = (int) $modId;
                $modLookup->execute([$modId]);
                $modifier = $modLookup->fetch();
                if (!$modifier || !$modifier['is_active']) {
                    throw new Exception("Modifier ID {$modId} is invalid or inactive.");
                }
                
                $modPriceCentavos = Money::toCentavos($modifier['price']);
                $itemTotalCentavos += $modPriceCentavos;
                
                $modStmt->execute([$orderItemId, $modId, Money::toDecimal($modPriceCentavos)]);
            }
        }
        
        $lineTotalCentavos = $itemTotalCentavos * $qty;
        $totalAmountCentavos += $lineTotalCentavos;
        
        $pdo->prepare("UPDATE order_items SET subtotal = ? WHERE id = ?")->execute([Money::toDecimal($lineTotalCentavos), $orderItemId]);
    }
    
    // Currently no discounts/VAT computed in creation (handled in payment phase)
    $finalAmountCentavos = $totalAmountCentavos;
    
    $pdo->prepare("UPDATE orders SET total_amount = ?, final_amount = ? WHERE id = ?")->execute([
        Money::toDecimal($totalAmountCentavos),
        Money::toDecimal($finalAmountCentavos),
        $orderId
    ]);
    
    Audit::log('ORDER_CREATED', "Order Number: {$orderNumber}");
    $pdo->commit();
    
    echo json_encode([
        "success" => true,
        "order_id" => $orderId,
        "order_number" => $orderNumber,
        "total" => Money::toDecimal($finalAmountCentavos)
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    exit(json_encode(["error" => $e->getMessage()]));
}
