document.addEventListener('DOMContentLoaded', () => {
    // --- State ---
    let menuData = { products: {}, modifiers: [] };
    let currentCategory = 'All';
    let cart = []; // Array of {cartItemId, product, quantity, modifiers: [], subtotal}
    
    // --- Receipt Logic ---
    function showReceiptModal(data) {
        document.getElementById('receipt-order-no').textContent = data.orderNo;
        document.getElementById('receipt-date').textContent = data.date;
        document.getElementById('receipt-time').textContent = data.time;
        document.getElementById('receipt-cashier').textContent = data.cashier;
        
        const tbody = document.getElementById('receipt-items');
        tbody.innerHTML = '';
        
        let subtotal = 0;
        
        data.items.forEach(item => {
            const tr = document.createElement('tr');
            const itemName = item.product.name;
            const price = (item.unitPrice * item.quantity);
            subtotal += price;
            
            const isVoided = item.quantity < 0;
            
            let html = `
                <td style="padding-bottom: 8px; ${isVoided ? 'text-decoration: line-through; color: #e74c3c;' : ''}">
                    <div>${isVoided ? '[VOID] ' : ''}${itemName}</div>
            `;
            if (item.modifiers && item.modifiers.length > 0) {
                const mods = item.modifiers.map(m => m.name).join(', ');
                html += `<div style="font-size: 11px; color: ${isVoided ? '#e74c3c' : '#777'};">+ ${mods}</div>`;
            }
            html += `</td>
                <td style="text-align: center; padding-bottom: 8px; ${isVoided ? 'color: #e74c3c;' : ''}">${item.quantity}</td>
                <td style="text-align: right; padding-bottom: 8px; ${isVoided ? 'color: #e74c3c;' : ''}">₱${price.toFixed(2)}</td>
            `;
            tr.innerHTML = html;
            tbody.appendChild(tr);
        });
        
        document.getElementById('receipt-subtotal').textContent = '₱' + (data.gross_total || subtotal).toFixed(2);
        
        const vat = data.vat_amount !== undefined ? data.vat_amount : (subtotal * 0.12);
        if (vat > 0) {
            document.getElementById('receipt-vat-label').textContent = 'VAT (12%)';
            document.getElementById('receipt-vat').textContent = '₱' + vat.toFixed(2);
        } else {
            document.getElementById('receipt-vat-label').textContent = 'VAT (Exempt)';
            document.getElementById('receipt-vat').textContent = '₱0.00';
        }
        
        if (data.discount_amount > 0) {
            document.getElementById('receipt-discount-container').style.display = 'flex';
            document.getElementById('receipt-discount').textContent = '-₱' + data.discount_amount.toFixed(2);
        } else {
            document.getElementById('receipt-discount-container').style.display = 'none';
        }
        
        const total = data.net_payable !== undefined ? data.net_payable : subtotal;
        document.getElementById('receipt-total').textContent = '₱' + total.toFixed(2);
        
        document.getElementById('receipt-tender-label').textContent = data.method === 'CASH' ? 'Cash Received' : 'Digital Ref.';
        
        if (data.method === 'CASH') {
            document.getElementById('receipt-tender').textContent = '₱' + data.received.toFixed(2);
            document.getElementById('receipt-change').textContent = '₱' + (data.received - total).toFixed(2);
        } else {
            document.getElementById('receipt-tender').textContent = 'E-Payment';
            document.getElementById('receipt-change').textContent = '₱0.00';
        }
        
        document.getElementById('receipt-modal').classList.add('active');
    }
    
    document.getElementById('btn-close-receipt').onclick = () => {
        window.print();
        setTimeout(() => {
            document.getElementById('receipt-modal').classList.remove('active');
            cart = [];
            renderCart();
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
    const btnPayDigital = document.getElementById('btn-pay-digital');
    const digitalRefContainer = document.getElementById('digital-ref-container');
    const digitalRefInput = document.getElementById('digital-ref-input');
    const btnConfirmDigital = document.getElementById('btn-confirm-digital');

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

    btnPayDigital.onclick = () => {
        digitalRefContainer.style.display = 'flex';
        digitalRefInput.focus();
    };

    btnConfirmDigital.onclick = () => {
        const ref = digitalRefInput.value.trim();
        if (!ref) {
            alert('Please enter a reference number.');
            return;
        }
        submitFinalCheckout('DIGITAL', ref);
    };

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
                    items: cart,
                    method: method,
                    received: method === 'CASH' ? (parseFloat(cashReceivedInput.value.replace(/[^0-9.]/g, '')) || 0) : null,
                    gross_total: data.gross_total,
                    vat_amount: data.vat_amount,
                    discount_amount: data.discount_amount,
                    net_payable: data.net_payable
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
