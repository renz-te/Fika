<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$orderId = (int) ($input['order_id'] ?? $_POST['order_id'] ?? $_GET['order_id'] ?? 0);
$kioskToken = $_SERVER['HTTP_X_KIOSK_TOKEN'] ?? $input['kiosk_token'] ?? $_POST['kiosk_token'] ?? $_GET['kiosk_token'] ?? null;

if ($orderId <= 0) {
    http_response_code(400);
    exit(json_encode(["error" => "Valid order ID is required."]));
}

global $pdo;

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    exit(json_encode(["error" => "Order not found."]));
}

// Authorization check: Cashier of the same branch OR valid Kiosk Token
$isAuthorized = false;
$auditUserId = null;

if (Auth::check()) {
    $user = Auth::user();
    // Verify permission and branch matching
    if (Rbac::can('pos.access') && ((int)$user['branch_id'] === (int)$order['branch_id'] || empty($user['branch_id']))) {
        $isAuthorized = true;
        $auditUserId = (int)$user['id'];
    }
}

if (!$isAuthorized && !empty($kioskToken)) {
    $tokStmt = $pdo->prepare("
        SELECT token_hash, expires_at 
        FROM order_kiosk_tokens 
        WHERE order_id = ? 
        ORDER BY id DESC LIMIT 1
    ");
    $tokStmt->execute([$orderId]);
    $tok = $tokStmt->fetch();
    if ($tok && hash_equals($tok['token_hash'], hash('sha256', $kioskToken))) {
        $isAuthorized = true;
        $auditUserId = !empty($order['cashier_id']) ? (int)$order['cashier_id'] : null;
    }
}

if (!$isAuthorized) {
    http_response_code(403);
    exit(json_encode(["error" => "Forbidden: insufficient permissions or invalid kiosk token."]));
}

// Log receipt printing
$logDetails = "Receipt printed for Order ID: {$order['id']}, Order Number: {$order['order_number']}";
Audit::log('RECEIPT_PRINTED', $logDetails, $auditUserId);

echo json_encode([
    "success" => true,
    "message" => "Receipt print logged successfully."
]);
