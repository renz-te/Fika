<?php
// POS Void and Refund Governance API
header('Content-Type: application/json');

$posHost = '127.0.0.1';
$posDb = 'cafe_pos';
$posUser = 'root';
$posPass = '';

try {
    $pdo = new PDO("mysql:host=$posHost;dbname=$posDb;charset=utf8mb4", $posUser, $posPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    $pdoHrms = new PDO("mysql:host=127.0.0.1;dbname=hrms;charset=utf8mb4", 'root', '');
    $pdoHrms->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdoHrms->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$order_id = $input['order_id'] ?? null;
$supervisor_id = $input['supervisor_id'] ?? null;
$reason = $input['reason'] ?? '';

if (!in_array($action, ['VOID', 'REFUND']) || !$order_id || !$supervisor_id || empty(trim($reason))) {
    echo json_encode(['success' => false, 'error' => 'Action, order_id, supervisor_id, and reason are required.']);
    exit;
}

try {
    // Authenticate supervisor
    $stmtSup = $pdoHrms->prepare("SELECT id, role, branch_id FROM users WHERE id = ?");
    $stmtSup->execute([$supervisor_id]);
    $supervisor = $stmtSup->fetch();
    
    if (!$supervisor || !in_array($supervisor['role'], ['Super Admin', 'Branch Manager', 'Head Barista'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized supervisor ID.']);
        exit;
    }

    $pdo->beginTransaction();
    $pdoHrms->beginTransaction();

    // Fetch Order
    $stmtOrder = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmtOrder->execute([$order_id]);
    $order = $stmtOrder->fetch();

    if (!$order) {
        throw new Exception("Order not found.");
    }
    
    if ($order['order_status'] === 'VOID' || $order['order_status'] === 'REFUNDED') {
        throw new Exception("Order is already voided or refunded.");
    }

    $branch_id = $order['branch_id'];

    // 1. Audit Log
    $stmtAudit = $pdo->prepare("INSERT INTO order_audit_logs (branch_id, order_id, action, supervisor_id, reason) VALUES (?, ?, ?, ?, ?)");
    $stmtAudit->execute([$branch_id, $order_id, $action, $supervisor_id, $reason]);

    if ($action === 'VOID') {
        $stmtUpdate = $pdo->prepare("UPDATE orders SET order_status = 'VOID', payment_status = 'UNPAID' WHERE id = ?");
        $stmtUpdate->execute([$order_id]);
    } 
    elseif ($action === 'REFUND') {
        $stmtUpdate = $pdo->prepare("UPDATE orders SET order_status = 'REFUNDED' WHERE id = ?");
        $stmtUpdate->execute([$order_id]);

        // Revert Inventory Transactions
        if (!$order['is_test']) {
            $stmtItems = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            $stmtItems->execute([$order_id]);
            $items = $stmtItems->fetchAll();

            foreach ($items as $itemData) {
                $qty = $itemData['quantity'];
                
                // For the product
                $stmtRecipe = $pdo->prepare("SELECT inventory_id, quantity FROM recipes WHERE product_id = ?");
                $stmtRecipe->execute([$itemData['product_id']]);
                $recipes = $stmtRecipe->fetchAll();
                
                foreach ($recipes as $r) {
                    $refundAmount = (float)$r['quantity'] * $qty;
                    
                    // Update Stock
                    $updStock = $pdo->prepare("UPDATE inventory_stock SET stock = stock + ? WHERE inventory_id = ? AND branch_id = ?");
                    $updStock->execute([$refundAmount, $r['inventory_id'], $branch_id]);
                    
                    // Get unit cost snapshot to log reversal
                    $stmtInv = $pdo->prepare("SELECT name, unit_cost FROM inventory WHERE id = ?");
                    $stmtInv->execute([$r['inventory_id']]);
                    $inv = $stmtInv->fetch();
                    
                    if ($inv) {
                        $updTrans = $pdoHrms->prepare("INSERT INTO inventory_transactions (branch_id, inventory_id, item_name, type, quantity, cost, unit_cost_snapshot, status, logged_by) VALUES (?, ?, ?, 'Restock', ?, ?, ?, 'Completed', ?)");
                        $updTrans->execute([$branch_id, $r['inventory_id'], $inv['name'], $refundAmount, 0, $inv['unit_cost'], $supervisor_id]);
                    }
                }
                
                // For Modifiers
                $stmtMod = $pdo->prepare("SELECT modifier_id FROM order_item_modifiers WHERE order_item_id IN (SELECT id FROM order_items WHERE order_id = ? AND product_id = ?)");
                $stmtMod->execute([$order_id, $itemData['product_id']]);
                $mods = $stmtMod->fetchAll();
                
                foreach ($mods as $mod) {
                    $stmtRecipeMod = $pdo->prepare("SELECT inventory_id, quantity FROM recipes WHERE modifier_id = ?");
                    $stmtRecipeMod->execute([$mod['modifier_id']]);
                    $modRecipes = $stmtRecipeMod->fetchAll();
                    
                    foreach ($modRecipes as $mr) {
                        $refundAmount = (float)$mr['quantity'] * $qty;
                        
                        $updStock = $pdo->prepare("UPDATE inventory_stock SET stock = stock + ? WHERE inventory_id = ? AND branch_id = ?");
                        $updStock->execute([$refundAmount, $mr['inventory_id'], $branch_id]);
                        
                        $stmtInv = $pdo->prepare("SELECT name, unit_cost FROM inventory WHERE id = ?");
                        $stmtInv->execute([$mr['inventory_id']]);
                        $inv = $stmtInv->fetch();
                        
                        if ($inv) {
                            $updTrans = $pdoHrms->prepare("INSERT INTO inventory_transactions (branch_id, inventory_id, item_name, type, quantity, cost, unit_cost_snapshot, status, logged_by) VALUES (?, ?, ?, 'Restock', ?, ?, ?, 'Completed', ?)");
                            $updTrans->execute([$branch_id, $mr['inventory_id'], $inv['name'], $refundAmount, 0, $inv['unit_cost'], $supervisor_id]);
                        }
                    }
                }
            }
        }
    }

    $pdo->commit();
    $pdoHrms->commit();

    echo json_encode(['success' => true, 'message' => "Order {$order['order_number']} successfully {$action}ED."]);

} catch (\Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    if ($pdoHrms->inTransaction()) { $pdoHrms->rollBack(); }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
