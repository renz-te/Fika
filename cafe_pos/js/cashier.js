document.addEventListener('DOMContentLoaded', () => {
    // --- State ---
    let menuData = { products: {}, modifiers: [] };
    let currentCategory = 'All';
    let cart = []; // Array of {cartItemId, product, quantity, modifiers: [], subtotal}
    
    // Helper to safely escape text for HTML injection
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    // --- Unified Receipt Logic ---
    function showReceiptModal(data) {
        data = data || {};
        
        // 1. Identification & Meta
        const orderNoElem = document.getElementById('receipt-order-no');
        if (orderNoElem) orderNoElem.textContent = data.orderNo || data.order_number || '-';

        const dateElem = document.getElementById('receipt-date');
        if (dateElem) dateElem.textContent = data.date || new Date().toLocaleDateString();

        const timeElem = document.getElementById('receipt-time');
        if (timeElem) timeElem.textContent = data.time || new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        const cashierElem = document.getElementById('receipt-cashier');
        if (cashierElem) cashierElem.textContent = data.cashier || activeCashierName || 'Cashier';

        const shiftElem = document.getElementById('receipt-shift-id');
        if (shiftElem) shiftElem.textContent = data.shift_id || currentSessionId || '1';

        const stationElem = document.getElementById('receipt-station-type');
        if (stationElem) stationElem.textContent = data.station || 'POS-01';

        // 2. Dynamic Status Markers
        const reprintMarker = document.getElementById('receipt-marker-reprint');
        if (reprintMarker) {
            reprintMarker.style.display = data.is_reprint ? 'block' : 'none';
        }

        const voidMarker = document.getElementById('receipt-marker-void');
        if (voidMarker) {
            if (data.is_void) {
                voidMarker.style.display = 'block';
                const voidTitleElem = document.getElementById('receipt-void-title');
                if (voidTitleElem) voidTitleElem.textContent = data.void_title || (data.action === 'REFUND' ? '** REFUND RECEIPT **' : '** VOID RECEIPT **');
                const voidOrigElem = document.getElementById('receipt-void-orig-no');
                if (voidOrigElem) voidOrigElem.textContent = data.original_order_no || data.orderNo || '-';
                const voidReasonElem = document.getElementById('receipt-void-reason');
                if (voidReasonElem) voidReasonElem.textContent = data.reason || data.void_reason || 'N/A';
                const voidSupElem = document.getElementById('receipt-void-supervisor');
                if (voidSupElem) voidSupElem.textContent = data.supervisor_name || data.supervisor || '-';
            } else {
                voidMarker.style.display = 'none';
            }
        }

        const trainingMarker = document.getElementById('receipt-marker-training');
        if (trainingMarker) {
            const isTest = data.is_test !== undefined ? data.is_test : (window.POS_CONFIG && window.POS_CONFIG.is_test);
            trainingMarker.style.display = isTest ? 'block' : 'none';
        }

        // 3. Line Items (qty × unit price = amount)
        const tbody = document.getElementById('receipt-items');
        if (tbody) {
            tbody.innerHTML = '';
            let computedSubtotal = 0;

            (data.items || []).forEach(item => {
                const tr = document.createElement('tr');
                const itemName = (item.product && item.product.name) ? item.product.name : (item.name || 'Item');
                const qty = item.quantity !== undefined ? item.quantity : 1;
                const unitPrice = item.unitPrice !== undefined ? item.unitPrice : ((item.subtotal && qty) ? (item.subtotal / qty) : 0);
                const price = item.subtotal !== undefined ? item.subtotal : (unitPrice * qty);
                computedSubtotal += price;

                const isVoided = qty < 0 || item.is_voided || data.is_void;

                let html = `
                    <td class="receipt-col-item ${isVoided ? 'receipt-item-voided' : ''}">
                        <div>${isVoided && !data.is_void ? '[VOID] ' : ''}${escapeHtml(itemName)}</div>
                        <div class="receipt-item-formula">${qty} x ₱${Number(unitPrice).toFixed(2)} = ₱${Number(price).toFixed(2)}</div>
                `;
                if (item.modifiers && item.modifiers.length > 0) {
                    const mods = item.modifiers.map(m => m.name || m).join(', ');
                    html += `<div class="receipt-item-mod">+ ${escapeHtml(mods)}</div>`;
                }
                html += `</td>
                    <td class="receipt-col-qty ${isVoided ? 'receipt-item-voided' : ''}">${qty}</td>
                    <td class="receipt-col-amt ${isVoided ? 'receipt-item-voided' : ''}">₱${Number(price).toFixed(2)}</td>
                `;
                tr.innerHTML = html;
                tbody.appendChild(tr);
            });
        }

        // 4. Financial Calculations & Summary
        const gross = data.gross_total !== undefined ? Number(data.gross_total) : (data.subtotal !== undefined ? Number(data.subtotal) : 0);
        const subtotalElem = document.getElementById('receipt-subtotal');
        if (subtotalElem) subtotalElem.textContent = '₱' + gross.toFixed(2);

        const discount = Number(data.discount_amount) || 0;
        const discountRow = document.getElementById('receipt-discount-row');
        const discountElem = document.getElementById('receipt-discount');
        if (discountRow && discountElem) {
            if (discount > 0) {
                discountRow.style.display = 'table-row';
                discountElem.textContent = '-₱' + discount.toFixed(2);
            } else {
                discountRow.style.display = 'none';
            }
        }

        const total = data.net_payable !== undefined ? Number(data.net_payable) : (gross - discount);
        const totalElem = document.getElementById('receipt-total');
        if (totalElem) totalElem.textContent = '₱' + total.toFixed(2);

        // BIR VAT Breakdown (VATable, VAT Amount 12%, VAT-Exempt, Zero-Rated)
        let vatable = 0;
        let vatAmount = 0;
        let vatExempt = 0;
        let zeroRated = 0;

        if (data.vat_amount !== undefined && Number(data.vat_amount) > 0) {
            vatAmount = Number(data.vat_amount);
            vatable = total - vatAmount;
            vatExempt = 0;
        } else if (data.discount_type === 'SENIOR' || data.discount_type === 'PWD' || (data.vat_amount === 0 && discount > 0)) {
            vatable = 0;
            vatAmount = 0;
            vatExempt = total;
        } else if (data.is_void) {
            vatable = 0;
            vatAmount = 0;
            vatExempt = 0;
        } else {
            vatable = total / 1.12;
            vatAmount = total - vatable;
            vatExempt = 0;
        }

        const vatableElem = document.getElementById('receipt-vatable');
        if (vatableElem) vatableElem.textContent = '₱' + vatable.toFixed(2);

        const vatElem = document.getElementById('receipt-vat');
        if (vatElem) vatElem.textContent = '₱' + vatAmount.toFixed(2);

        const vatExemptElem = document.getElementById('receipt-vat-exempt');
        if (vatExemptElem) vatExemptElem.textContent = '₱' + vatExempt.toFixed(2);

        const zeroRatedElem = document.getElementById('receipt-zero-rated');
        if (zeroRatedElem) zeroRatedElem.textContent = '₱' + zeroRated.toFixed(2);

        // 5. Payment Tender & Change
        const tenderLabel = document.getElementById('receipt-tender-label');
        const tenderElem = document.getElementById('receipt-tender');
        const changeRow = document.getElementById('receipt-change-row');
        const changeElem = document.getElementById('receipt-change');

        const isCash = (data.method === 'CASH');
        if (tenderLabel) tenderLabel.textContent = isCash ? 'Cash Received' : ('Paid (' + (data.method || 'Digital') + ')');

        if (isCash) {
            const received = data.received !== null && data.received !== undefined ? Number(data.received) : total;
            if (tenderElem) tenderElem.textContent = '₱' + received.toFixed(2);
            if (changeRow && changeElem) {
                changeRow.style.display = 'table-row';
                const change = Math.max(0, received - total);
                changeElem.textContent = '₱' + change.toFixed(2);
            }
        } else {
            if (tenderElem) tenderElem.textContent = '₱' + total.toFixed(2);
            if (changeRow) changeRow.style.display = 'none';
        }

        // Show Modal
        const modal = document.getElementById('receipt-modal');
        if (modal) modal.classList.add('active');
    }

    // Expose global helpers for reprint and void receipts
    window._isReprintOrVoidActive = false;
    window.showReceiptModal = showReceiptModal;

    window.printReceipt = function(receiptData) {
        showReceiptModal(receiptData);
    };

    window.reprintReceipt = function(orderData) {
        window._isReprintOrVoidActive = true;
        const data = Object.assign({}, orderData, { is_reprint: true });
        showReceiptModal(data);
    };

    window.voidReceipt = function(orderData, voidDetails) {
        window._isReprintOrVoidActive = true;
        const data = Object.assign({}, orderData, {
            is_void: true,
            original_order_no: orderData.orderNo || orderData.order_number,
            void_reason: (voidDetails && voidDetails.reason) ? voidDetails.reason : 'Supervisor Void/Refund',
            supervisor_name: (voidDetails && voidDetails.supervisor) ? voidDetails.supervisor : (activeCashierName || 'Supervisor')
        });
        showReceiptModal(data);
    };

    document.getElementById('btn-close-receipt').onclick = () => {
        window.print();
        setTimeout(() => {
            document.getElementById('receipt-modal').classList.remove('active');
            if (!window._isReprintOrVoidActive) {
                cart = [];
                renderCart();
            }
            window._isReprintOrVoidActive = false;
        }, 500);
    };

    // Keep session alive
    setInterval(checkCurrentSession, 60000);

    // Auth & Session State
    let currentSessionId = null;
    let activeCashierId = null;
    let activeCashierName = null;
    let selectedUnlockUserId = null;
    let currentHbIdForShift = null;
    
    // Modal State
    let activeProduct = null;
    let modalQuantity = 1;
    let selectedModifiers = new Set(); // Stores modifier IDs

    // --- DOM Elements ---
    const gridEl = document.getElementById('product-grid');
    const tabsEl = document.getElementById('category-tabs');
    const cartItemsEl = document.getElementById('cart-items');
    const cartTotalEl = document.getElementById('cart-total');
    const btnCheckout = document.getElementById('btn-checkout');
    const checkoutTotalEl = document.getElementById('checkout-total');
    const btnClearCart = document.getElementById('btn-clear-cart');
    const clearModal = document.getElementById('clear-modal');
    const btnClearNo = document.getElementById('btn-clear-no');
    const btnClearYes = document.getElementById('btn-clear-yes');
    
    const modalOverlay = document.getElementById('modifier-modal');
    const btnCloseModal = document.getElementById('btn-close-modal');
    const modalProductName = document.getElementById('modal-product-name');
    const modalModifierList = document.getElementById('modifier-list');
    const modalQtyEl = document.getElementById('modal-qty');
    const modalPriceEl = document.getElementById('modal-price');
    const btnQtyMinus = document.getElementById('btn-qty-minus');
    const btnQtyPlus = document.getElementById('btn-qty-plus');
    const btnModalAdd = document.getElementById('btn-modal-add');
    const btnCheckoutText = document.getElementById('btn-checkout-text');

    const pendingCount = document.getElementById('pending-count');
    const pendingQueueList = document.getElementById('pending-queue-list');

    const loadedOrderNum = document.getElementById('loaded-order-num');

    const cashRegisterModal = document.getElementById('cash-register-modal');
    const btnCloseRegister = document.getElementById('btn-close-register');
    const registerTotal = document.getElementById('register-total');
    const registerChange = document.getElementById('register-change');
    const cashReceivedInput = document.getElementById('cash-received-input');
    const btnConfirmCash = document.getElementById('btn-confirm-cash');

    const paymentModal = document.getElementById('payment-modal');
    const btnClosePayment = document.getElementById('btn-close-payment');
    const btnPayCash = document.getElementById('btn-pay-cash');
    const btnPayGcash = document.getElementById('btn-pay-gcash');
    const btnPayMaya = document.getElementById('btn-pay-maya');
    const btnPayDigital = document.getElementById('btn-pay-digital');
    const btnPayDigitalManual = document.getElementById('btn-pay-digital-manual');
    const digitalRefContainer = document.getElementById('digital-ref-container');
    const digitalRefInput = document.getElementById('digital-ref-input');
    const btnConfirmDigital = document.getElementById('btn-confirm-digital');

    const paymongoModal = document.getElementById('paymongo-modal');
    const btnClosePaymongo = document.getElementById('btn-close-paymongo');
    const btnCancelPaymongo = document.getElementById('btn-cancel-paymongo');
    const paymongoQrImg = document.getElementById('paymongo-qr-img');
    const paymongoModalAmount = document.getElementById('paymongo-modal-amount');
    const paymongoModalTitle = document.getElementById('paymongo-modal-title');
    const paymongoBadgeMethod = document.getElementById('paymongo-badge-method');
    const paymongoCheckoutBtn = document.getElementById('paymongo-checkout-btn');
    const paymongoPollStatusText = document.getElementById('paymongo-poll-status-text');
    let paymongoPollInterval = null;

    const confirmModal = document.getElementById('confirm-modal');
    const btnConfirmNo = document.getElementById('btn-confirm-no');
    const btnConfirmYes = document.getElementById('btn-confirm-yes');

    let loadedOrderId = null;
    let pendingOrdersData = [];

    // --- Auth & Session Elements ---
    const posLockScreen = document.getElementById('pos-lock-screen');
    const activeBaristasGrid = document.getElementById('active-baristas-grid');
    const pinEntryArea = document.getElementById('pin-entry-area');
    const selectedBaristaNameEl = document.getElementById('selected-barista-name');
    const unlockPinInput = document.getElementById('unlock-pin-input');
    const btnCancelUnlock = document.getElementById('btn-cancel-unlock');
    const btnSubmitUnlock = document.getElementById('btn-submit-unlock');
    const btnManageRegister = document.getElementById('btn-manage-register');
    
    const registerShiftModal = document.getElementById('register-shift-modal');
    const btnCloseShiftModal = document.getElementById('btn-close-shift-modal');
    const shiftLoginArea = document.getElementById('shift-login-area');
    const shiftHbId = document.getElementById('shift-hb-id');
    const shiftHbPin = document.getElementById('shift-hb-pin');
    const btnShiftLogin = document.getElementById('btn-shift-login');
    
    const shiftActionArea = document.getElementById('shift-action-area');
    const shiftOpenUi = document.getElementById('shift-open-ui');
    const shiftStartingCash = document.getElementById('shift-starting-cash');
    const btnOpenShift = document.getElementById('btn-open-shift');
    const shiftCloseUi = document.getElementById('shift-close-ui');
    const shiftActualCash = document.getElementById('shift-actual-cash');
    const btnCloseShift = document.getElementById('btn-close-shift');

    const shiftAlertModal = document.getElementById('shift-alert-modal');
    const shiftAlertTitle = document.getElementById('shift-alert-title');
    const shiftAlertMsg = document.getElementById('shift-alert-msg');
    const btnShiftAlertOk = document.getElementById('btn-shift-alert-ok');

    // --- Initialization ---
    updateClock();
    setInterval(updateClock, 60000);
    fetchMenu();
    fetchPendingOrders();
    setInterval(fetchPendingOrders, 10000);
    
    checkCurrentSession();
    fetchActiveBaristas();
    setInterval(fetchActiveBaristas, 30000);

    // --- Functions ---
    function updateClock() {
        const now = new Date();
        document.getElementById('clock').textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    async function fetchMenu() {
        try {
            const res = await fetch('api/menu.php');
            if(!res.ok) throw new Error("Failed to fetch menu");
            menuData = await res.json();
            
            // Render Categories
            renderCategories();
        } catch (err) {
            console.error(err);
            alert("Error loading menu. Is the backend running?");
        }
    }

    function renderCategories() {
        const categories = Object.keys(menuData.products);
        if (categories.length === 0) return;
        
        tabsEl.innerHTML = '';
        
        // Add "All" tab
        const allBtn = document.createElement('button');
        allBtn.className = `tab-btn ${currentCategory === 'All' ? 'active' : ''}`;
        allBtn.textContent = 'All';
        allBtn.onclick = () => {
            currentCategory = 'All';
            renderCategories();
            renderProducts();
        };
        tabsEl.appendChild(allBtn);

        categories.forEach(cat => {
            const btn = document.createElement('button');
            btn.className = `tab-btn ${cat === currentCategory ? 'active' : ''}`;
            btn.textContent = cat;
            btn.onclick = () => {
                currentCategory = cat;
                renderCategories(); // Re-render to update active class
                renderProducts();
            };
            tabsEl.appendChild(btn);
        });

        renderProducts();
    }

    function renderProducts() {
        let products = [];
        if (currentCategory === 'All') {
            Object.values(menuData.products).forEach(arr => products = products.concat(arr));
        } else {
            products = menuData.products[currentCategory] || [];
        }
        gridEl.innerHTML = '';

        products.forEach(p => {
            const card = document.createElement('div');
            card.className = 'product-card';
            card.innerHTML = `
                <h3>${p.name}</h3>
                <div class="price">₱${parseFloat(p.price).toFixed(2)}</div>
            `;
            card.onclick = () => openModifierModal(p);
            gridEl.appendChild(card);
        });
    }

    // --- Modal Logic ---
    function openModifierModal(product) {
        const allowedGroups = (product.allowed_modifier_groups || '').split(',').filter(Boolean);
        if (allowedGroups.length === 0) {
            // Bypass modifier modal if no modifiers allowed
            cart.push({
                cartItemId: Date.now().toString(),
                product: product,
                quantity: 1,
                modifiers: [],
                unitPrice: parseFloat(product.price)
            });
            renderCart();
            return;
        }

        activeProduct = product;
        modalQuantity = 1;
        selectedModifiers.clear();

        // Default to Tall
        const tallMod = menuData.modifiers.find(m => m.name === 'Tall');
        if (tallMod) selectedModifiers.add(tallMod.id);

        modalProductName.textContent = product.name;
        renderModalModifiers();
        updateModalPrice();
        
        modalOverlay.classList.add('active');
    }

    function closeModifierModal() {
        modalOverlay.classList.remove('active');
        activeProduct = null;
    }

    function renderModalModifiers() {
        modalModifierList.innerHTML = '';
        
        // Group modifiers, filtering by what's allowed for this product
        const groups = {};
        const allowedGroups = (activeProduct.allowed_modifier_groups || '').split(',');
        
        menuData.modifiers.forEach(mod => {
            const grp = mod.modifier_group || 'Add-ons';
            if (allowedGroups.includes(grp)) {
                if (!groups[grp]) groups[grp] = [];
                groups[grp].push(mod);
            }
        });

        // Ensure order: Size, Milk, Add-ons
        const order = ['Size', 'Milk', 'Add-ons'];
        const sortedGroups = Object.keys(groups).sort((a, b) => {
            let indexA = order.indexOf(a);
            let indexB = order.indexOf(b);
            if(indexA === -1) indexA = 99;
            if(indexB === -1) indexB = 99;
            return indexA - indexB;
        });

        sortedGroups.forEach(groupName => {
            const groupHeader = document.createElement('h5');
            groupHeader.style.marginTop = '12px';
            groupHeader.style.marginBottom = '8px';
            groupHeader.style.color = 'var(--text-muted)';
            groupHeader.style.textTransform = 'uppercase';
            groupHeader.style.letterSpacing = '1px';
            groupHeader.textContent = groupName;
            modalModifierList.appendChild(groupHeader);

            groups[groupName].forEach(mod => {
                const modItem = document.createElement('div');
                modItem.className = 'modifier-item';
                if (selectedModifiers.has(mod.id)) modItem.classList.add('selected');
                
                const priceAdj = parseFloat(mod.price_adjustment);
                const priceText = priceAdj > 0 ? `+₱${priceAdj.toFixed(2)}` : (groupName === 'Size' ? 'Included' : '+₱0.00');
                
                modItem.innerHTML = `
                    <span class="mod-name">${mod.name}</span>
                    <span class="mod-price">${priceText}</span>
                `;
                
                modItem.onclick = () => {
                    if (groupName === 'Size') {
                        groups['Size'].forEach(m => selectedModifiers.delete(m.id));
                        selectedModifiers.add(mod.id);
                    } else if (groupName === 'Milk') {
                        // Deselect if already selected, otherwise set it
                        if (selectedModifiers.has(mod.id)) {
                            selectedModifiers.delete(mod.id);
                        } else {
                            groups['Milk'].forEach(m => selectedModifiers.delete(mod.id));
                            selectedModifiers.add(mod.id);
                        }
                    } else {
                        // Toggle Add-ons
                        if (selectedModifiers.has(mod.id)) selectedModifiers.delete(mod.id);
                        else selectedModifiers.add(mod.id);
                    }
                    renderModalModifiers();
                    updateModalPrice();
                };
                modalModifierList.appendChild(modItem);
            });
        });
    }

    function updateModalPrice() {
        let basePrice = parseFloat(activeProduct.price);
        let modsPrice = 0;
        
        selectedModifiers.forEach(modId => {
            const mod = menuData.modifiers.find(m => m.id == modId);
            if (mod) modsPrice += parseFloat(mod.price_adjustment);
        });

        const unitPrice = basePrice + modsPrice;
        const total = unitPrice * modalQuantity;
        
        modalQtyEl.textContent = modalQuantity;
        modalPriceEl.textContent = `₱${total.toFixed(2)}`;
    }

    btnQtyMinus.onclick = () => {
        if (modalQuantity > 1) {
            modalQuantity--;
            updateModalPrice();
        }
    };

    btnQtyPlus.onclick = () => {
        modalQuantity++;
        updateModalPrice();
    };

    btnCloseModal.onclick = closeModifierModal;

    btnModalAdd.onclick = () => {
        // Collect selected modifiers objects
        const mods = Array.from(selectedModifiers).map(modId => {
            return menuData.modifiers.find(m => m.id == modId);
        });

        // Calculate unit price
        let unitPrice = parseFloat(activeProduct.price) + mods.reduce((sum, mod) => sum + parseFloat(mod.price_adjustment), 0);

        cart.push({
            cartItemId: Date.now().toString(), // unique id for cart row
            product: activeProduct,
            quantity: modalQuantity,
            modifiers: mods,
            unitPrice: unitPrice
        });

        closeModifierModal();
        renderCart();
    };

    // --- Cart Logic ---
    function renderCart() {
        if (cart.length === 0) {
            cartItemsEl.innerHTML = `
                <div class="empty-cart">
                    <div class="empty-icon"><i data-lucide="shopping-cart"></i></div>
                    <p>Cart is empty</p>
                </div>
            `;
            if (window.lucide) window.lucide.createIcons();
            cartTotalEl.textContent = '₱0.00';
            checkoutTotalEl.textContent = '₱0.00';
            
            if (cart.length === 0) {
                btnCheckout.disabled = true;
                btnCheckoutText.textContent = 'Charge';
            }
        }

        if (btnClearCart) {
            if (loadedOrderId) {
                btnClearCart.textContent = 'Deselect';
                btnClearCart.style.backgroundColor = '#95a5a6'; // Neutral gray so it doesn't look like a destructive delete
            } else {
                btnClearCart.textContent = 'Clear';
                btnClearCart.style.backgroundColor = '#e74c3c'; // Original red
            }

            btnClearCart.onclick = () => {
                if (loadedOrderId) {
                    // No confirmation needed to just deselect and close the order
                    clearLoadedOrder();
                } else if (cart.length > 0) {
                    // Confirm before clearing an unsaved walk-in cart
                    clearModal.classList.add('active');
                }
            };
        }

        if (btnClearNo) {
            btnClearNo.onclick = () => clearModal.classList.remove('active');
        }

        if (btnClearYes) {
            btnClearYes.onclick = () => {
                clearLoadedOrder();
                clearModal.classList.remove('active');
            };
        }

        if (cart.length === 0) return;

        cartItemsEl.innerHTML = '';
        let grandTotal = 0;

        cart.forEach((item, index) => {
            const rowTotal = item.unitPrice * item.quantity;
            grandTotal += rowTotal;

            const modText = item.modifiers.map(m => m.name).join(', ');
            
            const el = document.createElement('div');
            el.className = 'cart-item';
            el.innerHTML = `
                <div class="cart-item-info">
                    <h4>${item.product.name}</h4>
                    ${modText ? `<div class="cart-item-mods">${modText}</div>` : ''}
                    <div class="cart-item-price">₱${rowTotal.toFixed(2)}</div>
                </div>
                <div class="cart-item-qty">
                    <button class="qty-btn" onclick="updateCartQty(${index}, -1)">-</button>
                    <span class="qty-val">${item.quantity}</span>
                    <button class="qty-btn" onclick="updateCartQty(${index}, 1)">+</button>
                </div>
            `;
            cartItemsEl.appendChild(el);
        });

        cartTotalEl.textContent = `₱${grandTotal.toFixed(2)}`;
        checkoutTotalEl.textContent = `₱${grandTotal.toFixed(2)}`;
        btnCheckout.disabled = false;
        btnCheckoutText.textContent = loadedOrderId ? 'Update & Charge' : 'Charge';
    }

    window.updateCartQty = (index, delta) => {
        const item = cart[index];
        item.quantity += delta;
        if (item.quantity <= 0) {
            cart.splice(index, 1);
        }
        renderCart();
    };

    // --- Pending Queue Logic ---
    async function fetchPendingOrders() {
        try {
            const p = window.POS_CONFIG;
            const res = await fetch(`api/orders_pending.php?branch_id=${p ? p.branch_id : 1}&is_test=${p ? p.is_test : 0}`);
            const data = await res.json();
            pendingOrdersData = data.pending_orders || [];
            pendingCount.textContent = pendingOrdersData.length;
            renderPendingQueue();
        } catch (e) {
            console.error(e);
        }
    }

    function renderPendingQueue() {
        pendingQueueList.innerHTML = '';
        if (pendingOrdersData.length === 0) {
            pendingQueueList.innerHTML = '<p style="color:var(--text-muted);text-align:center;">No pending orders.</p>';
            return;
        }

        pendingOrdersData.forEach(order => {
            const el = document.createElement('div');
            el.className = 'glass-panel';
            el.style.padding = '12px';
            el.style.display = 'flex';
            el.style.justifyContent = 'space-between';
            el.style.alignItems = 'center';
            
            const timeStr = new Date(order.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});

            const isPaid = order.payment_status === 'PAID';
            const statusBadge = isPaid 
                ? `<span style="background: #2ecc71; color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; margin-left: 8px;">PAID / PREPARE</span>`
                : `<span style="background: #e74c3c; color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; margin-left: 8px;">UNPAID</span>`;

            el.innerHTML = `
                <div>
                    <strong style="color:var(--accent); font-size:18px;">${order.order_number}</strong>
                    ${statusBadge}
                    <div style="font-size:12px; color:var(--text-muted);">${timeStr} - ${order.table_source}</div>
                    <div style="font-weight:bold;">₱${parseFloat(order.total_price).toFixed(2)}</div>
                </div>
                <div style="display:flex; gap:8px;">
                    ${isPaid 
                        ? `<button class="add-to-cart-btn complete-btn" style="background:#2ecc71; padding:8px 12px;"><i data-lucide="check-circle" style="width:16px;height:16px;display:inline-block;margin-right:4px;"></i> Complete</button>`
                        : `<button class="add-to-cart-btn cancel-btn" style="background:#e74c3c; padding:8px 12px;">Dismiss</button>
                           <button class="add-to-cart-btn load-btn" style="padding:8px 12px;">Load</button>`
                    }
                </div>
            `;

            if (isPaid) {
                el.querySelector('.complete-btn').onclick = () => completeOrder(order.id);
            } else {
                el.querySelector('.cancel-btn').onclick = () => promptDismiss(order.id);
                el.querySelector('.load-btn').onclick = () => loadOrder(order.id, order.order_number);
            }
            
            pendingQueueList.appendChild(el);
            if (window.lucide) window.lucide.createIcons();
        });
    }

    // Poll for new pending orders every 5 seconds to simulate real-time updates
    setInterval(() => {
        fetchPendingOrders();
        fetchSalesSummary();
    }, 5000);

    async function fetchSalesSummary() {
        try {
            const p = window.POS_CONFIG;
            const res = await fetch(`api/sales_summary.php?branch_id=${p ? p.branch_id : 1}&is_test=${p ? p.is_test : 0}`);
            const data = await res.json();
            document.getElementById('header-total-sales').textContent = `₱${parseFloat(data.total_sales).toFixed(2)}`;
            document.getElementById('header-cash-sales').textContent = `₱${parseFloat(data.cash_sales).toFixed(2)}`;
        } catch (e) {
            console.error(e);
        }
    }

    let currentDismissId = null;

    function promptDismiss(id) {
        currentDismissId = id;
        confirmModal.classList.add('active');
    }

    btnConfirmNo.onclick = () => {
        confirmModal.classList.remove('active');
        currentDismissId = null;
    };

    btnConfirmYes.onclick = async () => {
        if (!currentDismissId) return;
        confirmModal.classList.remove('active');
        try {
            await fetch('api/orders_cancel.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: currentDismissId })
            });
            fetchPendingOrders();
            fetchSalesSummary();
            if (loadedOrderId == currentDismissId) clearLoadedOrder();
        } catch (e) {
            console.error(e);
        }
        currentDismissId = null;
    };

    async function loadOrder(id, orderNumber) {
        try {
            const p = window.POS_CONFIG;
            const res = await fetch(`api/orders_get.php?id=${id}&branch_id=${p ? p.branch_id : 1}&is_test=${p ? p.is_test : 0}`);
            const data = await res.json();
            
            cart = [];
            data.items.forEach(item => {
                const allProducts = Object.values(menuData.products).flat();
                const product = allProducts.find(p => p.id == item.product_id);
                if (!product) return;
                
                const modifiers = item.modifier_ids.map(mid => menuData.modifiers.find(m => m.id == mid)).filter(Boolean);
                
                cart.push({
                    cartItemId: Date.now().toString() + Math.random(),
                    product: product,
                    quantity: parseInt(item.quantity),
                    modifiers: modifiers,
                    unitPrice: parseFloat(item.subtotal) / parseInt(item.quantity)
                });
            });

            loadedOrderId = id;
            loadedOrderNum.textContent = `(#${orderNumber})`;
            document.getElementById('cart-table-badge').textContent = data.table_source;
            renderCart();
        } catch (e) {
            console.error("Failed to load order:", e);
            showCustomAlert('Error', 'Failed to load order data.');
        }
    }

    async function completeOrder(id) {
        try {
            const res = await fetch('api/orders_complete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: id })
            });
            const data = await res.json();
            if (data.success) {
                fetchPendingOrders();
            } else {
                showCustomAlert('Error', data.error || 'Failed to complete order.');
            }
        } catch (e) {
            console.error(e);
            showCustomAlert('Error', 'Network error.');
        }
    }

    function clearLoadedOrder() {
        loadedOrderId = null;
        loadedOrderNum.textContent = '';
        document.getElementById('cart-table-badge').textContent = 'Cashier';
        cart = [];
        renderCart();
    }

    // --- Payment Flow Logic ---
    btnCheckout.onclick = () => {
        if (cart.length === 0) return;
        
        if (loadedOrderId) {
            // Force cash payment for pre-loaded table orders
            const total = cart.reduce((sum, item) => sum + (item.unitPrice * item.quantity), 0);
            openCashRegister(total);
        } else {
            // Allow payment selection for new walk-in orders
            digitalRefContainer.style.display = 'none';
            digitalRefInput.value = '';
            paymentModal.classList.add('active');
        }
    };

    function openCashRegister(total) {
        registerTotal.textContent = '₱' + total.toFixed(2);
        cashReceivedInput.value = '';
        registerChange.textContent = '₱0.00';
        btnConfirmCash.disabled = true;

        const quickCashContainer = document.getElementById('quick-cash-container');
        quickCashContainer.innerHTML = '';
        
        const bills = ['Exact', 100, 200, 500, 1000];
        
        bills.forEach(amt => {
            const btn = document.createElement('button');
            btn.className = 'payment-method-btn';
            btn.style.padding = '8px 12px';
            btn.style.fontSize = '14px';
            btn.style.flex = '1 1 calc(33% - 8px)';
            btn.textContent = amt === 'Exact' ? 'Exact' : '₱' + amt.toFixed(2);
            btn.onclick = () => {
                if (amt === 'Exact') {
                    cashReceivedInput.value = '₱' + total.toFixed(2);
                } else {
                    cashReceivedInput.value = '₱' + amt.toFixed(2);
                }
                cashReceivedInput.dispatchEvent(new Event('input'));
            };
            quickCashContainer.appendChild(btn);
        });

        cashRegisterModal.classList.add('active');
        setTimeout(() => cashReceivedInput.focus(), 100);
    }

    btnClosePayment.onclick = () => paymentModal.classList.remove('active');

    btnPayCash.onclick = () => {
        paymentModal.classList.remove('active');
        const total = cart.reduce((sum, item) => sum + (item.unitPrice * item.quantity), 0);
        openCashRegister(total);
    };

    if (btnPayGcash) {
        btnPayGcash.onclick = () => initiatePayMongoCheckout('gcash');
    }

    if (btnPayMaya) {
        btnPayMaya.onclick = () => initiatePayMongoCheckout('paymaya');
    }

    if (btnPayDigitalManual) {
        btnPayDigitalManual.onclick = () => {
            digitalRefContainer.style.display = digitalRefContainer.style.display === 'flex' ? 'none' : 'flex';
            if (digitalRefContainer.style.display === 'flex') digitalRefInput.focus();
        };
    } else if (btnPayDigital) {
        btnPayDigital.onclick = () => {
            digitalRefContainer.style.display = 'flex';
            digitalRefInput.focus();
        };
    }

    btnConfirmDigital.onclick = () => {
        const ref = digitalRefInput.value.trim();
        if (!ref) {
            alert('Please enter a reference number.');
            return;
        }
        submitFinalCheckout('DIGITAL', ref);
    };

    async function initiatePayMongoCheckout(method) {
        if (cart.length === 0 && !loadedOrderId) return;
        
        const methodUpper = method.toUpperCase() === 'PAYMAYA' ? 'MAYA' : method.toUpperCase();
        const displayMethod = methodUpper === 'MAYA' ? 'Maya' : 'GCash';

        // Prepare UI state
        if (paymongoBadgeMethod) {
            paymongoBadgeMethod.textContent = displayMethod;
            paymongoBadgeMethod.style.background = methodUpper === 'MAYA' ? 'rgba(16, 185, 129, 0.2)' : 'rgba(0, 122, 255, 0.2)';
            paymongoBadgeMethod.style.color = methodUpper === 'MAYA' ? '#6ee7b7' : '#93c5fd';
        }
        if (paymongoModalTitle) paymongoModalTitle.textContent = `Scan to Pay via ${displayMethod}`;
        if (paymongoPollStatusText) paymongoPollStatusText.textContent = `Awaiting customer payment confirmation on ${displayMethod}...`;
        if (paymongoQrImg) {
            paymongoQrImg.src = '';
            paymongoQrImg.style.display = 'none';
        }

        try {
            let targetOrderId = loadedOrderId;
            let orderNumber = loadedOrderId ? loadedOrderNum.textContent : null;
            let grossAmount = 0;

            if (!targetOrderId) {
                // 1. Create walk-in order first with UNPAID status
                const payload = {
                    table_source: 'Cashier Walk-in',
                    payment_status: 'UNPAID',
                    payment_method: methodUpper,
                    payment_reference: null,
                    cashier_id: activeCashierId,
                    pos_session_id: currentSessionId,
                    is_test: window.POS_CONFIG ? window.POS_CONFIG.is_test : 0,
                    branch_id: window.POS_CONFIG ? window.POS_CONFIG.branch_id : 1,
                    cart: cart.map(item => ({
                        product_id: item.product.id,
                        quantity: item.quantity,
                        modifier_ids: item.modifiers.map(m => m.id)
                    }))
                };

                const orderRes = await fetch('api/orders.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const orderData = await orderRes.json();
                if (!orderData.success) {
                    throw new Error(orderData.error || 'Failed to initialize order');
                }
                targetOrderId = orderData.order_id;
                orderNumber = orderData.order_number;
                grossAmount = orderData.gross_total || orderData.total_price;
            } else {
                grossAmount = cart.reduce((sum, item) => sum + (item.unitPrice * item.quantity), 0);
            }

            // 2. Call payment_intent_create.php
            const intentRes = await fetch('api/payment_intent_create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    order_id: targetOrderId,
                    method: method,
                    shift_id: currentSessionId
                })
            });
            const intentData = await intentRes.json();
            if (!intentData.success) {
                throw new Error(intentData.error || 'Failed to create payment intent');
            }

            // 3. Update PayMongo Modal
            if (paymongoModalAmount) paymongoModalAmount.textContent = `₱${parseFloat(intentData.gross_amount || grossAmount).toFixed(2)}`;
            if (paymongoQrImg) {
                paymongoQrImg.src = intentData.qr_code_url;
                paymongoQrImg.style.display = 'block';
            }
            if (paymongoCheckoutBtn) {
                paymongoCheckoutBtn.href = intentData.checkout_url || '#';
            }

            // Show modal and hide selection modal
            paymentModal.classList.remove('active');
            paymongoModal.classList.add('active');

            // 4. Start live polling
            if (paymongoPollInterval) clearInterval(paymongoPollInterval);
            paymongoPollInterval = setInterval(async () => {
                try {
                    const stRes = await fetch(`api/payment_status.php?order_id=${targetOrderId}`);
                    const stData = await stRes.json();

                    if (stData.success && stData.is_paid) {
                        clearInterval(paymongoPollInterval);
                        paymongoPollInterval = null;
                        paymongoModal.classList.remove('active');

                        showReceiptModal({
                            orderNo: orderNumber || stData.order_number || ('ORD-' + targetOrderId),
                            date: new Date().toLocaleDateString(),
                            time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                            cashier: activeCashierName,
                            shift_id: currentSessionId,
                            items: cart,
                            method: methodUpper,
                            received: null,
                            gross_total: grossAmount,
                            net_payable: grossAmount,
                            is_test: window.POS_CONFIG ? window.POS_CONFIG.is_test : false
                        });

                        clearLoadedOrder();
                        fetchPendingOrders();
                        fetchSalesSummary();
                    }
                } catch (pollErr) {
                    console.error('Polling error:', pollErr);
                }
            }, 2000);

        } catch (err) {
            console.error(err);
            showCustomAlert('Payment Initialization Error', err.message || 'Could not initiate PayMongo transaction.');
        }
    }

    function stopPayMongoPolling() {
        if (paymongoPollInterval) {
            clearInterval(paymongoPollInterval);
            paymongoPollInterval = null;
        }
        if (paymongoModal) paymongoModal.classList.remove('active');
    }

    if (btnClosePaymongo) btnClosePaymongo.onclick = stopPayMongoPolling;
    if (btnCancelPaymongo) btnCancelPaymongo.onclick = stopPayMongoPolling;

    btnCloseRegister.onclick = () => cashRegisterModal.classList.remove('active');

    // Input Validations
    cashReceivedInput.addEventListener('keydown', (e) => {
        if (['e', 'E', '-', '+'].includes(e.key)) {
            e.preventDefault();
        }
    });

    cashReceivedInput.addEventListener('blur', formatCurrencyInput);
    cashReceivedInput.addEventListener('focus', unformatCurrencyInput);

    cashReceivedInput.addEventListener('input', () => {
        const total = cart.reduce((sum, item) => sum + (item.unitPrice * item.quantity), 0);
        const received = parseFloat(cashReceivedInput.value.replace(/[^0-9.]/g, '')) || 0;
        const change = received - total;

        if (received > total + 5000) {
            registerChange.textContent = 'Amount Too Large';
            registerChange.style.color = '#e74c3c';
            btnConfirmCash.disabled = true;
        } else if (change >= 0 && received > 0) {
            registerChange.textContent = '₱' + change.toFixed(2);
            registerChange.style.color = '#2ecc71';
            btnConfirmCash.disabled = false;
        } else {
            registerChange.textContent = 'Insufficient Funds';
            registerChange.style.color = '#e74c3c';
            btnConfirmCash.disabled = true;
        }
    });

    btnConfirmCash.onclick = () => submitFinalCheckout('CASH', null);

    async function submitFinalCheckout(method, reference) {
        if (method === 'CASH') {
            btnConfirmCash.disabled = true;
            btnConfirmCash.textContent = 'Processing...';
        } else {
            btnConfirmDigital.disabled = true;
            btnConfirmDigital.textContent = 'Processing...';
        }

        const payload = {
            table_source: 'Cashier Walk-in',
            payment_status: 'PAID',
            payment_method: method,
            payment_reference: reference,
            cashier_id: activeCashierId,
            pos_session_id: currentSessionId,
            is_test: window.POS_CONFIG ? window.POS_CONFIG.is_test : 0,
            branch_id: window.POS_CONFIG ? window.POS_CONFIG.branch_id : 1,
            cart: cart.map(item => ({
                product_id: item.product.id,
                quantity: item.quantity,
                modifier_ids: item.modifiers.map(m => m.id)
            }))
        };

        try {
            let url = 'api/orders.php';
            if (loadedOrderId) {
                url = 'api/orders_pay.php';
                payload.order_id = loadedOrderId;
            }

            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const data = await res.json();

            if (data.success) {
                cashRegisterModal.classList.remove('active');
                paymentModal.classList.remove('active');
                
                showReceiptModal({
                    orderNo: data.order_number || (loadedOrderId ? 'LOADED-' + loadedOrderId : 'UNKNOWN'),
                    date: new Date().toLocaleDateString(),
                    time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                    cashier: activeCashierName,
                    shift_id: currentSessionId,
                    items: cart,
                    method: method,
                    received: method === 'CASH' ? (parseFloat(cashReceivedInput.value.replace(/[^0-9.]/g, '')) || 0) : null,
                    gross_total: data.gross_total,
                    vat_amount: data.vat_amount,
                    discount_amount: data.discount_amount,
                    discount_type: data.discount_type,
                    net_payable: data.net_payable,
                    is_test: window.POS_CONFIG ? window.POS_CONFIG.is_test : false
                });
                
                clearLoadedOrder();
                fetchPendingOrders();
            } else {
                showCustomAlert('Checkout Failed', data.error || 'Unknown error');
            }
        } catch (err) {
            console.error(err);
            showCustomAlert('Network Error', 'Error during checkout.');
        } finally {
            if (method === 'CASH') {
                btnConfirmCash.disabled = false;
                btnConfirmCash.textContent = 'Complete Transaction';
            } else {
                btnConfirmDigital.disabled = false;
                btnConfirmDigital.textContent = 'Confirm Payment';
            }
        }
    }

    // --- Auth & Session Functions ---
    async function checkCurrentSession() {
        try {
            const res = await fetch('api/pos_sessions.php?action=current_session');
            const data = await res.json();
            if (data.success && data.session) {
                currentSessionId = data.session.id;
                currentHbNameForShift = data.session.head_barista_name;
                currentHbUsernameForShift = data.session.head_barista_username || '';
            } else {
                currentSessionId = null;
                currentHbNameForShift = null;
                currentHbUsernameForShift = '';
            }
        } catch (err) {
            console.error('Failed to check session', err);
        }
    }

    async function fetchActiveBaristas() {
        try {
            const res = await fetch('api/pos_auth.php?action=get_active_baristas');
            const data = await res.json();
            if (data.success) {
                renderActiveBaristas(data.baristas);
            }
        } catch (err) {
            console.error('Failed to fetch active baristas', err);
        }
    }

    function renderActiveBaristas(baristas) {
        activeBaristasGrid.innerHTML = '';
        if (baristas.length === 0) {
            activeBaristasGrid.innerHTML = '<p style="color:var(--text-muted); grid-column: 1/-1; text-align:center;">No Baristas currently clocked in.</p>';
            return;
        }

        baristas.forEach(b => {
            const btn = document.createElement('button');
            btn.className = 'payment-method-btn';
            btn.style.height = '100px';
            btn.style.display = 'flex';
            btn.style.flexDirection = 'column';
            btn.style.justifyContent = 'center';
            btn.style.alignItems = 'center';
            
            let icon = b.position.includes('Head') ? 'star' : 'user';
            
            btn.innerHTML = `<i data-lucide="${icon}" style="margin-bottom:8px;"></i><span>${b.username}</span>`;
            
            btn.onclick = () => {
                if (!currentSessionId) {
                    alert("A Head Barista must open the Register Shift first!");
                    return;
                }
                selectedUnlockUserId = b.user_id;
                selectedBaristaNameEl.textContent = `Unlocking as: ${b.full_name || b.username}`;
                activeBaristasGrid.style.display = 'none';
                pinEntryArea.style.display = 'flex';
                unlockPinInput.value = '';
                unlockPinInput.focus();
            };
            
            activeBaristasGrid.appendChild(btn);
        });
        if (window.lucide) window.lucide.createIcons();
    }

    btnCancelUnlock.onclick = () => {
        pinEntryArea.style.display = 'none';
        activeBaristasGrid.style.display = 'grid';
        selectedUnlockUserId = null;
        document.getElementById('unlock-error-msg').style.display = 'none';
        unlockPinInput.value = '';
    };

    unlockPinInput.addEventListener('input', () => {
        document.getElementById('unlock-error-msg').style.display = 'none';
        btnSubmitUnlock.disabled = unlockPinInput.value.length === 0;
    });

    btnSubmitUnlock.onclick = async () => {
        const pin = unlockPinInput.value;
        if (!pin) return;
        
        btnSubmitUnlock.disabled = true;
        document.getElementById('unlock-error-msg').style.display = 'none';
        
        try {
            const res = await fetch('api/pos_auth.php?action=verify_pin', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ user_id: selectedUnlockUserId, password: pin })
            });
            const data = await res.json();
            if (data.success) {
                // Unlocked!
                activeCashierId = selectedUnlockUserId;
                activeCashierName = selectedBaristaNameEl.textContent.replace('Unlocking as: ', '');
                
                posLockScreen.classList.remove('active');
                pinEntryArea.style.display = 'none';
                activeBaristasGrid.style.display = 'grid';
                unlockPinInput.value = '';
                
                // Update cashier name in UI
                const userProfile = document.querySelector('.user-profile');
                if(userProfile) {
                    const nameSpan = userProfile.querySelectorAll('span')[1];
                    if (nameSpan) nameSpan.textContent = activeCashierName;
                    
                    const actionSpan = userProfile.querySelectorAll('span')[2];
                    if (actionSpan) {
                        actionSpan.textContent = '[Lock Terminal]';
                        actionSpan.style.display = 'inline';
                    }
                    
                    userProfile.onclick = lockTerminal;
                }
            } else {
                document.getElementById('unlock-error-msg').textContent = 'Invalid Password/PIN';
                document.getElementById('unlock-error-msg').style.display = 'block';
                unlockPinInput.value = '';
            }
        } catch (err) {
            console.error(err);
            document.getElementById('unlock-error-msg').textContent = 'Error verifying PIN';
            document.getElementById('unlock-error-msg').style.display = 'block';
        } finally {
            btnSubmitUnlock.disabled = unlockPinInput.value.length === 0;
        }
    };

    function lockTerminal() {
        activeCashierId = null;
        activeCashierName = null;
        posLockScreen.classList.add('active');
        
        const userProfile = document.querySelector('.user-profile');
        if(userProfile) {
            const nameSpan = userProfile.querySelectorAll('span')[1];
            if (nameSpan) nameSpan.textContent = 'Cashier Terminal';
            
            const actionSpan = userProfile.querySelectorAll('span')[2];
            if (actionSpan) {
                actionSpan.style.display = 'none';
            }
            userProfile.onclick = null;
        }

        checkCurrentSession();
        fetchActiveBaristas();
    }

    // Register Management
    const checkShiftInput = () => {
        document.getElementById('shift-error-msg').style.display = 'none';
        if (shiftHbId.style.display !== 'none') {
            btnShiftLogin.disabled = shiftHbPin.value.length === 0 || shiftHbId.value.trim() === '';
        } else {
            btnShiftLogin.disabled = shiftHbPin.value.length === 0;
        }
    };
    shiftHbId.addEventListener('input', checkShiftInput);
    shiftHbPin.addEventListener('input', checkShiftInput);

    btnManageRegister.onclick = () => {
        if (currentSessionId) {
            document.getElementById('shift-active-hb-label').style.display = 'block';
            document.getElementById('shift-active-hb-name').textContent = currentHbUsernameForShift;
            shiftHbId.style.display = 'none';
            shiftHbId.value = currentHbUsernameForShift;
        } else {
            document.getElementById('shift-active-hb-label').style.display = 'none';
            shiftHbId.style.display = 'block';
            shiftHbId.value = '';
        }
        shiftHbPin.value = '';
        document.getElementById('shift-error-msg').style.display = 'none';
        checkShiftInput();
        
        shiftLoginArea.style.display = 'block';
        shiftActionArea.style.display = 'none';
        registerShiftModal.classList.add('active');
    };

    btnCloseShiftModal.onclick = () => {
        registerShiftModal.classList.remove('active');
    };

    // Currency formatting helpers for shift inputs
    function formatCurrencyInput(e) {
        let val = e.target.value.replace(/[^0-9.]/g, '');
        if (val) {
            e.target.value = '₱' + parseFloat(val).toFixed(2);
        } else {
            e.target.value = '';
        }
    }

    function unformatCurrencyInput(e) {
        let val = e.target.value.replace(/[^0-9.]/g, '');
        if (val) {
            e.target.value = val;
        }
    }

    shiftStartingCash.addEventListener('blur', formatCurrencyInput);
    shiftStartingCash.addEventListener('focus', unformatCurrencyInput);
    shiftActualCash.addEventListener('blur', formatCurrencyInput);
    shiftActualCash.addEventListener('focus', unformatCurrencyInput);

    let currentHbNameForShift = null;

    let currentHbUsernameForShift = '';

    btnShiftLogin.onclick = async () => {
        let hbId = shiftHbId.value.trim();
        if (shiftHbId.style.display === 'none') {
            hbId = currentHbUsernameForShift;
        }
        const pin = shiftHbPin.value;
        if (!pin || !hbId) return;

        btnShiftLogin.disabled = true;
        document.getElementById('shift-error-msg').style.display = 'none';
        
        try {
            const res = await fetch('api/pos_auth.php?action=login_head_barista', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ username: hbId, password: pin })
            });
            const data = await res.json();
            
            if (data.success) {
                if (!currentSessionId && data.user.has_open_shift) {
                    document.getElementById('shift-error-msg').textContent = 'You already have an open shift on another terminal.';
                    document.getElementById('shift-error-msg').style.display = 'block';
                    shiftHbId.value = '';
                    shiftHbPin.value = '';
                    return;
                }
                
                currentHbIdForShift = data.user.id;
                currentHbNameForShift = data.user.name;
                currentHbUsernameForShift = hbId;
                shiftLoginArea.style.display = 'none';
                shiftActionArea.style.display = 'block';
                
                if (currentSessionId) {
                    shiftOpenUi.style.display = 'none';
                    shiftCloseUi.style.display = 'block';
                    shiftActualCash.value = '';
                } else {
                    shiftOpenUi.style.display = 'block';
                    shiftCloseUi.style.display = 'none';
                    shiftStartingCash.value = '';
                }
            } else {
                document.getElementById('shift-error-msg').textContent = data.error || 'Invalid username or password.';
                document.getElementById('shift-error-msg').style.display = 'block';
            }
        } catch (err) {
            document.getElementById('shift-error-msg').textContent = 'Error communicating with server.';
            document.getElementById('shift-error-msg').style.display = 'block';
        } finally {
            checkShiftInput();
        }
    };

    btnOpenShift.onclick = async () => {
        const startingCash = parseFloat(shiftStartingCash.value.replace(/[^0-9.]/g, '')) || 0;
        try {
            const res = await fetch('api/pos_sessions.php?action=open_shift', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ 
                    head_barista_id: currentHbIdForShift, 
                    head_barista_name: currentHbNameForShift, 
                    starting_cash: startingCash 
                })
            });
            const data = await res.json();
            if (data.success) {
                currentSessionId = data.session_id;
                registerShiftModal.classList.remove('active');
                showCustomAlert('Shift Opened', 'Shift Opened Successfully!', false);
            } else {
                showCustomAlert('Error', data.error || 'Failed to open shift');
            }
        } catch (err) {
            showCustomAlert('Error', 'Network Error');
        }
    };

    btnCloseShift.onclick = async () => {
        const actualCash = parseFloat(shiftActualCash.value.replace(/[^0-9.]/g, '')) || 0;
        if (isNaN(actualCash)) {
            showCustomAlert('Invalid Input', 'Please enter a valid cash amount');
            return;
        }

        try {
            const res = await fetch('api/pos_sessions.php?action=close_shift', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ 
                    session_id: currentSessionId, 
                    actual_cash: actualCash 
                })
            });
            const data = await res.json();
            if (data.success) {
                currentSessionId = null;
                registerShiftModal.classList.remove('active');
                
                shiftAlertTitle.textContent = 'Shift Closed';
                let discText = data.discrepancy === 0 ? '<span style="color:#2ecc71;">Perfect Match</span>' 
                             : (data.discrepancy > 0 ? `<span style="color:#2ecc71;">+₱${data.discrepancy.toFixed(2)} Over</span>` 
                             : `<span style="color:#e74c3c;">-₱${Math.abs(data.discrepancy).toFixed(2)} Short</span>`);
                
                shiftAlertMsg.innerHTML = `
                    Expected Cash: ₱${data.expected.toFixed(2)}<br>
                    Actual Cash: ₱${data.actual.toFixed(2)}<br><br>
                    Discrepancy: ${discText}
                `;
                shiftAlertModal.classList.add('active');
                
                // Force lock terminal if unlocked
                lockTerminal();
            } else {
                showCustomAlert('Error', data.error || 'Failed to close shift');
            }
        } catch (err) {
            showCustomAlert('Error', 'Network Error');
        }
    };

    btnShiftAlertOk.onclick = () => {
        shiftAlertModal.classList.remove('active');
    };
});
