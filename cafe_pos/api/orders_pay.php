<?php
require_once __DIR__ . '/../database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['order_id']) || !isset($input['cart'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$order_id = $input['order_id'];
$cart = $input['cart'];
$payment_method = $input['payment_method'] ?? 'CASH';
$payment_reference = $input['payment_reference'] ?? null;
$pos_session_id = $input['pos_session_id'] ?? null;
$cashier_id = $input['cashier_id'] ?? null;
$is_test = isset($input['is_test']) ? (int)$input['is_test'] : 0;
$branch_id = isset($input['branch_id']) ? (int)$input['branch_id'] : 1;

$cashier_name = null;
if ($cashier_id) {
    try {
        $pdoHrms = new PDO("mysql:host=127.0.0.1;dbname=hrms;charset=utf8mb4", 'root', '');
        $stmtUser = $pdoHrms->prepare("SELECT e.full_name FROM users u LEFT JOIN employees e ON u.employee_id = e.id WHERE u.id = ?");
        $stmtUser->execute([$cashier_id]);
        $cashier_name = $stmtUser->fetchColumn() ?: 'Unknown';
    } catch (Exception $e) {}
}

try {
    $pdo->beginTransaction();

    // Recalculate total
    $total_price = 0;
    $order_items_data = [];

    foreach ($cart as $item) {
        $product_id = $item['product_id'];
        $quantity = $item['quantity'] ?? 1;
        $modifier_ids = $item['modifier_ids'] ?? [];

        $stmt = $pdo->prepare("SELECT price FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        if (!$product) throw new Exception("Product not found");

        $item_subtotal = (float)$product['price'];
        $valid_modifier_ids = [];

        if (!empty($modifier_ids)) {
            $inQuery = implode(',', array_fill(0, count($modifier_ids), '?'));
            $stmt = $pdo->prepare("SELECT id, price_adjustment FROM modifiers WHERE id IN ($inQuery)");
            $stmt->execute($modifier_ids);
            $modifiers = $stmt->fetchAll();

            foreach ($modifiers as $mod) {
                $item_subtotal += (float)$mod['price_adjustment'];
                $valid_modifier_ids[] = $mod['id'];
            }
        }

        $item_total = $item_subtotal * $quantity;
        $total_price += $item_total;

        $order_items_data[] = [
            'product_id' => $product_id,
            'quantity' => $quantity,
            'subtotal' => $item_total,
            'modifier_ids' => $valid_modifier_ids
        ];
    }

    // Fetch order number before updating
    $stmtNum = $pdo->prepare("SELECT order_number FROM orders WHERE id = ?");
    $stmtNum->execute([$order_id]);
    $order_number = $stmtNum->fetchColumn() ?: 'UNKNOWN';

    // Update order header
    $stmt = $pdo->prepare("UPDATE orders SET total_price = ?, payment_status = 'PAID', payment_method = ?, payment_reference = ?, pos_session_id = ?, cashier_id = ?, cashier_name = ?, created_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$total_price, $payment_method, $payment_reference, $pos_session_id, $cashier_id, $cashier_name, $order_id]);

    // --- Inventory: Restore old stock ---
    if (!$is_test) {
        $stmtOld = $pdo->prepare("SELECT id, product_id, quantity FROM order_items WHERE order_id = ?");
        $stmtOld->execute([$order_id]);
        $oldItems = $stmtOld->fetchAll();
        foreach ($oldItems as $oldItem) {
            $oldQty = $oldItem['quantity'];
            
            $prodRec = $pdo->prepare("SELECT inventory_id, quantity FROM recipes WHERE product_id = ?");
            $prodRec->execute([$oldItem['product_id']]);
            foreach ($prodRec->fetchAll() as $r) {
                $addAmount = (float)$r['quantity'] * $oldQty;
                $upd = $pdo->prepare("UPDATE inventory_stock SET stock = stock + ? WHERE inventory_id = ? AND branch_id = ?");
                $upd->execute([$addAmount, $r['inventory_id'], $branch_id]);
            }
            
            $modStmt = $pdo->prepare("SELECT modifier_id FROM order_item_modifiers WHERE order_item_id = ?");
            $modStmt->execute([$oldItem['id']]);
            $mods = $modStmt->fetchAll();
            foreach ($mods as $mod) {
                $modRec = $pdo->prepare("SELECT inventory_id, quantity FROM recipes WHERE modifier_id = ?");
                $modRec->execute([$mod['modifier_id']]);
                foreach ($modRec->fetchAll() as $mr) {
                    $addAmount = (float)$mr['quantity'] * $oldQty;
                    $upd = $pdo->prepare("UPDATE inventory_stock SET stock = stock + ? WHERE inventory_id = ? AND branch_id = ?");
                    $upd->execute([$addAmount, $mr['inventory_id'], $branch_id]);
                }
            }
        }
    }

    // Delete old items
    $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
    $stmt->execute([$order_id]);

    // Insert new items
    foreach ($order_items_data as $itemData) {
        $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, subtotal) VALUES (?, ?, ?, ?)");
        $stmt->execute([$order_id, $itemData['product_id'], $itemData['quantity'], $itemData['subtotal']]);
        $order_item_id = $pdo->lastInsertId();

        foreach ($itemData['modifier_ids'] as $mod_id) {
            $stmt = $pdo->prepare("INSERT INTO order_item_modifiers (order_item_id, modifier_id) VALUES (?, ?)");
            $stmt->execute([$order_item_id, $mod_id]);
        }
    }

    // --- Inventory: Deduct new stock ---
    if (!$is_test) {
        foreach ($order_items_data as $itemData) {
            $qty = $itemData['quantity'];
            
            $stmt = $pdo->prepare("SELECT inventory_id, quantity FROM recipes WHERE product_id = ?");
            $stmt->execute([$itemData['product_id']]);
            foreach ($stmt->fetchAll() as $r) {
                $deductAmount = (float)$r['quantity'] * $qty;
                $upd = $pdo->prepare("UPDATE inventory_stock SET stock = stock - ? WHERE inventory_id = ? AND branch_id = ?");
                $upd->execute([$deductAmount, $r['inventory_id'], $branch_id]);
            }
            
            foreach ($itemData['modifier_ids'] as $mod_id) {
                $stmt = $pdo->prepare("SELECT inventory_id, quantity FROM recipes WHERE modifier_id = ?");
                $stmt->execute([$mod_id]);
                foreach ($stmt->fetchAll() as $mr) {
                    $deductAmount = (float)$mr['quantity'] * $qty;
                    $upd = $pdo->prepare("UPDATE inventory_stock SET stock = stock - ? WHERE inventory_id = ? AND branch_id = ?");
                    $upd->execute([$deductAmount, $mr['inventory_id'], $branch_id]);
                }
            }
        }
    }

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'order_number' => $order_number
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
