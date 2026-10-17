<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../database.php';

if (!class_exists('Config')) {
    class Config {
        public static function get(string $key, mixed $default = null): mixed {
            static $cache = [];
            $envFile = dirname(__DIR__, 2) . '/.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#')) continue;
                    if (str_contains($line, '=')) {
                        [$k, $v] = explode('=', $line, 2);
                        $k = trim($k);
                        $v = trim($v, " \t\n\r\0\x0B\"'");
                        putenv("{$k}={$v}");
                        $_ENV[$k] = $v;
                        $_SERVER[$k] = $v;
                    }
                }
            }
            [$file, $item] = array_pad(explode('.', $key, 2), 2, null);
            $path = dirname(__DIR__, 2) . '/config/' . $file . '.php';
            $config = file_exists($path) ? require $path : [];
            if ($item === null) return $config ?? $default;
            return $config[$item] ?? $default;
        }
    }
}

$secretKey = Config::get('paymongo.secret_key');
if (empty($secretKey)) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Service unavailable: payment gateway not configured.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

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
    ?? $input['kiosk_token'] 
    ?? ''
));

if (!$isStaffSession && empty($kioskToken)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: authentication required.']);
    exit;
}

if (!isset($input['order_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'order_id is required']);
    exit;
}

$order_id = (int)$input['order_id'];

try {
    // Fetch order details
    $stmt = $pdo->prepare("SELECT id, order_number, total_price, branch_id, payment_status, order_status FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Order not found.']);
        exit;
    }

    // 2. Branch Ownership & Authorization Check
    if ($isStaffSession) {
        $staffBranchId = (int)($_SESSION['user']['branch_id'] ?? 0);
        if ($staffBranchId > 0 && (int)$order['branch_id'] !== $staffBranchId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden: order belongs to another branch.']);
            exit;
        }
    } else {
        // Validate kiosk token against order_kiosk_tokens
        $stmtToken = $pdo->prepare("SELECT token_hash FROM order_kiosk_tokens WHERE order_id = ? ORDER BY id DESC LIMIT 1");
        $stmtToken->execute([$order_id]);
        $storedHash = $stmtToken->fetchColumn();

        $providedHash = hash('sha256', $kioskToken);
        if (!$storedHash || !hash_equals($storedHash, $providedHash)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden: invalid kiosk token for this order.']);
            exit;
        }
    }

    if ($order['payment_status'] === 'PAID') {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Order is already paid.']);
        exit;
    }

    if (in_array(strtoupper((string)$order['order_status']), ['VOID', 'VOIDED', 'REFUNDED', 'CANCELLED'])) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Cannot checkout cancelled or voided order.']);
        exit;
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
        'Authorization: Basic ' . base64_encode($secretKey . ':')
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $responseData = json_decode((string)$response, true);

    if ($httpCode === 200 && isset($responseData['data']['attributes']['checkout_url'])) {
        echo json_encode([
            'success' => true,
            'checkout_url' => $responseData['data']['attributes']['checkout_url'],
            'checkout_session_id' => $responseData['data']['id']
        ]);
    } else {
        http_response_code(502);
        echo json_encode([
            'success' => false,
            'error' => 'Payment gateway rejected the checkout request.'
        ]);
    }

} catch (\Throwable $e) {
    error_log("PayMongo checkout error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An error occurred while creating checkout session.']);
}
