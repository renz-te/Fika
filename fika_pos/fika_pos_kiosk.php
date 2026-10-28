<?php
require_once __DIR__ . '/../app/bootstrap.php';

// Device Authentication
$deviceToken = $_GET['device_token'] ?? $_COOKIE['device_token'] ?? null;
if (!empty($_GET['device_token'])) {
    setcookie('device_token', $_GET['device_token'], time() + (86400 * 365), '/');
}

$device = Device::getDevice($deviceToken);

global $pdo;

// Fallback: If device token not provided, try resolving from authenticated session user
if (!$device && Auth::check()) {
    $sessUser = Auth::user();
    if (!empty($sessUser['id'])) {
        $devStmt = $pdo->prepare("SELECT id, branch_id, device_name, device_token, status FROM devices WHERE user_id = ? AND status = 'Active' AND deleted_at IS NULL LIMIT 1");
        $devStmt->execute([$sessUser['id']]);
        $device = $devStmt->fetch();
        if ($device && !empty($device['device_token'])) {
            setcookie('device_token', $device['device_token'], time() + (86400 * 365), '/');
        }
    }
}

// Fetch device exit pin for hidden manager logout trigger
$kioskBranchId = $device ? (int)$device['branch_id'] : (Auth::check() ? (Auth::user()['branch_id'] ?? null) : null);
$stmtPin = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'device_exit_pin' AND (branch_id = ? OR branch_id IS NULL) ORDER BY branch_id DESC LIMIT 1");
$stmtPin->execute([$kioskBranchId]);
$deviceExitPin = (string)($stmtPin->fetchColumn() ?: '123456');

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
            <div id="setup-kiosk-icon" class="inline-flex p-4 bg-amber-900/40 text-amber-500 rounded-2xl mb-6 cursor-pointer select-none">
                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <h1 id="setup-kiosk-title" class="text-2xl font-black text-amber-500 tracking-wider uppercase mb-2 cursor-pointer select-none">Fika Kiosk Terminal</h1>
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
        <script>
            const DEVICE_EXIT_PIN = <?= json_encode($deviceExitPin) ?>;
            function triggerSetupExit() {
                const code = prompt("Enter Exit Code:");
                if (code === null || code.trim() === '') return;
                if (code.trim() === DEVICE_EXIT_PIN) {
                    window.location.href = '../login.php?logout=1';
                } else {
                    alert("Invalid Code");
                }
            }
            document.getElementById('setup-kiosk-icon')?.addEventListener('click', triggerSetupExit);
            document.getElementById('setup-kiosk-title')?.addEventListener('click', triggerSetupExit);
        </script>
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
    SELECT p.id, p.name, p.category, p.price, p.price_cents, p.image_path,
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
foreach ($products as &$p) {
    $p['image_url'] = $p['image_path'] ?? null;
    $p['description'] = null;
    $cat = $p['category'] ?: 'Other';
    $groupedProducts[$cat][] = $p;
}
unset($p);

// Fetch active modifiers for customer kiosk modifier modal
$modStmt = $pdo->query("SELECT id, name, price FROM modifiers WHERE is_active = 1 AND deleted_at IS NULL ORDER BY id ASC");
$rawModifiers = $modStmt->fetchAll();

$modifiersCatalog = [
    'cold_sizes' => [],
    'hot_sizes' => [],
    'addons' => [],
    'sugar' => [],
    'ice' => []
];

foreach ($rawModifiers as $m) {
    $mName = (string)$m['name'];
    $mPrice = (float)$m['price'];
    $mPriceCents = Money::toCentavos($mPrice);
    $entry = [
        'id' => (int)$m['id'],
        'name' => $mName,
        'price' => $mPrice,
        'priceCents' => $mPriceCents
    ];

    if ($mName === 'Size: M (Cold)' || $mName === 'Size: L (Cold)') {
        $entry['label'] = ($mName === 'Size: M (Cold)') ? 'Medium (16oz)' : 'Large (22oz)';
        $modifiersCatalog['cold_sizes'][] = $entry;
    } elseif ($mName === 'Size: M (Hot)' || $mName === 'Size: L (Hot)') {
        $entry['label'] = ($mName === 'Size: M (Hot)') ? 'Medium (12oz)' : 'Large (16oz)';
        $modifiersCatalog['hot_sizes'][] = $entry;
    } elseif (in_array($mName, ['Extra Espresso Shot', 'Pearls', 'Whipped Cream', 'Caramel Drizzle'], true)) {
        $modifiersCatalog['addons'][] = $entry;
    } elseif (in_array($mName, ['Less Sugar', 'Extra Sugar'], true)) {
        $modifiersCatalog['sugar'][] = $entry;
    } elseif (in_array($mName, ['Less Ice', 'No Ice', 'Extra Ice'], true)) {
        $modifiersCatalog['ice'][] = $entry;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Self-Service Kiosk - Fika Coffee</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
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
        <div class="flex items-center space-x-4 text-sm">
            <?php 
            $currentUserRoleId = (int)(Auth::user()['role_id'] ?? 0);
            if (Auth::check() && in_array($currentUserRoleId, [1, 9], true)): 
            ?>
            <a href="fika_pos_dashboard.php" class="text-xs bg-amber-900/60 hover:bg-amber-900 text-amber-200 border border-amber-800/60 px-3 py-1.5 rounded-lg transition inline-flex items-center gap-1.5" title="Return to Cashier Dashboard">
                &larr; <span>Dashboard</span>
            </a>
            <?php endif; ?>
            <div id="kiosk-order-type" class="text-xs bg-amber-900/40 hover:bg-amber-900/60 text-amber-300 border border-amber-800/60 px-3 py-1.5 rounded-lg font-semibold tracking-wide cursor-pointer select-none transition">
                Order Type: <span class="text-amber-100 font-bold">Dine In</span>
            </div>
            <div id="kiosk-table-num" class="text-xs bg-amber-900/40 hover:bg-amber-900/60 text-amber-300 border border-amber-800/60 px-3 py-1.5 rounded-lg font-semibold tracking-wide cursor-pointer select-none transition">
                Table #<span class="text-amber-100 font-bold">1</span>
            </div>
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
                         onclick="openModifierModal(<?= (int)$prod['id'] ?>, '<?= e(addslashes($prod['name'])) ?>', <?= $priceCents ?>, '<?= e(addslashes($prod['category'])) ?>')">
                        
                        <div class="aspect-square bg-amber-50 rounded-xl mb-3 flex items-center justify-center overflow-hidden">
                            <?php 
                            $imgSrc = !empty($prod['image_path']) ? $prod['image_path'] : (!empty($prod['image_url']) ? $prod['image_url'] : null);
                            if (!empty($imgSrc)): 
                            ?>
                                <img src="<?= e($imgSrc) ?>" alt="<?= e($prod['name']) ?>" class="object-cover w-full h-full">
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
                            <span class="w-8 h-8 rounded-full bg-amber-100 text-amber-900 flex items-center justify-center font-bold text-lg hover:bg-amber-200"
                                  onclick="event.stopPropagation(); openModifierModal(<?= (int)$prod['id'] ?>, '<?= e(addslashes($prod['name'])) ?>', <?= $priceCents ?>, '<?= e(addslashes($prod['category'])) ?>')">+</span>
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
                    <p class="text-xs text-slate-500"><span id="cart-item-count">0</span> items &bull; <span id="cart-order-type" class="cursor-pointer select-none text-amber-800 font-medium hover:underline">Dine In (Table #1)</span></p>
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

    <!-- Modal: Modifier Selection Modal -->
    <div id="modifier-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl flex flex-col max-h-[90vh] overflow-hidden text-left">
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 shrink-0">
                <div>
                    <span id="mod-modal-category" class="text-xs font-bold uppercase tracking-wider text-amber-700">Category</span>
                    <h3 id="mod-modal-title" class="text-xl font-black text-slate-900 leading-tight">Product Name</h3>
                    <span id="mod-modal-base-price" class="text-sm font-semibold text-slate-500">₱0.00</span>
                </div>
                <button onclick="closeModifierModal()" class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 flex items-center justify-center text-lg font-bold">
                    &times;
                </button>
            </div>

            <!-- Scrollable Body -->
            <div class="flex-1 overflow-y-auto py-4 space-y-5 text-left hide-scrollbar">
                
                <!-- Size Section (Radio buttons: M, L) -->
                <div id="mod-section-size">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Size (Required)</label>
                    <div class="grid grid-cols-2 gap-3" id="mod-size-options">
                        <!-- Populated dynamically: M (0.00) / L (+20.00) -->
                    </div>
                </div>

                <!-- Add-ons Section (Checkboxes: Extra Shot, Pearls, etc.) -->
                <div id="mod-section-addons">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Add-ons</label>
                    <div class="space-y-2" id="mod-addon-options">
                        <!-- Populated dynamically: Extra Shot, Pearls, Whipped Cream, Caramel Drizzle -->
                    </div>
                </div>

                <!-- Sugar/Ice Controls (Dropdowns or checkboxes) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- Sugar Level -->
                    <div id="mod-section-sugar">
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Sugar Level</label>
                        <select id="mod-sugar-select" onchange="calculateModalTotal()" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-500">
                            <option value="" data-price="0">Normal Sugar (100%)</option>
                            <?php foreach ($modifiersCatalog['sugar'] as $s): ?>
                            <option value="<?= $s['id'] ?>" data-price="<?= $s['priceCents'] ?>"><?= e($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Ice Level (Cold only) -->
                    <div id="mod-section-ice">
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Ice Level</label>
                        <select id="mod-ice-select" onchange="calculateModalTotal()" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-500">
                            <option value="" data-price="0">Normal Ice (Default)</option>
                            <?php foreach ($modifiersCatalog['ice'] as $ic): ?>
                            <option value="<?= $ic['id'] ?>" data-price="<?= $ic['priceCents'] ?>"><?= e($ic['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Quantity in Modal -->
                <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Quantity</span>
                    <div class="flex items-center space-x-3">
                        <button type="button" onclick="changeModalQty(-1)" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold flex items-center justify-center text-sm">-</button>
                        <span id="mod-modal-qty" class="w-6 text-center font-bold text-slate-900 text-base">1</span>
                        <button type="button" onclick="changeModalQty(1)" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold flex items-center justify-center text-sm">+</button>
                    </div>
                </div>
            </div>

            <!-- Modal Footer: Add To Cart Button -->
            <div class="pt-4 border-t border-slate-100 shrink-0">
                <button id="mod-modal-submit" onclick="submitModalToCart()" class="w-full py-4 bg-amber-800 hover:bg-amber-700 text-white font-bold rounded-2xl shadow-lg shadow-amber-800/30 transition flex items-center justify-between px-6">
                    <span>Add to Cart</span>
                    <span id="mod-modal-total-btn" class="font-black text-lg">₱0.00</span>
                </button>
            </div>
        </div>
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
                    <span>Pay via GCash / Maya</span>
                </button>

                <div id="qr-display-box" class="hidden bg-slate-50 border border-slate-200 rounded-2xl p-4 text-center space-y-3">
                    <p class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center justify-center space-x-1.5">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                        <span>Scan QR Code with GCash or Maya</span>
                    </p>
                    <div class="flex justify-center">
                        <div id="kiosk-qrcode" class="p-3 bg-white rounded-2xl border border-slate-200 shadow-sm flex items-center justify-center min-w-[200px] min-h-[200px]"></div>
                        <img id="qr-image" src="" alt="Payment QR Code" class="hidden w-48 h-48 rounded-xl border border-slate-200 shadow-sm bg-white p-2">
                    </div>
                    <div class="text-[11px] text-slate-500 font-medium">Scan using your mobile phone camera or e-wallet app</div>
                    <a id="qr-direct-link" href="#" target="_blank" class="inline-block text-xs text-blue-600 hover:text-blue-700 font-bold underline">
                        Open Checkout on this screen &rarr;
                    </a>
                </div>
            </div>

            <div id="paid-badge" class="hidden bg-emerald-50 border border-emerald-200 rounded-2xl p-4 text-emerald-800 space-y-1">
                <div class="font-black text-lg text-emerald-700"><i class="fa-solid fa-circle-check mr-1.5"></i> PAYMENT RECEIVED!</div>
                <p class="text-xs text-emerald-600">Your order has been sent to the kitchen and is now being prepared.</p>
            </div>

            <div class="pt-2">
                <button id="btn-start-new-order" onclick="resetKioskSession()" class="w-full py-3 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl transition">
                    Start New Order
                </button>
            </div>
        </div>
    </div>

    <script>
        const DEVICE_TOKEN = <?= json_encode($device['device_token']) ?>;
        const DEVICE_EXIT_PIN = <?= json_encode($deviceExitPin) ?>;
        const MODIFIERS = <?= json_encode($modifiersCatalog) ?>;

        let cart = {}; // { itemKey: { key, id, name, basePriceCents, modifierIds, modifierNames, priceCents, qty } }
        let currentOrderId = null;
        let currentKioskToken = null;
        let pollTimer = null;

        let currentModalProduct = null;
        let modalQty = 1;

        // Hidden Manager Exit Trigger on Order Type / Table #
        function triggerDeviceExit() {
            const code = prompt("Enter Exit Code:");
            if (code === null || code.trim() === '') {
                return;
            }
            if (code.trim() === DEVICE_EXIT_PIN) {
                window.location.href = '../login.php?logout=1';
            } else {
                alert("Invalid Code");
            }
        }

        ['kiosk-order-type', 'kiosk-table-num', 'cart-order-type'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('click', triggerDeviceExit);
            }
        });

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

        // Modifier Modal Logic
        function openModifierModal(prodId, prodName, basePriceCents, category) {
            currentModalProduct = {
                id: prodId,
                name: prodName,
                basePriceCents: basePriceCents,
                category: category || ''
            };
            modalQty = 1;

            document.getElementById('mod-modal-category').innerText = category || 'Menu Item';
            document.getElementById('mod-modal-title').innerText = prodName;
            document.getElementById('mod-modal-base-price').innerText = '₱' + (basePriceCents / 100).toFixed(2);
            document.getElementById('mod-modal-qty').innerText = '1';

            const isPastry = /pastr/i.test(category);
            const isHot = /hot/i.test(category) || /hot/i.test(prodName);
            
            const sizeSection = document.getElementById('mod-section-size');
            const addonsSection = document.getElementById('mod-section-addons');
            const sugarSection = document.getElementById('mod-section-sugar');
            const iceSection = document.getElementById('mod-section-ice');
            const sizeContainer = document.getElementById('mod-size-options');
            const addonContainer = document.getElementById('mod-addon-options');

            if (isPastry) {
                sizeSection.classList.add('hidden');
                addonsSection.classList.add('hidden');
                sugarSection.classList.add('hidden');
                iceSection.classList.add('hidden');
            } else {
                sizeSection.classList.remove('hidden');
                addonsSection.classList.remove('hidden');
                sugarSection.classList.remove('hidden');
                if (isHot) {
                    iceSection.classList.add('hidden');
                } else {
                    iceSection.classList.remove('hidden');
                }

                // Render Size Options (Radio buttons: M, L)
                const sizes = isHot ? MODIFIERS.hot_sizes : MODIFIERS.cold_sizes;
                let sizeHtml = '';
                sizes.forEach((s, idx) => {
                    const checked = idx === 0 ? 'checked' : '';
                    const priceText = s.priceCents > 0 ? `+₱${(s.priceCents / 100).toFixed(2)}` : 'Included';
                    sizeHtml += `
                        <label class="flex items-center justify-between p-3.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer hover:bg-amber-50 hover:border-amber-300 transition">
                            <div class="flex items-center space-x-2.5">
                                <input type="radio" name="mod_size" value="${s.id}" data-price="${s.priceCents}" data-name="${escapeHtml(s.name)}" ${checked} onchange="calculateModalTotal()" class="w-4 h-4 text-amber-600 focus:ring-amber-500">
                                <span class="text-sm font-bold text-slate-800">${escapeHtml(s.label || s.name)}</span>
                            </div>
                            <span class="text-xs font-semibold text-slate-500">${priceText}</span>
                        </label>
                    `;
                });
                sizeContainer.innerHTML = sizeHtml;

                // Render Add-ons (Checkboxes: Extra Shot, Pearls, etc.)
                let addonHtml = '';
                MODIFIERS.addons.forEach(a => {
                    addonHtml += `
                        <label class="flex items-center justify-between p-3 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer hover:bg-amber-50 hover:border-amber-300 transition">
                            <div class="flex items-center space-x-2.5">
                                <input type="checkbox" name="mod_addon" value="${a.id}" data-price="${a.priceCents}" data-name="${escapeHtml(a.name)}" onchange="calculateModalTotal()" class="w-4 h-4 rounded text-amber-600 focus:ring-amber-500">
                                <span class="text-sm font-semibold text-slate-800">${escapeHtml(a.name)}</span>
                            </div>
                            <span class="text-xs font-bold text-amber-900">+₱${(a.priceCents / 100).toFixed(2)}</span>
                        </label>
                    `;
                });
                addonContainer.innerHTML = addonHtml;

                // Reset Sugar dropdown
                const sugarSelect = document.getElementById('mod-sugar-select');
                if (sugarSelect) sugarSelect.selectedIndex = 0;

                // Reset Ice dropdown
                const iceSelect = document.getElementById('mod-ice-select');
                if (iceSelect) iceSelect.selectedIndex = 0;
            }

            calculateModalTotal();
            document.getElementById('modifier-modal').classList.remove('hidden');
        }

        function closeModifierModal() {
            currentModalProduct = null;
            document.getElementById('modifier-modal').classList.add('hidden');
        }

        function changeModalQty(delta) {
            modalQty += delta;
            if (modalQty < 1) modalQty = 1;
            document.getElementById('mod-modal-qty').innerText = modalQty;
            calculateModalTotal();
        }

        function calculateModalTotal() {
            if (!currentModalProduct) return;
            let unitCents = currentModalProduct.basePriceCents;

            const isPastry = /pastr/i.test(currentModalProduct.category);
            if (!isPastry) {
                // Size
                const selectedSize = document.querySelector('input[name="mod_size"]:checked');
                if (selectedSize) {
                    unitCents += parseInt(selectedSize.dataset.price || '0', 10);
                }

                // Add-ons
                document.querySelectorAll('input[name="mod_addon"]:checked').forEach(cb => {
                    unitCents += parseInt(cb.dataset.price || '0', 10);
                });

                // Sugar
                const sugarSelect = document.getElementById('mod-sugar-select');
                if (sugarSelect && sugarSelect.value) {
                    const opt = sugarSelect.options[sugarSelect.selectedIndex];
                    unitCents += parseInt(opt.dataset.price || '0', 10);
                }

                // Ice
                const iceSelect = document.getElementById('mod-ice-select');
                if (iceSelect && iceSelect.value && !document.getElementById('mod-section-ice').classList.contains('hidden')) {
                    const opt = iceSelect.options[iceSelect.selectedIndex];
                    unitCents += parseInt(opt.dataset.price || '0', 10);
                }
            }

            const totalCents = unitCents * modalQty;
            document.getElementById('mod-modal-total-btn').innerText = '₱' + (totalCents / 100).toFixed(2);
        }

        function submitModalToCart() {
            if (!currentModalProduct) return;

            const modifierIds = [];
            const modifierNames = [];
            let modPriceCents = 0;

            const isPastry = /pastr/i.test(currentModalProduct.category);
            if (!isPastry) {
                // Size
                const selectedSize = document.querySelector('input[name="mod_size"]:checked');
                if (selectedSize) {
                    const mId = parseInt(selectedSize.value, 10);
                    modifierIds.push(mId);
                    modifierNames.push(selectedSize.dataset.name);
                    modPriceCents += parseInt(selectedSize.dataset.price || '0', 10);
                }

                // Add-ons
                document.querySelectorAll('input[name="mod_addon"]:checked').forEach(cb => {
                    const mId = parseInt(cb.value, 10);
                    modifierIds.push(mId);
                    modifierNames.push(cb.dataset.name);
                    modPriceCents += parseInt(cb.dataset.price || '0', 10);
                });

                // Sugar
                const sugarSelect = document.getElementById('mod-sugar-select');
                if (sugarSelect && sugarSelect.value) {
                    const mId = parseInt(sugarSelect.value, 10);
                    const opt = sugarSelect.options[sugarSelect.selectedIndex];
                    modifierIds.push(mId);
                    modifierNames.push(opt.text);
                    modPriceCents += parseInt(opt.dataset.price || '0', 10);
                }

                // Ice
                const iceSelect = document.getElementById('mod-ice-select');
                if (iceSelect && iceSelect.value && !document.getElementById('mod-section-ice').classList.contains('hidden')) {
                    const mId = parseInt(iceSelect.value, 10);
                    const opt = iceSelect.options[iceSelect.selectedIndex];
                    modifierIds.push(mId);
                    modifierNames.push(opt.text);
                    modPriceCents += parseInt(opt.dataset.price || '0', 10);
                }
            }

            // Combine into unique cart key
            const sortedModIds = modifierIds.slice().sort((a, b) => a - b);
            const itemKey = `${currentModalProduct.id}_${sortedModIds.join('-')}`;
            const unitPriceCents = currentModalProduct.basePriceCents + modPriceCents;

            if (cart[itemKey]) {
                cart[itemKey].qty += modalQty;
            } else {
                cart[itemKey] = {
                    key: itemKey,
                    id: currentModalProduct.id,
                    name: currentModalProduct.name,
                    basePriceCents: currentModalProduct.basePriceCents,
                    modifierIds: sortedModIds,
                    modifierNames: modifierNames,
                    priceCents: unitPriceCents,
                    qty: modalQty
                };
            }

            closeModifierModal();
            renderCart();
        }

        // Cart Actions
        function addToCart(id, name, priceCents) {
            const itemKey = `${id}_`;
            if (cart[itemKey]) {
                cart[itemKey].qty++;
            } else {
                cart[itemKey] = {
                    key: itemKey,
                    id: id,
                    name: name,
                    basePriceCents: priceCents,
                    modifierIds: [],
                    modifierNames: [],
                    priceCents: priceCents,
                    qty: 1
                };
            }
            renderCart();
        }

        function updateQty(itemKey, delta) {
            if (!cart[itemKey]) {
                const found = Object.values(cart).find(i => i.id == itemKey);
                if (found) {
                    itemKey = found.key;
                } else {
                    return;
                }
            }
            cart[itemKey].qty += delta;
            if (cart[itemKey].qty <= 0) {
                delete cart[itemKey];
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

                    const modHtml = item.modifierNames && item.modifierNames.length > 0
                        ? `<p class="text-[11px] text-amber-800 font-medium mt-0.5 leading-snug">${escapeHtml(item.modifierNames.join(', '))}</p>`
                        : '';

                    html += `
                        <div class="flex items-start justify-between p-3.5 bg-slate-50 rounded-xl border border-slate-200/70">
                            <div class="flex-1 mr-3">
                                <h4 class="text-sm font-bold text-slate-900 leading-snug">${escapeHtml(item.name)}</h4>
                                ${modHtml}
                                <span class="text-xs text-slate-700 font-bold mt-1 inline-block">₱${(item.priceCents / 100).toFixed(2)}</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button onclick="updateQty('${item.key}', -1)" class="w-7 h-7 rounded-lg bg-white border border-slate-200 text-slate-700 font-bold hover:bg-slate-100 flex items-center justify-center text-sm">-</button>
                                <span class="w-5 text-center text-sm font-bold text-slate-900">${item.qty}</span>
                                <button onclick="updateQty('${item.key}', 1)" class="w-7 h-7 rounded-lg bg-white border border-slate-200 text-slate-700 font-bold hover:bg-slate-100 flex items-center justify-center text-sm">+</button>
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

        // Submit Kiosk Order (Dine-in / Take-out queue ticket)
        async function submitKioskOrder() {
            const items = Object.values(cart).map(i => ({
                id: i.id,
                product_id: i.id,
                qty: i.qty,
                quantity: i.qty,
                modifier_ids: i.modifierIds || [],
                modifiers: i.modifierIds || []
            }));
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

                // Show success screen with queue ticket (bypassing online payment gateway)
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
            gcashBtn.innerHTML = '<span class="animate-pulse">Connecting to digital payment...</span>';

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
                    throw new Error(data.error || 'Digital payment temporarily unavailable.');
                }

                // Render QR code dynamically
                const qrContainer = document.getElementById('kiosk-qrcode');
                qrContainer.innerHTML = '';
                if (typeof QRCode !== 'undefined') {
                    new QRCode(qrContainer, {
                        text: data.checkout_url,
                        width: 190,
                        height: 190,
                        colorDark: "#0f172a",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.M
                    });
                    document.getElementById('qr-image').classList.add('hidden');
                    qrContainer.classList.remove('hidden');
                } else if (data.qr_code_url) {
                    document.getElementById('qr-image').src = data.qr_code_url;
                    document.getElementById('qr-image').classList.remove('hidden');
                    qrContainer.classList.add('hidden');
                }

                document.getElementById('qr-direct-link').href = data.checkout_url;
                document.getElementById('qr-display-box').classList.remove('hidden');
                gcashBtn.classList.add('hidden');

            } catch (err) {
                console.warn('Digital payment notice:', err.message);
                gcashBtn.disabled = false;
                gcashBtn.innerHTML = `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg><span>Pay via GCash / Maya</span>`;
                gcashBtn.className = "w-full py-3.5 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-2xl shadow-lg shadow-blue-600/30 transition flex items-center justify-center space-x-2";
                alert('Digital Payment Notice: ' + err.message);
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
            pollTimer = null;
            currentOrderId = null;
            currentKioskToken = null;
            document.getElementById('order-modal').classList.add('hidden');
            const gcashBtn = document.getElementById('btn-pay-gcash');
            if (gcashBtn) {
                gcashBtn.disabled = false;
                gcashBtn.classList.remove('hidden');
                gcashBtn.className = "w-full py-3.5 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-2xl shadow-lg shadow-blue-600/30 transition flex items-center justify-center space-x-2";
                gcashBtn.innerHTML = `
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    <span>Pay via GCash / Maya</span>
                `;
            }
            const qrBox = document.getElementById('qr-display-box');
            if (qrBox) qrBox.classList.add('hidden');
            clearCart();
            const btnPlace = document.getElementById('btn-place-order');
            if (btnPlace) {
                btnPlace.disabled = true;
                btnPlace.innerHTML = `
                    <span>Place Order</span>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                    </svg>
                `;
            }
        }

        function confirmReset() {
            if (confirm('Start a fresh session?')) {
                resetKioskSession();
            }
        }
    </script>
</body>
</html>
