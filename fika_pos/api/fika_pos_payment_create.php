<?php
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json');

// 1. Fail Closed: Payment gateway secret must be present
$secretKey = Config::get('paymongo.secret_key');
if (empty($secretKey)) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Service unavailable: payment gateway not configured.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$rawInput = json_decode(file_get_contents('php://input'), true);
$input = is_array($rawInput) ? array_merge($_POST, $rawInput) : $_POST;

// 2. Authentication Check: Staff session OR Kiosk token
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isStaffSession = false;
$staffUser = Auth::user();
if ($staffUser) {
    $isStaffSession = Rbac::can('pos.access');
}

$kioskToken = trim((string)(
    $_SERVER['HTTP_X_KIOSK_TOKEN'] 
    ?? $input['kiosk_token'] 
    ?? $_GET['kiosk_token'] 
    ?? ''
));

if (!$isStaffSession && empty($kioskToken)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: authentication required.']);
    exit;
}

$orderId = (int)($input['order_id'] ?? $_GET['order_id'] ?? 0);
if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid order_id is required.']);
    exit;
}

$rawMethod = strtolower(trim((string)($input['method'] ?? 'gcash')));
$allowedMethods = [
    'gcash'   => 'gcash',
    'paymaya' => 'paymaya',
    'maya'    => 'paymaya',
    'card'    => 'card'
];
$selectedMethod = $allowedMethods[$rawMethod] ?? 'gcash';

global $pdo;

try {
    // 3. Fetch order details
    $orderStmt = $pdo->prepare("
        SELECT id, order_number, branch_id, total_amount, final_amount, 
               COALESCE(final_amount, total_amount) AS total_price,
               payment_status, order_status, pos_session_id, is_test 
        FROM orders 
        WHERE id = ?
    ");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch();

    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Order not found.']);
        exit;
    }

    // 4. Authorization Check: Branch scoping for staff, or token hash match for kiosk
    if ($isStaffSession) {
        $staffBranchId = (int)($staffUser['branch_id'] ?? 0);
        if ($staffBranchId > 0 && (int)$order['branch_id'] !== $staffBranchId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden: order belongs to another branch.']);
            exit;
        }
    } else {
        $tokenStmt = $pdo->prepare("SELECT token_hash FROM order_kiosk_tokens WHERE order_id = ? ORDER BY id DESC LIMIT 1");
        $tokenStmt->execute([$orderId]);
        $storedHash = $tokenStmt->fetchColumn();

        $providedHash = hash('sha256', $kioskToken);
        if (!$storedHash || !hash_equals($storedHash, $providedHash)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden: invalid kiosk token for this order.']);
            exit;
        }
    }

    // 5. Order Status Validation: must be UNPAID and not VOIDED/REFUNDED
    if (strtoupper((string)$order['payment_status']) === 'PAID') {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Order has already been marked as PAID.']);
        exit;
    }

    if (in_array(strtoupper((string)($order['order_status'] ?? '')), ['VOID', 'VOIDED', 'REFUNDED'], true)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Order has been voided or refunded.']);
        exit;
    }

    // 6. Rate Limiting: max 10 intent creations per order per 10 minutes
    $rateStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM audit_logs 
        WHERE action = 'INTENT_CREATED' 
          AND details LIKE ? 
          AND created_at >= (NOW() - INTERVAL 10 MINUTE)
    ");
    $rateStmt->execute(["%Order ID: {$orderId}%"]);
    if ((int)$rateStmt->fetchColumn() >= 10) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Too many payment requests for this order. Please try again later.']);
        exit;
    }

    // 7. Server-side Shift Derivation: Ignore client-supplied shift_id; must be OPEN session of same branch
    $shiftStmt = $pdo->prepare("
        SELECT id 
        FROM cash_sessions 
        WHERE branch_id = ? AND status = 'OPEN' 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $shiftStmt->execute([(int)$order['branch_id']]);
    $openShift = $shiftStmt->fetch();

    if (!$openShift) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'No active shift is open for this branch.']);
        exit;
    }
    $shiftId = (int)$openShift['id'];

    // 8. Amount calculation in integer centavos
    $grossDecimal = (float)$order['total_price'];
    $amountInCents = Money::toCentavos($grossDecimal);

    if ($amountInCents <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Order total must be greater than zero for digital payment.']);
        exit;
    }

    // 9. Persist audit log for rate limiting
    Audit::log('INTENT_CREATED', "Order ID: {$orderId}, Number: {$order['order_number']}, Method: {$selectedMethod}, Amount: " . Money::toDecimal($amountInCents));

    // 10. Payment Gateway Dispatch (Dynamic URLs from .env)
    $envBaseUrl = getenv('WEBHOOK_BASE_URL') ?: (getenv('APP_URL') ?: '');
    if (!empty($envBaseUrl)) {
        $baseUrl = rtrim($envBaseUrl, '/');
    } else {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $scheme . '://' . $host;
        if (preg_match('#^(.*?)/fika_pos/#', $_SERVER['REQUEST_URI'] ?? '', $m)) {
            $baseUrl .= $m[1];
        }
    }

    $successUrl = $baseUrl . '/fika_pos/fika_pos_mock_checkout.php?status=success&order_id=' . $orderId;
    $cancelUrl = $baseUrl . '/fika_pos/fika_pos_mock_checkout.php?status=cancel&order_id=' . $orderId;

    $checkoutUrl = null;
    $intentId = null;

    if (!empty($secretKey)) {
        $paymongoPayload = [
            'data' => [
                'attributes' => [
                    'send_email_receipt' => false,
                    'show_description' => false,
                    'show_line_items' => true,
                    'line_items' => [
                        [
                            'currency' => 'PHP',
                            'amount' => $amountInCents,
                            'name' => 'Order ' . ($order['order_number'] ?? ('#' . $orderId)),
                            'quantity' => 1
                        ]
                    ],
                    'payment_method_types' => ['gcash', 'paymaya'],
                    'reference_number' => (string)($order['order_number'] ?? ('ORD-' . $orderId)),
                    'description' => 'Payment for Order ' . ($order['order_number'] ?? ('#' . $orderId)),
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl
                ]
            ]
        ];

        $ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymongoPayload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($secretKey . ':')
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $apiResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && !empty($apiResponse)) {
            $respData = json_decode($apiResponse, true);
            if (!empty($respData['data']['attributes']['checkout_url'])) {
                $checkoutUrl = $respData['data']['attributes']['checkout_url'];
                $intentId = $respData['data']['id'] ?? ('cs_' . bin2hex(random_bytes(12)));
            }
        }
    }

    // Fallback to local simulator if PayMongo API is unreachable
    if (empty($checkoutUrl)) {
        $intentId = 'cs_test_' . bin2hex(random_bytes(16));
        $checkoutUrl = $baseUrl . "/fika_pos/fika_pos_mock_checkout.php?intent_id={$intentId}&order_id={$orderId}&method={$selectedMethod}";
    }

    // 11. Store pending payment row in payments table
    $pdo->prepare("DELETE FROM payments WHERE order_id = ? AND status = 'pending'")->execute([$orderId]);

    $insPayment = $pdo->prepare("
        INSERT INTO payments (
            order_id, shift_id, intent_id, method, payment_method,
            gross_amount, amount_paid, fee_amount, net_amount, status, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, 
            ?, ?, 0.00, ?, 'pending', NOW()
        )
    ");
    $grossFormatted = Money::toDecimal($amountInCents);
    $insPayment->execute([
        $orderId, 
        $shiftId, 
        $intentId, 
        $selectedMethod, 
        strtoupper($selectedMethod),
        $grossFormatted, 
        $grossFormatted, 
        $grossFormatted
    ]);

    // Update order with shift and method
    $pdo->prepare("UPDATE orders SET pos_session_id = ?, payment_method = ? WHERE id = ?")
        ->execute([$shiftId, strtoupper($selectedMethod), $orderId]);

    echo json_encode([
        'success'      => true,
        'order_id'     => $orderId,
        'order_number' => $order['order_number'],
        'shift_id'     => $shiftId,
        'intent_id'    => $intentId,
        'method'       => $selectedMethod,
        'gross_amount' => $grossFormatted,
        'checkout_url' => $checkoutUrl,
        'qr_code_url'  => 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($checkoutUrl)
    ]);

} catch (Throwable $e) {
    error_log("Payment intent create error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to process payment request.']);
}
