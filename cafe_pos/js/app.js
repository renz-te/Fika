document.addEventListener('DOMContentLoaded', () => {
    // --- State ---
    let menuData = { products: {}, modifiers: [] };
    let currentCategory = 'All';
    let cart = []; // Array of {cartItemId, product, quantity, modifiers: [], subtotal}
    
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

    const btnTableLogout = document.getElementById('btn-table-logout');
    const logoutModal = document.getElementById('logout-modal');
    const btnLogoutCancel = document.getElementById('btn-logout-cancel');
    const btnLogoutConfirm = document.getElementById('btn-logout-confirm');
    const logoutPinInput = document.getElementById('logout-pin-input');

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

    const successOverlay = document.getElementById('success-overlay');
    const successOrderNum = document.getElementById('success-order-num');
    const btnNewOrder = document.getElementById('btn-new-order');
    const btnCheckoutText = document.getElementById('btn-checkout-text');
    
    const successIcon = document.getElementById('success-icon');
    const successTitle = document.getElementById('success-title');
    const successMessage = document.getElementById('success-message');

    const paymentModal = document.getElementById('payment-modal');
    const btnClosePayment = document.getElementById('btn-close-payment');
    const btnPayCash = document.getElementById('btn-pay-cash');
    const btnPayDigital = document.getElementById('btn-pay-digital');
    const digitalRefContainer = document.getElementById('digital-ref-container');
    const digitalRefInput = document.getElementById('digital-ref-input');
    const btnConfirmDigital = document.getElementById('btn-confirm-digital');

    // --- Initialization ---
    updateClock();
    setInterval(updateClock, 1000);
    fetchMenu();

    // --- Functions ---
    function updateClock() {
        const now = new Date();
        document.getElementById('time-display').textContent = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
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
        if (product.category === 'Pastries') {
            // Bypass modifier modal for pastries
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
            // Only add if this product explicitly allows this group
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
                            groups['Milk'].forEach(m => selectedModifiers.delete(m.id));
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
            btnCheckout.disabled = true;
            return;
        }

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
    }

    window.updateCartQty = (index, delta) => {
        const item = cart[index];
        item.quantity += delta;
        if (item.quantity <= 0) {
            cart.splice(index, 1);
        }
        renderCart();
    };

    // --- Checkout Logic ---
    btnCheckout.onclick = () => {
        if (cart.length === 0) return;
        digitalRefContainer.style.display = 'none';
        digitalRefInput.value = '';
        paymentModal.classList.add('active');
    };

    btnClosePayment.onclick = () => {
        paymentModal.classList.remove('active');
    };

    btnPayCash.onclick = () => {
        submitCheckout('CASH_AT_COUNTER', null, 'UNPAID');
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
        submitCheckout('DIGITAL', ref, 'PAID');
    };

    async function submitCheckout(method, reference, status) {
        paymentModal.classList.remove('active');
        btnCheckout.disabled = true;
        btnCheckoutText.textContent = 'Processing...';

        const payload = {
            table_source: 'Table 1',
            payment_status: status,
            payment_method: method,
            payment_reference: reference,
            is_test: window.POS_CONFIG ? window.POS_CONFIG.is_test : 0,
            branch_id: window.POS_CONFIG ? window.POS_CONFIG.branch_id : 1,
            cart: cart.map(item => ({
                product_id: item.product.id,
                quantity: item.quantity,
                modifier_ids: item.modifiers.map(m => m.id)
            }))
        };

        try {
            const res = await fetch('api/orders.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const data = await res.json();

            if (data.success) {
                if (status === 'UNPAID') {
                    successIcon.innerHTML = '<i data-lucide="ticket"></i>';
                    successTitle.textContent = 'Order Placed!';
                    successMessage.textContent = 'Please proceed to the counter to pay and provide Queue Number:';
                    if (window.lucide) window.lucide.createIcons();

                    // Show Success
                    successOrderNum.textContent = data.order_number;
                    successOverlay.classList.add('active');
                    
                    // Clear cart
                    cart = [];
                    renderCart();
                    btnCheckoutText.textContent = 'Place Order';
                } else {
                    // For DIGITAL payment, show receipt to print
                    showReceiptModal(data.order_number, payload.cart);
                    
                    // Also clear cart in background
                    cart = [];
                    renderCart();
                    btnCheckoutText.textContent = 'Place Order';
                }
            } else {
                alert("Checkout failed: " + (data.error || "Unknown error"));
                btnCheckout.disabled = false;
                btnCheckoutText.textContent = 'Place Order';
                renderCart();
            }
        } catch (err) {
            console.error(err);
            alert("Network error during checkout.");
            btnCheckout.disabled = false;
            btnCheckoutText.textContent = 'Place Order';
            renderCart();
        }
    }

    function showReceiptModal(orderNumber, cartData) {
        document.getElementById('receipt-order-no').textContent = orderNumber;
        const now = new Date();
        document.getElementById('receipt-date').textContent = now.toLocaleDateString();
        document.getElementById('receipt-time').textContent = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        
        const tbody = document.getElementById('receipt-items');
        tbody.innerHTML = '';
        
        let subtotal = 0;
        
        cartData.forEach(item => {
            const tr = document.createElement('tr');
            
            const allProducts = Object.values(menuData.products).flat();
            const product = allProducts.find(p => p.id == item.product_id);
            if (!product) return;
            
            let itemSubtotal = parseFloat(product.price);
            let modNames = [];
            
            if (item.modifier_ids && item.modifier_ids.length > 0) {
                item.modifier_ids.forEach(mid => {
                    const mod = menuData.modifiers.find(m => m.id == mid);
                    if (mod) {
                        itemSubtotal += parseFloat(mod.price_adjustment);
                        modNames.push(mod.name);
                    }
                });
            }
            
            const price = itemSubtotal * item.quantity;
            subtotal += price;
            
            let html = `
                <td style="padding-bottom: 8px;">
                    <div>${product.name}</div>
            `;
            if (modNames.length > 0) {
                html += `<div style="font-size: 11px; color: #777;">+ ${modNames.join(', ')}</div>`;
            }
            html += `</td>
                <td style="text-align: center; padding-bottom: 8px;">${item.quantity}</td>
                <td style="text-align: right; padding-bottom: 8px;">₱${price.toFixed(2)}</td>
            `;
            tr.innerHTML = html;
            tbody.appendChild(tr);
        });
        
        const vat = subtotal * 0.12;
        const total = subtotal; 
        const netSubtotal = total - vat;
        
        document.getElementById('receipt-subtotal').textContent = '₱' + netSubtotal.toFixed(2);
        document.getElementById('receipt-vat').textContent = '₱' + vat.toFixed(2);
        document.getElementById('receipt-total').textContent = '₱' + total.toFixed(2);
        
        document.getElementById('receipt-tender').textContent = '₱' + total.toFixed(2);
        
        document.getElementById('receipt-modal').classList.add('active');
    }

    const btnCloseReceipt = document.getElementById('btn-close-receipt');
    if (btnCloseReceipt) {
        btnCloseReceipt.onclick = () => {
            window.print();
            setTimeout(() => {
                document.getElementById('receipt-modal').classList.remove('active');
                
                // Show standard success screen after printing
                successIcon.innerHTML = '<i data-lucide="sparkles"></i>';
                successTitle.textContent = 'Payment Successful!';
                successMessage.textContent = 'Please wait for your number to be called:';
                successOrderNum.textContent = document.getElementById('receipt-order-no').textContent;
                successOverlay.classList.add('active');
                if (window.lucide) window.lucide.createIcons();
            }, 500);
        };
    }

    btnNewOrder.onclick = () => {
        successOverlay.classList.remove('active');
    };

    // --- Logout Logic ---
    if (btnTableLogout) {
        btnTableLogout.onclick = () => {
            logoutPinInput.value = '';
            logoutModal.classList.add('active');
            setTimeout(() => logoutPinInput.focus(), 100);
        };
    }

    if (btnLogoutCancel) {
        btnLogoutCancel.onclick = () => {
            logoutModal.classList.remove('active');
        };
    }

    if (btnClearCart) {
        btnClearCart.onclick = () => {
            if (cart.length > 0) {
                clearModal.classList.add('active');
            }
        };
    }

    if (btnLogoutConfirm) {
        btnLogoutConfirm.onclick = () => {
            if (logoutPinInput.value === '1234') {
                logoutModal.classList.remove('active');
                window.location.href = '../cafe_hrms/logout.php';
            } else {
                alert('Incorrect PIN');
                logoutPinInput.value = '';
                logoutPinInput.focus();
            }
        };
    }

    if (btnClearNo) {
        btnClearNo.onclick = () => clearModal.classList.remove('active');
    }

    if (btnClearYes) {
        btnClearYes.onclick = () => {
            cart = [];
            renderCart();
            clearModal.classList.remove('active');
        };
    }
});
