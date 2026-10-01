<?php
require_once __DIR__ . '/init.php';

// Access Control
if (empty($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['Super Admin', 'Admin', 'Head Barista'])) {
    redirect('login');
}

$pageTitle = 'Menu List Management';
require_once __DIR__ . '/includes/header.php';
?>

<div class="px-6 py-4 border-b border-slate-200 bg-white flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Menu List Management</h1>
        <p class="text-slate-500 text-sm">Manage products, categories, pricing, and customizations.</p>
    </div>
</div>

<div class="p-6 max-w-7xl mx-auto space-y-6">

    <!-- Tabs -->
    <div class="border-b border-slate-200">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <button id="tab-products" class="tab-btn border-blue-500 text-blue-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm" onclick="switchTab('products')">
                <i class="fa-solid fa-mug-hot mr-2"></i> Products
            </button>
            <button id="tab-modifiers" class="tab-btn border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm" onclick="switchTab('modifiers')">
                <i class="fa-solid fa-layer-group mr-2"></i> Modifiers (Customizations)
            </button>
        </nav>
    </div>

    <!-- Products View -->
    <div id="view-products" class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-4 border-b border-slate-200 flex justify-between items-center bg-slate-50">
            <h2 class="text-lg font-semibold text-slate-800">All Products</h2>
            <div class="flex items-center gap-3">
                <select id="filter-product-category" onchange="filterProducts()" class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm bg-white focus:ring-blue-500 focus:border-blue-500">
                    <option value="All">All Categories</option>
                    <option value="Hot Beverage">Hot Beverage</option>
                    <option value="Cold Beverage">Cold Beverage</option>
                    <option value="Pastries">Pastries</option>
                    <option value="Retail">Retail</option>
                </select>
                <button onclick="openProductModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    <i class="fa-solid fa-plus mr-2"></i> Add Product
                </button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-sm border-b border-slate-200">
                        <th class="p-4 font-medium">Name</th>
                        <th class="p-4 font-medium">Category</th>
                        <th class="p-4 font-medium">Cost</th>
                        <th class="p-4 font-medium">Price</th>
                        <th class="p-4 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="products-tbody" class="divide-y divide-slate-100 text-sm text-slate-700">
                    <!-- Products will be loaded here -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modifiers View -->
    <div id="view-modifiers" class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden hidden">
        <div class="p-4 border-b border-slate-200 flex justify-between items-center bg-slate-50">
            <h2 class="text-lg font-semibold text-slate-800">All Modifiers</h2>
            <div class="flex items-center gap-3">
                <select id="filter-modifier-group" onchange="filterModifiers()" class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm bg-white focus:ring-blue-500 focus:border-blue-500">
                    <option value="All">All Groups</option>
                    <option value="Hot Drink Size & Style">Hot Drink Size & Style</option>
                    <option value="Cold Drink Size">Cold Drink Size</option>
                    <option value="Espresso Sizes">Espresso Sizes</option>
                    <option value="Matcha Sizes">Matcha Sizes</option>
                    <option value="Frappe Sizes">Frappe Sizes</option>
                    <option value="Packaging">Packaging</option>
                    <option value="Milk">Milk Option</option>
                    <option value="Add-ons">Add-ons</option>
                </select>
                <button onclick="openModifierModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    <i class="fa-solid fa-plus mr-2"></i> Add Modifier
                </button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-sm border-b border-slate-200">
                        <th class="p-4 font-medium">Name</th>
                        <th class="p-4 font-medium">Group</th>
                        <th class="p-4 font-medium">Price Adjustment</th>
                        <th class="p-4 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="modifiers-tbody" class="divide-y divide-slate-100 text-sm text-slate-700">
                    <!-- Modifiers will be loaded here -->
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Recipe Modal -->
<div id="recipe-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden transform transition-all flex flex-col max-h-[90vh]">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h3 class="text-lg font-bold text-slate-800" id="recipe-modal-title">Recipe Settings</h3>
            <button onclick="closeRecipeModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
        <div class="p-6 flex-1 overflow-y-auto">
            <input type="hidden" id="recipe-target-type">
            <input type="hidden" id="recipe-target-id">
            
            <div class="mb-6">
                <h4 class="font-semibold text-slate-700 mb-2">Current Ingredients</h4>
                <div class="bg-slate-50 rounded-lg border border-slate-200 overflow-hidden">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-100 text-slate-600">
                            <tr>
                                <th class="p-3 font-medium">Inventory Item</th>
                                <th class="p-3 font-medium">Quantity</th>
                                <th class="p-3 font-medium text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody id="recipe-list" class="divide-y divide-slate-200">
                            <!-- Populated via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <form id="recipe-add-form" onsubmit="addRecipeIngredient(event)" class="bg-blue-50/50 p-4 rounded-lg border border-blue-100">
                <h4 class="font-semibold text-blue-900 mb-3 text-sm">Add New Ingredient</h4>
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-medium text-blue-800 mb-1">Inventory Item</label>
                        <select id="recipe-inv-id" required class="w-full border-blue-200 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border text-sm bg-white">
                            <!-- Populated via JS -->
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-blue-800 mb-1">Quantity (per order)</label>
                        <input type="number" min="0" step="0.01" onkeydown="return ['e', 'E', '-', '+'].includes(event.key) ? false : true" id="recipe-qty" required class="w-full border-blue-200 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border text-sm">
                    </div>
                </div>
                <p id="recipe-add-error" class="text-sm text-red-500 mb-2 font-medium"></p>
                <button type="submit" class="w-full bg-blue-600 text-white rounded-lg py-2 hover:bg-blue-700 text-sm font-medium transition-colors">
                    Add Ingredient
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Product Modal -->
<div id="product-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl overflow-hidden transform transition-all">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h3 class="text-lg font-bold text-slate-800" id="product-modal-title">Add Product</h3>
            <button onclick="closeProductModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
        <form id="product-form" onsubmit="saveProduct(event)">
            <input type="hidden" id="prod-id" value="">
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Left Column -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Product Name</label>
                        <input type="text" id="prod-name" required class="w-full border-slate-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                        <select id="prod-category" required class="w-full border-slate-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border">
                            <option value="Hot Beverage">Hot Beverage</option>
                            <option value="Cold Beverage">Cold Beverage</option>
                            <option value="Pastries">Pastries</option>
                            <option value="Specials">Specials</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Estimated COGS (Base)</label>
                            <div id="prod-cogs-display" class="w-full bg-slate-100 border-slate-300 rounded-lg shadow-sm px-3 py-2 border text-slate-700 font-medium font-mono">₱0.00</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Selling Price (₱)</label>
                            <input type="number" min="0" step="0.01" onkeydown="return ['e', 'E', '-', '+'].includes(event.key) ? false : true" id="prod-price" required class="w-full border-slate-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Allowed Modifiers</label>
                        <div class="space-y-2 mt-2 max-h-40 overflow-y-auto pr-2">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="prod-allowed-mods" value="Hot Drink Size & Style" class="rounded border-slate-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-slate-700">Hot Drink Size & Style</span>
                            </label><br>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="prod-allowed-mods" value="Cold Drink Size" class="rounded border-slate-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-slate-700">Cold Drink Size</span>
                            </label><br>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="prod-allowed-mods" value="Espresso Sizes" class="rounded border-slate-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-slate-700">Espresso Sizes</span>
                            </label><br>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="prod-allowed-mods" value="Matcha Sizes" class="rounded border-slate-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-slate-700">Matcha Sizes</span>
                            </label><br>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="prod-allowed-mods" value="Frappe Sizes" class="rounded border-slate-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-slate-700">Frappe Sizes</span>
                            </label><br>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="prod-allowed-mods" value="Packaging" class="rounded border-slate-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-slate-700">Packaging</span>
                            </label><br>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="prod-allowed-mods" value="Milk" class="rounded border-slate-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-slate-700">Milk Options</span>
                            </label><br>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="prod-allowed-mods" value="Add-ons" class="rounded border-slate-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-slate-700">Add-ons</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Right Column: COGS Breakdown -->
                <div class="bg-slate-50 p-5 rounded-xl border border-slate-200 flex flex-col">
                    <h4 class="font-semibold text-slate-800 mb-3"><i class="fa-solid fa-list-check text-blue-500 mr-2"></i>Recipe COGS Breakdown</h4>
                    <div class="overflow-y-auto flex-1 bg-white border border-slate-200 rounded-lg shadow-sm">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-slate-600 sticky top-0">
                                <tr>
                                    <th class="p-3 font-medium border-b border-slate-200">Ingredient Name</th>
                                    <th class="p-3 font-medium border-b border-slate-200">Qty</th>
                                    <th class="p-3 font-medium border-b border-slate-200 text-right">Line Total</th>
                                </tr>
                            </thead>
                            <tbody id="prod-cogs-breakdown" class="divide-y divide-slate-100">
                                <tr><td colspan="3" class="p-4 text-center text-slate-500 text-sm italic">New product. No recipe mapped yet.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 pt-3 border-t border-slate-200 flex justify-between items-center">
                        <span class="text-xs text-slate-500"><i class="fa-solid fa-circle-info mr-1"></i>Read-only. Use the Recipe map to edit.</span>
                        <div class="font-bold text-slate-800 flex items-center">
                            <span class="mr-2 text-sm">Total COGS:</span>
                            <span id="prod-cogs-total" class="text-blue-600">₱0.00</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end space-x-3">
                <button type="button" onclick="closeProductModal()" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">Save Product</button>
            </div>
        </form>
    </div>
</div>

<!-- Modifier Modal -->
<div id="modifier-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl overflow-hidden transform transition-all">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h3 class="text-lg font-bold text-slate-800" id="modifier-modal-title">Add Modifier</h3>
            <button onclick="closeModifierModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
        <form id="modifier-form" onsubmit="saveModifier(event)">
            <input type="hidden" id="mod-id" value="">
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Left Column -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Modifier Name</label>
                        <input type="text" id="mod-name" required class="w-full border-slate-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Group</label>
                        <select id="mod-group" required class="w-full border-slate-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border">
                            <option value="Hot Drink Size & Style">Hot Drink Size & Style</option>
                            <option value="Cold Drink Size">Cold Drink Size</option>
                            <option value="Espresso Sizes">Espresso Sizes</option>
                            <option value="Matcha Sizes">Matcha Sizes</option>
                            <option value="Frappe Sizes">Frappe Sizes</option>
                            <option value="Packaging">Packaging</option>
                            <option value="Milk">Milk Option</option>
                            <option value="Add-ons">Add-ons</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Price Adjustment (₱)</label>
                        <input type="number" min="0" step="0.01" onkeydown="return ['e', 'E', '-', '+'].includes(event.key) ? false : true" id="mod-price" required value="0.00" class="w-full border-slate-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border">
                        <p class="text-xs text-slate-500 mt-1">Use 0 if free, or positive numbers (e.g. 25.00)</p>
                    </div>
                </div>

                <!-- Right Column: COGS Breakdown -->
                <div class="bg-slate-50 p-5 rounded-xl border border-slate-200 flex flex-col">
                    <h4 class="font-semibold text-slate-800 mb-3"><i class="fa-solid fa-list-check text-blue-500 mr-2"></i>Recipe COGS Breakdown</h4>
                    <div class="overflow-y-auto flex-1 bg-white border border-slate-200 rounded-lg shadow-sm">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-slate-600 sticky top-0">
                                <tr>
                                    <th class="p-3 font-medium border-b border-slate-200">Ingredient Name</th>
                                    <th class="p-3 font-medium border-b border-slate-200">Qty</th>
                                    <th class="p-3 font-medium border-b border-slate-200 text-right">Line Total</th>
                                </tr>
                            </thead>
                            <tbody id="mod-cogs-breakdown" class="divide-y divide-slate-100">
                                <tr><td colspan="3" class="p-4 text-center text-slate-500 text-sm italic">New modifier. No recipe mapped yet.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 pt-3 border-t border-slate-200 flex justify-between items-center">
                        <span class="text-xs text-slate-500"><i class="fa-solid fa-circle-info mr-1"></i>Read-only. Use the Recipe map to edit.</span>
                        <div class="font-bold text-slate-800 flex items-center">
                            <span class="mr-2 text-sm">Total COGS:</span>
                            <span id="mod-cogs-total" class="text-blue-600">₱0.00</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end space-x-3">
                <button type="button" onclick="closeModifierModal()" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">Save Modifier</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Modal -->
<div id="delete-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-sm overflow-hidden transform transition-all">
        <div class="p-6 text-center">
            <div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                <i class="fa-solid fa-triangle-exclamation text-3xl text-red-600"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-800 mb-2">Confirm Deletion</h3>
            <p class="text-slate-500 text-sm mb-6">Are you sure you want to delete this item? This action cannot be undone and may break associated recipes.</p>
            <input type="hidden" id="delete-type">
            <input type="hidden" id="delete-id">
            <div class="flex justify-center space-x-3">
                <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 w-full">Cancel</button>
                <button type="button" onclick="executeDelete()" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 w-full">Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
let productsData = [];
let modifiersData = [];
let currentTab = 'products';

document.addEventListener('DOMContentLoaded', () => {
    loadProducts();
    loadModifiers();
});

function switchTab(tab) {
    currentTab = tab;
    // Update tabs
    document.getElementById('tab-products').className = tab === 'products' ? 'tab-btn border-blue-500 text-blue-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm' : 'tab-btn border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm';
    document.getElementById('tab-modifiers').className = tab === 'modifiers' ? 'tab-btn border-blue-500 text-blue-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm' : 'tab-btn border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm';
    
    // Update views
    document.getElementById('view-products').classList.toggle('hidden', tab !== 'products');
    document.getElementById('view-modifiers').classList.toggle('hidden', tab !== 'modifiers');
}

function filterProducts() {
    const filter = document.getElementById('filter-product-category').value;
    const tbody = document.getElementById('products-tbody');
    Array.from(tbody.rows).forEach(row => {
        if (row.cells.length < 2) return; // skip empty state row
        const category = row.cells[1].innerText.trim();
        row.style.display = (filter === 'All' || category === filter) ? '' : 'none';
    });
}

function filterModifiers() {
    const filter = document.getElementById('filter-modifier-group').value;
    const tbody = document.getElementById('modifiers-tbody');
    Array.from(tbody.rows).forEach(row => {
        if (row.cells.length < 2) return; // skip empty state row
        const group = row.cells[1].innerText.trim();
        row.style.display = (filter === 'All' || group === filter) ? '' : 'none';
    });
}

// --- Recipes ---
let inventoryItems = [];

async function loadInventoryOptions() {
    if (inventoryItems.length > 0) return;
    try {
        const res = await fetch('api/inventory_crud.php?type=inventory');
        const data = await res.json();
        if (data.success) {
            inventoryItems = data.data;
            const select = document.getElementById('recipe-inv-id');
            select.innerHTML = '<option value="">Select an Item...</option>';
            inventoryItems.forEach(item => {
                select.innerHTML += `<option value="${item.id}">${escapeHtml(item.item_name)} (${escapeHtml(item.unit)})</option>`;
            });
        }
    } catch(e) {}
}

async function openRecipeModal(type, id, name) {
    document.getElementById('recipe-modal-title').textContent = `Recipe: ${name}`;
    document.getElementById('recipe-target-type').value = type;
    document.getElementById('recipe-target-id').value = id;
    document.getElementById('recipe-qty').value = '';
    
    await loadInventoryOptions();
    await loadRecipeIngredients(type, id);
    
    document.getElementById('recipe-modal').classList.remove('hidden');
}

async function closeRecipeModal() {
    const type = document.getElementById('recipe-target-type').value;
    const targetId = document.getElementById('recipe-target-id').value;
    
    if (type === 'modifier') {
        const modifier = modifiersData.find(m => m.id == targetId);
        if (modifier && parseFloat(modifier.price_adjustment) > 0) {
            try {
                const res = await fetch(`api/inventory_crud.php?type=recipes&modifier_id=${targetId}`);
                const data = await res.json();
                if (data.success && data.data.length === 0) {
                    alert('This modifier has a price adjustment greater than 0. You must map at least one inventory ingredient for COGS tracking.');
                    return;
                }
            } catch(e) {
                console.error(e);
            }
        }
    } else if (type === 'product') {
        loadProducts();
    }
    document.getElementById('recipe-modal').classList.add('hidden');
}

async function loadRecipeIngredients(type, id) {
    const list = document.getElementById('recipe-list');
    list.innerHTML = '<tr><td colspan="3" class="text-center p-4">Loading...</td></tr>';
    try {
        const param = type === 'product' ? `product_id=${id}` : `modifier_id=${id}`;
        const res = await fetch(`api/inventory_crud.php?type=recipes&${param}`);
        const data = await res.json();
        
        list.innerHTML = '';
        if(data.success && data.data.length > 0) {
            data.data.forEach(r => {
                list.innerHTML += `
                    <tr class="hover:bg-slate-50">
                        <td class="p-3 text-slate-800 font-medium">${escapeHtml(r.item_name)}</td>
                        <td class="p-3 text-slate-600">
                            <span id="recipe-qty-text-${r.id}">${parseFloat(r.quantity)}</span> 
                            <span class="text-sm">${escapeHtml(r.unit)}</span>
                            <input type="number" min="0" step="0.01" onkeydown="return ['e', 'E', '-', '+'].includes(event.key) ? false : true" id="recipe-qty-input-${r.id}" class="hidden w-20 px-2 py-1 border rounded text-sm" value="${parseFloat(r.quantity)}">
                        </td>
                        <td class="p-3 text-right">
                            <button type="button" id="recipe-edit-btn-${r.id}" onclick="toggleEditRecipe(${r.id})" class="text-blue-500 hover:text-blue-700 mr-2" title="Edit Quantity">
                                <i class="fa-solid fa-pencil"></i>
                            </button>
                            <button type="button" id="recipe-save-btn-${r.id}" onclick="saveRecipeEdit(${r.id}, '${type}', ${id})" class="hidden text-green-500 hover:text-green-700 mr-2" title="Save">
                                <i class="fa-solid fa-check"></i>
                            </button>
                            <button type="button" onclick="deleteRecipeIngredient(${r.id}, '${type}', ${id})" class="text-red-500 hover:text-red-700" title="Remove">
                                <i class="fa-solid fa-times"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
        } else {
            list.innerHTML = '<tr><td colspan="3" class="text-center p-4 text-slate-500 text-sm">No ingredients configured.</td></tr>';
        }
    } catch(e) {
        list.innerHTML = '<tr><td colspan="3" class="text-center p-4 text-red-500">Error loading recipes.</td></tr>';
    }
}

function toggleEditRecipe(id) {
    document.getElementById(`recipe-qty-text-${id}`).classList.add('hidden');
    document.getElementById(`recipe-edit-btn-${id}`).classList.add('hidden');
    document.getElementById(`recipe-qty-input-${id}`).classList.remove('hidden');
    document.getElementById(`recipe-save-btn-${id}`).classList.remove('hidden');
    document.getElementById(`recipe-qty-input-${id}`).focus();
}

async function saveRecipeEdit(recipeId, targetType, targetId) {
    const qty = document.getElementById(`recipe-qty-input-${recipeId}`).value;
    try {
        const res = await fetch('api/inventory_crud.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ type: 'recipes', action: 'update', id: recipeId, quantity: qty })
        });
        const data = await res.json();
        if (data.success) {
            loadRecipeIngredients(targetType, targetId);
        } else {
            alert(data.error || 'Failed to update quantity');
        }
    } catch(e) {
        alert('Network error');
    }
}

async function addRecipeIngredient(e) {
    e.preventDefault();
    document.getElementById('recipe-add-error').textContent = '';
    
    const type = document.getElementById('recipe-target-type').value;
    const targetId = document.getElementById('recipe-target-id').value;
    const invId = document.getElementById('recipe-inv-id').value;
    const qty = document.getElementById('recipe-qty').value;
    
    const payload = {
        type: 'recipes',
        action: 'create',
        inventory_id: invId,
        quantity: qty
    };
    if (type === 'product') payload.product_id = targetId;
    else payload.modifier_id = targetId;
    
    try {
        const res = await fetch('api/inventory_crud.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if(data.success) {
            document.getElementById('recipe-qty').value = '';
            document.getElementById('recipe-inv-id').value = '';
            loadRecipeIngredients(type, targetId);
        } else {
            document.getElementById('recipe-add-error').textContent = data.error || 'Failed to add ingredient';
        }
    } catch (e) {
        document.getElementById('recipe-add-error').textContent = 'Network error occurred.';
    }
}

async function deleteRecipeIngredient(recipeId, type, targetId) {
    try {
        const res = await fetch('api/inventory_crud.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({type: 'recipes', action: 'delete', id: recipeId})
        });
        const data = await res.json();
        if (data.success) {
            loadRecipeIngredients(type, targetId);
        }
    } catch(e) {}
}

// --- Data Loading ---
async function loadProducts() {
    try {
        const res = await fetch('api/menu_crud.php?type=products');
        const data = await res.json();
        if (data.success) {
            productsData = data.data;
            renderProducts();
        }
    } catch (e) {
        console.error('Failed to load products', e);
    }
}

async function loadModifiers() {
    try {
        const res = await fetch('api/menu_crud.php?type=modifiers');
        const data = await res.json();
        if (data.success) {
            modifiersData = data.data;
            renderModifiers();
        }
    } catch (e) {
        console.error('Failed to load modifiers', e);
    }
}

// --- Rendering ---
function renderProducts() {
    const tbody = document.getElementById('products-tbody');
    tbody.innerHTML = '';
    
    productsData.forEach(p => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="p-4 font-medium text-slate-900">${escapeHtml(p.name)}</td>
            <td class="p-4"><span class="bg-slate-100 text-slate-600 px-2 py-1 rounded text-xs font-medium">${escapeHtml(p.category)}</span></td>
            <td class="p-4 font-medium text-slate-500">₱${parseFloat(p.calculated_cogs || 0).toFixed(2)}</td>
            <td class="p-4 font-medium text-slate-700">₱${parseFloat(p.price).toFixed(2)}</td>
            <td class="p-4 text-right">
                <button onclick='openRecipeModal("product", ${p.id}, "${escapeHtml(p.name)}")' class="text-indigo-500 hover:text-indigo-700 mr-3" title="Recipe"><i class="fa-solid fa-book-open"></i></button>
                <button onclick='editProduct(${JSON.stringify(p)})' class="text-blue-500 hover:text-blue-700 mr-3"><i class="fa-solid fa-pen-to-square"></i></button>
                <button onclick="confirmDelete('products', ${p.id})" class="text-red-500 hover:text-red-700"><i class="fa-solid fa-trash"></i></button>
            </td>
        `;
        tbody.appendChild(tr);
    });
    
    // Apply any active filters
    if (typeof filterProducts === 'function') filterProducts();
}

function renderModifiers() {
    const tbody = document.getElementById('modifiers-tbody');
    tbody.innerHTML = '';
    
    modifiersData.forEach(m => {
        const tr = document.createElement('tr');
        const priceText = parseFloat(m.price_adjustment) > 0 ? `+₱${parseFloat(m.price_adjustment).toFixed(2)}` : 'Free';
        tr.innerHTML = `
            <td class="p-4 font-medium text-slate-900">${escapeHtml(m.name)}</td>
            <td class="p-4"><span class="bg-slate-100 text-slate-600 px-2 py-1 rounded text-xs font-medium">${escapeHtml(m.modifier_group)}</span></td>
            <td class="p-4 font-medium text-slate-700">${priceText}</td>
            <td class="p-4 text-right">
                <button onclick='openRecipeModal("modifier", ${m.id}, "${escapeHtml(m.name)}")' class="text-indigo-500 hover:text-indigo-700 mr-3" title="Recipe"><i class="fa-solid fa-book-open"></i></button>
                <button onclick='editModifier(${JSON.stringify(m)})' class="text-blue-500 hover:text-blue-700 mr-3"><i class="fa-solid fa-pen-to-square"></i></button>
                <button onclick="confirmDelete('modifiers', ${m.id})" class="text-red-500 hover:text-red-700"><i class="fa-solid fa-trash"></i></button>
            </td>
        `;
        tbody.appendChild(tr);
    });
    
    // Apply any active filters
    if (typeof filterModifiers === 'function') filterModifiers();
}

// --- Modals ---
async function calculateCOGS(type, id) {
    const listId = type === 'product' ? 'prod-cogs-breakdown' : 'mod-cogs-breakdown';
    const totalId = type === 'product' ? 'prod-cogs-total' : 'mod-cogs-total';
    
    const list = document.getElementById(listId);
    const totalEl = document.getElementById(totalId);
    
    if (list) list.innerHTML = '<tr><td colspan="3" class="p-4 text-center text-slate-500 text-sm">Loading...</td></tr>';
    
    try {
        const param = type === 'product' ? `product_id=${id}` : `modifier_id=${id}`;
        const res = await fetch(`api/inventory_crud.php?type=recipes&${param}`);
        const data = await res.json();
        if (data.success) {
            let total = 0;
            if (list) list.innerHTML = '';
            
            if (data.data.length === 0) {
                if (list) list.innerHTML = '<tr><td colspan="3" class="p-4 text-center text-slate-500 text-sm italic">No recipe mapped yet.</td></tr>';
            } else {
                data.data.forEach(r => {
                    const qty = parseFloat(r.quantity);
                    const cost = parseFloat(r.unit_cost || 0);
                    const lineTotal = qty * cost;
                    total += lineTotal;
                    
                    if (list) {
                        list.innerHTML += `
                            <tr>
                                <td class="p-3 text-slate-800 font-medium">${escapeHtml(r.item_name)}</td>
                                <td class="p-3 text-slate-600">${qty} ${escapeHtml(r.unit)}</td>
                                <td class="p-3 text-slate-700 text-right font-mono">₱${lineTotal.toFixed(2)}</td>
                            </tr>
                        `;
                    }
                });
            }
            if (totalEl) totalEl.textContent = '₱' + total.toFixed(2);
            return total;
        }
    } catch(e) {}
    
    if (list) list.innerHTML = '<tr><td colspan="3" class="p-4 text-center text-red-500 text-sm">Failed to load COGS.</td></tr>';
    if (totalEl) totalEl.textContent = '₱0.00';
    return 0;
}

function openProductModal() {
    document.getElementById('product-modal-title').textContent = 'Add Product';
    document.getElementById('prod-id').value = '';
    document.getElementById('prod-name').value = '';
    document.getElementById('prod-category').value = 'Hot Beverage';
    document.getElementById('prod-cogs-display').textContent = '₱0.00';
    document.getElementById('prod-price').value = '';
    
    const list = document.getElementById('prod-cogs-breakdown');
    if (list) list.innerHTML = '<tr><td colspan="3" class="p-4 text-center text-slate-500 text-sm italic">New product. No recipe mapped yet.</td></tr>';
    const totalEl = document.getElementById('prod-cogs-total');
    if (totalEl) totalEl.textContent = '₱0.00';
    
    document.querySelectorAll('input[name="prod-allowed-mods"]').forEach(cb => cb.checked = false);
    
    document.getElementById('product-modal').classList.remove('hidden');
}

function closeProductModal() {
    document.getElementById('product-modal').classList.add('hidden');
}

async function editProduct(p) {
    document.getElementById('product-modal-title').textContent = 'Edit Product';
    document.getElementById('prod-id').value = p.id;
    document.getElementById('prod-name').value = p.name;
    document.getElementById('prod-category').value = p.category;
    document.getElementById('prod-price').value = p.price;
    
    document.getElementById('prod-cogs-display').textContent = 'Calculating...';
    const cogs = await calculateCOGS('product', p.id);
    document.getElementById('prod-cogs-display').textContent = '₱' + cogs.toFixed(2);
    
    const allowed = (p.allowed_modifier_groups || '').split(',');
    document.querySelectorAll('input[name="prod-allowed-mods"]').forEach(cb => {
        cb.checked = allowed.includes(cb.value);
    });
    
    document.getElementById('product-modal').classList.remove('hidden');
}

function openModifierModal() {
    document.getElementById('modifier-modal-title').textContent = 'Add Modifier';
    document.getElementById('mod-id').value = '';
    document.getElementById('mod-name').value = '';
    document.getElementById('mod-group').value = 'Hot Drink Size & Style';
    document.getElementById('mod-price').value = '0.00';
    
    const list = document.getElementById('mod-cogs-breakdown');
    if (list) list.innerHTML = '<tr><td colspan="3" class="p-4 text-center text-slate-500 text-sm italic">New modifier. No recipe mapped yet.</td></tr>';
    const totalEl = document.getElementById('mod-cogs-total');
    if (totalEl) totalEl.textContent = '₱0.00';
    
    document.getElementById('modifier-modal').classList.remove('hidden');
}

function closeModifierModal() {
    document.getElementById('modifier-modal').classList.add('hidden');
}

async function editModifier(m) {
    document.getElementById('modifier-modal-title').textContent = 'Edit Modifier';
    document.getElementById('mod-id').value = m.id;
    document.getElementById('mod-name').value = m.name;
    document.getElementById('mod-group').value = m.modifier_group;
    document.getElementById('mod-price').value = m.price_adjustment;
    
    await calculateCOGS('modifier', m.id);
    
    document.getElementById('modifier-modal').classList.remove('hidden');
}

// --- Actions ---
async function saveProduct(e) {
    e.preventDefault();
    const id = document.getElementById('prod-id').value;
    
    const checkboxes = document.querySelectorAll('input[name="prod-allowed-mods"]:checked');
    const allowed = Array.from(checkboxes).map(cb => cb.value);
    
    const data = {
        type: 'products',
        action: id ? 'update' : 'create',
        id: id,
        name: document.getElementById('prod-name').value,
        category: document.getElementById('prod-category').value,
        price: document.getElementById('prod-price').value,
        allowed_modifier_groups: allowed
    };
    
    const result = await sendCrudRequest(data);
    closeProductModal();
    await loadProducts();
    
    if (!id && result.success && result.id) {
        openRecipeModal('product', result.id, document.getElementById('prod-name').value);
    }
}

async function saveModifier(e) {
    e.preventDefault();
    const id = document.getElementById('mod-id').value;
    const name = document.getElementById('mod-name').value;
    const price = parseFloat(document.getElementById('mod-price').value) || 0;
    
    if (id && price > 0) {
        try {
            const res = await fetch(`api/inventory_crud.php?type=recipes&modifier_id=${id}`);
            const data = await res.json();
            if (data.success && data.data.length === 0) {
                alert('Cannot set a price > 0. You must map at least one inventory ingredient to this modifier first.');
                closeModifierModal();
                openRecipeModal('modifier', id, name);
                return;
            }
        } catch(err) {
            console.error(err);
        }
    }

    const data = {
        type: 'modifiers',
        action: id ? 'update' : 'create',
        id: id,
        name: name,
        modifier_group: document.getElementById('mod-group').value,
        price_adjustment: price
    };
    
    const result = await sendCrudRequest(data);
    closeModifierModal();
    await loadModifiers();

    if (!id && result.success && result.id) {
        openRecipeModal('modifier', result.id, name);
    }
}

function confirmDelete(type, id) {
    document.getElementById('delete-type').value = type;
    document.getElementById('delete-id').value = id;
    document.getElementById('delete-modal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('delete-modal').classList.add('hidden');
}

async function executeDelete() {
    const type = document.getElementById('delete-type').value;
    const id = document.getElementById('delete-id').value;
    
    const result = await sendCrudRequest({ type, action: 'delete', id });
    if (result && result.success) {
        if (type === 'products') loadProducts();
        else loadModifiers();
    }
    closeDeleteModal();
}

async function sendCrudRequest(data) {
    try {
        const res = await fetch('api/menu_crud.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (!result.success) {
            alert('Error: ' + result.error);
        }
        return result;
    } catch (e) {
        alert('Network error occurred.');
        console.error(e);
        return { success: false };
    }
}

function escapeHtml(unsafe) {
    return (unsafe||'').replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
