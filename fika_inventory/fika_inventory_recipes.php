<?php
require_once __DIR__ . '/../app/bootstrap.php';
class_exists('Output');

Auth::requireLogin();
Rbac::require_permission('inventory.view');

$user = Auth::user();
$canAdjust = Rbac::can('inventory.adjust');
$csrfToken = Csrf::getToken();

global $pdo;

// Fetch products and categories for direct rendering
$catStmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND deleted_at IS NULL ORDER BY category ASC");
$categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch inventory raw materials for dropdown
$rawStmt = $pdo->query("SELECT id, item_name, unit, unit_cost FROM inventory_items WHERE deleted_at IS NULL ORDER BY item_name ASC");
$rawMaterials = $rawStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Recipe BOM & Costing · Fika Inventory';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recipe BOM &amp; COGS Costing - Fika</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex">

    <?php require_once __DIR__ . '/../fika_hrms/includes/header.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto">
        <!-- Module Header & Sub-Nav -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center text-white font-black text-xl shadow-sm">
                        <i class="fa-solid fa-book-open text-base"></i>
                    </div>
                    <div>
                        <h1 class="font-bold text-lg text-slate-900 leading-tight">Recipe BOM &amp; Costing</h1>
                        <p class="text-xs text-slate-500">Bill of Materials, portion sizes, ingredient costs, and gross margins</p>
                    </div>
                </div>

                <nav class="hidden md:flex items-center gap-6 text-sm font-semibold text-slate-600">
                    <a href="fika_inventory_dashboard.php" class="hover:text-slate-900 transition-colors py-5">Dashboard</a>
                    <a href="fika_inventory_stock.php" class="hover:text-slate-900 transition-colors py-5">Stock Ledger</a>
                    <a href="fika_inventory_procurement.php" class="hover:text-slate-900 transition-colors py-5">Procurement</a>
                    <a href="fika_inventory_recipes.php" class="text-blue-600 font-bold border-b-2 border-blue-600 py-5">Recipe COGS</a>
                </nav>
            </div>
        </header>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full flex-1">

            <!-- Sub Nav Filter & Tab Bar -->
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 mb-6 flex flex-wrap justify-between items-center gap-4">
                <div class="flex items-center space-x-6">
                    <button type="button" onclick="switchType('product')" id="type-product" class="font-bold text-sm text-blue-600 border-b-2 border-blue-600 pb-1.5 inline-flex items-center gap-2">
                        <i class="fa-solid fa-mug-hot"></i> Menu Products
                    </button>
                    <button type="button" onclick="switchType('modifier')" id="type-modifier" class="font-semibold text-sm text-slate-500 hover:text-slate-800 border-b-2 border-transparent pb-1.5 transition inline-flex items-center gap-2">
                        <i class="fa-solid fa-cubes-stacked"></i> Modifiers &amp; Add-ons
                    </button>
                </div>

                <div class="flex items-center gap-3">
                    <div class="relative w-64">
                        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                        <input type="text" id="recipe-search" oninput="filterList()" placeholder="Search menu item..." class="pl-9 pr-3 py-2 border border-slate-300 rounded-xl text-xs w-full focus:ring-2 focus:ring-blue-500 bg-white shadow-xs">
                    </div>
                </div>
            </div>

            <!-- Recipe Directory Grid -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-200 bg-slate-50/70 flex justify-between items-center">
                    <h3 class="font-bold text-slate-800 text-sm" id="list-header-title">Menu Products Recipe Catalog</h3>
                    <span class="text-xs text-slate-500 font-medium" id="recipe-counter">Loading...</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 uppercase tracking-wider text-xs border-b border-slate-200">
                                <th class="py-3.5 px-6 font-bold">Item Name</th>
                                <th class="py-3.5 px-6 font-bold">Category</th>
                                <th class="py-3.5 px-6 font-bold">Selling Price</th>
                                <th class="py-3.5 px-6 font-bold">Total COGS</th>
                                <th class="py-3.5 px-6 font-bold">Gross Margin</th>
                                <th class="py-3.5 px-6 font-bold">Ingredients Mapped</th>
                                <th class="py-3.5 px-6 font-bold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="recipe-table-body" class="divide-y divide-slate-100">
                            <tr><td colspan="7" class="p-8 text-center text-slate-400">Loading recipe mapping...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- Recipe BOM Editing Modal -->
    <div id="recipe-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden border border-slate-200 flex flex-col max-h-[90vh]">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center bg-slate-50">
                <div>
                    <h3 class="font-bold text-base text-slate-800 flex items-center gap-2" id="recipe-modal-title">
                        <i class="fa-solid fa-book-open text-blue-600"></i> Recipe BOM: Espresso
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5" id="recipe-modal-subtitle">Retail: ₱120.00 | Manage ingredients and quantities</p>
                </div>
                <button type="button" onclick="closeModal('recipe-modal')" class="text-slate-400 hover:text-rose-600 transition p-1">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <!-- Modal Body (Scrollable) -->
            <div class="p-6 space-y-6 overflow-y-auto flex-1">
                <!-- Product Image Section (TASK 1) -->
                <div id="recipe-image-section" class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-3 flex items-center justify-between">
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-image text-blue-600"></i> Product Image</span>
                        <span class="text-[11px] text-slate-400 font-normal">Shown on Kiosk &amp; POS</span>
                    </h4>
                    
                    <form id="product-image-form" onsubmit="handleImageUpload(event)" enctype="multipart/form-data" class="flex flex-col sm:flex-row items-center gap-4">
                        <input type="hidden" name="product_id" id="image-product-id" value="">
                        
                        <!-- Image Preview -->
                        <div class="w-20 h-20 rounded-xl bg-white border border-slate-200 overflow-hidden flex items-center justify-center shrink-0 shadow-xs relative">
                            <img id="product-image-preview" src="" alt="Product Preview" class="w-full h-full object-cover hidden">
                            <span id="product-image-placeholder" class="text-xs font-bold text-slate-300 uppercase tracking-wider">No Image</span>
                        </div>
                        
                        <!-- Upload Input & Button -->
                        <div class="flex-1 w-full space-y-2">
                            <label class="block text-[11px] font-bold text-slate-600 uppercase">Select Image (JPEG, PNG, WebP &bull; Max 5MB)</label>
                            <div class="flex gap-2">
                                <input type="file" name="product_image" id="product-image-input" accept="image/jpeg, image/png, image/webp" onchange="previewSelectedImage(this)" class="w-full border border-slate-300 rounded-xl px-3 py-1.5 text-xs bg-white file:mr-3 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                                <?php if ($canAdjust): ?>
                                <button type="submit" id="product-image-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-4 py-2 rounded-xl text-xs transition shadow-xs whitespace-nowrap inline-flex items-center gap-1.5">
                                    <i class="fa-solid fa-cloud-arrow-up text-xs"></i> Upload
                                </button>
                                <?php endif; ?>
                            </div>
                            <div id="recipe-image-msg" class="text-xs font-semibold hidden"></div>
                        </div>
                    </form>
                </div>

                <!-- Add Ingredient Form -->
                <?php if ($canAdjust): ?>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                        <i class="fa-solid fa-plus-circle text-blue-600"></i> Add Ingredient to Recipe
                    </h4>
                    <form id="add-ingredient-form" onsubmit="handleAddIngredient(event)" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                        <input type="hidden" id="recipe-target-type" value="product">
                        <input type="hidden" id="recipe-target-id" value="">

                        <div class="md:col-span-6">
                            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Raw Material *</label>
                            <select id="recipe-inv-id" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 bg-white shadow-xs">
                                <option value="">-- Choose Raw Material --</option>
                                <?php foreach ($rawMaterials as $mat): ?>
                                    <option value="<?= $mat['id'] ?>" data-unit="<?= e($mat['unit']) ?>" data-cost="<?= (float)$mat['unit_cost'] ?>">
                                        <?= e($mat['item_name']) ?> (<?= e($mat['unit']) ?> - ₱<?= number_format((float)$mat['unit_cost'], 2) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="md:col-span-3">
                            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Portion Qty *</label>
                            <div class="flex shadow-xs">
                                <input type="number" min="0.001" step="0.001" id="recipe-qty" required placeholder="0.00" class="w-full border border-slate-300 rounded-l-xl px-3 py-2 text-xs font-mono focus:ring-2 focus:ring-blue-500">
                                <span id="recipe-unit-addon" class="inline-flex items-center px-2.5 rounded-r-xl border border-l-0 border-slate-300 bg-slate-100 text-slate-600 text-xs font-bold font-mono">unit</span>
                            </div>
                        </div>

                        <div class="md:col-span-3">
                            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-3 rounded-xl text-xs transition shadow-xs flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-plus text-xs"></i> Add
                            </button>
                        </div>
                    </form>
                    <div id="recipe-add-error" class="text-rose-600 text-xs font-semibold mt-2 hidden"></div>
                </div>
                <?php endif; ?>

                <!-- Current Ingredients Table -->
                <div>
                    <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center justify-between">
                        <span><i class="fa-solid fa-list-check text-blue-500 mr-1.5"></i> Recipe Ingredients &amp; Portion Breakdown</span>
                        <span class="text-slate-500 font-normal lowercase" id="recipe-ing-count">0 items</span>
                    </h4>

                    <div class="border border-slate-200 rounded-xl overflow-hidden shadow-xs">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead class="bg-slate-50 text-slate-500 uppercase tracking-wider text-[11px] border-b border-slate-200">
                                <tr>
                                    <th class="py-2.5 px-4 font-bold">Ingredient</th>
                                    <th class="py-2.5 px-4 font-bold">Portion Qty</th>
                                    <th class="py-2.5 px-4 font-bold">Unit Cost</th>
                                    <th class="py-2.5 px-4 font-bold">Subtotal</th>
                                    <th class="py-2.5 px-4 font-bold text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody id="recipe-ingredients-body" class="divide-y divide-slate-100">
                                <tr><td colspan="5" class="p-6 text-center text-slate-400">No ingredients mapped yet.</td></tr>
                            </tbody>
                            <tfoot class="bg-slate-50 border-t border-slate-200 font-bold">
                                <tr>
                                    <td colspan="3" class="py-3 px-4 text-slate-700 uppercase text-[11px]">Total Recipe COGS:</td>
                                    <td colspan="2" class="py-3 px-4 text-slate-900 font-mono text-sm" id="modal-cogs-total">₱0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-3.5 border-t border-slate-200 bg-slate-50 flex justify-end">
                <button type="button" onclick="closeModal('recipe-modal')" class="px-5 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl text-xs font-bold transition">
                    Done
                </button>
            </div>
        </div>
    </div>

    <script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const canAdjust = <?= json_encode((bool)$canAdjust) ?>;
    let currentType = 'product';
    let productsList = [];
    let modifiersList = [];
    let activeTarget = null;

    function closeModal(id) {
        const el = document.getElementById(id);
        if (el) {
            el.classList.add('hidden');
            el.classList.remove('flex');
        }
    }

    function openModal(id) {
        const el = document.getElementById(id);
        if (el) {
            el.classList.remove('hidden');
            el.classList.add('flex');
        }
    }

    function switchType(type) {
        currentType = type;
        const btnProd = document.getElementById('type-product');
        const btnMod = document.getElementById('type-modifier');
        const headerTitle = document.getElementById('list-header-title');

        if (type === 'product') {
            btnProd.className = "font-bold text-sm text-blue-600 border-b-2 border-blue-600 pb-1.5 inline-flex items-center gap-2";
            btnMod.className = "font-semibold text-sm text-slate-500 hover:text-slate-800 border-b-2 border-transparent pb-1.5 transition inline-flex items-center gap-2";
            headerTitle.innerText = "Menu Products Recipe Catalog";
            renderTable(productsList);
        } else {
            btnMod.className = "font-bold text-sm text-blue-600 border-b-2 border-blue-600 pb-1.5 inline-flex items-center gap-2";
            btnProd.className = "font-semibold text-sm text-slate-500 hover:text-slate-800 border-b-2 border-transparent pb-1.5 transition inline-flex items-center gap-2";
            headerTitle.innerText = "Modifiers & Add-ons Recipe Catalog";
            renderTable(modifiersList);
        }
    }

    async function loadRecipes() {
        try {
            const res = await fetch('api/fika_inventory_recipe.php?action=list');
            const data = await res.json();
            if (data.success) {
                productsList = data.products || [];
                modifiersList = data.modifiers || [];
                renderTable(currentType === 'product' ? productsList : modifiersList);
            }
        } catch (err) {
            console.error('Failed to load recipes:', err);
        }
    }

    function renderTable(items) {
        const tbody = document.getElementById('recipe-table-body');
        const counter = document.getElementById('recipe-counter');
        tbody.innerHTML = '';
        counter.innerText = `Showing ${items.length} item(s)`;

        if (!items || items.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="p-8 text-center text-slate-400 font-medium">No items found.</td></tr>`;
            return;
        }

        items.forEach(item => {
            const retail = parseFloat(item.price || 0);
            const cogs = parseFloat(item.cogs || 0);
            const margin = retail > 0 ? (((retail - cogs) / retail) * 100).toFixed(1) : '0.0';
            const ingCount = item.ingredients ? item.ingredients.length : 0;

            let marginColor = 'text-emerald-600 font-bold';
            if (parseFloat(margin) < 50) marginColor = 'text-amber-600 font-bold';
            if (parseFloat(margin) < 20) marginColor = 'text-rose-600 font-bold';

            const tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-50/70 transition-colors';
            tr.innerHTML = `
                <td class="py-4 px-6 font-bold text-slate-900">${escapeHtml(item.name)}</td>
                <td class="py-4 px-6 text-xs text-slate-500 font-medium">${escapeHtml(item.category || 'Modifier')}</td>
                <td class="py-4 px-6 font-mono text-xs font-bold text-slate-800">₱${retail.toFixed(2)}</td>
                <td class="py-4 px-6 font-mono text-xs font-bold text-slate-600">₱${cogs.toFixed(2)}</td>
                <td class="py-4 px-6 text-xs font-mono ${marginColor}">${margin}%</td>
                <td class="py-4 px-6">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold ${ingCount > 0 ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-500'}">
                        ${ingCount} ingredient${ingCount === 1 ? '' : 's'}
                    </span>
                </td>
                <td class="py-4 px-6 text-right whitespace-nowrap">
                    <button type="button" onclick="openRecipeModal('${currentType}', ${item.id})" class="text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors inline-flex items-center gap-1.5">
                        <i class="fa-solid fa-list-check text-[11px]"></i> Edit Recipe BOM
                    </button>
                    ${currentType === 'product' && canAdjust ? `
                        <button type="button" onclick="deleteProduct(${item.id}, '${escapeHtml(item.name).replace(/'/g, "\\'")}')" class="text-rose-600 hover:text-rose-800 bg-rose-50 hover:bg-rose-100 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors inline-flex items-center gap-1.5 ml-2">
                            <i class="fa-solid fa-trash-can text-[11px]"></i> Delete
                        </button>
                    ` : ''}
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function filterList() {
        const q = (document.getElementById('recipe-search').value || '').toLowerCase().trim();
        const base = (currentType === 'product') ? productsList : modifiersList;
        const filtered = base.filter(i => (i.name || '').toLowerCase().includes(q) || (i.category || '').toLowerCase().includes(q));
        renderTable(filtered);
    }

    function openRecipeModal(type, targetId) {
        const pool = (type === 'product') ? productsList : modifiersList;
        const item = pool.find(i => i.id == targetId);
        if (!item) return;

        activeTarget = item;
        document.getElementById('recipe-target-type').value = type;
        document.getElementById('recipe-target-id').value = item.id;
        document.getElementById('recipe-modal-title').innerHTML = `<i class="fa-solid fa-book-open text-blue-600"></i> Recipe BOM: ${escapeHtml(item.name)}`;
        document.getElementById('recipe-modal-subtitle').innerText = `Retail: ₱${parseFloat(item.price).toFixed(2)} | Category: ${item.category || 'Modifier'}`;
        document.getElementById('recipe-qty').value = '';
        document.getElementById('recipe-inv-id').value = '';
        document.getElementById('recipe-unit-addon').innerText = 'unit';
        document.getElementById('recipe-add-error').classList.add('hidden');

        // Product Image UI section
        const imageSection = document.getElementById('recipe-image-section');
        const imgPreview = document.getElementById('product-image-preview');
        const imgPlaceholder = document.getElementById('product-image-placeholder');
        const imgInput = document.getElementById('product-image-input');
        const imgMsg = document.getElementById('recipe-image-msg');

        if (imgMsg) imgMsg.className = 'text-xs font-semibold hidden';
        if (imgInput) imgInput.value = '';

        if (type === 'product') {
            imageSection.classList.remove('hidden');
            document.getElementById('image-product-id').value = item.id;
            if (item.image_path) {
                imgPreview.src = '../' + item.image_path.replace(/^\/+/, '');
                imgPreview.classList.remove('hidden');
                imgPlaceholder.classList.add('hidden');
            } else {
                imgPreview.src = '';
                imgPreview.classList.add('hidden');
                imgPlaceholder.classList.remove('hidden');
            }
        } else {
            imageSection.classList.add('hidden');
        }

        renderModalIngredients(item.ingredients || []);
        openModal('recipe-modal');
    }

    function renderModalIngredients(ingredients) {
        const tbody = document.getElementById('recipe-ingredients-body');
        const countSpan = document.getElementById('recipe-ing-count');
        const totalSpan = document.getElementById('modal-cogs-total');
        tbody.innerHTML = '';
        countSpan.innerText = `${ingredients.length} item(s)`;

        let totalCogs = 0.0;

        if (!ingredients || ingredients.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="p-6 text-center text-slate-400 font-medium">No ingredients mapped yet. Add one above.</td></tr>`;
            totalSpan.innerText = '₱0.00';
            return;
        }

        ingredients.forEach(ing => {
            const sub = parseFloat(ing.subtotal_cost || (ing.quantity * ing.unit_cost) || 0);
            totalCogs += sub;

            const tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-50 transition-colors';
            tr.innerHTML = `
                <td class="py-3 px-4 font-bold text-slate-900">${escapeHtml(ing.item_name)}</td>
                <td class="py-3 px-4 font-mono font-semibold text-slate-700">${parseFloat(ing.quantity).toFixed(2)} ${escapeHtml(ing.unit)}</td>
                <td class="py-3 px-4 font-mono text-slate-500">₱${parseFloat(ing.unit_cost).toFixed(2)}</td>
                <td class="py-3 px-4 font-mono font-bold text-slate-800">₱${sub.toFixed(2)}</td>
                <td class="py-3 px-4 text-right">
                    <button type="button" onclick="deleteIngredient(${ing.id})" class="text-rose-600 hover:text-rose-800 hover:bg-rose-50 p-1.5 rounded-lg transition" title="Remove ingredient">
                        <i class="fa-solid fa-trash-can text-xs"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        totalSpan.innerText = `₱${totalCogs.toFixed(2)}`;
    }

    document.getElementById('recipe-inv-id').addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        const unit = opt.getAttribute('data-unit') || 'unit';
        document.getElementById('recipe-unit-addon').innerText = unit;
    });

    async function handleAddIngredient(e) {
        e.preventDefault();
        const errDiv = document.getElementById('recipe-add-error');
        errDiv.classList.add('hidden');

        const type = document.getElementById('recipe-target-type').value;
        const targetId = document.getElementById('recipe-target-id').value;
        const invId = document.getElementById('recipe-inv-id').value;
        const qty = parseFloat(document.getElementById('recipe-qty').value);

        if (!invId || isNaN(qty) || qty <= 0) {
            errDiv.innerText = 'Please select an ingredient and enter a positive portion quantity.';
            errDiv.classList.remove('hidden');
            return;
        }

        const payload = {
            action: 'save_ingredient',
            type: type,
            target_id: targetId,
            inventory_id: invId,
            quantity: qty,
            csrf_token: csrfToken
        };

        try {
            const res = await fetch('api/fika_inventory_recipe.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify(payload)
            });
            const json = await res.json();
            if (res.ok && json.success) {
                document.getElementById('recipe-qty').value = '';
                document.getElementById('recipe-inv-id').value = '';
                await loadRecipes();
                // Refresh modal with updated ingredients
                const pool = (type === 'product') ? productsList : modifiersList;
                const updated = pool.find(i => i.id == targetId);
                if (updated) {
                    renderModalIngredients(updated.ingredients || []);
                }
            } else {
                errDiv.innerText = json.error || 'Failed to add ingredient.';
                errDiv.classList.remove('hidden');
            }
        } catch (err) {
            errDiv.innerText = 'Network error: ' + err.message;
            errDiv.classList.remove('hidden');
        }
    }

    async function deleteIngredient(recipeId) {
        if (!confirm('Are you sure you want to remove this ingredient from the recipe?')) return;

        try {
            const res = await fetch('api/fika_inventory_recipe.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({
                    action: 'delete_ingredient',
                    id: recipeId,
                    csrf_token: csrfToken
                })
            });
            const json = await res.json();
            if (res.ok && json.success) {
                const type = document.getElementById('recipe-target-type').value;
                const targetId = document.getElementById('recipe-target-id').value;
                await loadRecipes();
                const pool = (type === 'product') ? productsList : modifiersList;
                const updated = pool.find(i => i.id == targetId);
                if (updated) {
                    renderModalIngredients(updated.ingredients || []);
                }
            } else {
                alert(json.error || 'Failed to remove ingredient.');
            }
        } catch (err) {
            alert('Network error: ' + err.message);
        }
    }

    function previewSelectedImage(input) {
        const preview = document.getElementById('product-image-preview');
        const placeholder = document.getElementById('product-image-placeholder');
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
                placeholder.classList.add('hidden');
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    async function handleImageUpload(e) {
        e.preventDefault();
        const msgDiv = document.getElementById('recipe-image-msg');
        msgDiv.className = 'text-xs font-semibold hidden';
        
        const fileInput = document.getElementById('product-image-input');
        const prodId = document.getElementById('image-product-id').value;

        if (!fileInput.files || !fileInput.files[0]) {
            msgDiv.innerText = 'Please select an image file to upload.';
            msgDiv.className = 'text-xs font-semibold text-rose-600 block mt-1';
            return;
        }

        const formData = new FormData();
        formData.append('action', 'upload_image');
        formData.append('product_id', prodId);
        formData.append('product_image', fileInput.files[0]);
        formData.append('csrf_token', csrfToken);

        const btn = document.getElementById('product-image-btn');
        if (btn) btn.disabled = true;

        try {
            const res = await fetch('api/fika_inventory_recipe.php', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                body: formData
            });
            const json = await res.json();
            if (res.ok && json.success) {
                msgDiv.innerText = json.message || 'Image uploaded successfully!';
                msgDiv.className = 'text-xs font-semibold text-emerald-600 block mt-1';
                
                // Update local product entry
                const prod = productsList.find(p => p.id == prodId);
                if (prod) {
                    prod.image_path = json.image_path;
                }
                const preview = document.getElementById('product-image-preview');
                const placeholder = document.getElementById('product-image-placeholder');
                preview.src = '../' + json.image_path.replace(/^\/+/, '');
                preview.classList.remove('hidden');
                placeholder.classList.add('hidden');
            } else {
                msgDiv.innerText = json.error || 'Failed to upload image.';
                msgDiv.className = 'text-xs font-semibold text-rose-600 block mt-1';
            }
        } catch (err) {
            msgDiv.innerText = 'Network error: ' + err.message;
            msgDiv.className = 'text-xs font-semibold text-rose-600 block mt-1';
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    async function deleteProduct(productId, productName) {
        if (!confirm(`Are you sure you want to delete and deactivate '${productName}'?\nThis will remove it from the Kiosk and POS menu.`)) {
            return;
        }

        try {
            const res = await fetch('api/fika_inventory_recipe.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({
                    action: 'delete_product',
                    product_id: productId,
                    csrf_token: csrfToken
                })
            });
            const json = await res.json();
            if (res.ok && json.success) {
                await loadRecipes();
            } else {
                alert(json.error || 'Failed to deactivate product.');
            }
        } catch (err) {
            alert('Network error: ' + err.message);
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.innerText = str;
        return div.innerHTML;
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadRecipes();
    });
    </script>
</body>
</html>
