<?php
require_once __DIR__ . '/../database.php';
header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Order ID is required']);
    exit;
}

$order_id = $_GET['id'];
$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 1;
$is_test = isset($_GET['is_test']) ? (int)$_GET['is_test'] : 0;

try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND branch_id = ? AND is_test = ?");
    $stmt->execute([$order_id, $branch_id, $is_test]);
    $order = $stmt->fetch();

    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();

    foreach ($items as &$item) {
        $stmtMods = $pdo->prepare("SELECT modifier_id FROM order_item_modifiers WHERE order_item_id = ?");
        $stmtMods->execute([$item['id']]);
        $item['modifier_ids'] = $stmtMods->fetchAll(PDO::FETCH_COLUMN);
    }

    $order['items'] = $items;
    echo json_encode($order);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
