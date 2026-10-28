<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

// 1. Device Authentication (X-Device-Token header, never mac_address)
$deviceToken = $_SERVER['HTTP_X_DEVICE_TOKEN'] ?? $_POST['device_token'] ?? null;
if (!$deviceToken) {
    $rawInput = json_decode(file_get_contents('php://input'), true);
    if (is_array($rawInput) && !empty($rawInput['device_token'])) {
        $deviceToken = $rawInput['device_token'];
    }
}

$device = Device::getDevice($deviceToken);
if (!$device) {
    http_response_code(401);
    exit(json_encode(["error" => "Unauthorized device."]));
}

// 2. Derive branch strictly from device (Never trust client-sent branch or totals)
$branchId = (int)$device['branch_id'];

$rawInput = json_decode(file_get_contents('php://input'), true);
$input = is_array($rawInput) ? array_merge($_POST, $rawInput) : $_POST;

if (empty($input['items']) || !is_array($input['items'])) {
    http_response_code(400);
    exit(json_encode(["error" => "Order items cannot be empty."]));
}

global $pdo;

try {
    $pdo->beginTransaction();

    // 3. Generate Order Number
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

    // 4. Validate Items and Compute Total Price Server-Side
    $totalAmountCentavos = 0;

    // Constraint 2: Leave cashier_id and pos_session_id as NULL for kiosk orders
    $orderStmt = $pdo->prepare("
        INSERT INTO orders (order_number, branch_id, pos_session_id, cashier_id, order_status, payment_status, total_amount, final_amount, is_test, created_at, updated_at) 
        VALUES (?, ?, NULL, NULL, 'PENDING', 'UNPAID', 0, 0, 0, NOW(), NOW())
    ");
    $orderStmt->execute([$orderNumber, $branchId]);
    $orderId = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, quantity, price_snapshot, price_snapshot_cents, subtotal, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    $modStmt = $pdo->prepare("
        INSERT INTO order_item_modifiers (order_item_id, modifier_id, price_snapshot, created_at) 
        VALUES (?, ?, ?, NOW())
    ");

    $prodLookup = $pdo->prepare("
        SELECT p.*, bp.is_active AS branch_active 
        FROM products p
        LEFT JOIN branch_products bp ON bp.product_id = p.id AND bp.branch_id = ?
        WHERE p.id = ? AND p.deleted_at IS NULL
    ");

    $modLookup = $pdo->prepare("
        SELECT id, name, price, is_active 
        FROM modifiers 
        WHERE id = ? AND deleted_at IS NULL
    ");

    foreach ($input['items'] as $item) {
        $prodId = (int)($item['id'] ?? $item['product_id'] ?? 0);
        $qty = (int)($item['qty'] ?? $item['quantity'] ?? 1);
        if ($prodId <= 0 || $qty <= 0) {
            continue;
        }

        $prodLookup->execute([$branchId, $prodId]);
        $product = $prodLookup->fetch();

        // Enforce product global and branch availability
        $isAvailable = $product && (int)$product['is_active'] === 1 && ($product['branch_active'] === null || (int)$product['branch_active'] === 1);
        if (!$isAvailable) {
            throw new Exception("Product ID {$prodId} is invalid or inactive for this branch.");
        }

        $basePriceCentavos = isset($product['price_cents']) && (int)$product['price_cents'] > 0
            ? (int)$product['price_cents']
            : Money::toCentavos($product['price']);

        $itemUnitCentavos = $basePriceCentavos;
        $modIds = $item['modifier_ids'] ?? $item['modifiers'] ?? [];
        $validMods = [];

        if (!empty($modIds) && is_array($modIds)) {
            foreach ($modIds as $mId) {
                $mId = (int)$mId;
                if ($mId <= 0) continue;
                $modLookup->execute([$mId]);
                $mod = $modLookup->fetch();
                if ($mod && (int)$mod['is_active'] === 1) {
                    $mPriceCentavos = Money::toCentavos($mod['price']);
                    $itemUnitCentavos += $mPriceCentavos;
                    $validMods[] = [
                        'id' => $mId,
                        'price_decimal' => Money::toDecimal($mPriceCentavos)
                    ];
                }
            }
        }

        $lineTotalCentavos = $itemUnitCentavos * $qty;
        $totalAmountCentavos += $lineTotalCentavos;

        $itemStmt->execute([
            $orderId,
            $prodId,
            $qty,
            Money::toDecimal($itemUnitCentavos),
            $itemUnitCentavos,
            Money::toDecimal($lineTotalCentavos)
        ]);
        $orderItemId = (int)$pdo->lastInsertId();

        foreach ($validMods as $vm) {
            $modStmt->execute([$orderItemId, $vm['id'], $vm['price_decimal']]);
        }
    }

    if ($totalAmountCentavos <= 0) {
        throw new Exception("Order must contain at least one valid item.");
    }

    // Update Order Header with Computed Server-Side Totals
    $finalDecimal = Money::toDecimal($totalAmountCentavos);
    $upOrderStmt = $pdo->prepare("UPDATE orders SET total_amount = ?, final_amount = ? WHERE id = ?");
    $upOrderStmt->execute([$finalDecimal, $finalDecimal, $orderId]);

    // 5. Issue Order-Bound Kiosk Token (SHA-256 hash stored, expires in 30 mins)
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = date('Y-m-d H:i:s', time() + 1800); // 30 minutes

    $tokenStmt = $pdo->prepare("
        INSERT INTO order_kiosk_tokens (order_id, token_hash, expires_at, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $tokenStmt->execute([$orderId, $tokenHash, $expiresAt]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'order_number' => $orderNumber,
        'branch_id' => $branchId,
        'payment_status' => 'UNPAID',
        'discount_type' => null,
        'discount_amount' => 0.0,
        'total_amount' => $finalDecimal,
        'total_price' => (float)$finalDecimal,
        'total_cents' => $totalAmountCentavos,
        'kiosk_token' => $rawToken,
        'expires_at' => $expiresAt
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
