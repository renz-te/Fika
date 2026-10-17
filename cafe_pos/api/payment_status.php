<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../database.php';

// 1. Authentication Check: Staff session OR Kiosk token
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isStaffSession = !empty($_SESSION['user']) && in_array(
    $_SESSION['user']['role'] ?? '',
    ['Cashier', 'Admin', 'Super Admin', 'Branch Manager', 'Head Barista', 'Branch Admin'],
    true
);

$kioskToken = trim((string)(
    $_SERVER['HTTP_X_KIOSK_TOKEN'] 
    ?? $_GET['kiosk_token'] 
    ?? $_POST['kiosk_token'] 
    ?? ''
));

if (!$isStaffSession && empty($kioskToken)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: authentication required.']);
    exit;
}

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
            SELECT o.id as order_id, o.order_number, o.branch_id, o.payment_status, o.order_status, 
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
            SELECT o.id as order_id, o.order_number, o.branch_id, o.payment_status, o.order_status, 
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

    $targetOrderId = (int)$data['order_id'];

    // 2. Authorization Check: Branch scoping for staff, or token hash match for kiosk
    if ($isStaffSession) {
        $staffBranchId = (int)($_SESSION['user']['branch_id'] ?? 0);
        if ($staffBranchId > 0 && (int)$data['branch_id'] !== $staffBranchId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden: order belongs to another branch.']);
            exit;
        }
    } else {
        // Kiosk token must match THIS order
        $stmtToken = $pdo->prepare("SELECT token_hash FROM order_kiosk_tokens WHERE order_id = ? ORDER BY id DESC LIMIT 1");
        $stmtToken->execute([$targetOrderId]);
        $storedHash = $stmtToken->fetchColumn();

        $providedHash = hash('sha256', $kioskToken);
        if (!$storedHash || !hash_equals($storedHash, $providedHash)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden: invalid kiosk token for this order.']);
            exit;
        }
    }

    $isPaid = ($data['payment_status'] === 'PAID') || ($data['payment_record_status'] === 'paid');

    echo json_encode([
        'success'        => true,
        'order_id'       => $targetOrderId,
        'order_number'   => $data['order_number'],
        'is_paid'        => $isPaid,
        'payment_status' => $data['payment_status'],
        'order_status'   => $data['order_status'],
        'payment_record_status' => $data['payment_record_status'] ?? ($isPaid ? 'paid' : 'pending'),
        'method'         => $data['method'] ?? $data['payment_method'],
        'paid_at'        => $data['paid_at']
    ]);

} catch (Throwable $e) {
    error_log("Payment status query error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to retrieve payment status.']);
}
