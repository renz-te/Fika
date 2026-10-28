<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/core/Output.php';

Auth::requireLogin();
Rbac::require_permission('pos.access');

$user = Auth::user();
$branchId = $user['branch_id'] ?? 1;

global $pdo;

// 1. Fetch Kiosk Order Age Limit Setting (default 60 minutes)
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'kiosk_order_age_limit_minutes'");
$stmt->execute();
$ageLimitMinutes = (int)($stmt->fetchColumn() ?: 60);

// 2. Fetch Active Register Session
$activeSession = null;
if ($branchId) {
    $sessStmt = $pdo->prepare("SELECT * FROM cash_sessions WHERE branch_id = ? AND status = 'OPEN' ORDER BY id DESC LIMIT 1");
    $sessStmt->execute([$branchId]);
    $activeSession = $sessStmt->fetch();
    if ($activeSession) {
        $_SESSION['pos_session_id'] = $activeSession['id'];
    }
}

// 3. Fetch Pending Unpaid Orders (Newest First)
$orders = [];
if ($branchId) {
    $orderSql = "
        SELECT o.id, o.order_number, o.branch_id, o.total_amount, o.final_amount,
               o.order_status, o.payment_status, o.created_at, o.pos_session_id, o.cashier_id,
               TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) AS age_minutes,
               (SELECT GROUP_CONCAT(CONCAT(oi.quantity, 'x ', p.name) SEPARATOR ', ')
                FROM order_items oi
                JOIN products p ON p.id = oi.product_id
                WHERE oi.order_id = o.id) AS items_summary
        FROM orders o
        WHERE o.branch_id = ? 
          AND o.payment_status = 'UNPAID'
          AND o.order_status != 'CANCELLED'
        ORDER BY o.created_at DESC
    ";
    $orderStmt = $pdo->prepare($orderSql);
    $orderStmt->execute([$branchId]);
    $orders = $orderStmt->fetchAll();

    // 4. Fetch Recent Paid Orders (for receipt printing)
    $recentPaidSql = "
        SELECT o.id, o.order_number, o.branch_id, o.final_amount, o.order_status, o.payment_status, o.created_at,
               (SELECT GROUP_CONCAT(CONCAT(oi.quantity, 'x ', p.name) SEPARATOR ', ')
                FROM order_items oi
                JOIN products p ON p.id = oi.product_id
                WHERE oi.order_id = o.id) AS items_summary
        FROM orders o
        WHERE o.branch_id = ? 
          AND o.payment_status = 'PAID'
        ORDER BY o.created_at DESC
        LIMIT 6
    ";
    $recentPaidStmt = $pdo->prepare($recentPaidSql);
    $recentPaidStmt->execute([$branchId]);
    $recentPaidOrders = $recentPaidStmt->fetchAll();
}

$activeOrders = [];
$agedOrders = [];

foreach ($orders as $ord) {
    if ((int)$ord['age_minutes'] > $ageLimitMinutes) {
        $agedOrders[] = $ord;
    } else {
        $activeOrders[] = $ord;
    }
}

$csrfToken = Csrf::getToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashier Dashboard - Fika POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans antialiased min-h-screen flex">

    <?php require_once __DIR__ . '/../fika_hrms/includes/header.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto">
        <!-- Meta Tokens -->
        <input type="hidden" id="csrf-token" value="<?= e($csrfToken) ?>">
        <input type="hidden" id="has-active-session" value="<?= $activeSession ? '1' : '0' ?>">

        <!-- Top Navigation -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16 items-center">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-600 flex items-center justify-center text-white font-black text-xl shadow-sm">
                            <i class="fa-solid fa-cash-register text-base"></i>
                        </div>
                        <div>
                            <h1 class="font-bold text-lg text-slate-900 leading-tight">POS Cashier Dashboard</h1>
                            <p class="text-xs text-slate-500">Live order queue, cash drawers, and terminal reconciliation</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-6 text-sm">
                        <div class="text-right">
                            <span class="font-bold text-slate-900 block"><?= e($user['username'] ?? 'Cashier') ?></span>
                            <span class="text-xs text-slate-500">Branch #<?= e($branchId ?? 'HQ') ?></span>
                        </div>
                        <?php if ($activeSession): ?>
                            <div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 mr-2 animate-pulse"></span>
                                Register Shift Open (#<?= e($activeSession['id']) ?>)
                            </div>
                        <?php else: ?>
                            <div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-800">
                                <span class="w-2 h-2 rounded-full bg-rose-500 mr-2"></span>
                                Register Closed
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 flex-1 w-full space-y-6">
        
        <!-- Shift Warning if not open -->
        <?php if (!$activeSession): ?>
        <div class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-xl shadow-sm flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <svg class="w-6 h-6 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <p class="text-sm text-amber-800 font-medium">
                    No active cash register shift is open for this branch. Please open the register session before accepting cash payments.
                </p>
            </div>
            <button onclick="promptOpenRegister()" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-lg shadow transition">
                Open Register
            </button>
        </div>
        <?php endif; ?>

        <!-- Dashboard Header & Controls -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Pending Orders Queue</h1>
                <p class="text-xs text-slate-500 mt-1">
                    Live orders from Self-Service Kiosks and counters awaiting payment.
                    Configured age threshold: <span class="font-bold text-slate-700"><?= $ageLimitMinutes ?> minutes</span>.
                </p>
            </div>
            
            <!-- Controls / Age Filter Toggle -->
            <div class="flex items-center space-x-3">
                <a href="fika_pos_kiosk.php" class="inline-flex items-center space-x-2 px-4 py-2.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-95" title="Open POS Ordering Screen">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <span>Start POS / New Order</span>
                </a>
                <label class="inline-flex items-center cursor-pointer select-none bg-slate-50 hover:bg-slate-100 border border-slate-200 px-4 py-2.5 rounded-xl transition">
                    <input type="checkbox" id="toggle-old-orders" onchange="toggleAgedOrders()" class="w-4 h-4 text-amber-600 rounded border-slate-300 focus:ring-amber-500">
                    <span class="ml-2.5 text-xs font-bold text-slate-700">
                        Show Orders Older Than <?= $ageLimitMinutes ?> mins
                        <span class="ml-1 px-2 py-0.5 rounded-full text-[10px] font-black <?= count($agedOrders) > 0 ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-600' ?>">
                            <?= count($agedOrders) ?>
                        </span>
                    </span>
                </label>
                <button onclick="location.reload()" class="p-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl transition" title="Refresh Queue">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </button>
            </div>
        </div>

        <!-- Active Orders Grid -->
        <div class="space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500 flex items-center justify-between">
                <span>Active Orders (<?= count($activeOrders) ?>)</span>
                <span class="text-xs font-normal lowercase text-slate-400">newest first</span>
            </h2>

            <?php if (empty($activeOrders)): ?>
                <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-12 text-center text-slate-400">
                    <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="font-bold text-slate-600">No active pending orders</p>
                    <p class="text-xs text-slate-400 mt-1">New self-service kiosk and register orders will appear here automatically.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5" id="active-orders-container">
                    <?php foreach ($activeOrders as $ord): 
                        $finalDecimal = Money::toDecimal(Money::toCentavos($ord['final_amount']));
                    ?>
                    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-sm hover:shadow-md transition p-5 flex flex-col justify-between" id="order-card-<?= (int)$ord['id'] ?>">
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <span class="font-mono text-base font-black text-slate-900 tracking-tight">
                                    <?= e($ord['order_number']) ?>
                                </span>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200/60">
                                    <?= (int)$ord['age_minutes'] ?>m ago
                                </span>
                            </div>
                            <div class="text-xs text-slate-500 mb-4 line-clamp-2">
                                <span class="font-semibold text-slate-700">Items:</span> <?= e($ord['items_summary'] ?: 'No items') ?>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-100 flex items-center justify-between mt-2">
                            <div>
                                <span class="text-[10px] font-semibold text-slate-400 uppercase block">Total Due</span>
                                <span class="text-xl font-black text-slate-900">₱<?= $finalDecimal ?></span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button onclick="openDigitalPayModal(<?= (int)$ord['id'] ?>, '<?= e($ord['order_number']) ?>', '<?= $finalDecimal ?>')"
                                        class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-sm transition active:scale-95">
                                    Pay Digital
                                </button>
                                <button onclick="openPayModal(<?= (int)$ord['id'] ?>, '<?= e($ord['order_number']) ?>', '<?= $finalDecimal ?>')"
                                        class="px-3 py-2 bg-amber-700 hover:bg-amber-800 text-white font-bold text-xs rounded-xl shadow-sm transition active:scale-95">
                                    Pay Cash
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Aged / Old Orders Section (Hidden by default behind toggle) -->
        <div id="aged-orders-section" class="hidden space-y-4 pt-6 border-t border-slate-200">
            <h2 class="text-sm font-bold uppercase tracking-wider text-rose-600 flex items-center space-x-2">
                <span>Aged Orders (> <?= $ageLimitMinutes ?> mins old) (<?= count($agedOrders) ?>)</span>
                <span class="text-xs font-normal text-slate-400">(Preserved safely, not deleted)</span>
            </h2>

            <?php if (empty($agedOrders)): ?>
                <div class="bg-white rounded-2xl border border-slate-200 p-8 text-center text-slate-400 text-sm">
                    No aged unpaid orders found.
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <?php foreach ($agedOrders as $ord): 
                        $finalDecimal = Money::toDecimal(Money::toCentavos($ord['final_amount']));
                    ?>
                    <div class="bg-white rounded-2xl border border-rose-200 bg-rose-50/20 shadow-sm p-5 flex flex-col justify-between" id="order-card-<?= (int)$ord['id'] ?>">
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <span class="font-mono text-base font-black text-slate-900 tracking-tight">
                                    <?= e($ord['order_number']) ?>
                                </span>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                    <?= (int)$ord['age_minutes'] ?>m ago (Aged)
                                </span>
                            </div>
                            <div class="text-xs text-slate-500 mb-4 line-clamp-2">
                                <span class="font-semibold text-slate-700">Items:</span> <?= e($ord['items_summary'] ?: 'No items') ?>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-100 flex items-center justify-between mt-2">
                            <div>
                                <span class="text-[10px] font-semibold text-slate-400 uppercase block">Total Due</span>
                                <span class="text-xl font-black text-slate-900">₱<?= $finalDecimal ?></span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button onclick="openDigitalPayModal(<?= (int)$ord['id'] ?>, '<?= e($ord['order_number']) ?>', '<?= $finalDecimal ?>')"
                                        class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-sm transition active:scale-95">
                                    Pay Digital
                                </button>
                                <button onclick="openPayModal(<?= (int)$ord['id'] ?>, '<?= e($ord['order_number']) ?>', '<?= $finalDecimal ?>')"
                                        class="px-3 py-2 bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs rounded-xl shadow-sm transition active:scale-95">
                                    Pay Cash
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Completed Orders & Receipts Section -->
        <div class="space-y-4 pt-6 border-t border-slate-200">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 tracking-tight flex items-center space-x-2">
                        <span>Recent Completed Orders & Receipts</span>
                        <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-slate-200 text-slate-700"><?= count($recentPaidOrders) ?></span>
                    </h2>
                    <p class="text-xs text-slate-500">Print or reprint 58mm thermal receipts for paid branch orders.</p>
                </div>
            </div>

            <?php if (empty($recentPaidOrders)): ?>
                <div class="bg-white rounded-2xl border border-slate-200 p-8 text-center text-slate-400 text-sm">
                    No paid orders recorded yet.
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <?php foreach ($recentPaidOrders as $pord): 
                        $paidDecimal = Money::toDecimal(Money::toCentavos($pord['final_amount']));
                    ?>
                    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-sm hover:shadow-md transition p-5 flex flex-col justify-between" id="paid-order-card-<?= (int)$pord['id'] ?>">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-mono text-base font-black text-slate-900"><?= e($pord['order_number']) ?></span>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                                    PAID
                                </span>
                            </div>
                            <div class="text-xs text-slate-500 mb-3 line-clamp-1">
                                <?= e($pord['items_summary'] ?: 'Order items') ?>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-slate-100 flex items-center justify-between mt-2">
                            <div>
                                <span class="text-[10px] font-semibold text-slate-400 uppercase block">Total Paid</span>
                                <span class="text-lg font-black text-slate-900">₱<?= $paidDecimal ?></span>
                            </div>
                            <a href="fika_pos_receipt.php?order_id=<?= (int)$pord['id'] ?>" target="_blank"
                               class="px-3.5 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl shadow-sm transition inline-flex items-center space-x-1.5 active:scale-95">
                                <span><i class="fa-solid fa-print mr-1"></i> Print Receipt</span>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Cash Payment Modal -->
    <div id="payment-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-md w-full p-8 shadow-2xl space-y-6">
            <div class="flex justify-between items-center pb-4 border-b border-slate-100">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Cash Payment</h3>
                    <p class="text-xs text-slate-500 font-mono" id="modal-order-number">ORD-000000</p>
                </div>
                <button onclick="closePayModal()" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
            </div>

            <div class="bg-amber-50/60 rounded-2xl p-4 border border-amber-200/60 flex justify-between items-center">
                <span class="text-xs font-bold uppercase text-amber-900">Amount Due</span>
                <span class="text-2xl font-black text-amber-900" id="modal-amount-due">₱0.00</span>
            </div>

            <div class="space-y-2">
                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider">Amount Tendered</label>
                <div class="relative">
                    <span class="absolute left-4 top-3 text-slate-400 font-bold">₱</span>
                    <input type="number" step="0.01" id="tendered-input" oninput="calculateChange()" placeholder="0.00"
                           class="w-full pl-8 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-lg font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <!-- Quick Amount Buttons -->
                <div class="grid grid-cols-4 gap-2 pt-2" id="quick-amount-buttons"></div>
            </div>

            <div class="bg-slate-50 rounded-2xl p-4 border border-slate-200/80 flex justify-between items-center">
                <span class="text-xs font-bold uppercase text-slate-500">Change Due</span>
                <span class="text-xl font-black text-slate-900" id="modal-change-due">₱0.00</span>
            </div>

            <div id="payment-error" class="hidden text-xs font-semibold text-rose-600 bg-rose-50 p-3 rounded-xl border border-rose-200"></div>

            <button id="btn-confirm-payment" onclick="submitCashPayment()"
                    class="w-full py-4 bg-amber-800 hover:bg-amber-700 text-white font-bold text-base rounded-2xl shadow-lg shadow-amber-800/30 transition active:scale-95">
                Confirm & Pay
            </button>
        </div>
    </div>

    <!-- Digital Payment Modal -->
    <div id="digital-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-md w-full p-8 shadow-2xl space-y-6 text-center">
            <div class="flex justify-between items-center pb-4 border-b border-slate-100">
                <div class="text-left">
                    <h3 class="text-lg font-bold text-slate-900">Digital Payment</h3>
                    <p class="text-xs text-slate-500 font-mono" id="digital-order-number">ORD-000000</p>
                </div>
                <button onclick="closeDigitalModal()" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
            </div>

            <div class="bg-blue-50/60 rounded-2xl p-4 border border-blue-200/60 flex justify-between items-center text-left">
                <span class="text-xs font-bold uppercase text-blue-900">Total Due</span>
                <span class="text-2xl font-black text-blue-900" id="digital-amount-due">₱0.00</span>
            </div>

            <div id="digital-qr-wrapper" class="space-y-4">
                <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100 text-center">
                    <p class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2.5 flex items-center justify-center space-x-1.5">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                        <span>Customer Scan QR (GCash / Maya)</span>
                    </p>
                    <div class="flex justify-center">
                        <div id="cashier-qrcode" class="p-3 bg-white rounded-2xl border border-slate-200 shadow-sm flex items-center justify-center min-w-[210px] min-h-[210px]"></div>
                        <img id="digital-qr-img" src="" alt="Payment QR Code" class="hidden w-48 h-48 rounded-2xl border border-slate-200 shadow-sm p-2 bg-white">
                    </div>
                    <p class="text-[11px] text-slate-500 mt-2 font-medium">Turn screen toward customer to scan</p>
                </div>
                <div class="flex items-center justify-center space-x-2 text-xs font-bold text-amber-600 bg-amber-50 py-2.5 rounded-xl border border-amber-200">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-ping"></span>
                    <span>Waiting for customer payment confirmation...</span>
                </div>
                <div class="pt-1 flex justify-center">
                    <a id="digital-checkout-link" href="#" target="_blank" class="text-xs font-bold text-blue-600 hover:text-blue-800 underline">
                        Open Customer Checkout Link &rarr;
                    </a>
                </div>
            </div>

            <div id="digital-paid-banner" class="hidden bg-emerald-50 border border-emerald-200 rounded-2xl p-4 text-emerald-800 space-y-3">
                <div class="font-black text-lg text-emerald-700"><i class="fa-solid fa-circle-check mr-1.5"></i> PAYMENT CONFIRMED!</div>
                <p class="text-xs text-emerald-600">The order has been marked as PAID via PayMongo.</p>
                <button onclick="printReceiptForOrder(selectedOrderId)"
                        class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl transition text-xs shadow-sm">
                    <i class="fa-solid fa-print mr-1.5"></i> Print Thermal Receipt
                </button>
            </div>

            <button onclick="closeDigitalModal()" class="w-full py-3 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl transition">
                Close
            </button>
        </div>
    </div>

    <script>
        let selectedOrderId = null;
        let selectedOrderDue = 0;
        let digitalPollTimer = null;
        const CSRF_TOKEN = document.getElementById('csrf-token').value;
        const HAS_ACTIVE_SESSION = document.getElementById('has-active-session').value === '1';

        async function openDigitalPayModal(orderId, orderNumber, amountDueStr) {
            selectedOrderId = orderId;
            selectedOrderDue = parseFloat(amountDueStr);

            document.getElementById('digital-order-number').innerText = orderNumber;
            document.getElementById('digital-amount-due').innerText = '₱' + selectedOrderDue.toFixed(2);
            document.getElementById('digital-paid-banner').classList.add('hidden');
            document.getElementById('digital-qr-wrapper').classList.remove('hidden');

            try {
                const res = await fetch('api/fika_pos_payment_create.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        order_id: orderId,
                        method: 'gcash'
                    })
                });

                const data = await res.json();
                if (!res.ok) {
                    throw new Error(data.error || 'Failed to create digital checkout.');
                }

                // Render dynamic QR Code using qrcode.js
                const qrContainer = document.getElementById('cashier-qrcode');
                qrContainer.innerHTML = '';
                if (typeof QRCode !== 'undefined') {
                    new QRCode(qrContainer, {
                        text: data.checkout_url,
                        width: 200,
                        height: 200,
                        colorDark: "#0f172a",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.M
                    });
                    document.getElementById('digital-qr-img').classList.add('hidden');
                    qrContainer.classList.remove('hidden');
                } else if (data.qr_code_url) {
                    document.getElementById('digital-qr-img').src = data.qr_code_url;
                    document.getElementById('digital-qr-img').classList.remove('hidden');
                    qrContainer.classList.add('hidden');
                }

                document.getElementById('digital-checkout-link').href = data.checkout_url;
                document.getElementById('digital-modal').classList.remove('hidden');

                // Start polling payment status
                startDigitalStatusPolling(orderId);

            } catch (e) {
                alert('Digital Payment Error: ' + e.message);
            }
        }

        function closeDigitalModal() {
            if (digitalPollTimer) clearInterval(digitalPollTimer);
            document.getElementById('digital-modal').classList.add('hidden');
            selectedOrderId = null;
        }

        function startDigitalStatusPolling(orderId) {
            if (digitalPollTimer) clearInterval(digitalPollTimer);
            digitalPollTimer = setInterval(async () => {
                try {
                    const res = await fetch(`api/fika_pos_payment_status.php?order_id=${orderId}`);
                    if (res.ok) {
                        const data = await res.json();
                        if (data.is_paid) {
                            clearInterval(digitalPollTimer);
                            document.getElementById('digital-qr-wrapper').classList.add('hidden');
                            document.getElementById('digital-paid-banner').classList.remove('hidden');
                            setTimeout(() => {
                                closeDigitalModal();
                                location.reload();
                            }, 2500);
                        }
                    }
                } catch (e) {}
            }, 3000);
        }

        function toggleAgedOrders() {
            const isChecked = document.getElementById('toggle-old-orders').checked;
            const agedSection = document.getElementById('aged-orders-section');
            if (isChecked) {
                agedSection.classList.remove('hidden');
            } else {
                agedSection.classList.add('hidden');
            }
        }

        function openPayModal(orderId, orderNumber, amountDueStr) {
            if (!HAS_ACTIVE_SESSION) {
                alert('Notice: No open cash register shift session. Please open your register session first.');
            }

            selectedOrderId = orderId;
            selectedOrderDue = parseFloat(amountDueStr);

            document.getElementById('modal-order-number').innerText = orderNumber;
            document.getElementById('modal-amount-due').innerText = '₱' + selectedOrderDue.toFixed(2);
            document.getElementById('tendered-input').value = selectedOrderDue.toFixed(2);
            document.getElementById('payment-error').classList.add('hidden');
            
            // Build quick amount buttons
            buildQuickButtons(selectedOrderDue);
            calculateChange();

            document.getElementById('payment-modal').classList.remove('hidden');
        }

        function closePayModal() {
            document.getElementById('payment-modal').classList.add('hidden');
            selectedOrderId = null;
        }

        function buildQuickButtons(due) {
            const container = document.getElementById('quick-amount-buttons');
            const round50 = Math.ceil(due / 50) * 50;
            const round100 = Math.ceil(due / 100) * 100;
            const round500 = Math.ceil(due / 500) * 500;

            const amounts = Array.from(new Set([due, round50, round100, round500])).sort((a, b) => a - b);
            let html = '';
            amounts.slice(0, 4).forEach(amt => {
                html += `<button type="button" onclick="setTendered(${amt})" class="py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-700 hover:bg-slate-100 transition">₱${amt.toFixed(2)}</button>`;
            });
            container.innerHTML = html;
        }

        function setTendered(amt) {
            document.getElementById('tendered-input').value = amt.toFixed(2);
            calculateChange();
        }

        function calculateChange() {
            const tendered = parseFloat(document.getElementById('tendered-input').value) || 0;
            const change = tendered - selectedOrderDue;
            const changeEl = document.getElementById('modal-change-due');
            if (change >= 0) {
                changeEl.innerText = '₱' + change.toFixed(2);
                changeEl.className = 'text-xl font-black text-emerald-600';
            } else {
                changeEl.innerText = 'Insufficient';
                changeEl.className = 'text-xl font-black text-rose-600';
            }
        }

        async function submitCashPayment() {
            const tendered = parseFloat(document.getElementById('tendered-input').value) || 0;
            if (tendered < selectedOrderDue) {
                showPayError('Amount tendered is less than amount due.');
                return;
            }

            const btn = document.getElementById('btn-confirm-payment');
            btn.disabled = true;
            btn.innerText = 'Processing...';

            try {
                const res = await fetch('api/fika_pos_order_pay_cash.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        order_id: selectedOrderId,
                        amount_paid: tendered,
                        idempotency_key: 'PAY-' + selectedOrderId + '-' + Date.now()
                    })
                });

                const data = await res.json();
                if (!res.ok) {
                    throw new Error(data.error || 'Payment failed.');
                }

                const paidOrderId = selectedOrderId;
                closePayModal();
                if (confirm(`Payment successful! Change: ₱${data.change}\n\nWould you like to print the receipt now?`)) {
                    window.open(`fika_pos_receipt.php?order_id=${paidOrderId}`, '_blank');
                }
                location.reload();
            } catch (err) {
                showPayError(err.message);
                btn.disabled = false;
                btn.innerText = 'Confirm & Pay';
            }
        }

        function printReceiptForOrder(orderId) {
            if (orderId) {
                window.open(`fika_pos_receipt.php?order_id=${orderId}`, '_blank');
            }
        }

        function showPayError(msg) {
            const errEl = document.getElementById('payment-error');
            errEl.innerText = msg;
            errEl.classList.remove('hidden');
        }

        function promptOpenRegister() {
            const float = prompt('Enter starting cash float (₱):', '1000.00');
            if (!float) return;

            const form = new FormData();
            form.append('starting_cash', float);
            form.append('csrf_token', CSRF_TOKEN);

            fetch('api/fika_pos_register_open.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': CSRF_TOKEN },
                body: form
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    alert('Register opened successfully.');
                    location.reload();
                } else {
                    alert('Error: ' + (d.error || 'Failed to open register'));
                }
            })
            .catch(e => alert('Error opening register: ' + e.message));
        }
    </script>
    </div>
</body>
</html>
