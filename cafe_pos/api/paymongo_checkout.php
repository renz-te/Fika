<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../database.php';
// Include config to get the PayMongo keys
$config = require __DIR__ . '/../../cafe_hrms/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['order_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'order_id is required']);
    exit;
}

$order_id = $input['order_id'];

try {
    // Fetch order details
    $stmt = $pdo->prepare("SELECT id, order_number, total_price, branch_id, payment_status FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        throw new Exception("Order not found.");
    }

    if ($order['payment_status'] === 'PAID') {
        throw new Exception("Order is already paid.");
    }

    // PayMongo requires amount in cents
    $net_payable = (float)$order['total_price'];
    $amount_in_cents = (int)round($net_payable * 100);

    // Prepare PayMongo Request payload
    $payload = [
        'data' => [
            'attributes' => [
                'send_email_receipt' => false,
                'show_description' => false,
                'show_line_items' => true,
                'line_items' => [
                    [
                        'currency' => 'PHP',
                        'amount' => $amount_in_cents,
                        'name' => 'Order ' . $order['order_number'],
                        'quantity' => 1
                    ]
                ],
                'payment_method_types' => ['gcash', 'paymaya', 'grab_pay', 'billease'],
                'reference_number' => $order['order_number'],
                'description' => 'Payment for ' . $order['order_number'],
                'metadata' => [
                    'order_id' => (string)$order['id'],
                    'branch_id' => (string)$order['branch_id']
                ],
                // Assuming successful payment will close the window or redirect
                'success_url' => 'http://localhost/Fika/Fika/cafe_pos/kiosk.php?payment=success&order_id=' . $order['id'],
                'cancel_url' => 'http://localhost/Fika/Fika/cafe_pos/kiosk.php?payment=cancel&order_id=' . $order['id'],
            ]
        ]
    ];

    $ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':')
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $responseData = json_decode($response, true);

    if ($httpCode === 200 && isset($responseData['data']['attributes']['checkout_url'])) {
        echo json_encode([
            'success' => true,
            'checkout_url' => $responseData['data']['attributes']['checkout_url'],
            'checkout_session_id' => $responseData['data']['id']
        ]);
   } else {
            http_response_code(500);
            echo json_encode([
                'error' => 'PayMongo rejected the request.',
                'http_code' => $httpCode,
                'paymongo_response' => $responseData
            ]);
        }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
