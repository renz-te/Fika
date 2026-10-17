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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!class_exists('Config')) {
    class Config {
        public static function get(string $key, mixed $default = null): mixed {
            static $cache = [];
            $envFile = dirname(__DIR__, 2) . '/.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#')) continue;
                    if (str_contains($line, '=')) {
                        [$k, $v] = explode('=', $line, 2);
                        $k = trim($k);
                        $v = trim($v, " \t\n\r\0\x0B\"'");
                        putenv("{$k}={$v}");
                        $_ENV[$k] = $v;
                        $_SERVER[$k] = $v;
                    }
                }
            }
            [$file, $item] = array_pad(explode('.', $key, 2), 2, null);
            $path = dirname(__DIR__, 2) . '/config/' . $file . '.php';
            $config = file_exists($path) ? require $path : [];
            if ($item === null) return $config ?? $default;
            return $config[$item] ?? $default;
        }
    }
}

// 1. Mandatory Staff Session Authentication
$isCashierSession = !empty($_SESSION['user']) && in_array(
    $_SESSION['user']['role'] ?? '',
    ['Cashier', 'Admin', 'Super Admin', 'Branch Manager', 'Head Barista', 'Branch Admin'],
    true
);

if (!$isCashierSession) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: authentication required']);
    exit;
}

$order_id = (int)$input['order_id'];
$cart = $input['cart'];
$pos_session_id = $input['pos_session_id'] ?? null;

// 2. Fetch Order outside transaction to verify existence, branch, and status
$stmtOrder = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmtOrder->execute([$order_id]);
$order = $stmtOrder->fetch();

if (!$order) {
    http_response_code(404);
    echo json_encode(['error' => 'Order not found']);
    exit;
}

// 3. Branch Ownership Enforcement
$staffBranch = (int)($_SESSION['user']['branch_id'] ?? 0);
if ($staffBranch > 0 && (int)$order['branch_id'] !== $staffBranch) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: wrong branch']);
    exit;
}

// 4. Reject already-PAID or VOID/VOIDED orders (409) with Trap 3 audit outside transaction
$existingStatus = strtoupper(trim((string)$order['order_status']));
$existingPayStatus = strtoupper(trim((string)$order['payment_status']));
if ($existingPayStatus === 'PAID' || in_array($existingStatus, ['VOID', 'VOIDED', 'REFUNDED', 'CANCELLED'])) {
    try {
        $pdo->prepare("
            INSERT INTO order_audit_logs (branch_id, order_id, action, supervisor_id, reason, created_at)
            VALUES (?, ?, 'PAYMENT_REJECTED', ?, ?, NOW())
        ")->execute([
            (int)$order['branch_id'],
            $order_id,
            (int)$_SESSION['user']['id'],
            "Rejected payment attempt on order with status {$existingStatus}/{$existingPayStatus}"
        ]);
    } catch (\Throwable $t) {}

    http_response_code(409);
    echo json_encode(['error' => "Cannot pay order with status {$existingStatus}/{$existingPayStatus}."]);
    exit;
}

// 5. Server-derived context (ignore client-supplied values)
$branch_id = (int)$order['branch_id'];
$cashier_id = (int)$_SESSION['user']['id'];
$cashier_name = $_SESSION['user']['name'] ?? 'Staff';
$is_test = (Config::get('paymongo.is_test') === true) ? 1 : 0;

$cleanMethod = strtoupper(trim((string)($input['payment_method'] ?? 'CASH')));
$payment_status = ($cleanMethod === 'CASH') ? 'PAID' : 'UNPAID';
$payment_method = $cleanMethod;
$payment_reference = null;

try {
    $pdo->beginTransaction();

    // Recalculate total
    $gross_total = 0;
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
        $vat_exempt_total = $gross_total / 1.12;
        $discount_amount = $vat_exempt_total * 0.20;
        $net_payable = $vat_exempt_total - $discount_amount;
        $vat_amount = 0;
    } else {
        $net_payable = $gross_total;
        $vat_amount = $gross_total - ($gross_total / 1.12);
        $discount_amount = 0;
    }

    $total_price = $net_payable;

    // Fetch order number before updating
    $stmtNum = $pdo->prepare("SELECT order_number FROM orders WHERE id = ?");
    $stmtNum->execute([$order_id]);
    $order_number = $stmtNum->fetchColumn() ?: 'UNKNOWN';

    // Update order header
    $stmt = $pdo->prepare("UPDATE orders SET total_price = ?, vat_amount = ?, discount_amount = ?, payment_status = ?, payment_method = ?, payment_reference = ?, pos_session_id = ?, cashier_id = ?, cashier_name = ?, created_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$total_price, $vat_amount, $discount_amount, $payment_status, $payment_method, $payment_reference, $pos_session_id, $cashier_id, $cashier_name, $order_id]);

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

    // Audit Log on Order Update
    $pdo->prepare("
        INSERT INTO order_audit_logs (branch_id, order_id, action, supervisor_id, reason, created_at)
        VALUES (?, ?, 'ORDER_UPDATED', ?, ?, NOW())
    ")->execute([
        $branch_id,
        $order_id,
        $cashier_id ?: null,
        "Order #{$order_number} payment processed [Method: {$payment_method}, Status: {$payment_status}]"
    ]);

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'order_number' => $order_number,
        'gross_total' => $gross_total,
        'vat_amount' => $vat_amount,
        'discount_amount' => $discount_amount,
        'net_payable' => $total_price,
        'discount_type' => $discount_type
    ]);

} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    try {
        $pdo->prepare("
            INSERT INTO order_audit_logs (branch_id, order_id, action, supervisor_id, reason, created_at)
            VALUES (?, ?, 'PAYMENT_FAILED', ?, ?, NOW())
        ")->execute([
            $branch_id ?? ((int)($order['branch_id'] ?? 1)),
            $order_id,
            (int)($_SESSION['user']['id'] ?? 0) ?: null,
            "Payment failed: " . $e->getMessage()
        ]);
    } catch (\Throwable $t) {}

    error_log("Payment processing error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred while processing payment.']);
}
