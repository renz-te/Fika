<?php
error_reporting(0);
header('Content-Type: application/json');
require_once __DIR__ . '/../database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 1;
$is_test = isset($_GET['is_test']) ? (int)$_GET['is_test'] : 0;

try {
    $stmt = $pdo->prepare("
        SELECT id, order_number, table_source, total_price, payment_status, created_at 
        FROM orders 
        WHERE order_status = 'PENDING' AND branch_id = ? AND is_test = ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$branch_id, $is_test]);
    $orders = $stmt->fetchAll();

    echo json_encode(['pending_orders' => $orders]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
