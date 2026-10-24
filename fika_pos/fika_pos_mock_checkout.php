<?php
require_once __DIR__ . '/../app/bootstrap.php';

// 1. Constraint 5: Accessible ONLY when is_test === true AND a Cashier session exists
$isTest = Config::get('paymongo.is_test') === true;
if (!$isTest) {
    http_response_code(404);
    exit('404 Not Found');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = Auth::user();
$hasCashierSession = $user && Rbac::can('pos.access');
if (!$hasCashierSession) {
    http_response_code(404);
    exit('404 Not Found');
}

$orderId = (int)($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
$intentId = trim((string)($_GET['intent_id'] ?? $_POST['intent_id'] ?? ''));
$method = strtolower(trim((string)($_GET['method'] ?? $_POST['method'] ?? 'gcash')));

global $pdo;

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    exit('Order not found');
}

$amountCents = Money::toCentavos($order['final_amount'] ?? $order['total_amount']);
$amountFormatted = Money::toDecimal($amountCents);
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['authorize_mock_payment'])) {
    $webhookSecret = Config::get('paymongo.webhook_secret');
    if (!$webhookSecret) {
        $error = 'Webhook secret not configured in system.';
    } else {
        $payload = json_encode([
            'data' => [
                'id' => 'evt_mock_' . bin2hex(random_bytes(10)),
                'type' => 'event',
                'attributes' => [
                    'type' => 'payment.paid',
                    'livemode' => false,
                    'data' => [
                        'id' => $intentId ?: ('pay_mock_' . bin2hex(random_bytes(10))),
                        'type' => 'payment',
                        'attributes' => [
                            'amount' => $amountCents,
                            'fee' => (int)round($amountCents * 0.025),
                            'net_amount' => (int)round($amountCents * 0.975),
                            'currency' => 'PHP',
                            'status' => 'paid',
                            'source' => [
                                'id' => 'src_mock_' . bin2hex(random_bytes(8)),
                                'type' => $method
                            ],
                            'metadata' => [
                                'order_id' => (string)$orderId,
                                'shift_id' => (string)($order['pos_session_id'] ?: 1),
                                'method' => $method
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $ts = time();
        $hash = hash_hmac('sha256', "{$ts}.{$payload}", $webhookSecret);
        $sigHeader = "t={$ts},te={$hash}";

        // Dispatch to webhook internally via cURL
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $webhookUrl = $baseUrl . dirname($_SERVER['PHP_SELF']) . '/api/fika_pos_payment_webhook.php';

        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Paymongo-Signature: ' . $sigHeader
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200) {
            $message = "Payment of ₱{$amountFormatted} successfully authorized and marked as PAID!";
            // Refresh order status
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
        } else {
            $error = "Webhook call failed (HTTP {$code}): " . htmlspecialchars($res);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayMongo Test Checkout Simulation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex items-center justify-center p-6">
    <div class="max-w-md w-full bg-slate-800 border border-slate-700 rounded-3xl p-8 shadow-2xl space-y-6">
        <div class="flex items-center justify-between border-b border-slate-700/80 pb-4">
            <div class="flex items-center space-x-3">
                <span class="bg-blue-600 text-white text-xs font-black px-2.5 py-1 rounded-lg uppercase tracking-wider">Test Mode</span>
                <span class="text-sm font-bold text-slate-300 uppercase"><?= e($method) ?> Checkout</span>
            </div>
            <span class="text-xs text-slate-500 font-mono">STAFF SIMULATOR</span>
        </div>

        <div>
            <span class="text-xs text-slate-400 font-semibold uppercase block tracking-wider">Order Reference</span>
            <h2 class="text-2xl font-black text-white font-mono mt-1"><?= e($order['order_number']) ?></h2>
        </div>

        <div class="bg-slate-900/80 p-5 rounded-2xl border border-slate-700 flex justify-between items-center">
            <div>
                <span class="text-xs text-slate-400 font-semibold uppercase block">Amount Due</span>
                <span class="text-3xl font-black text-amber-500">₱<?= $amountFormatted ?></span>
            </div>
            <div class="text-right">
                <span class="text-xs text-slate-400 font-semibold uppercase block">Current Status</span>
                <span class="text-xs font-black px-2.5 py-1 rounded-full <?= $order['payment_status'] === 'PAID' ? 'bg-emerald-900/60 text-emerald-400' : 'bg-amber-900/60 text-amber-400' ?>">
                    <?= e($order['payment_status']) ?>
                </span>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="p-4 bg-emerald-950/80 border border-emerald-500 text-emerald-300 text-sm font-semibold rounded-2xl flex items-center gap-2">
                <i class="fa-solid fa-circle-check"></i> <?= e($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="p-4 bg-rose-950/80 border border-rose-500 text-rose-300 text-sm font-semibold rounded-2xl flex items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation"></i> <?= e($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($order['payment_status'] !== 'PAID'): ?>
            <form method="POST" action="">
                <input type="hidden" name="authorize_mock_payment" value="1">
                <button type="submit" class="w-full py-4 bg-blue-600 hover:bg-blue-500 text-white font-bold text-base rounded-2xl shadow-lg shadow-blue-600/30 transition active:scale-95">
                    Authorize Mock <?= strtoupper(e($method)) ?> Payment
                </button>
            </form>
        <?php else: ?>
            <div class="text-center py-2 text-emerald-400 font-bold text-sm">
                Order is already PAID. You may close this window.
            </div>
        <?php endif; ?>

        <div class="text-center">
            <button onclick="window.close()" class="text-xs text-slate-400 hover:text-slate-300 font-semibold">
                Close Simulator Window
            </button>
        </div>
    </div>
</body>
</html>
