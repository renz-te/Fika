<?php
require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['cart']) || !is_array($input['cart'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload. "cart" array is required.']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$cart = $input['cart'];
$table_source = $input['table_source'] ?? null;
$pos_session_id = $input['pos_session_id'] ?? null;
$cashier_id = $input['cashier_id'] ?? null;
$is_test = isset($input['is_test']) ? (int)$input['is_test'] : 0;
$branch_id = isset($input['branch_id']) ? (int)$input['branch_id'] : 1;

// Authenticate Cashier Session
$isCashierSession = false;
if (!empty($_SESSION['user']) && in_array($_SESSION['user']['role'] ?? '', ['Cashier', 'Admin', 'Super Admin', 'Branch Manager', 'Head Barista', 'Branch Admin'])) {
    $isCashierSession = true;
    if (!$cashier_id && !empty($_SESSION['user']['id'])) {
        $cashier_id = (int)$_SESSION['user']['id'];
    }
} elseif (!empty($cashier_id)) {
    try {
        $stmtCashier = $pdo->prepare("
            SELECT u.id, u.name, r.name as role 
            FROM hrms.users u 
            JOIN hrms.roles r ON u.role_id = r.id 
            WHERE u.id = ? AND r.name IN ('Cashier', 'Admin', 'Super Admin', 'Branch Manager', 'Head Barista', 'Branch Admin')
        ");
        $stmtCashier->execute([$cashier_id]);
        if ($stmtCashier->fetch()) {
            $isCashierSession = true;
        }
    } catch (Exception $e) {}
}

$cleanMethod = strtoupper(trim((string)($input['payment_method'] ?? 'CASH')));

// Trust boundary: Accept payment_status='PAID' only when payment_method is CASH AND caller is an authenticated cashier session.
// For GCASH/MAYA/CARD/DIGITAL: Always create and keep the order UNPAID. PAID is set only by payment_webhook.php.
$clientPaymentStatus = strtoupper(trim((string)($input['payment_status'] ?? 'UNPAID')));
if ($cleanMethod === 'CASH' && $isCashierSession && $clientPaymentStatus === 'PAID') {
    $payment_status = 'PAID';
} else {
    $payment_status = 'UNPAID';
}

$payment_method = $cleanMethod;
$payment_reference = null; // Strip manual digital reference entirely

$cashier_name = null;
if ($cashier_id) {
    try {
        $stmtUser = $pdo->prepare("SELECT e.full_name FROM users u LEFT JOIN employees e ON u.employee_id = e.id WHERE u.id = ?");
        $stmtUser->execute([$cashier_id]);
        $cashier_name = $stmtUser->fetchColumn() ?: 'Unknown';
    } catch (Exception $e) {}
}

try {
    $pdo->beginTransaction();

    $gross_total = 0;
    $order_items_data = [];

    foreach ($cart as $item) {
        if (!isset($item['product_id'])) {
            throw new \Exception("Product ID is required for all cart items.");
        }
        
        $product_id = $item['product_id'];
        $quantity = $item['quantity'] ?? 1;
        $modifier_ids = $item['modifier_ids'] ?? [];

        // Fetch product price
        $stmt = $pdo->prepare("SELECT price FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();

        if (!$product) {
            throw new \Exception("Product ID $product_id not found.");
        }

        $item_subtotal = (float)$product['price'];

        // Add modifiers prices
        $valid_modifier_ids = [];
        if (!empty($modifier_ids) && is_array($modifier_ids)) {
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
        $gross_total += $item_total;

        $order_items_data[] = [
            'product_id' => $product_id,
            'quantity' => $quantity,
            'subtotal' => $item_total,
            'modifier_ids' => $valid_modifier_ids
        ];
    }

    $discount_type = $input['discount_type'] ?? 'NONE';
    $vat_amount = 0;
    $discount_amount = 0;
    $net_payable = 0;

    if ($discount_type === 'SENIOR' || $discount_type === 'PWD') {
        // Senior/PWD: VAT Exempt, then 20% discount on the VAT-exempt amount
        $vat_exempt_total = $gross_total / 1.12;
        $discount_amount = $vat_exempt_total * 0.20;
        $net_payable = $vat_exempt_total - $discount_amount;
        $vat_amount = 0;
    } else {
        // Standard transaction
        $net_payable = $gross_total;
        $vat_amount = $gross_total - ($gross_total / 1.12);
        $discount_amount = 0;
    }

    $total_price = $net_payable;

    // Generate sequential daily order number scoped to branch and date (e.g. 20261002-0001)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE branch_id = ?");
    $stmt->execute([$branch_id]);
    $branch_count = (int)$stmt->fetchColumn() + 1;
    $order_number = sprintf("BR%d-INV-%06d", $branch_id, $branch_count);

    // Insert Order
    $stmt = $pdo->prepare("INSERT INTO orders (order_number, table_source, total_price, vat_amount, discount_amount, payment_status, payment_method, payment_reference, pos_session_id, cashier_id, cashier_name, branch_id, is_test) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$order_number, $table_source, $total_price, $vat_amount, $discount_amount, $payment_status, $payment_method, $payment_reference, $pos_session_id, $cashier_id, $cashier_name, $branch_id, $is_test]);
    $order_id = $pdo->lastInsertId();

    // Insert Order Items and Modifiers
    foreach ($order_items_data as $itemData) {
        $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, subtotal) VALUES (?, ?, ?, ?)");
        $stmt->execute([$order_id, $itemData['product_id'], $itemData['quantity'], $itemData['subtotal']]);
        $order_item_id = $pdo->lastInsertId();

        foreach ($itemData['modifier_ids'] as $mod_id) {
            $stmt = $pdo->prepare("INSERT INTO order_item_modifiers (order_item_id, modifier_id) VALUES (?, ?)");
            $stmt->execute([$order_item_id, $mod_id]);
        }
    }

    // --- Inventory Deduction Logic ---
    if (!$is_test) {
        foreach ($order_items_data as $itemData) {
            $qty = $itemData['quantity'];
            
            // 1. Deduct for the product itself
            $stmt = $pdo->prepare("SELECT inventory_id, quantity FROM recipes WHERE product_id = ?");
            $stmt->execute([$itemData['product_id']]);
            $recipes = $stmt->fetchAll();
            foreach ($recipes as $r) {
                $deductAmount = (float)$r['quantity'] * $qty;
                $upd = $pdo->prepare("UPDATE inventory_stock SET stock = stock - ? WHERE inventory_id = ? AND branch_id = ?");
                $upd->execute([$deductAmount, $r['inventory_id'], $branch_id]);
            }
            
            // 2. Deduct for each modifier
            foreach ($itemData['modifier_ids'] as $mod_id) {
                $stmt = $pdo->prepare("SELECT inventory_id, quantity FROM recipes WHERE modifier_id = ?");
                $stmt->execute([$mod_id]);
                $modRecipes = $stmt->fetchAll();
                foreach ($modRecipes as $mr) {
                    $deductAmount = (float)$mr['quantity'] * $qty;
                    $upd = $pdo->prepare("UPDATE inventory_stock SET stock = stock - ? WHERE inventory_id = ? AND branch_id = ?");
                    $upd->execute([$deductAmount, $mr['inventory_id'], $branch_id]);
                }
            }
        }
    }

    // 4. Audit Log on Order Creation
    $pdo->prepare("
        INSERT INTO order_audit_logs (branch_id, order_id, action, supervisor_id, reason, created_at)
        VALUES (?, ?, 'ORDER_CREATED', ?, ?, NOW())
    ")->execute([
        $branch_id,
        $order_id,
        $cashier_id ?: null,
        "Order #{$order_number} created with total " . number_format($total_price, 2) . " PHP [Method: {$payment_method}, Status: {$payment_status}]"
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'order_number' => $order_number,
        'total_price' => $total_price,
        'gross_total' => $gross_total,
        'vat_amount' => $vat_amount,
        'discount_amount' => $discount_amount,
        'discount_type' => $discount_type,
        'payment_status' => $payment_status
    ]);

} catch (\Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
