<?php
require_once __DIR__ . '/../app/bootstrap.php';

Auth::requireLogin();
Rbac::require_permission('inventory.view');

$user = Auth::user();
$canAdjust = Rbac::can('inventory.adjust');
$csrfToken = Csrf::getToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recipe Management & Cost Breakdown - Fika</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <style>
        .tab-btn.active {
            border-bottom: 2px solid #2563eb;
            color: #2563eb;
            font-weight: 600;
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans antialiased min-h-screen flex">

    <?php require_once __DIR__ . '/../fika_hrms/includes/header.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto">
        <!-- Module Header & Sub-Nav -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center font-bold text-xl shadow-sm">
                        <i class="fa-solid fa-boxes-stacked text-base"></i>
                    </div>
                    <div>
                        <span class="text-base font-extrabold text-slate-900 tracking-tight block leading-none">Recipe BOM & Costing</span>
                        <span class="text-[10px] uppercase font-bold text-blue-700 tracking-wider">Bill of Materials & COGS Breakdown</span>
                    </div>
                </div>

                <nav class="flex items-center gap-6 text-sm font-semibold text-slate-600">
                    <a href="fika_inventory_dashboard.php" class="hover:text-slate-900 transition-colors py-5">Dashboard</a>
                    <a href="fika_inventory_stock.php" class="hover:text-slate-900 transition-colors py-5">Stock Ledger</a>
                    <a href="fika_inventory_procurement.php" class="hover:text-slate-900 transition-colors py-5">Procurement</a>
                    <a href="fika_inventory_recipes.php" class="text-blue-600 font-bold border-b-2 border-blue-600 py-5">Recipe COGS</a>
                </nav>
            </div>
        </header>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 w-full flex-1">

    <!-- Main Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Toast / Alerts -->
        <div id="alertBox" class="hidden mb-6 p-4 rounded-lg text-sm border shadow-sm transition-all" role="alert"></div>

        <!-- Tab Navigation -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mb-6">
            <div class="border-b border-gray-200 px-6 pt-2 bg-gray-50/50 flex justify-between items-center">
                <nav class="flex space-x-8 -mb-px" aria-label="Tabs">
                    <button type="button" onclick="switchTab('products')" id="tabBtnProducts" class="tab-btn active py-4 px-1 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                        ☕ Product Recipes
                    </button>
                    <button type="button" onclick="switchTab('modifiers')" id="tabBtnModifiers" class="tab-btn py-4 px-1 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                        🥛 Modifier Recipes
                    </button>
                    <button type="button" onclick="switchTab('costReport')" id="tabBtnCostReport" class="tab-btn py-4 px-1 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                        📊 Cost Per Product Report
                    </button>
                </nav>

                <div>
                    <button type="button" onclick="loadRecipesData()" class="text-xs text-gray-600 hover:text-gray-900 border border-gray-300 rounded px-2.5 py-1.5 bg-white shadow-sm">
                        🔄 Refresh Data
                    </button>
                </div>
            </div>

            <!-- Tab 1: Product Recipes -->
            <div id="tabPanelProducts" class="p-6">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <!-- Left: Products List -->
                    <div class="lg:col-span-5 border border-gray-200 rounded-lg overflow-hidden bg-white">
                        <div class="p-3 bg-gray-50 border-b border-gray-200">
                            <input type="text" id="searchProduct" oninput="filterProductsList()" placeholder="Filter products..." class="w-full text-xs border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div id="productList" class="divide-y divide-gray-100 max-h-[600px] overflow-y-auto">
                            <div class="p-4 text-center text-gray-400 text-xs">Loading products...</div>
                        </div>
                    </div>

                    <!-- Right: Product Recipe Details & Ingredient Adder -->
                    <div class="lg:col-span-7 space-y-5">
                        <div id="noProductSelected" class="p-12 text-center border-2 border-dashed border-gray-200 rounded-xl text-gray-400 text-sm">
                            Select a product from the list on the left to view and edit its ingredient recipe.
                        </div>

                        <div id="productRecipePanel" class="hidden space-y-5">
                            <!-- Product Summary Card -->
                            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-5 shadow-sm flex justify-between items-center">
                                <div>
                                    <div id="selectedProdCategory" class="text-xs uppercase font-bold tracking-wider text-blue-600">Category</div>
                                    <h2 id="selectedProdName" class="text-xl font-bold text-gray-900 mt-0.5">Product Name</h2>
                                    <div class="text-xs text-gray-600 mt-1">Retail Price: <strong id="selectedProdPrice" class="font-mono text-gray-900">₱0.00</strong></div>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs font-semibold text-gray-500 uppercase">Cost of Goods (COGS)</div>
                                    <div id="selectedProdCogs" class="text-2xl font-bold font-mono text-emerald-700">₱0.00</div>
                                    <div id="selectedProdMargin" class="text-xs font-semibold text-emerald-800">Margin: 0%</div>
                                </div>
                            </div>

                            <!-- Ingredients Table -->
                            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                                <div class="px-5 py-3.5 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                                    <h3 class="text-sm font-bold text-gray-800">Recipe Ingredients (Bill of Materials)</h3>
                                    <span id="ingredientCountBadge" class="text-xs bg-gray-200 text-gray-700 px-2 py-0.5 rounded-full font-semibold">0 items</span>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                                        <thead class="bg-gray-50 text-gray-500 uppercase font-semibold">
                                            <tr>
                                                <th class="px-4 py-3 text-left">Ingredient</th>
                                                <th class="px-4 py-3 text-right">Qty Required</th>
                                                <th class="px-4 py-3 text-left">Unit</th>
                                                <th class="px-4 py-3 text-right">Unit Cost</th>
                                                <th class="px-4 py-3 text-right">Total Cost</th>
                                                <th class="px-4 py-3 text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="ingredientsTableBody" class="divide-y divide-gray-100 bg-white">
                                            <!-- Dynamically populated -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Add Ingredient Form -->
                            <?php if ($canAdjust): ?>
                                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700 mb-3">Add / Update Ingredient in Recipe</h4>
                                    <form onsubmit="handleSaveIngredient(event, 'product')" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                                        <div class="sm:col-span-6">
                                            <label class="block text-xs font-medium text-gray-700 mb-1">Inventory Ingredient</label>
                                            <select id="prodIngItemSelect" required class="w-full text-xs border border-gray-300 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                <option value="">-- Choose Ingredient --</option>
                                            </select>
                                        </div>
                                        <div class="sm:col-span-4">
                                            <label class="block text-xs font-medium text-gray-700 mb-1">Required Quantity</label>
                                            <input type="number" step="0.01" min="0.01" id="prodIngQtyInput" required placeholder="e.g. 20.00" class="w-full text-xs border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                        <div class="sm:col-span-2">
                                            <button type="submit" class="w-full px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs rounded-lg shadow-sm">
                                                Add / Save
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Modifier Recipes (Constraint 4) -->
            <div id="tabPanelModifiers" class="p-6 hidden">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <!-- Left: Modifiers List -->
                    <div class="lg:col-span-5 border border-gray-200 rounded-lg overflow-hidden bg-white">
                        <div class="p-3 bg-gray-50 border-b border-gray-200">
                            <input type="text" id="searchModifier" oninput="filterModifiersList()" placeholder="Filter modifiers..." class="w-full text-xs border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div id="modifierList" class="divide-y divide-gray-100 max-h-[600px] overflow-y-auto">
                            <div class="p-4 text-center text-gray-400 text-xs">Loading modifiers...</div>
                        </div>
                    </div>

                    <!-- Right: Modifier Recipe Details & Ingredient Adder -->
                    <div class="lg:col-span-7 space-y-5">
                        <div id="noModifierSelected" class="p-12 text-center border-2 border-dashed border-gray-200 rounded-xl text-gray-400 text-sm">
                            Select a modifier from the list on the left to map its inventory deduction.
                        </div>

                        <div id="modifierRecipePanel" class="hidden space-y-5">
                            <!-- Modifier Summary Card -->
                            <div class="bg-gradient-to-r from-purple-50 to-pink-50 border border-purple-200 rounded-xl p-5 shadow-sm flex justify-between items-center">
                                <div>
                                    <div class="text-xs uppercase font-bold tracking-wider text-purple-600">Modifier Add-on</div>
                                    <h2 id="selectedModName" class="text-xl font-bold text-gray-900 mt-0.5">Modifier Name</h2>
                                    <div class="text-xs text-gray-600 mt-1">Extra Price: <strong id="selectedModPrice" class="font-mono text-gray-900">₱0.00</strong></div>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs font-semibold text-gray-500 uppercase">Ingredient Cost</div>
                                    <div id="selectedModCogs" class="text-2xl font-bold font-mono text-purple-700">₱0.00</div>
                                </div>
                            </div>

                            <!-- Modifier Ingredients Table -->
                            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                                <div class="px-5 py-3.5 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                                    <h3 class="text-sm font-bold text-gray-800">Modifier Ingredient Deductions</h3>
                                    <span id="modIngredientCountBadge" class="text-xs bg-gray-200 text-gray-700 px-2 py-0.5 rounded-full font-semibold">0 items</span>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                                        <thead class="bg-gray-50 text-gray-500 uppercase font-semibold">
                                            <tr>
                                                <th class="px-4 py-3 text-left">Ingredient</th>
                                                <th class="px-4 py-3 text-right">Qty Required</th>
                                                <th class="px-4 py-3 text-left">Unit</th>
                                                <th class="px-4 py-3 text-right">Unit Cost</th>
                                                <th class="px-4 py-3 text-right">Total Cost</th>
                                                <th class="px-4 py-3 text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="modIngredientsTableBody" class="divide-y divide-gray-100 bg-white">
                                            <!-- Dynamically populated -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Add Modifier Ingredient Form -->
                            <?php if ($canAdjust): ?>
                                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700 mb-3">Add / Update Modifier Ingredient</h4>
                                    <form onsubmit="handleSaveIngredient(event, 'modifier')" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                                        <div class="sm:col-span-6">
                                            <label class="block text-xs font-medium text-gray-700 mb-1">Inventory Ingredient</label>
                                            <select id="modIngItemSelect" required class="w-full text-xs border border-gray-300 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                <option value="">-- Choose Ingredient --</option>
                                            </select>
                                        </div>
                                        <div class="sm:col-span-4">
                                            <label class="block text-xs font-medium text-gray-700 mb-1">Required Quantity</label>
                                            <input type="number" step="0.01" min="0.01" id="modIngQtyInput" required placeholder="e.g. 0.25" class="w-full text-xs border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                        <div class="sm:col-span-2">
                                            <button type="submit" class="w-full px-3 py-2 bg-purple-600 hover:bg-purple-700 text-white font-semibold text-xs rounded-lg shadow-sm">
                                                Add / Save
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Cost Per Product Report (Task 4) -->
            <div id="tabPanelCostReport" class="p-6 hidden">
                <div class="mb-5 flex justify-between items-center">
                    <div>
                        <h3 class="text-base font-bold text-gray-900">Cost of Goods Sold (COGS) & Profit Margin Analysis</h3>
                        <p class="text-xs text-gray-500">Theoretical recipe cost computed from raw ingredient costs against retail selling prices.</p>
                    </div>
                </div>

                <div class="overflow-x-auto border border-gray-200 rounded-xl shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-gray-50 text-gray-600 font-semibold uppercase">
                            <tr>
                                <th class="px-5 py-3.5 text-left">Product</th>
                                <th class="px-4 py-3.5 text-left">Category</th>
                                <th class="px-4 py-3.5 text-right">Retail Price</th>
                                <th class="px-4 py-3.5 text-right">Recipe COGS</th>
                                <th class="px-4 py-3.5 text-right">Gross Profit</th>
                                <th class="px-4 py-3.5 text-right">Margin %</th>
                                <th class="px-5 py-3.5 text-left">Ingredients Breakdown</th>
                            </tr>
                        </thead>
                        <tbody id="costReportTableBody" class="divide-y divide-gray-200 bg-white">
                            <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">Loading cost report...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- JavaScript Application Logic -->
    <script>
        const CAN_ADJUST = <?= $canAdjust ? 'true' : 'false' ?>;
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const API_URL = 'api/fika_inventory_recipe.php';

        let productsData = [];
        let modifiersData = [];
        let inventoryItems = [];
        let selectedProduct = null;
        let selectedModifier = null;

        document.addEventListener('DOMContentLoaded', () => {
            loadRecipesData();
        });

        function switchTab(tab) {
            const tabs = ['products', 'modifiers', 'costReport'];
            tabs.forEach(t => {
                const panel = document.getElementById('tabPanel' + t.charAt(0).toUpperCase() + t.slice(1));
                const btn = document.getElementById('tabBtn' + t.charAt(0).toUpperCase() + t.slice(1));
                if (t === tab) {
                    panel.classList.remove('hidden');
                    btn.classList.add('active');
                } else {
                    panel.classList.add('hidden');
                    btn.classList.remove('active');
                }
            });

            if (tab === 'costReport') {
                loadCostReport();
            }
        }

        function showAlert(msg, isError = false) {
            const box = document.getElementById('alertBox');
            box.className = isError 
                ? 'mb-6 p-4 rounded-lg text-sm border shadow-sm transition-all bg-red-50 text-red-800 border-red-200'
                : 'mb-6 p-4 rounded-lg text-sm border shadow-sm transition-all bg-green-50 text-green-800 border-green-200';
            box.innerHTML = (isError ? '<strong>Error:</strong> ' : '<strong>Success:</strong> ') + escapeHtml(msg);
            box.classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function hideAlert() {
            document.getElementById('alertBox').classList.add('hidden');
        }

        function escapeHtml(text) {
            if (!text) return '';
            return String(text)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        async function loadRecipesData() {
            try {
                const res = await fetch(`${API_URL}?action=list`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    showAlert(data.error || 'Failed to load recipe data.', true);
                    return;
                }

                productsData = data.products || [];
                modifiersData = data.modifiers || [];
                inventoryItems = data.inventory_items || [];

                populateDropdowns();
                renderProductsList(productsData);
                renderModifiersList(modifiersData);

                // If currently selected, re-render
                if (selectedProduct) {
                    const fresh = productsData.find(p => p.id === selectedProduct.id);
                    if (fresh) selectProduct(fresh);
                }
                if (selectedModifier) {
                    const freshMod = modifiersData.find(m => m.id === selectedModifier.id);
                    if (freshMod) selectModifier(freshMod);
                }
            } catch (err) {
                showAlert('Network error: ' + err.message, true);
            }
        }

        function populateDropdowns() {
            const prodSelect = document.getElementById('prodIngItemSelect');
            const modSelect = document.getElementById('modIngItemSelect');

            const optionsHtml = '<option value="">-- Choose Ingredient --</option>' +
                inventoryItems.map(item => `
                    <option value="${item.id}">
                        ${escapeHtml(item.item_name)} (${escapeHtml(item.unit)}) - ₱${parseFloat(item.unit_cost).toFixed(2)}
                    </option>
                `).join('');

            if (prodSelect) prodSelect.innerHTML = optionsHtml;
            if (modSelect) modSelect.innerHTML = optionsHtml;
        }

        // ==================== Products Recipe Handling ====================
        function renderProductsList(list) {
            const container = document.getElementById('productList');
            if (!list || list.length === 0) {
                container.innerHTML = '<div class="p-4 text-center text-gray-400 text-xs">No products found.</div>';
                return;
            }

            container.innerHTML = list.map(p => {
                const isSelected = selectedProduct && selectedProduct.id === p.id;
                const cogs = parseFloat(p.cogs || 0);
                const hasRecipe = p.ingredients && p.ingredients.length > 0;

                return `
                    <div onclick="onProductClick(${p.id})" class="p-3.5 cursor-pointer hover:bg-gray-50 flex justify-between items-center transition-colors ${isSelected ? 'bg-blue-50/70 border-l-4 border-blue-600' : ''}">
                        <div>
                            <div class="font-semibold text-gray-900 text-xs">${escapeHtml(p.name)}</div>
                            <div class="text-[11px] text-gray-500">${escapeHtml(p.category)} · ₱${parseFloat(p.price).toFixed(2)}</div>
                        </div>
                        <div class="text-right">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold ${hasRecipe ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'}">
                                ${hasRecipe ? `COGS: ₱${cogs.toFixed(2)}` : 'No Recipe'}
                            </span>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function filterProductsList() {
            const q = (document.getElementById('searchProduct').value || '').toLowerCase();
            const filtered = productsData.filter(p => p.name.toLowerCase().includes(q) || (p.category && p.category.toLowerCase().includes(q)));
            renderProductsList(filtered);
        }

        function onProductClick(prodId) {
            const prod = productsData.find(p => p.id === prodId);
            if (prod) selectProduct(prod);
        }

        function selectProduct(prod) {
            selectedProduct = prod;
            renderProductsList(productsData);

            document.getElementById('noProductSelected').classList.add('hidden');
            const panel = document.getElementById('productRecipePanel');
            panel.classList.remove('hidden');

            document.getElementById('selectedProdName').textContent = prod.name;
            document.getElementById('selectedProdCategory').textContent = prod.category;
            document.getElementById('selectedProdPrice').textContent = `₱${parseFloat(prod.price).toFixed(2)}`;

            const cogs = parseFloat(prod.cogs || 0);
            const price = parseFloat(prod.price || 0);
            const margin = price > 0 ? ((price - cogs) / price) * 100 : 0;

            document.getElementById('selectedProdCogs').textContent = `₱${cogs.toFixed(2)}`;
            document.getElementById('selectedProdMargin').textContent = `Margin: ${margin.toFixed(1)}% (Profit: ₱${(price - cogs).toFixed(2)})`;

            renderIngredientsTable(prod.ingredients || []);
        }

        function renderIngredientsTable(ingredients) {
            const tbody = document.getElementById('ingredientsTableBody');
            document.getElementById('ingredientCountBadge').textContent = `${ingredients.length} items`;

            if (!ingredients || ingredients.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-8 text-center text-gray-400 italic">No ingredients mapped for this recipe yet.</td></tr>`;
                return;
            }

            tbody.innerHTML = ingredients.map(ing => {
                const qty = parseFloat(ing.quantity || ing.quantity_required);
                const unitCost = parseFloat(ing.unit_cost);
                const subtotal = parseFloat(ing.subtotal_cost);

                return `
                    <tr class="hover:bg-gray-50/60">
                        <td class="px-4 py-2.5 font-medium text-gray-900">${escapeHtml(ing.item_name)}</td>
                        <td class="px-4 py-2.5 text-right font-mono font-bold text-gray-800">${qty.toFixed(2)}</td>
                        <td class="px-4 py-2.5 text-gray-500">${escapeHtml(ing.unit)}</td>
                        <td class="px-4 py-2.5 text-right font-mono text-gray-600">₱${unitCost.toFixed(2)}</td>
                        <td class="px-4 py-2.5 text-right font-mono font-bold text-emerald-700">₱${subtotal.toFixed(2)}</td>
                        <td class="px-4 py-2.5 text-center">
                            ${CAN_ADJUST ? `
                                <button type="button" onclick="deleteIngredient(${ing.id}, 'product')" class="text-red-500 hover:text-red-700 font-semibold text-xs">
                                    Remove
                                </button>
                            ` : `<span class="text-gray-400">Locked</span>`}
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // ==================== Modifiers Recipe Handling (Constraint 4) ====================
        function renderModifiersList(list) {
            const container = document.getElementById('modifierList');
            if (!list || list.length === 0) {
                container.innerHTML = '<div class="p-4 text-center text-gray-400 text-xs">No modifiers found.</div>';
                return;
            }

            container.innerHTML = list.map(m => {
                const isSelected = selectedModifier && selectedModifier.id === m.id;
                const cogs = parseFloat(m.cogs || 0);
                const hasRecipe = m.ingredients && m.ingredients.length > 0;

                return `
                    <div onclick="onModifierClick(${m.id})" class="p-3.5 cursor-pointer hover:bg-gray-50 flex justify-between items-center transition-colors ${isSelected ? 'bg-purple-50/70 border-l-4 border-purple-600' : ''}">
                        <div>
                            <div class="font-semibold text-gray-900 text-xs">${escapeHtml(m.name)}</div>
                            <div class="text-[11px] text-gray-500">Price: ₱${parseFloat(m.price).toFixed(2)}</div>
                        </div>
                        <div class="text-right">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold ${hasRecipe ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-600'}">
                                ${hasRecipe ? `Cost: ₱${cogs.toFixed(2)}` : 'No Recipe'}
                            </span>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function filterModifiersList() {
            const q = (document.getElementById('searchModifier').value || '').toLowerCase();
            const filtered = modifiersData.filter(m => m.name.toLowerCase().includes(q));
            renderModifiersList(filtered);
        }

        function onModifierClick(modId) {
            const mod = modifiersData.find(m => m.id === modId);
            if (mod) selectModifier(mod);
        }

        function selectModifier(mod) {
            selectedModifier = mod;
            renderModifiersList(modifiersData);

            document.getElementById('noModifierSelected').classList.add('hidden');
            const panel = document.getElementById('modifierRecipePanel');
            panel.classList.remove('hidden');

            document.getElementById('selectedModName').textContent = mod.name;
            document.getElementById('selectedModPrice').textContent = `₱${parseFloat(mod.price).toFixed(2)}`;
            document.getElementById('selectedModCogs').textContent = `₱${parseFloat(mod.cogs || 0).toFixed(2)}`;

            renderModIngredientsTable(mod.ingredients || []);
        }

        function renderModIngredientsTable(ingredients) {
            const tbody = document.getElementById('modIngredientsTableBody');
            document.getElementById('modIngredientCountBadge').textContent = `${ingredients.length} items`;

            if (!ingredients || ingredients.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-8 text-center text-gray-400 italic">No ingredient deduction mapped for this modifier yet.</td></tr>`;
                return;
            }

            tbody.innerHTML = ingredients.map(ing => {
                const qty = parseFloat(ing.quantity || ing.quantity_required);
                const unitCost = parseFloat(ing.unit_cost);
                const subtotal = parseFloat(ing.subtotal_cost);

                return `
                    <tr class="hover:bg-gray-50/60">
                        <td class="px-4 py-2.5 font-medium text-gray-900">${escapeHtml(ing.item_name)}</td>
                        <td class="px-4 py-2.5 text-right font-mono font-bold text-gray-800">${qty.toFixed(2)}</td>
                        <td class="px-4 py-2.5 text-gray-500">${escapeHtml(ing.unit)}</td>
                        <td class="px-4 py-2.5 text-right font-mono text-gray-600">₱${unitCost.toFixed(2)}</td>
                        <td class="px-4 py-2.5 text-right font-mono font-bold text-purple-700">₱${subtotal.toFixed(2)}</td>
                        <td class="px-4 py-2.5 text-center">
                            ${CAN_ADJUST ? `
                                <button type="button" onclick="deleteIngredient(${ing.id}, 'modifier')" class="text-red-500 hover:text-red-700 font-semibold text-xs">
                                    Remove
                                </button>
                            ` : `<span class="text-gray-400">Locked</span>`}
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // ==================== Save & Delete Ingredient Handlers ====================
        async function handleSaveIngredient(e, type) {
            e.preventDefault();
            hideAlert();

            const targetId = (type === 'product') ? selectedProduct?.id : selectedModifier?.id;
            if (!targetId) {
                showAlert('Please select a ' + type + ' first.', true);
                return;
            }

            const invSelect = document.getElementById(type === 'product' ? 'prodIngItemSelect' : 'modIngItemSelect');
            const qtyInput = document.getElementById(type === 'product' ? 'prodIngQtyInput' : 'modIngQtyInput');

            const payload = {
                action: 'save_ingredient',
                type: type,
                target_id: targetId,
                inventory_id: parseInt(invSelect.value),
                quantity: parseFloat(qtyInput.value)
            };

            try {
                const res = await fetch(API_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (!res.ok || !data.success) {
                    showAlert(data.error || 'Failed to save ingredient.', true);
                    return;
                }

                showAlert(data.message || 'Ingredient saved.');
                qtyInput.value = '';
                invSelect.value = '';
                await loadRecipesData();
            } catch (err) {
                showAlert('Network error: ' + err.message, true);
            }
        }

        async function deleteIngredient(recipeId, type) {
            if (!confirm('Are you sure you want to remove this ingredient from the recipe?')) {
                return;
            }
            hideAlert();

            try {
                const res = await fetch(API_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'delete_ingredient',
                        id: recipeId
                    })
                });
                const data = await res.json();

                if (!res.ok || !data.success) {
                    showAlert(data.error || 'Failed to delete ingredient.', true);
                    return;
                }

                showAlert(data.message || 'Ingredient removed.');
                await loadRecipesData();
            } catch (err) {
                showAlert('Network error: ' + err.message, true);
            }
        }

        // ==================== Cost Report Handling (Task 4) ====================
        async function loadCostReport() {
            const tbody = document.getElementById('costReportTableBody');
            tbody.innerHTML = '<tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">Generating cost report...</td></tr>';

            try {
                const res = await fetch(`${API_URL}?action=cost_report`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();

                if (!res.ok || !data.success) {
                    tbody.innerHTML = `<tr><td colspan="7" class="px-6 py-8 text-center text-red-500">${escapeHtml(data.error || 'Error loading report')}</td></tr>`;
                    return;
                }

                const report = data.data || [];
                if (report.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">No product cost data available.</td></tr>';
                    return;
                }

                tbody.innerHTML = report.map(row => {
                    const price = parseFloat(row.price);
                    const cogs = parseFloat(row.cogs);
                    const profit = price - cogs;
                    const margin = parseFloat(row.profit_margin_pct);

                    const ingredientsSummary = (row.ingredients || []).map(i => 
                        `${escapeHtml(i.item_name)} (${parseFloat(i.quantity_required).toFixed(2)}${escapeHtml(i.unit)})`
                    ).join(', ') || '<span class="text-gray-400 italic">No recipe mapped</span>';

                    const marginBadgeClass = margin >= 70 ? 'text-emerald-700 bg-emerald-50' : (margin >= 50 ? 'text-blue-700 bg-blue-50' : 'text-amber-700 bg-amber-50');

                    return `
                        <tr class="hover:bg-gray-50/80 transition-colors">
                            <td class="px-5 py-3.5 font-bold text-gray-900">${escapeHtml(row.product_name)}</td>
                            <td class="px-4 py-3.5 text-gray-600">${escapeHtml(row.category)}</td>
                            <td class="px-4 py-3.5 text-right font-mono font-semibold text-gray-900">₱${price.toFixed(2)}</td>
                            <td class="px-4 py-3.5 text-right font-mono font-bold text-gray-800">₱${cogs.toFixed(2)}</td>
                            <td class="px-4 py-3.5 text-right font-mono font-semibold text-emerald-700">₱${profit.toFixed(2)}</td>
                            <td class="px-4 py-3.5 text-right font-mono">
                                <span class="inline-block px-2 py-0.5 rounded text-xs font-bold ${marginBadgeClass}">
                                    ${margin.toFixed(1)}%
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-gray-600 text-[11px] max-w-xs truncate" title="${escapeHtml(ingredientsSummary)}">
                                ${ingredientsSummary}
                            </td>
                        </tr>
                    `;
                }).join('');
            } catch (err) {
                tbody.innerHTML = `<tr><td colspan="7" class="px-6 py-8 text-center text-red-500">Network error: ${escapeHtml(err.message)}</td></tr>`;
            }
        }
    </script>
    </div>
    </div>
</body>
</html>
