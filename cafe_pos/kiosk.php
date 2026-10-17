<?php
if (isset($_GET['payment']) && $_GET['payment'] === 'mock_checkout') {
    if (!class_exists('Config')) {
        class Config {
            public static function get(string $key, mixed $default = null): mixed {
                $envFile = dirname(__DIR__) . '/.env';
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
                $path = dirname(__DIR__) . '/config/' . $file . '.php';
                $config = file_exists($path) ? require $path : [];
                if ($item === null) return $config ?? $default;
                return $config[$item] ?? $default;
            }
        }
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $isTestMode = Config::get('paymongo.is_test') === true;
    $isCashierOrManager = !empty($_SESSION['user']) && in_array(
        $_SESSION['user']['role'] ?? '',
        ['Cashier', 'Admin', 'Super Admin', 'Branch Manager', 'Head Barista', 'Branch Admin'],
        true
    );

    if (!$isTestMode || !$isCashierOrManager) {
        http_response_code(404);
        echo "404 Not Found";
        exit;
    }

    require_once __DIR__ . '/database.php';
    $paymongoConfig = require __DIR__ . '/../config/paymongo.php';
    $orderId = (int)($_GET['order_id'] ?? 0);
    $intentId = htmlspecialchars($_GET['intent_id'] ?? '');
    $method = htmlspecialchars($_GET['method'] ?? 'gcash');

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    $notice = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['authorize_mock_payment']) && $order) {
        $amountInCents = (int)round((float)$order['total_price'] * 100);
        $payload = [
            'data' => [
                'id' => 'evt_mock_' . bin2hex(random_bytes(12)),
                'type' => 'event',
                'attributes' => [
                    'type' => 'payment.paid',
                    'livemode' => false,
                    'data' => [
                        'id' => 'pay_mock_' . bin2hex(random_bytes(12)),
                        'type' => 'payment',
                        'attributes' => [
                            'amount' => $amountInCents,
                            'fee' => 0,
                            'net_amount' => $amountInCents,
                            'currency' => 'PHP',
                            'status' => 'paid',
                            'source' => [
                                'id' => 'src_mock_' . bin2hex(random_bytes(10)),
                                'type' => $method === 'paymaya' ? 'paymaya' : 'gcash'
                            ],
                            'payment_intent_id' => $intentId,
                            'description' => 'Mock Sandbox Payment for Order #' . $order['order_number'],
                            'metadata' => [
                                'order_id' => (string)$order['id'],
                                'shift_id' => (string)($order['pos_session_id'] ?: 1),
                                'method' => $method,
                                'branch_id' => (string)$order['branch_id']
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $rawBody = json_encode($payload);
        $t = time();
        $sig = hash_hmac('sha256', "{$t}.{$rawBody}", $paymongoConfig['webhook_secret']);
        $sigHeader = "t={$t},te={$sig}";

        $subPath = '';
        if (preg_match('#^(.*?)/cafe_pos/#', $_SERVER['REQUEST_URI'] ?? '', $m)) {
            $subPath = $m[1];
        }
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $webhookUrl = $baseUrl . $subPath . '/cafe_pos/api/payment_webhook.php';

        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Paymongo-Signature: ' . $sigHeader
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        $notice = ($code === 200) ? 'SUCCESS' : 'ERROR: Webhook returned HTTP ' . $code . ' (' . htmlspecialchars((string)$res) . ')';
    }

    $paidAlready = ($order && $order['payment_status'] === 'PAID');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>PayMongo Test Checkout</title>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #0b0f19; font-family: 'Outfit', sans-serif; color: #f3f4f6; margin: 0; padding: 20px; box-sizing: border-box; }
            .checkout-card { background: #131b2e; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 32px; max-width: 440px; width: 100%; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.5); }
            .badge { display: inline-block; padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; margin-bottom: 16px; }
            .badge-gcash { background: rgba(0, 122, 255, 0.2); color: #60a5fa; border: 1px solid rgba(96, 165, 250, 0.3); }
            .badge-maya { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(52, 211, 153, 0.3); }
            .amount { font-size: 38px; font-weight: 700; color: #f59e0b; margin: 16px 0 24px; }
            .btn-pay { width: 100%; padding: 14px; border: none; border-radius: 10px; background: #10b981; color: white; font-weight: 600; font-size: 16px; cursor: pointer; transition: background 0.2s; }
            .btn-pay:hover { background: #059669; }
            .success-msg { background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; color: #34d399; padding: 16px; border-radius: 10px; margin-top: 16px; line-height: 1.5; }
            .error-msg { background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #f87171; padding: 16px; border-radius: 10px; margin-top: 16px; }
        </style>
    </head>
    <body>
        <div class="checkout-card">
            <span class="badge <?= $method === 'paymaya' ? 'badge-maya' : 'badge-gcash' ?>"><?= htmlspecialchars(strtoupper($method)) ?> Test Sandbox</span>
            <h2 style="margin: 0 0 8px; font-size: 22px;">PayMongo Simulation</h2>
            <p style="color: #9ca3af; font-size: 14px; margin: 0 0 20px;">Customer Payment Authorization Screen</p>

            <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; padding: 16px; text-align: left; font-size: 14px; margin-bottom: 8px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span style="color: #9ca3af;">Order No:</span>
                    <strong><?= htmlspecialchars($order['order_number'] ?? 'N/A') ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #9ca3af;">Payment Status:</span>
                    <strong style="color: <?= $paidAlready ? '#10b981' : '#f59e0b' ?>"><?= $paidAlready ? 'PAID' : 'PENDING' ?></strong>
                </div>
            </div>

            <div class="amount">₱<?= number_format((float)($order['total_price'] ?? 0), 2) ?></div>

            <?php if ($paidAlready): ?>
                <div class="success-msg">
                    <strong style="font-size: 16px;">✓ Payment Confirmed!</strong><br>
                    This order has been verified and marked as PAID.<br>
                    You can return to the kiosk tablet screen.
                </div>
            <?php else: ?>
                <?php if ($notice && $notice !== 'SUCCESS'): ?>
                    <div class="error-msg"><?= $notice ?></div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="authorize_mock_payment" value="1">
                    <button type="submit" class="btn-pay">Authorize & Complete Payment</button>
                </form>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$is_test = isset($_GET['mode']) && $_GET['mode'] === 'test' ? 1 : 0;
$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafe POS - Kiosk Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="assets/css/receipt.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        window.POS_CONFIG = {
            is_test: <?= $is_test ?>,
            branch_id: <?= $branch_id ?>
        };
    </script>
</head>
<body>
    <?php if ($is_test): ?>
    <div style="background-color: #ef4444; color: white; text-align: center; padding: 8px; font-weight: bold; font-size: 14px; z-index: 9999; position: relative;">
        TRAINING MODE ACTIVE: Test transactions will not affect live revenue or inventory.
    </div>
    <?php endif; ?>
    <div class="pos-layout">
        <!-- Main Content (Left) -->
        <main class="main-content">
            <header class="top-bar">
                <div class="logo">
                    <span class="logo-icon"></span>
                    <h1>Slow Hours</h1>
                </div>
                <div style="display: flex; gap: 16px; align-items: center;">
                    <div class="user-profile" id="btn-table-logout" style="display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.05); padding: 6px 12px; border-radius: 20px; cursor: pointer;">
                        <span style="font-size: 18px; display: flex; align-items: center;"><i data-lucide="smartphone" style="width: 18px; height: 18px;"></i></span>
                        <span style="font-size: 14px; color: var(--text-light);">Table 1</span>
                    </div>
                    <div class="time-display" id="time-display">00:00</div>
                </div>
            </header>

            <nav class="category-tabs" id="category-tabs">
                <!-- Tabs injected by JS -->
            </nav>

            <section class="product-grid" id="product-grid">
                <!-- Product cards injected by JS -->
            </section>
        </main>

        <!-- Right Sidebar (Cart) -->
        <aside class="cart-sidebar">
                <div class="cart-header">
                    <h2>Current Order</h2>
                    <span class="table-badge">Table 1</span>
                </div>
            
            <div class="cart-items" id="cart-items">
                <div class="empty-cart">
                    <div class="empty-icon"><i data-lucide="shopping-cart"></i></div>
                    <p>Cart is empty</p>
                </div>
            </div>

            <div class="cart-footer">
                <div class="totals">
                    <div class="total-row grand-total">
                        <span>Total</span>
                        <span id="cart-total">₱0.00</span>
                    </div>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button class="add-to-cart-btn" id="btn-clear-cart" style="flex: 1; background: #e74c3c; color: white;">Clear</button>
                    <button class="checkout-btn" id="btn-checkout" style="flex: 2;" disabled>
                        <span class="btn-text" id="btn-checkout-text">Place Order</span>
                        <span class="btn-price" id="checkout-total">₱0.00</span>
                    </button>
                </div>
            </div>
        </aside>
    </div>

    <!-- Modifier Modal -->
    <div class="modal-overlay" id="modifier-modal">
        <div class="modal-content glass-panel">
            <div class="modal-header">
                <div>
                    <h3 id="modal-product-name">Product</h3>
                    <p class="modal-subtitle">Customize your order</p>
                </div>
                <button class="close-modal" id="btn-close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modifier-list" id="modifier-list">
                    <!-- Modifiers injected by JS -->
                </div>
            </div>
            <div class="modal-footer">
                <div class="quantity-selector">
                    <button id="btn-qty-minus">-</button>
                    <span id="modal-qty">1</span>
                    <button id="btn-qty-plus">+</button>
                </div>
                <button class="add-to-cart-btn" id="btn-modal-add">
                    Add to Order <span id="modal-price">₱0.00</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Success Overlay -->
    <div class="success-overlay" id="success-overlay">
        <div class="success-card glass-panel">
            <div class="success-icon" id="success-icon"><i data-lucide="ticket"></i></div>
            <h2 id="success-title">Order Placed!</h2>
            <p id="success-message">Please proceed to the counter and provide Queue Number:</p>
            <div class="order-number-display" id="success-order-num">----</div>
            <button class="add-to-cart-btn" id="btn-new-order" style="margin-top: 24px;">Start New Order</button>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal-overlay" id="payment-modal">
        <div class="modal-content glass-panel" style="max-width: 440px;">
            <div class="modal-header">
                <h3>Select Payment Method</h3>
                <button class="close-modal" id="btn-close-payment">&times;</button>
            </div>
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 14px;">
                <button class="payment-method-btn" id="btn-pay-cash" style="display: flex; align-items: center; justify-content: center; gap: 10px;">
                    <i data-lucide="footprints" style="width: 22px; height: 22px;"></i> Pay Cash at Counter
                </button>
                <button class="payment-method-btn btn-pay-gcash-style" id="btn-pay-gcash" style="display: flex; align-items: center; justify-content: center; gap: 10px;">
                    <i data-lucide="smartphone" style="width: 22px; height: 22px;"></i> Pay with GCash
                </button>
                <button class="payment-method-btn btn-pay-maya-style" id="btn-pay-maya" style="display: flex; align-items: center; justify-content: center; gap: 10px;">
                    <i data-lucide="wallet" style="width: 22px; height: 22px;"></i> Pay with Maya
                </button>
            </div>
        </div>
    </div>

    <!-- Kiosk PayMongo QR Display Modal -->
    <div class="modal-overlay" id="kiosk-qr-modal" style="z-index: 1010;">
        <div class="modal-content glass-panel" style="max-width: 420px; text-align: center; padding: 28px 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <span id="kiosk-qr-badge" style="background: rgba(0, 122, 255, 0.2); color: #93c5fd; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase;">GCASH</span>
                <button class="close-modal" id="btn-close-kiosk-qr" style="position: static; font-size: 24px;">&times;</button>
            </div>
            <h3 id="kiosk-qr-title" style="margin-bottom: 6px; font-size: 22px; color: var(--text-light);">Scan to Pay</h3>
            <p id="kiosk-qr-subtitle" style="color: var(--text-muted); font-size: 13px; margin-bottom: 16px;">Open your e-wallet app and scan the QR code to complete payment.</p>
            
            <div class="qr-code-wrapper" id="kiosk-qr-wrapper">
                <img id="kiosk-qr-img" src="" alt="Payment QR Code">
            </div>

            <div style="margin: 12px 0 6px 0;">
                <span style="font-size: 13px; color: var(--text-muted);">Amount Due</span>
                <div id="kiosk-qr-amount" style="font-size: 28px; font-weight: bold; color: var(--accent);">₱0.00</div>
            </div>

            <div style="display: flex; align-items: center; justify-content: center; gap: 10px; color: #f59e0b; margin: 16px 0 20px 0; font-size: 13px; font-weight: 500;">
                <span class="payment-spinner"></span>
                <span id="kiosk-qr-status">Waiting for payment confirmation...</span>
            </div>

            <div style="display: flex; gap: 10px;">
                <a id="kiosk-qr-direct-link" href="#" target="_blank" class="payment-method-btn" style="flex: 1; text-align: center; font-size: 13px; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 6px; padding: 12px;">
                    <i data-lucide="external-link" style="width: 15px; height: 15px;"></i> Pay on Device
                </a>
                <button class="add-to-cart-btn" id="btn-cancel-kiosk-qr" style="flex: 1; background: #e74c3c; border-radius: 8px; padding: 12px; font-size: 14px;">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Logout/Admin Modal -->
    <div class="modal-overlay" id="logout-modal">
        <div class="modal-content glass-panel" style="max-width: 350px; text-align: center; padding: 30px;">
            <div style="font-size: 40px; margin-bottom: 16px; display: flex; justify-content: center;"><i data-lucide="lock" style="width: 40px; height: 40px;"></i></div>
            <h2 style="margin-bottom: 12px; color: var(--text-light); font-size: 24px;">Admin Access</h2>
            <p style="color: var(--text-muted); margin-bottom: 24px; font-size: 14px;">Enter PIN to unlock tablet or end session.</p>
            <input type="password" id="logout-pin-input" placeholder="Enter PIN" class="ref-input" style="margin-bottom: 24px; font-size: 24px; text-align: center; letter-spacing: 4px;">
            <div style="display: flex; gap: 12px; justify-content: center;">
                <button class="payment-method-btn" id="btn-logout-cancel" style="flex: 1;">Cancel</button>
                <button class="add-to-cart-btn" id="btn-logout-confirm" style="flex: 1;">Unlock</button>
            </div>
        </div>
    </div>

    <!-- Clear Cart Modal -->
    <div class="modal-overlay" id="clear-modal" style="z-index: 1005;">
        <div class="modal-content glass-panel" style="max-width: 400px; text-align: center; padding: 40px 30px;">
            <div style="font-size: 54px; margin-bottom: 16px; display: flex; justify-content: center;"><i data-lucide="trash-2" style="width: 54px; height: 54px;"></i></div>
            <h2 style="margin-bottom: 12px; color: var(--text-light); font-size: 28px;">Clear Cart?</h2>
            <p style="color: var(--text-muted); margin-bottom: 32px; font-size: 16px;">Are you sure you want to remove all items from your cart?</p>
            <div style="display: flex; gap: 16px; justify-content: center;">
                <button class="payment-method-btn" id="btn-clear-no" style="flex: 1; font-weight: 600;">Cancel</button>
                <button class="add-to-cart-btn" id="btn-clear-yes" style="flex: 1; background: #e74c3c; border-radius: 12px; padding: 16px;">Yes, Clear</button>
            </div>
        </div>
    </div>

    <!-- Receipt Modal -->
    <?php
    $receipt_button_text = 'Print & Continue';
    include __DIR__ . '/partials/receipt.php';
    ?>
    <div class="modal-overlay" id="clear-modal" style="z-index: 1005;">
        <div class="modal-content glass-panel" style="max-width: 400px; text-align: center; padding: 40px 30px;">
            <div style="font-size: 54px; margin-bottom: 16px; display: flex; justify-content: center;"><i data-lucide="trash-2" style="width: 54px; height: 54px;"></i></div>
            <h2 style="margin-bottom: 12px; color: var(--text-light); font-size: 28px;">Clear Cart?</h2>
            <p style="color: var(--text-muted); margin-bottom: 32px; font-size: 16px;">Are you sure you want to remove all items from your cart?</p>
            <div style="display: flex; gap: 16px; justify-content: center;">
                <button class="payment-method-btn" id="btn-clear-no" style="flex: 1; font-weight: 600;">Cancel</button>
                <button class="add-to-cart-btn" id="btn-clear-yes" style="flex: 1; background: #e74c3c; border-radius: 12px; padding: 16px;">Yes, Clear</button>
            </div>
        </div>
    </div>

    <script src="js/app.js?v=2"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
