<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

$orderId = (int)($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
if ($orderId <= 0) {
    $rawInput = json_decode(file_get_contents('php://input'), true);
    if (is_array($rawInput) && !empty($rawInput['order_id'])) {
        $orderId = (int)$rawInput['order_id'];
    }
}

if ($orderId <= 0) {
    http_response_code(400);
    exit(json_encode(["error" => "Valid order ID is required."]));
}

// 1. Kiosk Token
$kioskToken = $_SERVER['HTTP_X_KIOSK_TOKEN'] ?? $_GET['kiosk_token'] ?? $_POST['kiosk_token'] ?? null;
if (!$kioskToken) {
    $rawInput = json_decode(file_get_contents('php://input'), true);
    if (is_array($rawInput) && !empty($rawInput['kiosk_token'])) {
        $kioskToken = $rawInput['kiosk_token'];
    }
}

if (empty($kioskToken)) {
    http_response_code(403);
    exit(json_encode(["error" => "Forbidden: missing kiosk token."]));
}

global $pdo;

// 2. Fetch Order
$orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$orderStmt->execute([$orderId]);
$order = $orderStmt->fetch();

if (!$order) {
    http_response_code(404);
    exit(json_encode(["error" => "Order not found."]));
}

// 3. Optional Device Authentication & Branch Isolation Check
$deviceToken = $_SERVER['HTTP_X_DEVICE_TOKEN'] ?? $_GET['device_token'] ?? $_POST['device_token'] ?? null;
if ($deviceToken) {
    $device = Device::getDevice($deviceToken);
    if ($device && (int)$device['branch_id'] !== (int)$order['branch_id']) {
        http_response_code(403);
        exit(json_encode(["error" => "Forbidden: kiosk cannot access orders of another branch."]));
    }
}

// 4. Validate Order-Bound Token
$tokenStmt = $pdo->prepare("SELECT token_hash, expires_at FROM order_kiosk_tokens WHERE order_id = ? ORDER BY id DESC LIMIT 1");
$tokenStmt->execute([$orderId]);
$tok = $tokenStmt->fetch();

if (!$tok) {
    http_response_code(403);
    exit(json_encode(["error" => "Forbidden: no kiosk token issued for this order."]));
}

$providedHash = hash('sha256', $kioskToken);
if (!hash_equals($tok['token_hash'], $providedHash)) {
    http_response_code(403);
    exit(json_encode(["error" => "Forbidden: invalid kiosk token for this order."]));
}

// Check expiration
if (strtotime($tok['expires_at']) < time()) {
    http_response_code(403);
    exit(json_encode(["error" => "Forbidden: kiosk token has expired."]));
}

$isPaid = ($order['payment_status'] === 'PAID') || ($order['order_status'] === 'PAID');

echo json_encode([
    'success' => true,
    'order_id' => (int)$order['id'],
    'order_number' => $order['order_number'],
    'branch_id' => (int)$order['branch_id'],
    'order_status' => $order['order_status'],
    'payment_status' => $order['payment_status'],
    'total_amount' => $order['total_amount'],
    'final_amount' => $order['final_amount'],
    'is_paid' => $isPaid,
    'created_at' => $order['created_at'],
    'expires_at' => $tok['expires_at']
]);
