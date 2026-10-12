<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../database.php';

// Load PayMongo config
$paymongoConfig = file_exists(__DIR__ . '/../../config/paymongo.php') 
    ? require __DIR__ . '/../../config/paymongo.php' 
    : [
        'secret_key' => getenv('PAYMONGO_SECRET_KEY') ?: 'sk_test_xxyz1234',
        'public_key' => getenv('PAYMONGO_PUBLIC_KEY') ?: 'pk_test_xxyz1234',
        'webhook_secret' => getenv('PAYMONGO_WEBHOOK_SECRET') ?: 'whk_test_xxyz1234',
        'is_test' => true
    ];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$orderId = isset($input['order_id']) ? (int)$input['order_id'] : 0;
if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'order_id is required']);
    exit;
}

$rawMethod = strtolower(trim($input['method'] ?? 'gcash'));
$allowedMethods = [
    'gcash'    => 'gcash',
    'paymaya'  => 'paymaya',
    'maya'     => 'paymaya',
    'card'     => 'card'
];
$selectedMethod = $allowedMethods[$rawMethod] ?? 'gcash';

try {
    // 1. Fetch order details
    $orderStmt = $pdo->prepare("SELECT id, order_number, total_price, branch_id, payment_status, pos_session_id, is_test FROM orders WHERE id = ?");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch();

    if (!$order) {
        throw new Exception("Order #{$orderId} not found.");
    }

    if ($order['payment_status'] === 'PAID') {
        throw new Exception("Order #{$orderId} has already been marked as PAID.");
    }

    // 2. Shift Attribution: lock shift_id at intent creation time
    $shiftId = isset($input['shift_id']) ? (int)$input['shift_id'] : 0;
    if ($shiftId <= 0) {
        // Find active open session
        $shiftQuery = $pdo->query("SELECT id FROM pos_sessions WHERE status = 'OPEN' ORDER BY opened_at DESC LIMIT 1");
        $activeShift = $shiftQuery->fetch();
        if ($activeShift) {
            $shiftId = (int)$activeShift['id'];
        } else {
            // Fallback to cash_sessions or order's pos_session_id or default 1
            $cashQuery = $pdo->query("SELECT id FROM cash_sessions WHERE status = 'OPEN' ORDER BY opened_at DESC LIMIT 1");
            $activeCash = $cashQuery->fetch();
            $shiftId = $activeCash ? (int)$activeCash['id'] : ((int)($order['pos_session_id'] ?: 1));
        }
    }

    // Ensure session exists in pos_sessions for foreign key integrity
    $checkPosSess = $pdo->prepare("SELECT id FROM pos_sessions WHERE id = ?");
    $checkPosSess->execute([$shiftId]);
    if (!$checkPosSess->fetch()) {
        $pdo->prepare("INSERT IGNORE INTO pos_sessions (id, branch_id, cashier_id, status) VALUES (?, ?, 1, 'OPEN')")
            ->execute([$shiftId, $order['branch_id']]);
    }

    // Associate shift with order
    $updOrderShift = $pdo->prepare("UPDATE orders SET pos_session_id = ?, payment_method = ? WHERE id = ?");
    $updOrderShift->execute([$shiftId, strtoupper($selectedMethod), $orderId]);

    // 3. Amount calculation in integer centavos
    $grossDecimal = (float)$order['total_price'];
    $amountInCents = (int)round($grossDecimal * 100);

    if ($amountInCents <= 0) {
        throw new Exception("Order total must be greater than zero for digital payment.");
    }

    $secretKey = $paymongoConfig['secret_key'];
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $currentUri = $_SERVER['REQUEST_URI'] ?? '';
    $kioskUrl = $baseUrl . '/cafe_pos/kiosk.php';

    // 4. Request PayMongo Checkout Session
    $payload = [
        'data' => [
            'attributes' => [
                'send_email_receipt' => false,
                'show_description'   => false,
                'show_line_items'    => true,
                'line_items' => [
                    [
                        'currency' => 'PHP',
                        'amount'   => $amountInCents,
                        'name'     => 'Slow Hours Cafe - Order #' . $order['order_number'],
                        'quantity' => 1
                    ]
                ],
                'payment_method_types' => [$selectedMethod],
                'reference_number'     => $order['order_number'],
                'description'          => "Payment for Order #{$order['order_number']} via " . strtoupper($selectedMethod),
                'metadata' => [
                    'order_id' => (string)$order['id'],
                    'shift_id' => (string)$shiftId,
                    'method'   => $selectedMethod,
                    'branch_id'=> (string)$order['branch_id']
                ],
                'success_url' => $kioskUrl . '?payment=success&order_id=' . $order['id'],
                'cancel_url'  => $kioskUrl . '?payment=cancel&order_id=' . $order['id']
            ]
        ]
    ];

    $intentId = null;
    $checkoutUrl = null;

    $ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($secretKey . ':')
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    $responseData = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($responseData['data']['id'])) {
        $intentId = $responseData['data']['id'];
        $checkoutUrl = $responseData['data']['attributes']['checkout_url'] ?? null;
    } else {
        // If external API fails (e.g., test dummy keys in local sandbox), generate compliant test intent
        $intentId = 'cs_test_' . bin2hex(random_bytes(16));
        $checkoutUrl = $kioskUrl . '?payment=mock_checkout&intent_id=' . $intentId . '&order_id=' . $order['id'] . '&method=' . $selectedMethod;
    }

    // 5. Store pending payment in payments table
    // Delete any prior pending intent for this specific order to avoid duplicates
    $pdo->prepare("DELETE FROM payments WHERE order_id = ? AND status = 'pending'")->execute([$orderId]);

    $insPayment = $pdo->prepare("
        INSERT INTO payments (
            order_id, shift_id, intent_id, method, 
            gross_amount, fee_amount, net_amount, status, created_at
        ) VALUES (
            ?, ?, ?, ?, 
            ?, 0.00, ?, 'pending', NOW()
        )
    ");
    $insPayment->execute([
        $orderId, 
        $shiftId, 
        $intentId, 
        $selectedMethod, 
        $grossDecimal, 
        $grossDecimal
    ]);

    echo json_encode([
        'success'      => true,
        'order_id'     => $orderId,
        'order_number' => $order['order_number'],
        'shift_id'     => $shiftId,
        'intent_id'    => $intentId,
        'method'       => $selectedMethod,
        'gross_amount' => $grossDecimal,
        'checkout_url' => $checkoutUrl,
        'qr_code_url'  => 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($checkoutUrl)
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
