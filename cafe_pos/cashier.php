<?php
require_once __DIR__ . '/../cafe_hrms/init.php';

if (empty($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['Cashier', 'Admin'])) {
    header('Location: ../cafe_hrms/login.php');
    exit;
}

$machineAccountName = $_SESSION['user']['name'] ?? 'Cashier Terminal';
$is_test = isset($_GET['mode']) && $_GET['mode'] === 'test' ? 1 : 0;
$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : ($_SESSION['user']['branch_id'] ?? 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafe POS - Cashier Portal</title>
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
        <!-- Left Sidebar (Pending Orders) -->
        <aside class="pending-sidebar glass-panel" style="border-right: 1px solid rgba(255,255,255,0.05);">
            <div class="cart-header" style="border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 16px; margin-bottom: 16px;">
                <h2>Pending Orders (<span id="pending-count">0</span>)</h2>
            </div>
            <div id="pending-queue-list" style="display: flex; flex-direction: column; gap: 12px; overflow-y: auto; flex: 1; padding-right: 8px;">
                <!-- Orders injected by JS -->
            </div>
        </aside>

        <!-- Main Content (Middle) -->
        <main class="main-content">
            <header class="header glass-panel">
        <div class="logo">
            <span class="logo-icon"></span>
            <h1>Slow Hours - Cashier</h1>
        </div>
        <div class="header-controls" style="display: flex; gap: 16px; align-items: center; flex: 1; justify-content: flex-end;">

            <div class="user-profile" id="top-user-profile" style="display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.05); padding: 6px 12px; border-radius: 20px; cursor: pointer;">
                <span style="font-size: 18px; display: flex; align-items: center;"><i data-lucide="user" style="width: 18px; height: 18px;"></i></span>
                <span style="font-size: 14px; color: var(--text-light);"><?php echo htmlspecialchars($machineAccountName); ?></span>
                <span style="font-size: 12px; color: #f1c40f; margin-left: 8px; text-decoration: none; display: none;">[Lock Terminal]</span>
            </div>
            <div class="clock" id="clock">00:00</div>
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
                    <h2>Current Order <span id="loaded-order-num" style="color:var(--accent); font-size:14px; margin-left: 8px;"></span></h2>
                    <span class="table-badge" id="cart-table-badge">Cashier</span>
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

    <!-- Cash Register Modal -->
    <div class="modal-overlay" id="cash-register-modal">
        <div class="modal-content glass-panel" style="max-width: 400px; text-align: center;">
            <div class="modal-header">
                <h3>Cash Register</h3>
                <button class="close-modal" id="btn-close-register">&times;</button>
            </div>
            <div class="modal-body" style="max-height: none; overflow: visible;">
                <div style="font-size: 14px; color: var(--text-muted); margin-bottom: 8px;">Total Amount Due</div>
                <div id="register-total" style="font-size: 36px; font-weight: bold; color: var(--accent); margin-bottom: 24px;">₱0.00</div>
                
                <input type="text" id="cash-received-input" autocomplete="off" placeholder="₱0.00" class="ref-input" style="font-size: 24px; text-align: center; margin-bottom: 16px;" oninput="this.value = this.value.replace(/[^0-9.]/g, '')">
                
                <div id="quick-cash-container" style="display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; margin-bottom: 24px;">
                    <!-- Injected by JS -->
                </div>

                <div style="background: rgba(0,0,0,0.2); padding: 16px; border-radius: 8px; margin-bottom: 24px;">
                    <div style="font-size: 14px; color: var(--text-muted); margin-bottom: 4px;">Change</div>
                    <div id="register-change" style="font-size: 28px; font-weight: bold; color: #2ecc71;">₱0.00</div>
                </div>
                <button class="add-to-cart-btn" id="btn-confirm-cash" disabled>Complete Transaction</button>
            </div>
        </div>
    </div>

    <!-- Payment Selection Modal -->
    <div class="modal-overlay" id="payment-modal">
        <div class="modal-content glass-panel">
            <div class="modal-header">
                <h3>Select Payment Method</h3>
                <button class="close-modal" id="btn-close-payment">&times;</button>
            </div>
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 16px;">
                <button class="payment-method-btn" id="btn-pay-cash" style="display: flex; align-items: center; justify-content: center; gap: 8px;"><i data-lucide="banknote" style="width: 20px; height: 20px;"></i> Physical Cash</button>
                <div class="digital-payment-section">
                    <button class="payment-method-btn" id="btn-pay-digital" style="display: flex; align-items: center; justify-content: center; gap: 8px;"><i data-lucide="credit-card" style="width: 20px; height: 20px;"></i> Digital Payment (E-Wallet/Card)</button>
                    <div id="digital-ref-container" style="display: none; margin-top: 12px; display: flex; flex-direction: column; gap: 8px;">
                        <input type="text" id="digital-ref-input" placeholder="Enter Reference Number..." class="ref-input">
                        <button class="add-to-cart-btn" id="btn-confirm-digital">Confirm Payment</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Confirm Modal -->
    <div class="modal-overlay" id="confirm-modal" style="z-index: 1005;">
        <div class="modal-content glass-panel" style="max-width: 400px; text-align: center; padding: 40px 30px;">
            <div style="font-size: 54px; margin-bottom: 16px; display: flex; justify-content: center;"><i data-lucide="trash-2" style="width: 54px; height: 54px;"></i></div>
            <h2 style="margin-bottom: 12px; color: var(--text-light); font-size: 28px;">Dismiss Order?</h2>
            <p style="color: var(--text-muted); margin-bottom: 32px; font-size: 16px;">Are you sure you want to dismiss this unpaid order? This action cannot be undone.</p>
            <div style="display: flex; gap: 16px; justify-content: center;">
                <button class="payment-method-btn" id="btn-confirm-no" style="flex: 1; font-weight: 600;">Keep Order</button>
                <button class="add-to-cart-btn" id="btn-confirm-yes" style="flex: 1; background: #e74c3c; border-radius: 12px; padding: 16px;">Yes, Dismiss</button>
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

    <!-- POS Lock Screen Overlay -->
    <div class="modal-overlay active" id="pos-lock-screen" style="z-index: 2000; display: flex; flex-direction: column; align-items: center; justify-content: center; background: rgba(10, 10, 10, 0.95);">
        <div style="text-align: center; margin-bottom: 32px;">
            <div style="font-size: 64px; color: var(--accent); margin-bottom: 16px; display: flex; justify-content: center;"><i data-lucide="lock" style="width: 64px; height: 64px;"></i></div>
            <h1 style="font-size: 36px; color: var(--text-light); margin-bottom: 8px;">Terminal Locked</h1>
            <p style="color: var(--text-muted); font-size: 18px;">Select your profile and enter PIN to unlock.</p>
        </div>
        
        <div class="glass-panel" style="width: 100%; max-width: 800px; padding: 32px;">
            <div id="active-baristas-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 16px; margin-bottom: 32px;">
                <!-- Baristas injected by JS -->
            </div>
            
            <!-- Pin Entry Area (Hidden by default until profile selected) -->
            <div id="pin-entry-area" style="display: none; flex-direction: column; align-items: center;">
                <h3 id="selected-barista-name" style="color: var(--accent); margin-bottom: 16px; font-size: 24px;"></h3>
                <input type="password" id="unlock-pin-input" autocomplete="new-password" placeholder="Enter Password/PIN" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: white; padding: 16px; border-radius: 8px; font-size: 24px; text-align: center; letter-spacing: 8px; margin-bottom: 8px; width: 300px;">
                <p id="unlock-error-msg" style="color: #e74c3c; margin-bottom: 16px; font-size: 14px; display: none;"></p>
                <div style="display: flex; gap: 16px;">
                    <button class="payment-method-btn" id="btn-cancel-unlock">Cancel</button>
                    <button class="add-to-cart-btn" id="btn-submit-unlock" style="padding: 12px 32px;" disabled>Unlock</button>
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 16px; margin-top: 32px; align-items: center;">
            <button class="payment-method-btn" id="btn-manage-register" style="background: rgba(255,255,255,0.05); border: none; margin: 0;">
                <i data-lucide="settings"></i> Manage Register Shift (Head Barista)
            </button>
            <a href="../cafe_hrms/logout.php" style="color: #e74c3c; text-decoration: none; font-size: 14px; display: flex; align-items: center; gap: 8px; padding: 12px 24px; border-radius: 8px; transition: background 0.2s;" onmouseover="this.style.background='rgba(231,76,60,0.1)'" onmouseout="this.style.background='transparent'">
                <i data-lucide="power"></i> Log Off Terminal
            </a>
        </div>
    </div>

    <!-- Register Shift Management Modal -->
    <div class="modal-overlay" id="register-shift-modal" style="z-index: 2001;">
        <div class="modal-content glass-panel" style="max-width: 500px;">
            <div class="modal-header">
                <h2>Register Shift Management</h2>
                <button class="close-modal" id="btn-close-shift-modal">&times;</button>
            </div>
            
            <div class="modal-body" style="padding: 24px;">
                <div id="shift-login-area">
                    <p id="shift-login-desc" style="color: var(--text-muted); margin-bottom: 16px;">Head Barista credentials required to open or close the register.</p>
                    <h3 id="shift-active-hb-label" style="display: none; color: var(--accent); margin-bottom: 16px; font-size: 20px;">Closing Shift for: <span id="shift-active-hb-name"></span></h3>
                    <input type="text" id="shift-hb-id" autocomplete="off" placeholder="Head Barista Username" style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: white; padding: 12px; border-radius: 8px; margin-bottom: 12px;">
                    <input type="password" id="shift-hb-pin" autocomplete="new-password" placeholder="Password" style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: white; padding: 12px; border-radius: 8px; margin-bottom: 8px;">
                    <p id="shift-error-msg" style="color: #e74c3c; margin-bottom: 16px; font-size: 14px; display: none;"></p>
                    <button class="add-to-cart-btn" id="btn-shift-login" style="width: 100%;" disabled>Authenticate</button>
                </div>

                <div id="shift-action-area" style="display: none;">
                    <div id="shift-open-ui">
                        <h3 style="color: #2ecc71; margin-bottom: 16px;">Register is CLOSED</h3>
                        <p style="color: var(--text-muted); margin-bottom: 12px;">Enter Starting Float to Open Shift</p>
                        <input type="text" id="shift-starting-cash" autocomplete="off" placeholder="₱0.00" style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #2ecc71; font-size: 24px; padding: 12px; border-radius: 8px; margin-bottom: 16px;">
                        <button class="add-to-cart-btn" id="btn-open-shift" style="width: 100%; background: #2ecc71;">Open Register</button>
                    </div>

                    <div id="shift-close-ui" style="display: none;">
                        <h3 style="color: #e74c3c; margin-bottom: 16px;">Register is OPEN</h3>
                        <p style="color: var(--text-muted); margin-bottom: 12px;">Enter Actual Cash in Drawer to Close Shift (Blind Close)</p>
                        <input type="text" id="shift-actual-cash" autocomplete="off" placeholder="₱0.00" style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #e74c3c; font-size: 24px; padding: 12px; border-radius: 8px; margin-bottom: 16px;">
                        <button class="add-to-cart-btn" id="btn-close-shift" style="width: 100%; background: #e74c3c;">Close Register</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Modal for Shift Discrepancy -->
    <div class="modal-overlay" id="shift-alert-modal" style="z-index: 2002;">
        <div class="modal-content glass-panel" style="max-width: 400px; text-align: center; padding: 40px 30px;">
            <div id="shift-alert-icon" style="font-size: 54px; margin-bottom: 16px; display: flex; justify-content: center;"><i data-lucide="info" style="width: 54px; height: 54px;"></i></div>
            <h2 id="shift-alert-title" style="margin-bottom: 12px; color: var(--text-light); font-size: 28px;">Shift Closed</h2>
            <p id="shift-alert-msg" style="color: var(--text-muted); margin-bottom: 32px; font-size: 16px;"></p>
            <button class="add-to-cart-btn" id="btn-shift-alert-ok" style="width: 100%;">OK</button>
        </div>
    </div>

    <!-- Receipt Modal -->
    <div class="modal-overlay" id="receipt-modal" style="z-index: 2005;">
        <div class="modal-content" style="background: white; color: black; max-width: 400px; padding: 0; font-family: 'Courier New', Courier, monospace; border-radius: 0; box-shadow: 0 10px 25px rgba(0,0,0,0.5);">
            <div style="padding: 32px 24px;">
                <div style="text-align: center; margin-bottom: 24px;">
                    <h2 style="font-size: 24px; font-weight: bold; margin-bottom: 8px;"><?= htmlspecialchars(get_setting($pdo, 'company_name', 'Slow Hours')) ?></h2>
                    <p style="font-size: 14px; color: #555;">Bayan, Dasmarinas City, Cavite</p>
                    <p style="font-size: 14px; color: #555;">Tel: (555) 123-4567</p>
                </div>
                
                <div style="border-top: 1px dashed #ccc; border-bottom: 1px dashed #ccc; padding: 12px 0; margin-bottom: 16px; font-size: 14px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                        <span>Order #: <strong id="receipt-order-no"></strong></span>
                        <span id="receipt-date"></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Cashier: <span id="receipt-cashier"></span></span>
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
                        <span>VAT (12%)</span>
                        <span id="receipt-vat"></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 16px; font-size: 18px; font-weight: bold;">
                        <span>TOTAL</span>
                        <span id="receipt-total"></span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px; color: #555;">
                        <span id="receipt-tender-label">Cash Received</span>
                        <span id="receipt-tender"></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; color: #555;">
                        <span>Change</span>
                        <span id="receipt-change"></span>
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: 32px; font-size: 14px; color: #555;">
                    <p style="margin-bottom: 8px;">Thank you for your visit!</p>
                </div>
            </div>
            
            <button class="add-to-cart-btn" id="btn-close-receipt" style="width: 100%; border-radius: 0; padding: 16px; background: #2ecc71; font-weight: bold; text-transform: uppercase;">Print & New Order</button>
        </div>
    </div>

    <script src="js/cashier.js?v=<?php echo time(); ?>"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
