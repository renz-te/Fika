<?php
require_once __DIR__ . '/../app/bootstrap.php';
class_exists('Output');

Auth::requireLogin();
Rbac::require_permission('inventory.view');

$user = Auth::user();
$canAdjust = Rbac::can('inventory.adjust');
$canApprove = Rbac::can('inventory.approve');

global $pdo;

$user_role_id = $user['role_id'] ?? null;
$user_branch_id = $user['branch_id'] ?? null;
$branches_list = [];

if ($user_branch_id === null) {
    $branches_list = $pdo->query("SELECT id, name FROM branches WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $selectedBranchId = (int)($_GET['branch_id'] ?? ($branches_list[0]['id'] ?? 1));
} else {
    $selectedBranchId = (int)$user_branch_id;
    $bStmt = $pdo->prepare("SELECT id, name FROM branches WHERE id = ?");
    $bStmt->execute([$selectedBranchId]);
    $branches_list = $bStmt->fetchAll(PDO::FETCH_ASSOC);
}

$csrfToken = Csrf::getToken();
$pageTitle = 'Inventory Management · Stock Ledger';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Fika</title>
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
                        <i class="fa-solid fa-boxes-stacked text-base"></i>
                    </div>
                    <div>
                        <h1 class="font-bold text-lg text-slate-900 leading-tight">Inventory Management</h1>
                        <p class="text-xs text-slate-500">Manage raw materials, stock levels, and audit adjustments</p>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <nav class="hidden md:flex items-center gap-6 text-sm font-semibold text-slate-600">
                        <a href="fika_inventory_dashboard.php" class="hover:text-slate-900 transition-colors py-5">Dashboard</a>
                        <a href="fika_inventory_stock.php" class="text-blue-600 font-bold border-b-2 border-blue-600 py-5">Stock Ledger</a>
                        <a href="fika_inventory_procurement.php" class="hover:text-slate-900 transition-colors py-5">Procurement</a>
                        <a href="fika_inventory_recipes.php" class="hover:text-slate-900 transition-colors py-5">Recipe COGS</a>
                    </nav>

                    <?php if ($canAdjust): ?>
                        <button type="button" onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-xl text-xs transition shadow-sm inline-flex items-center gap-1.5">
                            <i class="fa-solid fa-plus text-xs"></i> Add Item
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full flex-1">

            <!-- Branch Scoping & Tabs Bar -->
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 mb-6 flex flex-wrap justify-between items-center gap-4">
                <div class="flex items-center space-x-6">
                    <button type="button" onclick="switchTab('inventory')" id="tab-inventory" class="font-bold text-sm text-blue-600 border-b-2 border-blue-600 pb-1.5 inline-flex items-center gap-2">
                        <i class="fa-solid fa-warehouse"></i> Inventory Stock
                    </button>
                    <button type="button" onclick="switchTab('approvals')" id="tab-approvals" class="font-semibold text-sm text-slate-500 hover:text-slate-800 border-b-2 border-transparent pb-1.5 transition inline-flex items-center gap-2">
                        <i class="fa-solid fa-clipboard-check"></i> Pending Approvals
                        <span id="pending-badge" class="hidden px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-600 text-white leading-none">0</span>
                    </button>
                </div>

                <div class="flex items-center gap-3">
                    <?php if ($user_branch_id === null && count($branches_list) > 1): ?>
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wider"><i class="fa-solid fa-location-dot text-blue-500 mr-1"></i> Branch:</label>
                        <select id="global-branch-selector" onchange="handleBranchChange()" class="border border-slate-300 rounded-xl px-3 py-1.5 text-xs font-semibold focus:ring-2 focus:ring-blue-500 bg-white shadow-xs">
                            <?php foreach ($branches_list as $branch): ?>
                                <option value="<?= $branch['id'] ?>" <?= ($branch['id'] == $selectedBranchId) ? 'selected' : '' ?>><?= e($branch['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="hidden" id="global-branch-selector" value="<?= $selectedBranchId ?>">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 text-xs font-bold border border-slate-200">
                            <i class="fa-solid fa-location-dot text-blue-500"></i> <?= e($branches_list[0]['name'] ?? 'Main Branch') ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab 1: Inventory Stock Table -->
            <div id="view-inventory">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-4 border-b border-slate-200 bg-slate-50/70 flex justify-between items-center">
                        <div class="relative w-72">
                            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                            <input type="text" id="search-input" oninput="filterInventory()" placeholder="Filter items by name..." class="pl-9 pr-3 py-2 border border-slate-300 rounded-xl text-xs w-full focus:ring-2 focus:ring-blue-500 bg-white shadow-xs">
                        </div>
                        <span class="text-xs text-slate-500 font-medium" id="item-counter">Loading...</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-sm">
                            <thead>
                                <tr class="bg-slate-50 text-slate-500 uppercase tracking-wider text-xs border-b border-slate-200">
                                    <th class="py-3.5 px-6 font-bold">Item Name</th>
                                    <th class="py-3.5 px-6 font-bold">Base Unit</th>
                                    <th class="py-3.5 px-6 font-bold">Unit Cost</th>
                                    <th class="py-3.5 px-6 font-bold">Stock Level</th>
                                    <th class="py-3.5 px-6 font-bold">Status</th>
                                    <th class="py-3.5 px-6 font-bold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="inventory-list" class="divide-y divide-slate-100">
                                <tr><td colspan="6" class="p-8 text-center text-slate-400">Loading inventory items...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Pending Approvals Table -->
            <div id="view-approvals" class="hidden">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-4 border-b border-slate-200 bg-slate-50/70">
                        <h3 class="font-bold text-slate-800 text-sm">Pending Stock Adjustments (Maker-Checker Queue)</h3>
                        <p class="text-xs text-slate-500">Adjustments exceeding approval thresholds awaiting verification.</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-sm">
                            <thead>
                                <tr class="bg-slate-50 text-slate-500 uppercase tracking-wider text-xs border-b border-slate-200">
                                    <th class="py-3.5 px-6 font-bold">Date</th>
                                    <th class="py-3.5 px-6 font-bold">Item Name</th>
                                    <th class="py-3.5 px-6 font-bold">Quantity</th>
                                    <th class="py-3.5 px-6 font-bold">Requested By</th>
                                    <th class="py-3.5 px-6 font-bold">Reason</th>
                                    <th class="py-3.5 px-6 font-bold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="approvals-list" class="divide-y divide-slate-100">
                                <tr><td colspan="6" class="p-8 text-center text-slate-400">Loading pending adjustments...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Add / Edit Inventory Item Modal -->
    <div id="inv-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-slate-200">
            <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center bg-slate-50">
                <h3 class="font-bold text-base text-slate-800 flex items-center gap-2" id="modal-title">
                    <i class="fa-solid fa-box text-blue-600"></i> Add Inventory Item
                </h3>
                <button type="button" onclick="closeModal('inv-modal')" class="text-slate-400 hover:text-rose-600 transition p-1">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
            <form id="inv-form" onsubmit="handleItemSave(event)" class="p-6 space-y-4">
                <input type="hidden" id="inv-id" value="">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Item Name *</label>
                    <input type="text" id="inv-name" required placeholder="e.g. Espresso Beans, Whole Milk" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 shadow-xs">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Base Unit *</label>
                        <select id="inv-unit" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 bg-white shadow-xs">
                            <option value="pcs">pcs</option>
                            <option value="g">g</option>
                            <option value="kg">kg</option>
                            <option value="ml">ml</option>
                            <option value="L">L</option>
                            <option value="shots">shots</option>
                            <option value="pumps">pumps</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Unit Cost (₱) *</label>
                        <input type="number" min="0" step="0.01" id="inv-unit-cost" required placeholder="0.00" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono focus:ring-2 focus:ring-blue-500 shadow-xs">
                    </div>
                </div>
                <p class="text-[11px] text-slate-500"><i class="fa-solid fa-circle-info mr-1 text-blue-500"></i>Use base units (e.g. grams, ml, pcs) and input the cost per single unit.</p>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Current Stock</label>
                        <input type="number" step="0.01" id="inv-stock" readonly class="w-full border border-slate-200 bg-slate-50 text-slate-500 rounded-xl px-3 py-2 text-xs font-mono cursor-not-allowed">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Low Stock Alert</label>
                        <input type="number" min="0" step="0.01" id="inv-low-stock" value="10.00" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono focus:ring-2 focus:ring-blue-500 shadow-xs">
                    </div>
                </div>
                <div class="pt-3 flex justify-end gap-2">
                    <button type="button" onclick="closeModal('inv-modal')" class="px-4 py-2 border border-slate-300 text-slate-600 rounded-xl text-xs font-bold hover:bg-slate-50 transition">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-xs transition">Save Item</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Manual Stock Adjustment Modal -->
    <div id="adj-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-slate-200">
            <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center bg-slate-50">
                <h3 class="font-bold text-base text-slate-800 flex items-center gap-2" id="adj-modal-title">
                    <i class="fa-solid fa-sliders text-amber-600"></i> Manual Stock Adjustment
                </h3>
                <button type="button" onclick="closeModal('adj-modal')" class="text-slate-400 hover:text-rose-600 transition p-1">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
            <form id="adj-form" onsubmit="handleAdjustmentSubmit(event)" class="p-6 space-y-4">
                <input type="hidden" id="adj-id" value="">

                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200">
                    <div class="text-xs text-slate-500">Selected Item:</div>
                    <div id="adj-item-name" class="font-bold text-slate-900 text-sm">Item Name</div>
                    <div class="text-xs text-slate-500 mt-1 font-mono">Current Stock: <span id="adj-current-stock" class="font-bold text-slate-800">0.00</span></div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Adjustment Type *</label>
                    <select id="adj-type" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 bg-white shadow-xs">
                        <option value="Spoilage">Spoilage / Expired (Deduct)</option>
                        <option value="Spill">Spill / Breakage (Deduct)</option>
                        <option value="Staff Meal">Staff Meal (Deduct)</option>
                        <option value="Stock Correction">Stock Count Correction</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Quantity <span id="adj-unit-label"></span> *</label>
                    <div class="flex shadow-xs">
                        <input type="number" step="0.01" id="adj-qty" required placeholder="Enter adjustment (+ or -)" class="flex-1 border border-slate-300 rounded-l-xl px-3 py-2 text-xs font-mono focus:ring-2 focus:ring-blue-500">
                        <span id="adj-unit-suffix" class="inline-flex items-center px-3 rounded-r-xl border border-l-0 border-slate-300 bg-slate-50 text-slate-500 text-xs font-bold">unit</span>
                    </div>
                    <p class="text-[11px] text-slate-500 mt-1">For spoilage/spills, enter a negative number (e.g. -500) to deduct from stock.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Remarks / Justification *</label>
                    <textarea id="adj-remarks" rows="2" required class="w-full border border-slate-300 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-blue-500 shadow-xs" placeholder="Reason for inventory variance..."></textarea>
                </div>

                <div class="pt-3 flex justify-end gap-2">
                    <button type="button" onclick="closeModal('adj-modal')" class="px-4 py-2 border border-slate-300 text-slate-600 rounded-xl text-xs font-bold hover:bg-slate-50 transition">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold shadow-xs transition">Submit Adjustment</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    let inventoryItems = [];
    const canApproveUser = <?= $canApprove ? 'true' : 'false' ?>;
    const canAdjustUser = <?= $canAdjust ? 'true' : 'false' ?>;

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

    function switchTab(tab) {
        const tabInv = document.getElementById('tab-inventory');
        const tabApp = document.getElementById('tab-approvals');
        const viewInv = document.getElementById('view-inventory');
        const viewApp = document.getElementById('view-approvals');

        if (tab === 'inventory') {
            tabInv.className = "font-bold text-sm text-blue-600 border-b-2 border-blue-600 pb-1.5 inline-flex items-center gap-2";
            tabApp.className = "font-semibold text-sm text-slate-500 hover:text-slate-800 border-b-2 border-transparent pb-1.5 transition inline-flex items-center gap-2";
            viewInv.classList.remove('hidden');
            viewApp.classList.add('hidden');
        } else {
            tabApp.className = "font-bold text-sm text-blue-600 border-b-2 border-blue-600 pb-1.5 inline-flex items-center gap-2";
            tabInv.className = "font-semibold text-sm text-slate-500 hover:text-slate-800 border-b-2 border-transparent pb-1.5 transition inline-flex items-center gap-2";
            viewInv.classList.add('hidden');
            viewApp.classList.remove('hidden');
            fetchApprovals();
        }
    }

    function handleBranchChange() {
        fetchInventory();
        fetchApprovals();
    }

    async function fetchInventory() {
        const branchSelector = document.getElementById('global-branch-selector');
        const branchId = branchSelector ? branchSelector.value : 1;
        try {
            const res = await fetch(`api/fika_inventory_adjust.php?action=list&branch_id=${branchId}`);
            const data = await res.json();
            if (data.success) {
                inventoryItems = data.data;
                renderInventory(inventoryItems);
            }
        } catch (err) {
            console.error('Failed to load inventory items:', err);
        }
    }

    function renderInventory(items) {
        const list = document.getElementById('inventory-list');
        const counter = document.getElementById('item-counter');
        list.innerHTML = '';
        counter.innerText = `Showing ${items.length} item(s)`;

        if (!items || items.length === 0) {
            list.innerHTML = `<tr><td colspan="6" class="p-8 text-center text-slate-400 font-medium">No inventory items found.</td></tr>`;
            return;
        }

        items.forEach(item => {
            const isLow = (item.current_stock <= item.low_stock_threshold);
            const statusClass = isLow ? 'bg-rose-100 text-rose-800 border border-rose-200' : 'bg-emerald-100 text-emerald-800 border border-emerald-200';
            const statusText = isLow ? 'Low Stock Alert' : 'In Stock';

            const tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-50/70 transition-colors';
            tr.innerHTML = `
                <td class="py-4 px-6 font-bold text-slate-900">${escapeHtml(item.item_name)}</td>
                <td class="py-4 px-6 font-mono text-xs font-semibold text-slate-600">${escapeHtml(item.unit)}</td>
                <td class="py-4 px-6 font-mono text-xs font-semibold text-slate-700">₱${parseFloat(item.unit_cost).toFixed(2)}</td>
                <td class="py-4 px-6">
                    <span class="font-mono text-xs font-bold ${isLow ? 'text-rose-600' : 'text-slate-900'}">${parseFloat(item.current_stock).toFixed(2)}</span>
                    <span class="text-[11px] text-slate-400 ml-1">/ alert: ${parseFloat(item.low_stock_threshold).toFixed(2)}</span>
                </td>
                <td class="py-4 px-6">
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider ${statusClass}">
                        ${statusText}
                    </span>
                </td>
                <td class="py-4 px-6 text-right whitespace-nowrap">
                    <div class="inline-flex items-center gap-2">
                        ${canAdjustUser ? `
                            <button type="button" onclick="openAdjustModal(${item.id})" class="text-amber-700 hover:text-amber-900 bg-amber-50 hover:bg-amber-100 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors inline-flex items-center gap-1">
                                <i class="fa-solid fa-sliders text-[11px]"></i> Adjust
                            </button>
                        ` : ''}
                        ${canAdjustUser ? `
                            <button type="button" onclick="openEditModal(${item.id})" class="text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors inline-flex items-center gap-1">
                                <i class="fa-solid fa-pen text-[11px]"></i> Edit
                            </button>
                        ` : ''}
                    </div>
                </td>
            `;
            list.appendChild(tr);
        });
    }

    function filterInventory() {
        const q = (document.getElementById('search-input').value || '').toLowerCase().trim();
        const filtered = inventoryItems.filter(i => (i.item_name || '').toLowerCase().includes(q));
        renderInventory(filtered);
    }

    async function fetchApprovals() {
        const branchSelector = document.getElementById('global-branch-selector');
        const branchId = branchSelector ? branchSelector.value : 1;
        try {
            const res = await fetch(`api/fika_inventory_adjust.php?action=pending_list&branch_id=${branchId}`);
            const data = await res.json();
            if (data.success) {
                renderApprovals(data.data);
                const badge = document.getElementById('pending-badge');
                if (data.data.length > 0) {
                    badge.innerText = data.data.length;
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
            }
        } catch (err) {
            console.error('Failed to load pending approvals:', err);
        }
    }

    function renderApprovals(listData) {
        const list = document.getElementById('approvals-list');
        list.innerHTML = '';

        if (!listData || listData.length === 0) {
            list.innerHTML = `<tr><td colspan="6" class="p-8 text-center text-slate-400 font-medium">No pending adjustments awaiting approval.</td></tr>`;
            return;
        }

        listData.forEach(adj => {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-50 transition-colors';
            tr.innerHTML = `
                <td class="py-4 px-6 text-xs text-slate-600 font-mono">${escapeHtml(adj.created_at)}</td>
                <td class="py-4 px-6 font-bold text-slate-900">${escapeHtml(adj.item_name)}</td>
                <td class="py-4 px-6 font-mono text-xs font-bold ${adj.quantity < 0 ? 'text-rose-600' : 'text-emerald-600'}">
                    ${parseFloat(adj.quantity).toFixed(2)} ${escapeHtml(adj.unit)}
                </td>
                <td class="py-4 px-6 text-xs text-slate-700 font-medium">${escapeHtml(adj.maker_username || 'Staff')}</td>
                <td class="py-4 px-6 text-xs text-slate-600">${escapeHtml(adj.reason || 'None')}</td>
                <td class="py-4 px-6 text-right whitespace-nowrap">
                    ${canApproveUser ? `
                        <button type="button" onclick="decideApproval(${adj.id}, 'approve')" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-1.5 px-3 rounded-lg text-xs mr-1 shadow-xs transition">
                            <i class="fa-solid fa-check mr-1"></i> Approve
                        </button>
                        <button type="button" onclick="decideApproval(${adj.id}, 'reject')" class="bg-rose-600 hover:bg-rose-700 text-white font-bold py-1.5 px-3 rounded-lg text-xs shadow-xs transition">
                            <i class="fa-solid fa-xmark mr-1"></i> Reject
                        </button>
                    ` : '<span class="text-xs text-slate-400 italic">Pending Maker-Checker</span>'}
                </td>
            `;
            list.appendChild(tr);
        });
    }

    function openAddModal() {
        document.getElementById('modal-title').innerHTML = '<i class="fa-solid fa-box text-blue-600"></i> Add Inventory Item';
        document.getElementById('inv-id').value = '';
        document.getElementById('inv-name').value = '';
        document.getElementById('inv-unit').value = 'pcs';
        document.getElementById('inv-unit-cost').value = '';
        document.getElementById('inv-stock').value = '0.00';
        document.getElementById('inv-low-stock').value = '10.00';
        openModal('inv-modal');
    }

    function openEditModal(id) {
        const item = inventoryItems.find(i => i.id == id);
        if (!item) return;

        document.getElementById('modal-title').innerHTML = '<i class="fa-solid fa-pen-to-square text-blue-600"></i> Edit Inventory Item';
        document.getElementById('inv-id').value = item.id;
        document.getElementById('inv-name').value = item.item_name;
        document.getElementById('inv-unit').value = item.unit;
        document.getElementById('inv-unit-cost').value = parseFloat(item.unit_cost).toFixed(2);
        document.getElementById('inv-stock').value = parseFloat(item.current_stock).toFixed(2);
        document.getElementById('inv-low-stock').value = parseFloat(item.low_stock_threshold).toFixed(2);
        openModal('inv-modal');
    }

    function openAdjustModal(id) {
        const item = inventoryItems.find(i => i.id == id);
        if (!item) return;

        document.getElementById('adj-id').value = item.id;
        document.getElementById('adj-item-name').innerText = item.item_name;
        document.getElementById('adj-current-stock').innerText = `${parseFloat(item.current_stock).toFixed(2)} ${item.unit}`;
        document.getElementById('adj-unit-suffix').innerText = item.unit;
        document.getElementById('adj-unit-label').innerText = `(${item.unit})`;
        document.getElementById('adj-qty').value = '';
        document.getElementById('adj-remarks').value = '';
        openModal('adj-modal');
    }

    async function handleItemSave(e) {
        e.preventDefault();
        const branchSelector = document.getElementById('global-branch-selector');
        const branchId = branchSelector ? branchSelector.value : 1;

        const payload = {
            action: 'item_save',
            id: document.getElementById('inv-id').value,
            item_name: document.getElementById('inv-name').value,
            unit: document.getElementById('inv-unit').value,
            unit_cost: document.getElementById('inv-unit-cost').value,
            low_stock_threshold: document.getElementById('inv-low-stock').value,
            branch_id: branchId,
            csrf_token: csrfToken
        };

        try {
            const res = await fetch('api/fika_inventory_adjust.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify(payload)
            });
            const json = await res.json();
            if (res.ok && json.success) {
                closeModal('inv-modal');
                fetchInventory();
            } else {
                alert(json.error || 'Failed to save item.');
            }
        } catch (err) {
            alert('Network error: ' + err.message);
        }
    }

    async function handleAdjustmentSubmit(e) {
        e.preventDefault();
        const branchSelector = document.getElementById('global-branch-selector');
        const branchId = branchSelector ? branchSelector.value : 1;
        const adjType = document.getElementById('adj-type').value;
        let qty = parseFloat(document.getElementById('adj-qty').value);

        if (isNaN(qty) || qty === 0) {
            alert('Please enter a valid non-zero adjustment quantity.');
            return;
        }

        const remarks = document.getElementById('adj-remarks').value.trim();
        const fullReason = `[${adjType}] ${remarks}`;

        const payload = {
            action: 'submit',
            branch_id: branchId,
            inventory_id: document.getElementById('adj-id').value,
            quantity: qty,
            reason: fullReason,
            csrf_token: csrfToken
        };

        try {
            const res = await fetch('api/fika_inventory_adjust.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify(payload)
            });
            const json = await res.json();
            if (res.ok && json.success) {
                closeModal('adj-modal');
                fetchInventory();
                fetchApprovals();
                if (json.status === 'PENDING') {
                    alert('Adjustment submitted! Because of high value, it is placed in the Maker-Checker Pending Approvals queue.');
                }
            } else {
                alert(json.error || 'Failed to submit adjustment.');
            }
        } catch (err) {
            alert('Network error: ' + err.message);
        }
    }

    async function decideApproval(adjId, decision) {
        if (!confirm(`Are you sure you want to ${decision} this stock adjustment?`)) return;

        try {
            const res = await fetch('api/fika_inventory_adjust.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({
                    action: decision,
                    adjustment_id: adjId,
                    csrf_token: csrfToken
                })
            });
            const json = await res.json();
            if (res.ok && json.success) {
                fetchInventory();
                fetchApprovals();
            } else {
                alert(json.error || `Failed to ${decision} adjustment.`);
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
        fetchInventory();
        fetchApprovals();
    });
    </script>
</body>
</html>
