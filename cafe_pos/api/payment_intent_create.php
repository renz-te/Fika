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
    echo json_encode(['error' => 'Service unavailable: payment gateway not configured.']);
    exit;
}

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

    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $method  = $selectedMethod;

    if (Config::get('paymongo.is_test') === true) {
        $intentId    = 'cs_test_' . bin2hex(random_bytes(16));
        $subPath     = '';
        if (preg_match('#^(.*?)/cafe_pos/#', $_SERVER['REQUEST_URI'] ?? '', $m)) {
            $subPath = $m[1];
        }
        $checkoutUrl = $baseUrl . $subPath
                     . "/cafe_pos/kiosk.php?payment=mock_checkout"
                     . "&intent_id={$intentId}&order_id={$orderId}&method={$method}";
    } else {
        // Real PayMongo API call goes here.
        // If not yet implemented, return 502 so nothing silently falls through.
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => 'Live payment gateway not yet wired.']);
        exit;
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

    // Audit Log on Intent Creation
    $pdo->prepare("
        INSERT INTO order_audit_logs (branch_id, order_id, action, supervisor_id, reason, created_at)
        VALUES (?, ?, 'INTENT_CREATED', NULL, ?, NOW())
    ")->execute([
        (int)$order['branch_id'],
        $orderId,
        "Payment intent created: {$intentId} for Order #{$order['order_number']} via " . strtoupper($selectedMethod) . " (" . number_format($grossDecimal, 2) . " PHP)"
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
