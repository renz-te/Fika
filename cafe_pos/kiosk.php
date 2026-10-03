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
        <div class="modal-content glass-panel">
            <div class="modal-header">
                <h3>Select Payment Method</h3>
                <button class="close-modal" id="btn-close-payment">&times;</button>
            </div>
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 16px;">
                <button class="payment-method-btn" id="btn-pay-cash" style="display: flex; align-items: center; justify-content: center; gap: 8px;"><i data-lucide="footprints" style="width: 20px; height: 20px;"></i> Pay Cash at Counter</button>
                <div class="digital-payment-section">
                    <button class="payment-method-btn" id="btn-pay-digital" style="display: flex; align-items: center; justify-content: center; gap: 8px;"><i data-lucide="credit-card" style="width: 20px; height: 20px;"></i> Online Payment (E-Wallet/Card)</button>
                </div>
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
    <div class="modal-overlay" id="receipt-modal" style="z-index: 2005;">
        <div class="modal-content" style="background: white; color: black; max-width: 400px; padding: 0; font-family: 'Courier New', Courier, monospace; border-radius: 0; box-shadow: 0 10px 25px rgba(0,0,0,0.5);">
            <div style="padding: 32px 24px;">
                <div style="text-align: center; margin-bottom: 24px;">
                    <h2 style="font-size: 24px; font-weight: bold; margin-bottom: 8px;">Slow Hours Cafe</h2>
                    <p style="font-size: 14px; color: #555;">Bayan, Dasmarinas City, Cavite</p>
                    <p style="font-size: 14px; color: #555;">Tel: (555) 123-4567</p>
                </div>
                
                <div style="border-top: 1px dashed #ccc; border-bottom: 1px dashed #ccc; padding: 12px 0; margin-bottom: 16px; font-size: 14px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                        <span>Order #: <strong id="receipt-order-no"></strong></span>
                        <span id="receipt-date"></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Table: <span id="receipt-cashier">Kiosk</span></span>
                        <span id="receipt-time"></span>
                    </div>
                </div>

                <table style="width: 100%; font-size: 14px; margin-bottom: 16px; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid #eee;">
                            <th style="text-align: left; padding-bottom: 8px; width: 60%;">Item</th>
                            <th style="text-align: center; padding-bottom: 8px; width: 15%;">Qty</th>
                            <th style="text-align: right; padding-bottom: 8px; width: 25%;">Amount</th>
                        </tr>
                    </thead>
                    <tbody id="receipt-items">
                        <!-- Items injected by JS -->
                    </tbody>
                </table>

                <div style="border-top: 1px dashed #ccc; padding-top: 16px; font-size: 14px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                        <span>Subtotal</span>
                        <span id="receipt-subtotal"></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                        <span id="receipt-vat-label">VAT (12%)</span>
                        <span id="receipt-vat"></span>
                    </div>
                    <div id="receipt-discount-container" style="display: none; justify-content: space-between; margin-bottom: 4px; color: #e74c3c;">
                        <span>Discount Applied</span>
                        <span id="receipt-discount"></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 16px; font-size: 18px; font-weight: bold;">
                        <span>TOTAL</span>
                        <span id="receipt-total"></span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px; color: #555;">
                        <span id="receipt-tender-label">Paid (Digital)</span>
                        <span id="receipt-tender"></span>
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: 32px; font-size: 14px; color: #555;">
                    <p style="margin-bottom: 8px;">Thank you for your visit!</p>
                </div>
            </div>
            
            <button class="add-to-cart-btn" id="btn-close-receipt" style="width: 100%; border-radius: 0; padding: 16px; background: #2ecc71; font-weight: bold; text-transform: uppercase;">Print & Continue</button>
        </div>
    </div>
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
