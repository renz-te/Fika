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
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
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
    ?? $_GET['kiosk_token'] 
    ?? ''
));

if (!$isStaffSession && empty($kioskToken)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: authentication required.']);
    exit;
}

$orderId = isset($input['order_id']) ? (int)$input['order_id'] : 0;
if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'order_id is required']);
    exit;
}

$rawMethod = strtolower(trim((string)($input['method'] ?? 'gcash')));
$allowedMethods = [
    'gcash'    => 'gcash',
    'paymaya'  => 'paymaya',
    'maya'     => 'paymaya',
    'card'     => 'card'
];
$selectedMethod = $allowedMethods[$rawMethod] ?? 'gcash';

try {
    // 2. Fetch order details
    $orderStmt = $pdo->prepare("SELECT id, order_number, total_price, branch_id, payment_status, order_status, pos_session_id, is_test FROM orders WHERE id = ?");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch();

    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Order not found.']);
        exit;
    }

    // 3. Authorization Check: Branch scoping for staff, or token hash match for kiosk
    if ($isStaffSession) {
        $staffBranchId = (int)($_SESSION['user']['branch_id'] ?? 0);
        if ($staffBranchId > 0 && (int)$order['branch_id'] !== $staffBranchId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden: order belongs to another branch.']);
            exit;
        }
    } else {
        // Kiosk token must match THIS order
        $stmtToken = $pdo->prepare("SELECT token_hash FROM order_kiosk_tokens WHERE order_id = ? ORDER BY id DESC LIMIT 1");
        $stmtToken->execute([$orderId]);
        $storedHash = $stmtToken->fetchColumn();

        $providedHash = hash('sha256', $kioskToken);
        if (!$storedHash || !hash_equals($storedHash, $providedHash)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden: invalid kiosk token for this order.']);
            exit;
        }
    }

    // 4. Order Status Validation: must be UNPAID and not VOIDED
    if (strtoupper((string)$order['payment_status']) === 'PAID') {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Order has already been marked as PAID.']);
        exit;
    }

    if (in_array(strtoupper((string)($order['order_status'] ?? '')), ['VOID', 'VOIDED', 'REFUNDED'], true)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Order has been voided.']);
        exit;
    }

    // 5. Rate Limiting: max 10 intent creations per order per 10 minutes
    $stmtRate = $pdo->prepare("
        SELECT COUNT(*) 
        FROM order_audit_logs 
        WHERE order_id = ? 
          AND action = 'INTENT_CREATED' 
          AND created_at >= (NOW() - INTERVAL 10 MINUTE)
    ");
    $stmtRate->execute([$orderId]);
    if ((int)$stmtRate->fetchColumn() >= 10) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Too many payment requests for this order. Please try again later.']);
        exit;
    }

    // 6. Server-side Shift Derivation: Ignore client-supplied shift_id; must be OPEN session of same branch
    $stmtShift = $pdo->prepare("
        SELECT id 
        FROM pos_sessions 
        WHERE branch_id = ? AND status = 'OPEN' 
        ORDER BY opened_at DESC 
        LIMIT 1
    ");
    $stmtShift->execute([(int)$order['branch_id']]);
    $openShift = $stmtShift->fetch();

    if (!$openShift) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'No active shift is open for this branch.']);
        exit;
    }
    $shiftId = (int)$openShift['id'];

    // Associate shift and method with order
    $updOrderShift = $pdo->prepare("UPDATE orders SET pos_session_id = ?, payment_method = ? WHERE id = ?");
    $updOrderShift->execute([$shiftId, strtoupper($selectedMethod), $orderId]);

    // 7. Amount calculation in integer centavos
    $grossDecimal = (float)$order['total_price'];
    $amountInCents = (int)round($grossDecimal * 100);

    if ($amountInCents <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Order total must be greater than zero for digital payment.']);
        exit;
    }

    // 8. Rate Limit Audit Persistence (Trap 3): Record INTENT_CREATED BEFORE external API / simulation attempt
    $pdo->prepare("
        INSERT INTO order_audit_logs (branch_id, order_id, action, supervisor_id, reason, created_at)
        VALUES (?, ?, 'INTENT_CREATED', NULL, ?, NOW())
    ")->execute([
        (int)$order['branch_id'],
        $orderId,
        "Payment intent creation attempt for Order #{$order['order_number']} via " . strtoupper($selectedMethod) . " (" . number_format($grossDecimal, 2) . " PHP)"
    ]);

    // 9. Payment Gateway Dispatch
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
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => 'Live payment gateway not yet wired.']);
        exit;
    }

    // 10. Store pending payment record in payments table
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

} catch (Throwable $e) {
    error_log("Payment intent create error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to process payment request.']);
}
