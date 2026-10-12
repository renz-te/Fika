<?php
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
