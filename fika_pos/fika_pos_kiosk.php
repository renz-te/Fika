<?php
require_once __DIR__ . '/../app/bootstrap.php';

// Device Authentication
$deviceToken = $_GET['device_token'] ?? $_COOKIE['device_token'] ?? null;
if (!empty($_GET['device_token'])) {
    setcookie('device_token', $_GET['device_token'], time() + (86400 * 365), '/');
}

$device = Device::getDevice($deviceToken);

global $pdo;

// If device not authenticated, display device setup pairing screen
if (!$device) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Fika Kiosk Setup</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
        <style>body { font-family: 'Inter', sans-serif; }</style>
    </head>
    <body class="bg-slate-900 text-slate-100 min-h-screen flex items-center justify-center p-6">
        <div class="max-w-md w-full bg-slate-800 rounded-2xl shadow-2xl p-8 border border-slate-700 text-center">
            <div class="inline-flex p-4 bg-amber-900/40 text-amber-500 rounded-2xl mb-6">
                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-black text-amber-500 tracking-wider uppercase mb-2">Fika Kiosk Terminal</h1>
            <p class="text-slate-400 text-sm mb-6">This terminal is not yet paired or authenticated. Enter the secure Device Token configured in admin settings.</p>
            
            <form method="GET" action="" class="space-y-4">
                <div class="text-left">
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Device Token</label>
                    <input type="password" name="device_token" placeholder="Paste device token..." required
                           class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                </div>
                <button type="submit" class="w-full bg-amber-600 hover:bg-amber-500 text-white font-bold py-3.5 px-6 rounded-xl transition duration-200 shadow-lg shadow-amber-600/30">
                    Pair & Launch Kiosk
                </button>
            </form>
            <div class="mt-6 text-xs text-slate-500">
                Self-service kiosks require a valid token linked to an active branch device.
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Device is verified - Branch is strictly derived from registered device
$branchId = (int)$device['branch_id'];

$branchStmt = $pdo->prepare("SELECT name FROM branches WHERE id = ?");
$branchStmt->execute([$branchId]);
$branchName = $branchStmt->fetchColumn() ?: "Branch #{$branchId}";

// Fetch categories
$catStmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
$categories = $catStmt->fetchAll();

// Fetch active products available for this branch
$prodStmt = $pdo->prepare("
    SELECT p.id, p.name, p.category, p.price, p.price_cents, p.image_url, p.description,
           bp.is_active AS branch_active
    FROM products p
    LEFT JOIN branch_products bp ON bp.product_id = p.id AND bp.branch_id = ?
    WHERE p.deleted_at IS NULL 
      AND p.is_active = 1 
      AND (bp.is_active = 1 OR bp.is_active IS NULL)
    ORDER BY p.category ASC, p.name ASC
");
$prodStmt->execute([$branchId]);
$products = $prodStmt->fetchAll();

$groupedProducts = [];
foreach ($products as $p) {
    $cat = $p['category'] ?: 'Other';
    $groupedProducts[$cat][] = $p;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Self-Service Kiosk - Fika Coffee</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; -webkit-tap-highlight-color: transparent; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 h-screen flex flex-col overflow-hidden select-none">

    <!-- Top Banner -->
    <header class="bg-amber-950 text-white px-8 py-5 flex items-center justify-between shadow-md shrink-0">
        <div class="flex items-center space-x-4">
            <div class="bg-amber-500 text-amber-950 font-black text-2xl px-3 py-1 rounded-xl tracking-tighter">FIKA</div>
            <div>
                <h1 class="text-xl font-bold tracking-tight">Self-Service Ordering</h1>
                <p class="text-xs text-amber-300 font-medium"><?= e($branchName) ?> &bull; <?= e($device['device_name'] ?? 'Kiosk') ?></p>
            </div>
        </div>
        <div class="flex items-center space-x-6 text-sm">
            <div id="clock-display" class="font-mono text-amber-200 font-semibold tracking-wider">--:--</div>
            <button onclick="confirmReset()" class="text-xs bg-amber-900/60 hover:bg-amber-900 text-amber-300 px-3 py-1.5 rounded-lg transition">
                Reset
            </button>
        </div>
    </header>

    <!-- Main Workspace (Catalog + Cart Drawer) -->
    <div class="flex-1 flex overflow-hidden">
        
        <!-- Left: Product Catalog -->
        <main class="flex-1 flex flex-col bg-slate-50 overflow-hidden">
            <!-- Category Tabs -->
            <div class="bg-white border-b border-slate-200 px-6 py-3 flex space-x-3 overflow-x-auto hide-scrollbar shrink-0">
                <button onclick="filterCategory('ALL')" id="tab-ALL"
                        class="cat-tab px-5 py-2.5 rounded-full text-sm font-bold transition-all bg-amber-800 text-white shadow-sm">
                    All Items
                </button>
                <?php foreach ($categories as $cat): ?>
                <button onclick="filterCategory('<?= e($cat['name']) ?>')" id="tab-<?= e($cat['name']) ?>"
                        class="cat-tab px-5 py-2.5 rounded-full text-sm font-bold transition-all bg-slate-100 hover:bg-slate-200 text-slate-700">
                    <?= e($cat['name']) ?>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- Product Grid -->
            <div class="flex-1 overflow-y-auto p-6">
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5" id="product-grid">
                    <?php foreach ($products as $prod): 
                        $priceCents = (int)($prod['price_cents'] ?? Money::toCentavos($prod['price']));
                        $priceFormatted = Money::toDecimal($priceCents);
                    ?>
                    <div class="product-card bg-white rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition-all p-4 flex flex-col justify-between cursor-pointer active:scale-95"
                         data-category="<?= e($prod['category']) ?>"
                         onclick="addToCart(<?= (int)$prod['id'] ?>, '<?= e(addslashes($prod['name'])) ?>', <?= $priceCents ?>)">
                        
                        <div class="aspect-square bg-amber-50 rounded-xl mb-3 flex items-center justify-center overflow-hidden">
                            <?php if (!empty($prod['image_url'])): ?>
                                <img src="<?= e($prod['image_url']) ?>" alt="<?= e($prod['name']) ?>" class="object-cover w-full h-full">
                            <?php else: ?>
                                <span class="text-3xl font-black text-amber-800/30">FIKA</span>
                            <?php endif; ?>
                        </div>

                        <div>
                            <span class="text-[11px] font-semibold text-amber-800 uppercase tracking-wider"><?= e($prod['category']) ?></span>
                            <h3 class="text-base font-bold text-slate-900 leading-snug line-clamp-2 mt-0.5"><?= e($prod['name']) ?></h3>
                        </div>

                        <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-lg font-black text-slate-900">₱<?= $priceFormatted ?></span>
                            <span class="w-8 h-8 rounded-full bg-amber-100 text-amber-900 flex items-center justify-center font-bold text-lg hover:bg-amber-200">+</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>

        <!-- Right: Cart & Checkout Drawer -->
        <aside class="w-96 bg-white border-l border-slate-200 flex flex-col shadow-lg shrink-0">
            <div class="px-6 py-5 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Your Order</h2>
                    <p class="text-xs text-slate-500"><span id="cart-item-count">0</span> items</p>
                </div>
                <button onclick="clearCart()" class="text-xs text-rose-600 hover:text-rose-700 font-semibold px-2 py-1 rounded">
                    Clear All
                </button>
            </div>

            <!-- Cart Items List -->
            <div class="flex-1 overflow-y-auto p-6 space-y-4" id="cart-items">
                <div id="cart-empty-state" class="h-full flex flex-col items-center justify-center text-center text-slate-400 py-12">
                    <svg class="w-16 h-16 text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                    <p class="text-sm font-semibold">Your cart is empty</p>
                    <p class="text-xs text-slate-400 mt-1">Tap any item on the menu to add</p>
                </div>
            </div>

            <!-- Cart Footer -->
            <div class="p-6 bg-slate-50 border-t border-slate-200 space-y-4">
                <div class="flex items-center justify-between text-base font-medium text-slate-600">
                    <span>Subtotal</span>
                    <span id="cart-subtotal" class="font-bold text-slate-900">₱0.00</span>
                </div>
                <div class="flex items-center justify-between text-xl font-black text-slate-900 pt-2 border-t border-slate-200">
                    <span>Total</span>
                    <span id="cart-total" class="text-amber-900">₱0.00</span>
                </div>

                <button id="btn-place-order" onclick="submitKioskOrder()" disabled
                        class="w-full py-4 bg-amber-800 disabled:bg-slate-300 text-white font-bold text-lg rounded-2xl shadow-lg shadow-amber-800/30 transition-all flex items-center justify-center space-x-2">
                    <span>Place Order</span>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                    </svg>
                </button>
            </div>
        </aside>
    </div>

    <!-- Modal: Order In Progress / Pay At Counter -->
    <div id="order-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-md w-full p-8 shadow-2xl text-center space-y-6">
            <div class="w-20 h-20 bg-amber-100 text-amber-800 rounded-full flex items-center justify-center mx-auto shadow-inner">
                <svg class="w-10 h-10 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>

            <div>
                <span class="text-xs font-bold text-amber-700 bg-amber-50 border border-amber-200 px-3 py-1 rounded-full uppercase tracking-wider">
                    Queue Ticket Generated
                </span>
                <h2 class="text-3xl font-black text-slate-900 mt-3 font-mono tracking-wider" id="modal-order-number">ORD-000000</h2>
                <p class="text-sm text-slate-500 mt-2">Please proceed to the cashier counter to complete your payment.</p>
            </div>

            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 flex justify-between items-center text-left">
                <div>
                    <span class="text-xs text-slate-400 font-semibold block uppercase">Amount Due</span>
                    <span class="text-2xl font-black text-amber-900" id="modal-total-amount">₱0.00</span>
                </div>
                <div class="flex items-center space-x-2 text-xs font-bold text-amber-600 bg-amber-50 px-3 py-1.5 rounded-xl border border-amber-200/60">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-ping"></span>
                    <span>Awaiting Payment</span>
                </div>
            </div>

            <!-- Digital Payment Button & QR Display -->
            <div id="digital-pay-container" class="space-y-3 pt-2">
                <button id="btn-pay-gcash" onclick="initiateDigitalPayment('gcash')" 
                        class="w-full py-3.5 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-2xl shadow-lg shadow-blue-600/30 transition flex items-center justify-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    <span>Pay with GCash / E-Wallet</span>
                </button>

                <div id="qr-display-box" class="hidden bg-slate-50 border border-slate-200 rounded-2xl p-4 text-center space-y-3">
                    <p class="text-xs font-bold text-slate-700 uppercase tracking-wider">Scan QR Code to Pay</p>
                    <div class="flex justify-center">
                        <img id="qr-image" src="" alt="GCash QR Code" class="w-48 h-48 rounded-xl border border-slate-200 shadow-sm bg-white p-2">
                    </div>
                    <a id="qr-direct-link" href="#" target="_blank" class="inline-block text-xs text-blue-600 hover:text-blue-700 font-bold underline">
                        Open Checkout on this device &rarr;
                    </a>
                </div>
            </div>

            <div id="paid-badge" class="hidden bg-emerald-50 border border-emerald-200 rounded-2xl p-4 text-emerald-800 space-y-1">
                <div class="font-black text-lg text-emerald-700"><i class="fa-solid fa-circle-check mr-1.5"></i> PAYMENT RECEIVED!</div>
                <p class="text-xs text-emerald-600">Your order has been sent to the kitchen and is now being prepared.</p>
            </div>

            <div class="pt-2">
                <button onclick="resetKioskSession()" class="w-full py-3 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl transition">
                    Start New Order
                </button>
            </div>
        </div>
    </div>

    <script>
        const DEVICE_TOKEN = <?= json_encode($device['device_token']) ?>;
        let cart = {}; // { prodId: { id, name, priceCents, qty } }
        let currentOrderId = null;
        let currentKioskToken = null;
        let pollTimer = null;

        // Clock display
        function updateClock() {
            const now = new Date();
            document.getElementById('clock-display').innerText = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Category Filter
        function filterCategory(cat) {
            document.querySelectorAll('.cat-tab').forEach(b => {
                b.className = 'cat-tab px-5 py-2.5 rounded-full text-sm font-bold transition-all bg-slate-100 hover:bg-slate-200 text-slate-700';
            });
            const activeTab = document.getElementById('tab-' + cat);
            if (activeTab) {
                activeTab.className = 'cat-tab px-5 py-2.5 rounded-full text-sm font-bold transition-all bg-amber-800 text-white shadow-sm';
            }

            document.querySelectorAll('.product-card').forEach(card => {
                if (cat === 'ALL' || card.dataset.category === cat) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Cart Actions
        function addToCart(id, name, priceCents) {
            if (cart[id]) {
                cart[id].qty++;
            } else {
                cart[id] = { id, name, priceCents, qty: 1 };
            }
            renderCart();
        }

        function updateQty(id, delta) {
            if (!cart[id]) return;
            cart[id].qty += delta;
            if (cart[id].qty <= 0) {
                delete cart[id];
            }
            renderCart();
        }

        function clearCart() {
            cart = {};
            renderCart();
        }

        function renderCart() {
            const container = document.getElementById('cart-items');
            const items = Object.values(cart);
            let totalCents = 0;
            let totalCount = 0;

            if (items.length === 0) {
                container.innerHTML = `
                    <div id="cart-empty-state" class="h-full flex flex-col items-center justify-center text-center text-slate-400 py-12">
                        <svg class="w-16 h-16 text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                        <p class="text-sm font-semibold">Your cart is empty</p>
                        <p class="text-xs text-slate-400 mt-1">Tap any item on the menu to add</p>
                    </div>
                `;
                document.getElementById('btn-place-order').disabled = true;
            } else {
                let html = '';
                items.forEach(item => {
                    const lineCents = item.priceCents * item.qty;
                    totalCents += lineCents;
                    totalCount += item.qty;

                    html += `
                        <div class="flex items-center justify-between p-3.5 bg-slate-50 rounded-xl border border-slate-200/70">
                            <div class="flex-1 mr-3">
                                <h4 class="text-sm font-bold text-slate-900 leading-snug">${escapeHtml(item.name)}</h4>
                                <span class="text-xs text-amber-900 font-semibold">₱${(item.priceCents / 100).toFixed(2)}</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button onclick="updateQty(${item.id}, -1)" class="w-7 h-7 rounded-lg bg-white border border-slate-200 text-slate-700 font-bold hover:bg-slate-100 flex items-center justify-center text-sm">-</button>
                                <span class="w-5 text-center text-sm font-bold text-slate-900">${item.qty}</span>
                                <button onclick="updateQty(${item.id}, 1)" class="w-7 h-7 rounded-lg bg-white border border-slate-200 text-slate-700 font-bold hover:bg-slate-100 flex items-center justify-center text-sm">+</button>
                            </div>
                        </div>
                    `;
                });
                container.innerHTML = html;
                document.getElementById('btn-place-order').disabled = false;
            }

            document.getElementById('cart-item-count').innerText = totalCount;
            const formattedTotal = '₱' + (totalCents / 100).toFixed(2);
            document.getElementById('cart-subtotal').innerText = formattedTotal;
            document.getElementById('cart-total').innerText = formattedTotal;
        }

        function escapeHtml(text) {
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        // Submit Kiosk Order
        async function submitKioskOrder() {
            const items = Object.values(cart).map(i => ({ id: i.id, qty: i.qty }));
            if (items.length === 0) return;

            const btn = document.getElementById('btn-place-order');
            btn.disabled = true;
            btn.innerHTML = `<span class="animate-pulse">Placing Order...</span>`;

            try {
                const res = await fetch('api/fika_pos_kiosk_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Device-Token': DEVICE_TOKEN
                    },
                    body: JSON.stringify({ items: items })
                });

                const data = await res.json();
                if (!res.ok) {
                    throw new Error(data.error || 'Failed to place order.');
                }

                currentOrderId = data.order_id;
                currentKioskToken = data.kiosk_token;

                // Open modal
                document.getElementById('modal-order-number').innerText = data.order_number;
                document.getElementById('modal-total-amount').innerText = '₱' + parseFloat(data.total_amount).toFixed(2);
                document.getElementById('paid-badge').classList.add('hidden');
                document.getElementById('order-modal').classList.remove('hidden');

                clearCart();

                // Start polling status
                startStatusPolling();

            } catch (err) {
                alert('Order error: ' + err.message);
                btn.disabled = false;
                renderCart();
            }
        }

        async function initiateDigitalPayment(method) {
            if (!currentOrderId || !currentKioskToken) return;

            const gcashBtn = document.getElementById('btn-pay-gcash');
            gcashBtn.disabled = true;
            gcashBtn.innerHTML = '<span class="animate-pulse">Generating payment link...</span>';

            try {
                const res = await fetch('api/fika_pos_payment_create.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Kiosk-Token': currentKioskToken
                    },
                    body: JSON.stringify({
                        order_id: currentOrderId,
                        method: method
                    })
                });

                const data = await res.json();
                if (!res.ok) {
                    throw new Error(data.error || 'Failed to initiate digital payment.');
                }

                // Show QR code
                document.getElementById('qr-image').src = data.qr_code_url;
                document.getElementById('qr-direct-link').href = data.checkout_url;
                document.getElementById('qr-display-box').classList.remove('hidden');
                gcashBtn.classList.add('hidden');

            } catch (err) {
                alert('Payment gateway error: ' + err.message);
                gcashBtn.disabled = false;
                gcashBtn.innerHTML = `<span>Pay with GCash / E-Wallet</span>`;
            }
        }

        function startStatusPolling() {
            if (pollTimer) clearInterval(pollTimer);
            pollTimer = setInterval(async () => {
                if (!currentOrderId || !currentKioskToken) return;

                try {
                    const res = await fetch(`api/fika_pos_kiosk_status.php?order_id=${currentOrderId}`, {
                        headers: {
                            'X-Kiosk-Token': currentKioskToken,
                            'X-Device-Token': DEVICE_TOKEN
                        }
                    });

                    if (res.ok) {
                        const data = await res.json();
                        if (data.is_paid) {
                            clearInterval(pollTimer);
                            document.getElementById('paid-badge').classList.remove('hidden');
                            setTimeout(() => {
                                resetKioskSession();
                            }, 5000);
                        }
                    }
                } catch (e) {
                    // silent polling retry
                }
            }, 3000);
        }

        function resetKioskSession() {
            if (pollTimer) clearInterval(pollTimer);
            currentOrderId = null;
            currentKioskToken = null;
            document.getElementById('order-modal').classList.add('hidden');
            clearCart();
        }

        function confirmReset() {
            if (confirm('Start a fresh session?')) {
                resetKioskSession();
            }
        }
    </script>
</body>
</html>
