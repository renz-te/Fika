<?php
error_reporting(0);
header('Content-Type: application/json');
require_once __DIR__ . '/../database.php';

$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 1;
$is_test = isset($_GET['is_test']) ? (int)$_GET['is_test'] : 0;

try {
    $stmt = $pdo->prepare("SELECT 
        COUNT(id) as total_orders,
        SUM(total_price) as total_sales,
        SUM(CASE WHEN payment_method = 'CASH_AT_COUNTER' OR payment_method = 'CASH' THEN total_price ELSE 0 END) as cash_sales
        FROM orders WHERE payment_status = 'PAID' AND branch_id = ? AND is_test = ?");
    $stmt->execute([$branch_id, $is_test]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'total_orders' => (int)$result['total_orders'],
        'total_sales' => (float)$result['total_sales'],
        'cash_sales' => (float)$result['cash_sales']
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
