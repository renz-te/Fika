<?php
require_once __DIR__ . '/../database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Mandatory Staff Session Authentication
$isStaff = !empty($_SESSION['user']) && in_array(
    $_SESSION['user']['role'] ?? '',
    ['Cashier', 'Admin', 'Super Admin', 'Branch Manager', 'Head Barista', 'Branch Admin'],
    true
);

if (!$isStaff) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: authentication required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['order_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$orderId = (int)$input['order_id'];

// 2. Fetch Order outside transaction to verify existence and branch
$stmtOrder = $pdo->prepare("SELECT id, order_number, branch_id FROM orders WHERE id = ?");
$stmtOrder->execute([$orderId]);
$order = $stmtOrder->fetch();

if (!$order) {
    http_response_code(404);
    echo json_encode(['error' => 'Order not found']);
    exit;
}

// 3. Branch Ownership Enforcement
$staffBranch = (int)($_SESSION['user']['branch_id'] ?? 0);
if ($staffBranch > 0 && (int)$order['branch_id'] !== $staffBranch) {
    try {
        $pdo->prepare("
            INSERT INTO order_audit_logs (branch_id, order_id, action, supervisor_id, reason, created_at)
            VALUES (?, ?, 'AUTH_FAIL', ?, ?, NOW())
        ")->execute([
            (int)$order['branch_id'],
            $orderId,
            (int)$_SESSION['user']['id'],
            "Cross-branch order completion attempt denied"
        ]);
    } catch (\Throwable $t) {}

    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: wrong branch']);
    exit;
}

$branchId = (int)$order['branch_id'];

try {
    $stmt = $pdo->prepare("UPDATE orders SET order_status = 'COMPLETED' WHERE id = ?");
    $stmt->execute([$orderId]);

    try {
        $pdo->prepare("
            INSERT INTO order_audit_logs (branch_id, order_id, action, supervisor_id, reason, created_at)
            VALUES (?, ?, 'ORDER_COMPLETED', ?, ?, NOW())
        ")->execute([
            $branchId,
            $orderId,
            (int)$_SESSION['user']['id'],
            "Order #{$order['order_number']} marked COMPLETED by staff"
        ]);
    } catch (\Throwable $t) {}

    echo json_encode(['success' => true]);
} catch (\Throwable $e) {
    error_log("Order completion error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred while completing the order.']);
}
