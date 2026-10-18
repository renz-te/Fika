<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method Not Allowed']));
}

Auth::requireLogin();
Csrf::requireValid();
Rbac::require_permission('pos.access');

$user = Auth::user();
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$orderId = (int)($input['order_id'] ?? $_POST['order_id'] ?? 0);

if ($orderId <= 0) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid order ID']));
}

global $pdo;

try {
    $stmtOrder = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmtOrder->execute([$orderId]);
    $order = $stmtOrder->fetch();

    if (!$order) {
        http_response_code(404);
        exit(json_encode(['error' => 'Order not found']));
    }

    if (!empty($user['branch_id']) && (int)$order['branch_id'] !== (int)$user['branch_id']) {
        Audit::log('AUTH_FAIL', "Cross-branch order completion attempt denied for Order {$orderId}", $user['id']);
        http_response_code(403);
        exit(json_encode(['error' => 'Forbidden: wrong branch']));
    }

    $stmt = $pdo->prepare("UPDATE orders SET order_status = 'COMPLETED' WHERE id = ?");
    $stmt->execute([$orderId]);

    Audit::log('ORDER_COMPLETED', "Order #{$order['order_number']} marked COMPLETED by staff", $user['id']);

    echo json_encode(['success' => true]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred while completing the order.']);
}
