<?php
require_once __DIR__ . '/../database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['order_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Fetch items to restore stock
    $stmt = $pdo->prepare("SELECT id, product_id, quantity FROM order_items WHERE order_id = ?");
    $stmt->execute([$input['order_id']]);
    $items = $stmt->fetchAll();
    
    foreach ($items as $item) {
        $qty = $item['quantity'];
        
        // Restore product inventory
        $prodRec = $pdo->prepare("SELECT inventory_id, quantity FROM recipes WHERE product_id = ?");
        $prodRec->execute([$item['product_id']]);
        foreach ($prodRec->fetchAll() as $r) {
            $addAmount = (float)$r['quantity'] * $qty;
            $upd = $pdo->prepare("UPDATE inventory SET stock = stock + ? WHERE id = ?");
            $upd->execute([$addAmount, $r['inventory_id']]);
        }
        
        // Restore modifier inventory
        $modStmt = $pdo->prepare("SELECT modifier_id FROM order_item_modifiers WHERE order_item_id = ?");
        $modStmt->execute([$item['id']]);
        $mods = $modStmt->fetchAll();
        
        foreach ($mods as $mod) {
            $modRec = $pdo->prepare("SELECT inventory_id, quantity FROM recipes WHERE modifier_id = ?");
            $modRec->execute([$mod['modifier_id']]);
            foreach ($modRec->fetchAll() as $mr) {
                $addAmount = (float)$mr['quantity'] * $qty;
                $upd = $pdo->prepare("UPDATE inventory SET stock = stock + ? WHERE id = ?");
                $upd->execute([$addAmount, $mr['inventory_id']]);
            }
        }
    }

    // Delete the order entirely to clear the queue
    $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
    $stmt->execute([$input['order_id']]);
    
    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
