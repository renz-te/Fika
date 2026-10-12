<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../database.php';

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$intentId = isset($_GET['intent_id']) ? trim($_GET['intent_id']) : '';

if ($orderId <= 0 && empty($intentId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'order_id or intent_id required']);
    exit;
}

try {
    if ($orderId > 0) {
        $stmt = $pdo->prepare("
            SELECT o.id as order_id, o.order_number, o.payment_status, o.order_status, 
                   o.payment_method, o.total_price,
                   p.intent_id, p.status as payment_record_status, p.paid_at, p.method
            FROM orders o
            LEFT JOIN payments p ON o.id = p.order_id
            WHERE o.id = ?
            ORDER BY p.id DESC LIMIT 1
        ");
        $stmt->execute([$orderId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT o.id as order_id, o.order_number, o.payment_status, o.order_status, 
                   o.payment_method, o.total_price,
                   p.intent_id, p.status as payment_record_status, p.paid_at, p.method
            FROM payments p
            JOIN orders o ON p.order_id = o.id
            WHERE p.intent_id = ?
            ORDER BY p.id DESC LIMIT 1
        ");
        $stmt->execute([$intentId]);
    }

    $data = $stmt->fetch();
    if (!$data) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Order/Payment not found']);
        exit;
    }

    $isPaid = ($data['payment_status'] === 'PAID') || ($data['payment_record_status'] === 'paid');

    echo json_encode([
        'success'        => true,
        'order_id'       => (int)$data['order_id'],
        'order_number'   => $data['order_number'],
        'is_paid'        => $isPaid,
        'payment_status' => $data['payment_status'],
        'order_status'   => $data['order_status'],
        'payment_record_status' => $data['payment_record_status'] ?? ($isPaid ? 'paid' : 'pending'),
        'method'         => $data['method'] ?? $data['payment_method'],
        'paid_at'        => $data['paid_at']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
