<?php
require_once __DIR__ . '/init.php';

// Access Control
if (empty($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['Super Admin', 'Admin', 'Head Barista'])) {
    redirect('dashboard');
}

$user_role = $_SESSION['user']['role'];
$user_branch_id = $_SESSION['user']['branch_id'] ?? null;
$branches_list = [];

if (in_array($user_role, ['Super Admin', 'Admin'])) {
    $branches_list = $pdo->query("SELECT id, name FROM branches WHERE status = 'Active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = 'Inventory Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Cafe HRMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-50 font-sans text-slate-800 h-screen flex overflow-hidden">

    <?php include 'includes/header.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="bg-white border-b border-slate-200 px-6 py-4 flex justify-between items-center z-10">
            <div>
                <h2 class="text-xl font-bold text-slate-800">Inventory Management</h2>
                <p class="text-sm text-slate-500">Manage raw materials and stock levels</p>
            </div>
            <div class="flex gap-3">
                <button onclick="openModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    <i class="fa-solid fa-plus mr-2"></i> Add Item
                </button>
            </div>
        </header>

        <?php if (in_array($user_role, ['Super Admin', 'Admin'])): ?>
            <div class="px-6 py-3 bg-white border-b border-slate-200 flex items-center shadow-sm z-0 relative">
                <label class="text-sm font-medium text-slate-700 mr-3"><i class="fa-solid fa-location-dot mr-1 text-blue-500"></i> Scope Branch:</label>
                <select id="global-branch-selector" class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm w-64 bg-slate-50 focus:bg-white focus:ring-blue-500 focus:border-blue-500" onchange="handleBranchChange()">
                    <?php foreach ($branches_list as $branch): ?>
                        <option value="<?= $branch['id'] ?>" <?= ($branch['id'] == $user_branch_id) ? 'selected' : '' ?>><?= htmlspecialchars($branch['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php else: ?>
            <input type="hidden" id="global-branch-selector" value="<?= $user_branch_id ?>">
        <?php endif; ?>

        <div class="px-6 border-b border-slate-200 bg-white">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                <button onclick="switchTab('inventory')" id="tab-inventory" class="border-blue-500 text-blue-600 whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium">Inventory Stock</button>
                <?php if (in_array($user_role, ['Super Admin', 'Admin'])): ?>
                <button onclick="switchTab('approvals')" id="tab-approvals" class="border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium">Pending Approvals</button>
                <?php endif; ?>
            </nav>
        </div>

        <main class="flex-1 overflow-y-auto p-6 bg-slate-50 relative">
            <?php if (isset($_SESSION['success_msg'])): ?>
                <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg flex justify-between items-center shadow-sm relative">
                    <div class="flex items-center">
                        <i class="fa-solid fa-circle-check mr-2"></i>
                        <span class="block sm:inline"><?php echo htmlspecialchars($_SESSION['success_msg']); ?></span>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-emerald-700 hover:text-emerald-900 focus:outline-none">
                        <i class="fa-solid fa-times"></i>
                    </button>
                </div>
                <?php unset($_SESSION['success_msg']); ?>
            <?php endif; ?>

            <div id="view-inventory">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 text-sm border-b border-slate-200">
                            <th class="p-4 font-medium">Item Name</th>
                            <th class="p-4 font-medium">Unit</th>
                            <th class="p-4 font-medium">Unit Cost (₱)</th>
                            <th class="p-4 font-medium">Stock Level</th>
                            <th class="p-4 font-medium">Status</th>
                            <th class="p-4 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="inventory-list">
                        <!-- Injected by JS -->
                    </tbody>
                </table>
                </div>
            </div>

            <div id="view-approvals" class="hidden">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 text-sm border-b border-slate-200">
                            <th class="p-4 font-medium">Date</th>
                            <th class="p-4 font-medium">Item Name</th>
                            <th class="p-4 font-medium">Type</th>
                            <th class="p-4 font-medium">Quantity Deducted</th>
                            <th class="p-4 font-medium">Remarks / Evidence</th>
                            <th class="p-4 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="approvals-list">
                        <!-- Injected by JS -->
                    </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Inventory Modal -->
    <div id="inv-modal" class="fixed inset-0 bg-slate-900/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center">
                <h3 class="font-bold text-lg text-slate-800" id="modal-title">Add Inventory Item</h3>
                <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-times"></i></button>
            </div>
            <form id="inv-form" class="p-6 space-y-4">
                <input type="hidden" id="inv-id">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Item Name</label>
                    <input type="text" id="inv-name" required class="w-full border-slate-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Unit (e.g. pcs, g)</label>
                        <select id="inv-unit" required class="w-full border-slate-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border">
                            <option value="pcs">pcs</option>
                            <option value="g">g</option>
                            <option value="kg">kg</option>
                            <option value="ml">ml</option>
                            <option value="L">L</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Unit Cost (₱)</label>
                        <input type="number" min="0" step="0.01" onkeydown="return ['e', 'E', '-', '+'].includes(event.key) ? false : true" id="inv-unit-cost" required class="w-full border-slate-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border">
                    </div>
                </div>
                <p class="text-xs text-slate-500"><i class="fa-solid fa-circle-info mr-1"></i>Use base units (e.g., grams, ml, pcs) and input the exact cost per single unit.</p>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Current Stock</label>
                        <input type="number" min="0" step="0.01" onkeydown="return ['e', 'E', '-', '+'].includes(event.key) ? false : true" id="inv-stock" readonly required class="w-full border-slate-300 bg-slate-50 text-slate-500 cursor-not-allowed rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Low Stock Alert Level</label>
                        <input type="number" min="0" step="0.01" onkeydown="return ['e', 'E', '-', '+'].includes(event.key) ? false : true" id="inv-low-stock" required class="w-full border-slate-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border">
                    </div>
                </div>
                <div class="pt-4 flex justify-end gap-3">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 font-medium text-sm">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm">Save Item</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Manual Adjustment Modal -->
    <div id="adj-modal" class="fixed inset-0 bg-slate-900/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center">
                <h3 class="font-bold text-lg text-slate-800" id="adj-modal-title">Manual Stock Adjustment</h3>
                <button onclick="closeAdjModal()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-times"></i></button>
            </div>
            <form id="adj-form" class="p-6 space-y-4">
                <input type="hidden" id="adj-id" value="">
                <div class="mb-2">
                    <p class="text-sm font-medium text-slate-700"><span id="adj-item-name" class="font-bold text-slate-900"></span></p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Adjustment Type</label>
                    <select id="adj-type" required class="w-full border-slate-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border">
                        <option value="Spoilage">Spoilage</option>
                        <option value="Spill">Spill</option>
                        <option value="Staff Meal">Staff Meal</option>
                        <option value="Stock Correction">Stock Correction</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Quantity to Deduct <span id="adj-unit-label"></span></label>
                    <div class="flex shadow-sm">
                        <input type="number" min="0" step="0.01" onkeydown="return ['e', 'E', '-', '+'].includes(event.key) ? false : true" id="adj-qty" required class="flex-1 border-slate-300 rounded-none rounded-l-lg focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border focus:z-10">
                        <span id="adj-unit-suffix" class="inline-flex items-center px-3 rounded-none rounded-r-lg border border-l-0 border-slate-300 bg-slate-50 text-slate-500 text-sm font-medium"></span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">This quantity will be subtracted from the current stock and logged.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Remarks</label>
                    <textarea id="adj-remarks" rows="2" class="w-full border-slate-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border" placeholder="Enter justification..."></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Evidence (Optional)</label>
                    <input type="file" id="adjustment-evidence" accept="image/*" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                </div>
                <div class="pt-4 flex justify-end gap-3">
                    <button type="button" onclick="closeAdjModal()" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 font-medium text-sm">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 font-medium text-sm">Log Adjustment</button>
                </div>
            </form>
        </div>
    </div>

<script>
let items = [];

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.innerText = str;
    return div.innerHTML;
}

async function fetchInventory() {
    try {
        const branchSelector = document.getElementById('global-branch-selector');
        const branchId = branchSelector ? branchSelector.value : 1;
        const res = await fetch(`api/inventory_crud.php?type=inventory&branch_id=${branchId}`);
        const data = await res.json();
        if (data.success) {
            items = data.data;
            renderInventory();
        }
    } catch (err) {
        console.error(err);
    }
}

function renderInventory() {
    const list = document.getElementById('inventory-list');
    list.innerHTML = '';
    
    if (items.length === 0) {
        list.innerHTML = `<tr><td colspan="5" class="p-8 text-center text-slate-500">No inventory items found.</td></tr>`;
        return;
    }
    
    items.forEach(item => {
        const tr = document.createElement('tr');
        tr.className = 'border-b border-slate-100 hover:bg-slate-50 transition-colors';
        
        const stock = parseFloat(item.stock);
        const low = parseFloat(item.low_stock_level);
        
        let statusHtml = '';
        if (stock <= 0) {
            statusHtml = `<span class="bg-red-100 text-red-700 px-2 py-1 rounded text-xs font-medium">Out of Stock</span>`;
        } else if (stock <= low) {
            statusHtml = `<span class="bg-orange-100 text-orange-700 px-2 py-1 rounded text-xs font-medium">Low Stock</span>`;
        } else {
            statusHtml = `<span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-medium">In Stock</span>`;
        }
        
        tr.innerHTML = `
            <td class="p-4 font-medium text-slate-900">${escapeHtml(item.item_name)}</td>
            <td class="p-4 text-slate-600">${escapeHtml(item.unit)}</td>
            <td class="p-4 text-slate-600">₱${parseFloat(item.unit_cost || 0).toFixed(2)}</td>
            <td class="p-4 font-bold text-slate-700">${stock.toFixed(2)}</td>
            <td class="p-4">${statusHtml}</td>
            <td class="p-4 text-right">
                <a href="procurement?request_item_id=${item.id}" class="text-emerald-500 hover:text-emerald-700 mr-3" title="Order"><i class="fa-solid fa-cart-plus"></i></a>
                <button onclick='openAdjModal(${JSON.stringify(item)})' class="text-amber-500 hover:text-amber-700 mr-3" title="Manual Adjustment"><i class="fa-solid fa-scale-balanced"></i></button>
                <button onclick='editItem(${JSON.stringify(item)})' class="text-blue-500 hover:text-blue-700 mr-3"><i class="fa-solid fa-pen-to-square"></i></button>
                <button onclick='deleteItem(${item.id})' class="text-red-500 hover:text-red-700"><i class="fa-solid fa-trash"></i></button>
            </td>
        `;
        list.appendChild(tr);
    });
}

function openModal() {
    document.getElementById('modal-title').textContent = 'Add Inventory Item';
    document.getElementById('inv-id').value = '';
    document.getElementById('inv-name').value = '';
    document.getElementById('inv-unit').value = '';
    document.getElementById('inv-unit-cost').value = '';
    document.getElementById('inv-stock').value = '';
    document.getElementById('inv-low-stock').value = '';
    document.getElementById('inv-modal').classList.remove('hidden');
    document.getElementById('inv-modal').classList.add('flex');
}

function closeModal() {
    document.getElementById('inv-modal').classList.add('hidden');
    document.getElementById('inv-modal').classList.remove('flex');
}

function editItem(item) {
    document.getElementById('modal-title').textContent = 'Edit Inventory Item';
    document.getElementById('inv-id').value = item.id;
    document.getElementById('inv-name').value = item.item_name;
    document.getElementById('inv-unit').value = item.unit;
    document.getElementById('inv-unit-cost').value = item.unit_cost;
    document.getElementById('inv-stock').value = item.stock;
    document.getElementById('inv-low-stock').value = item.low_stock_level;
    document.getElementById('inv-modal').classList.remove('hidden');
    document.getElementById('inv-modal').classList.add('flex');
}

function openAdjModal(item) {
    document.getElementById('adj-modal-title').textContent = 'Manual Stock Adjustment - ' + item.item_name;
    document.getElementById('adj-id').value = item.id;
    document.getElementById('adj-qty').value = '';
    document.getElementById('adj-type').value = 'Spoilage';
    document.getElementById('adj-unit-label').textContent = '(' + item.unit + ')';
    document.getElementById('adj-unit-suffix').textContent = item.unit;
    document.getElementById('adj-modal').classList.remove('hidden');
    document.getElementById('adj-modal').classList.add('flex');
}

function closeAdjModal() {
    document.getElementById('adj-modal').classList.add('hidden');
    document.getElementById('adj-modal').classList.remove('flex');
}

document.getElementById('inv-form').onsubmit = async (e) => {
    e.preventDefault();
    
    const id = document.getElementById('inv-id').value;
    const branchSelector = document.getElementById('global-branch-selector');
    const branchId = branchSelector ? branchSelector.value : 1;

    const payload = {
        type: 'inventory',
        action: id ? 'update' : 'create',
        id: id,
        item_name: document.getElementById('inv-name').value,
        unit: document.getElementById('inv-unit').value,
        unit_cost: parseFloat(document.getElementById('inv-unit-cost').value),
        stock: parseFloat(document.getElementById('inv-stock').value),
        low_stock_level: parseFloat(document.getElementById('inv-low-stock').value),
        branch_id: branchId
    };
    
    try {
        const res = await fetch('api/inventory_crud.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if(data.success) {
            closeModal();
            fetchInventory();
        } else {
            alert(data.error || 'Failed to save');
        }
    } catch(err) {
        console.error(err);
    }
};

document.getElementById('adj-form').onsubmit = async (e) => {
    e.preventDefault();
    
    const branchSelector = document.getElementById('global-branch-selector');
    const branchId = branchSelector ? branchSelector.value : 1;

    const formData = new FormData();
    formData.append('type', 'inventory');
    formData.append('action', 'manual_adjust');
    formData.append('id', document.getElementById('adj-id').value);
    formData.append('branch_id', branchId);
    formData.append('adjustment_type', document.getElementById('adj-type').value);
    formData.append('quantity', document.getElementById('adj-qty').value);
    formData.append('remarks', document.getElementById('adj-remarks').value);
    
    const evidenceInput = document.getElementById('adjustment-evidence');
    if(evidenceInput.files.length > 0) {
        formData.append('evidence', evidenceInput.files[0]);
    }
    
    try {
        const res = await fetch('api/inventory_crud.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if(data.success) {
            window.location.reload();
        } else {
            alert(data.error || 'Failed to log adjustment');
        }
    } catch(err) {
        console.error(err);
        alert('An error occurred while logging the adjustment.');
    }
};

async function deleteItem(id) {
    if(!confirm('Are you sure you want to delete this inventory item? Associated recipes may break.')) return;
    
    try {
        const res = await fetch('api/inventory_crud.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ type: 'inventory', action: 'delete', id: id })
        });
        const data = await res.json();
        if(data.success) {
            fetchInventory();
        }
    } catch(err) {
        console.error(err);
    }
}

let pendingItems = [];

function switchTab(tab) {
    document.getElementById('view-inventory').classList.add('hidden');
    document.getElementById('view-approvals')?.classList.add('hidden');
    
    document.getElementById('tab-inventory').className = "border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium";
    const tabApprovals = document.getElementById('tab-approvals');
    if (tabApprovals) tabApprovals.className = "border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium";

    document.getElementById(`view-${tab}`).classList.remove('hidden');
    document.getElementById(`tab-${tab}`).className = "border-blue-500 text-blue-600 whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium";

    if (tab === 'approvals' && typeof fetchPendingAdjustments === 'function') {
        fetchPendingAdjustments();
    } else {
        fetchInventory();
    }
}

async function fetchPendingAdjustments() {
    try {
        const branchSelector = document.getElementById('global-branch-selector');
        const branchId = branchSelector ? branchSelector.value : 1;
        const res = await fetch('api/inventory_crud.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ type: 'inventory', action: 'get_pending_adjustments', branch_id: branchId })
        });
        const data = await res.json();
        if (data.success) {
            pendingItems = data.data;
            renderPendingQueue();
        }
    } catch(err) {
        console.error(err);
    }
}

function renderPendingQueue() {
    const list = document.getElementById('approvals-list');
    if (!list) return;
    list.innerHTML = '';

    if (pendingItems.length === 0) {
        list.innerHTML = `<tr><td colspan="6" class="p-8 text-center text-slate-500">No pending approvals.</td></tr>`;
        return;
    }

    pendingItems.forEach(item => {
        const tr = document.createElement('tr');
        tr.className = 'border-b border-slate-100 hover:bg-slate-50 transition-colors';
        
        const evidenceHtml = item.evidence_img 
            ? `<a href="${escapeHtml(item.evidence_img)}" target="_blank" class="text-blue-500 hover:underline text-xs block mt-1"><i class="fa-solid fa-image"></i> View Evidence</a>`
            : '';

        tr.innerHTML = `
            <td class="p-4 text-slate-600">${escapeHtml(item.transaction_date)}</td>
            <td class="p-4 font-medium text-slate-900">${escapeHtml(item.item_name)}</td>
            <td class="p-4 text-slate-600"><span class="bg-amber-100 text-amber-800 px-2 py-1 rounded text-xs font-medium">${escapeHtml(item.type)}</span></td>
            <td class="p-4 font-bold text-red-600">-${parseFloat(item.quantity).toFixed(2)} ${escapeHtml(item.unit)}</td>
            <td class="p-4 text-sm text-slate-600">
                <div class="truncate w-48" title="${escapeHtml(item.remarks)}">${escapeHtml(item.remarks || 'No remarks')}</div>
                ${evidenceHtml}
            </td>
            <td class="p-4 text-right">
                <button onclick="approveAdjustment(${item.id})" class="text-green-600 hover:text-green-800 mr-3 px-3 py-1 bg-green-50 rounded" title="Approve">Approve</button>
                <button onclick="rejectAdjustment(${item.id})" class="text-red-600 hover:text-red-800 px-3 py-1 bg-red-50 rounded" title="Reject">Reject</button>
            </td>
        `;
        list.appendChild(tr);
    });
}

async function approveAdjustment(id) {
    if(!confirm('Are you sure you want to approve this adjustment? Stock will be deducted immediately.')) return;
    try {
        const res = await fetch('api/inventory_crud.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ type: 'inventory', action: 'approve_adjustment', id: id })
        });
        const data = await res.json();
        if(data.success) {
            fetchPendingAdjustments();
        } else {
            alert(data.error || 'Failed to approve');
        }
    } catch(err) {
        console.error(err);
    }
}

async function rejectAdjustment(id) {
    if(!confirm('Are you sure you want to reject this adjustment?')) return;
    try {
        const res = await fetch('api/inventory_crud.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ type: 'inventory', action: 'reject_adjustment', id: id })
        });
        const data = await res.json();
        if(data.success) {
            fetchPendingAdjustments();
        } else {
            alert(data.error || 'Failed to reject');
        }
    } catch(err) {
        console.error(err);
    }
}

function handleBranchChange() {
    fetchInventory();
    if(typeof fetchPendingAdjustments === 'function' && document.getElementById('tab-approvals')) {
        fetchPendingAdjustments();
    }
}

// Initial load
fetchInventory();
if(document.getElementById('tab-approvals')) {
    fetchPendingAdjustments();
}
</script>
</body>
</html>
