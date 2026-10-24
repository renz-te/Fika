<?php
require_once __DIR__ . '/../app/bootstrap.php';

Auth::requireLogin();
Rbac::require_permission('inventory.view');

$user = Auth::user();
global $pdo;

// Fetch branches for branch switcher (Admin) or single branch
$stmtBranches = $pdo->query("SELECT id, name FROM branches ORDER BY id ASC");
$allBranches = $stmtBranches->fetchAll();

$userBranchId = $user['branch_id'] ?? null;
if ($userBranchId !== null) {
    $currentBranchId = (int)$userBranchId;
} else {
    $currentBranchId = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : (int)($allBranches[0]['id'] ?? 1);
}

// Fetch branch name
$branchName = 'Branch ' . $currentBranchId;
foreach ($allBranches as $b) {
    if ((int)$b['id'] === $currentBranchId) {
        $branchName = $b['name'];
        break;
    }
}

$canAdjust = Rbac::can('inventory.adjust');
$canApprove = Rbac::can('inventory.approve');
$csrfToken = Csrf::getToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Stock & Adjustments - Fika</title>
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
                        <span class="text-base font-extrabold text-slate-900 tracking-tight block leading-none">Stock Ledger & Adjustments</span>
                        <span class="text-[10px] uppercase font-bold text-blue-700 tracking-wider">Inventory Counts & Maker-Checker</span>
                    </div>
                </div>

                <nav class="flex items-center gap-6 text-sm font-semibold text-slate-600">
                    <a href="fika_inventory_dashboard.php" class="hover:text-slate-900 transition-colors py-5">Dashboard</a>
                    <a href="fika_inventory_stock.php" class="text-blue-600 font-bold border-b-2 border-blue-600 py-5">Stock Ledger</a>
                    <a href="fika_inventory_procurement.php" class="hover:text-slate-900 transition-colors py-5">Procurement</a>
                    <a href="fika_inventory_recipes.php" class="hover:text-slate-900 transition-colors py-5">Recipe COGS</a>
                </nav>
            </div>
        </header>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 w-full flex-1">

    <!-- Main Content Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Toast / Alerts -->
        <div id="alertBox" class="hidden mb-6 p-4 rounded-lg text-sm border shadow-sm transition-all" role="alert"></div>

        <!-- Metric Stat Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-6">
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Total Items</div>
                    <div id="statTotalItems" class="text-2xl font-bold text-gray-900 mt-1">--</div>
                </div>
                <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center font-bold text-lg">
                    📦
                </div>
            </div>
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Low Stock Alerts</div>
                    <div id="statLowStock" class="text-2xl font-bold text-amber-600 mt-1">--</div>
                </div>
                <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center font-bold text-lg">
                    ⚠️
                </div>
            </div>
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Pending Approvals</div>
                    <div id="statPendingApprovals" class="text-2xl font-bold text-purple-600 mt-1">--</div>
                </div>
                <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center font-bold text-lg">
                    ⏳
                </div>
            </div>
        </div>

        <!-- Navigation Tabs & Actions -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mb-6">
            <div class="flex flex-wrap justify-between items-center border-b border-gray-200 px-6 pt-2 bg-gray-50/50">
                <nav class="flex space-x-8 -mb-px" aria-label="Tabs">
                    <button type="button" onclick="switchTab('stock')" id="tabBtnStock" class="tab-btn active py-4 px-1 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                        Live Stock Ledger
                    </button>
                    <button type="button" onclick="switchTab('pending')" id="tabBtnPending" class="tab-btn py-4 px-1 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 inline-flex items-center">
                        Pending Approvals
                        <span id="pendingBadge" class="hidden ml-2 px-2 py-0.5 text-xs font-bold bg-purple-100 text-purple-800 rounded-full">0</span>
                    </button>
                </nav>

                <div class="py-2 flex items-center space-x-3">
                    <?php if ($canAdjust): ?>
                        <button type="button" onclick="openItemModal()" class="inline-flex items-center px-3.5 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            + Add New Item
                        </button>
                    <?php endif; ?>
                    <button type="button" onclick="loadAllData()" class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 shadow-sm">
                        🔄 Refresh
                    </button>
                </div>
            </div>

            <!-- Tab 1: Live Stock Table -->
            <div id="tabPanelStock" class="p-6">
                <div class="flex flex-wrap justify-between items-center gap-4 mb-4">
                    <div class="relative w-full sm:w-72">
                        <input type="text" id="searchInput" oninput="filterItems()" placeholder="Search item or unit..." class="w-full text-sm border border-gray-300 rounded-lg pl-3 pr-8 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="flex items-center space-x-2">
                        <label class="text-xs text-gray-600 font-medium">Filter:</label>
                        <select id="stockFilter" onchange="filterItems()" class="text-xs border border-gray-300 rounded-md px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="all">All Items</option>
                            <option value="low">Low Stock Only</option>
                            <option value="healthy">Adequate Stock</option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-gray-600 font-semibold text-xs uppercase tracking-wider">
                            <tr>
                                <th class="px-5 py-3.5 text-left">Item Name</th>
                                <th class="px-4 py-3.5 text-left">Unit</th>
                                <th class="px-4 py-3.5 text-right">Unit Cost</th>
                                <th class="px-4 py-3.5 text-right">Current Stock</th>
                                <th class="px-4 py-3.5 text-center">Status</th>
                                <th class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="stockTableBody" class="divide-y divide-gray-200 bg-white">
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">Loading live stock data...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 2: Pending Approvals (Maker-Checker) -->
            <div id="tabPanelPending" class="p-6 hidden">
                <div class="bg-blue-50/60 border border-blue-200 rounded-lg p-4 mb-5 text-sm text-blue-900 flex items-start space-x-3">
                    <div class="text-lg">ℹ️</div>
                    <div>
                        <div class="font-semibold">Maker-Checker Policy Enforcement</div>
                        <div class="text-xs text-blue-800 mt-0.5">
                            Adjustments with total financial impact exceeding ₱500.00 require secondary approval by an authorized checker (Branch Admin or Admin). The submitting maker cannot approve their own submission.
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-gray-600 font-semibold text-xs uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3.5 text-left">Date Submitted</th>
                                <th class="px-4 py-3.5 text-left">Item</th>
                                <th class="px-4 py-3.5 text-right">Qty</th>
                                <th class="px-4 py-3.5 text-right">Unit Cost</th>
                                <th class="px-4 py-3.5 text-right">Total Impact</th>
                                <th class="px-4 py-3.5 text-left">Reason / Remarks</th>
                                <th class="px-4 py-3.5 text-left">Maker</th>
                                <th class="px-4 py-3.5 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="pendingTableBody" class="divide-y divide-gray-200 bg-white">
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-gray-500">Loading pending adjustments...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal: Stock Adjustment -->
    <div id="adjustModal" class="hidden fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-md w-full shadow-2xl border border-gray-200 overflow-hidden transform transition-all">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                <h3 class="text-lg font-bold text-gray-900">Adjust Inventory Stock</h3>
                <button type="button" onclick="closeAdjustModal()" class="text-gray-400 hover:text-gray-600 font-bold text-xl">&times;</button>
            </div>
            <form id="adjustForm" onsubmit="handleAdjustSubmit(event)" class="p-6 space-y-4">
                <input type="hidden" id="adjInventoryId" name="inventory_id">
                
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase">Item</label>
                    <div id="adjItemDisplay" class="text-base font-semibold text-gray-900 mt-1">--</div>
                </div>

                <div class="grid grid-cols-2 gap-4 bg-gray-50 p-3 rounded-lg border border-gray-200">
                    <div>
                        <div class="text-xs text-gray-500 font-medium">Current Stock</div>
                        <div id="adjCurrentStockDisplay" class="text-base font-bold text-gray-800">0.00</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 font-medium">Unit Cost</div>
                        <div id="adjUnitCostDisplay" class="text-base font-bold text-gray-800">₱0.00</div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase">Adjustment Quantity</label>
                    <p class="text-xs text-gray-500 mb-1.5">Enter positive for addition/restock, negative for deduction/spoilage.</p>
                    <input type="number" step="0.01" id="adjQuantity" name="quantity" required oninput="calcAdjustmentImpact()" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Live Impact Calculator -->
                <div id="adjImpactBox" class="p-3 rounded-lg border text-xs space-y-1 bg-gray-50 border-gray-200">
                    <div class="flex justify-between font-semibold text-gray-700">
                        <span>New Resulting Stock:</span>
                        <span id="adjNewStock">0.00</span>
                    </div>
                    <div class="flex justify-between font-semibold text-gray-700">
                        <span>Financial Value:</span>
                        <span id="adjTotalImpact">₱0.00</span>
                    </div>
                    <div id="adjThresholdWarning" class="text-xs font-medium pt-1 text-gray-500">
                        Adjustments over ₱500.00 require Maker-Checker approval.
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase">Reason / Justification <span class="text-red-500">*</span></label>
                    <textarea id="adjReason" name="reason" rows="2.5" required placeholder="e.g. Broken bag during restocking, weekly audit discrepancy" class="w-full text-sm border border-gray-300 rounded-lg p-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>

                <div class="pt-2 flex justify-end space-x-3">
                    <button type="button" onclick="closeAdjustModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" id="adjSubmitBtn" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">Submit Adjustment</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Add / Edit Item -->
    <div id="itemModal" class="hidden fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-md w-full shadow-2xl border border-gray-200 overflow-hidden transform transition-all">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                <h3 id="itemModalTitle" class="text-lg font-bold text-gray-900">Add Inventory Item</h3>
                <button type="button" onclick="closeItemModal()" class="text-gray-400 hover:text-gray-600 font-bold text-xl">&times;</button>
            </div>
            <form id="itemForm" onsubmit="handleItemSubmit(event)" class="p-6 space-y-4">
                <input type="hidden" id="itemId" name="id" value="0">
                
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase">Item Name <span class="text-red-500">*</span></label>
                    <input type="text" id="itemName" name="item_name" required placeholder="e.g. Arabica Coffee Beans" class="mt-1 w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase">Unit <span class="text-red-500">*</span></label>
                        <input type="text" id="itemUnit" name="unit" required placeholder="e.g. kg, pcs, liters" class="mt-1 w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase">Unit Cost (₱) <span class="text-red-500">*</span></label>
                        <input type="number" step="0.01" min="0" id="itemUnitCost" name="unit_cost" required placeholder="0.00" class="mt-1 w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase">Low Stock Threshold</label>
                    <p class="text-xs text-gray-500 mb-1">When stock drops to or below this level, a warning badge is shown.</p>
                    <input type="number" step="0.01" min="0" id="itemLowThreshold" name="low_stock_threshold" value="10.00" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="pt-2 flex justify-end space-x-3">
                    <button type="button" onclick="closeItemModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" id="itemSubmitBtn" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">Save Item</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript Application Logic -->
    <script>
        const CURRENT_BRANCH_ID = <?= (int)$currentBranchId ?>;
        const CURRENT_USER_ID = <?= (int)$user['id'] ?>;
        const CAN_ADJUST = <?= $canAdjust ? 'true' : 'false' ?>;
        const CAN_APPROVE = <?= $canApprove ? 'true' : 'false' ?>;
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const API_URL = 'api/fika_inventory_adjust.php';

        let itemsData = [];
        let pendingData = [];
        let activeAdjustItem = null;

        document.addEventListener('DOMContentLoaded', () => {
            loadAllData();
        });

        function changeBranch(branchId) {
            window.location.href = `fika_inventory_stock.php?branch_id=${branchId}`;
        }

        function switchTab(tab) {
            const stockTab = document.getElementById('tabPanelStock');
            const pendingTab = document.getElementById('tabPanelPending');
            const btnStock = document.getElementById('tabBtnStock');
            const btnPending = document.getElementById('tabBtnPending');

            if (tab === 'stock') {
                stockTab.classList.remove('hidden');
                pendingTab.classList.add('hidden');
                btnStock.classList.add('active');
                btnPending.classList.remove('active');
            } else {
                stockTab.classList.add('hidden');
                pendingTab.classList.remove('hidden');
                btnStock.classList.remove('active');
                btnPending.classList.add('active');
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

        async function loadAllData() {
            await Promise.all([fetchStockList(), fetchPendingList()]);
        }

        async function fetchStockList() {
            try {
                const res = await fetch(`${API_URL}?action=list&branch_id=${CURRENT_BRANCH_ID}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    showAlert(data.error || 'Failed to fetch inventory stock.', true);
                    return;
                }
                itemsData = data.data || [];
                renderStockTable(itemsData);
                updateStats();
            } catch (err) {
                showAlert('Network error fetching stock: ' + err.message, true);
            }
        }

        async function fetchPendingList() {
            try {
                const res = await fetch(`${API_URL}?action=pending_list&branch_id=${CURRENT_BRANCH_ID}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    showAlert(data.error || 'Failed to fetch pending adjustments.', true);
                    return;
                }
                pendingData = data.data || [];
                renderPendingTable(pendingData);
                updateStats();
            } catch (err) {
                showAlert('Network error fetching pending approvals: ' + err.message, true);
            }
        }

        function updateStats() {
            document.getElementById('statTotalItems').textContent = itemsData.length;
            const lowCount = itemsData.filter(i => i.is_low_stock).length;
            document.getElementById('statLowStock').textContent = lowCount;
            document.getElementById('statPendingApprovals').textContent = pendingData.length;

            const badge = document.getElementById('pendingBadge');
            if (pendingData.length > 0) {
                badge.textContent = pendingData.length;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }

        function filterItems() {
            const query = (document.getElementById('searchInput').value || '').toLowerCase();
            const filter = document.getElementById('stockFilter').value;

            const filtered = itemsData.filter(item => {
                const matchesQuery = item.item_name.toLowerCase().includes(query) || (item.unit && item.unit.toLowerCase().includes(query));
                if (!matchesQuery) return false;

                if (filter === 'low') return item.is_low_stock;
                if (filter === 'healthy') return !item.is_low_stock;
                return true;
            });

            renderStockTable(filtered);
        }

        function renderStockTable(items) {
            const tbody = document.getElementById('stockTableBody');
            if (!items || items.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">No inventory items found for this branch.</td></tr>`;
                return;
            }

            tbody.innerHTML = items.map(item => {
                const stock = parseFloat(item.current_stock || 0);
                const threshold = parseFloat(item.low_stock_threshold || 10);
                const unitCost = parseFloat(item.unit_cost || 0);
                const isLow = item.is_low_stock;

                const statusBadge = isLow
                    ? `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                         ⚠️ Low Stock (≤ ${threshold.toFixed(2)})
                       </span>`
                    : `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                         ✓ In Stock
                       </span>`;

                return `
                    <tr class="hover:bg-gray-50/80 transition-colors">
                        <td class="px-5 py-3.5 font-medium text-gray-900">${escapeHtml(item.item_name)}</td>
                        <td class="px-4 py-3.5 text-gray-600">${escapeHtml(item.unit)}</td>
                        <td class="px-4 py-3.5 text-right font-mono text-gray-800">₱${unitCost.toFixed(2)}</td>
                        <td class="px-4 py-3.5 text-right font-mono font-bold ${isLow ? 'text-red-600' : 'text-gray-900'}">${stock.toFixed(2)}</td>
                        <td class="px-4 py-3.5 text-center">${statusBadge}</td>
                        <td class="px-5 py-3.5 text-right space-x-2">
                            ${CAN_ADJUST ? `
                                <button type="button" onclick="openAdjustModal(${item.id})" class="inline-flex items-center px-2.5 py-1 bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 rounded text-xs font-semibold">
                                    Adjust
                                </button>
                                <button type="button" onclick="editItem(${item.id})" class="inline-flex items-center px-2.5 py-1 bg-gray-50 text-gray-700 hover:bg-gray-100 border border-gray-200 rounded text-xs font-medium">
                                    Edit
                                </button>
                                <button type="button" onclick="deactivateItem(${item.id}, '${escapeHtml(item.item_name)}')" class="inline-flex items-center px-2 py-1 text-red-600 hover:text-red-800 text-xs">
                                    Deactivate
                                </button>
                            ` : `<span class="text-xs text-gray-400">View only</span>`}
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function renderPendingTable(adjustments) {
            const tbody = document.getElementById('pendingTableBody');
            if (!adjustments || adjustments.length === 0) {
                tbody.innerHTML = `<tr><td colspan="8" class="px-6 py-8 text-center text-gray-500">No pending adjustments awaiting approval.</td></tr>`;
                return;
            }

            tbody.innerHTML = adjustments.map(adj => {
                const qty = parseFloat(adj.quantity);
                const totalCost = (adj.total_cost_cents / 100).toFixed(2);
                const unitCost = parseFloat(adj.unit_cost_snapshot || 0).toFixed(2);
                const isMaker = (parseInt(adj.created_by) === CURRENT_USER_ID);

                let actionButtons = '';
                if (CAN_APPROVE) {
                    if (isMaker) {
                        actionButtons = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-600 font-medium">Your Submission (Pending)</span>`;
                    } else {
                        actionButtons = `
                            <div class="inline-flex items-center space-x-2">
                                <button type="button" onclick="handlePendingAction(${adj.id}, 'approve')" class="px-2.5 py-1 bg-green-600 hover:bg-green-700 text-white rounded text-xs font-semibold shadow-sm">
                                    Approve
                                </button>
                                <button type="button" onclick="handlePendingAction(${adj.id}, 'reject')" class="px-2.5 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-semibold shadow-sm">
                                    Reject
                                </button>
                            </div>
                        `;
                    }
                } else {
                    actionButtons = `<span class="text-xs text-gray-400">Pending Checker</span>`;
                }

                return `
                    <tr class="hover:bg-gray-50/80 transition-colors">
                        <td class="px-4 py-3.5 text-xs text-gray-600">${escapeHtml(adj.created_at)}</td>
                        <td class="px-4 py-3.5 font-medium text-gray-900">${escapeHtml(adj.item_name)} (${escapeHtml(adj.unit)})</td>
                        <td class="px-4 py-3.5 text-right font-mono font-bold ${qty > 0 ? 'text-green-600' : 'text-red-600'}">
                            ${qty > 0 ? '+' : ''}${qty.toFixed(2)}
                        </td>
                        <td class="px-4 py-3.5 text-right font-mono text-gray-700">₱${unitCost}</td>
                        <td class="px-4 py-3.5 text-right font-mono font-bold text-gray-900">₱${totalCost}</td>
                        <td class="px-4 py-3.5 text-xs text-gray-700 max-w-xs truncate" title="${escapeHtml(adj.reason)}">${escapeHtml(adj.reason)}</td>
                        <td class="px-4 py-3.5 text-xs text-gray-600 font-medium">${escapeHtml(adj.maker_username || 'User #' + adj.created_by)}</td>
                        <td class="px-4 py-3.5 text-center">${actionButtons}</td>
                    </tr>
                `;
            }).join('');
        }

        // Adjust Modal Handling
        function openAdjustModal(itemId) {
            hideAlert();
            const item = itemsData.find(i => i.id === itemId);
            if (!item) return;

            activeAdjustItem = item;
            document.getElementById('adjInventoryId').value = item.id;
            document.getElementById('adjItemDisplay').textContent = `${item.item_name} (${item.unit})`;
            document.getElementById('adjCurrentStockDisplay').textContent = parseFloat(item.current_stock || 0).toFixed(2);
            document.getElementById('adjUnitCostDisplay').textContent = `₱${parseFloat(item.unit_cost || 0).toFixed(2)}`;
            document.getElementById('adjQuantity').value = '';
            document.getElementById('adjReason').value = '';
            
            calcAdjustmentImpact();
            document.getElementById('adjustModal').classList.remove('hidden');
        }

        function closeAdjustModal() {
            document.getElementById('adjustModal').classList.add('hidden');
            activeAdjustItem = null;
        }

        function calcAdjustmentImpact() {
            if (!activeAdjustItem) return;
            const currentStock = parseFloat(activeAdjustItem.current_stock || 0);
            const unitCost = parseFloat(activeAdjustItem.unit_cost || 0);
            const qty = parseFloat(document.getElementById('adjQuantity').value || 0);
            const newStock = currentStock + qty;

            const totalImpactPesos = Math.abs(qty) * unitCost;

            document.getElementById('adjNewStock').textContent = newStock.toFixed(2);
            document.getElementById('adjTotalImpact').textContent = `₱${totalImpactPesos.toFixed(2)}`;

            const warnElem = document.getElementById('adjThresholdWarning');
            const impactBox = document.getElementById('adjImpactBox');

            if (totalImpactPesos > 500) {
                warnElem.innerHTML = `⚠️ <strong>Exceeds ₱500.00 threshold:</strong> Will be queued as <strong>PENDING</strong> for Checker approval.`;
                warnElem.className = 'text-xs font-semibold pt-1 text-purple-700';
                impactBox.className = 'p-3 rounded-lg border text-xs space-y-1 bg-purple-50 border-purple-200';
            } else {
                warnElem.innerHTML = `✓ Within ₱500.00 threshold: Will be auto-approved immediately.`;
                warnElem.className = 'text-xs font-medium pt-1 text-green-700';
                impactBox.className = 'p-3 rounded-lg border text-xs space-y-1 bg-green-50 border-green-200';
            }
        }

        async function handleAdjustSubmit(e) {
            e.preventDefault();
            hideAlert();

            const btn = document.getElementById('adjSubmitBtn');
            btn.disabled = true;
            btn.textContent = 'Submitting...';

            const payload = {
                action: 'submit',
                branch_id: CURRENT_BRANCH_ID,
                inventory_id: parseInt(document.getElementById('adjInventoryId').value),
                quantity: parseFloat(document.getElementById('adjQuantity').value),
                reason: document.getElementById('adjReason').value.trim()
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
                    showAlert(data.error || 'Failed to submit adjustment.', true);
                    return;
                }

                closeAdjustModal();
                if (data.status === 'PENDING') {
                    showAlert(`Adjustment #${data.adjustment_id} exceeds threshold and has been submitted for Checker approval.`);
                    switchTab('pending');
                } else {
                    showAlert(`Adjustment #${data.adjustment_id} auto-approved. New stock: ${data.new_stock}`);
                }

                await loadAllData();
            } catch (err) {
                showAlert('Network error: ' + err.message, true);
            } finally {
                btn.disabled = false;
                btn.textContent = 'Submit Adjustment';
            }
        }

        // Pending Maker-Checker Approve / Reject
        async function handlePendingAction(adjId, action) {
            if (!confirm(`Are you sure you want to ${action.toUpperCase()} adjustment #${adjId}?`)) {
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
                        action: action,
                        adjustment_id: adjId
                    })
                });
                const data = await res.json();

                if (!res.ok || !data.success) {
                    showAlert(data.error || `Failed to ${action} adjustment.`, true);
                    return;
                }

                showAlert(`Adjustment #${adjId} successfully ${data.status}.`);
                await loadAllData();
            } catch (err) {
                showAlert(`Error processing ${action}: ` + err.message, true);
            }
        }

        // Item Add / Edit Modal Handling
        function openItemModal() {
            hideAlert();
            document.getElementById('itemModalTitle').textContent = 'Add Inventory Item';
            document.getElementById('itemId').value = '0';
            document.getElementById('itemName').value = '';
            document.getElementById('itemUnit').value = '';
            document.getElementById('itemUnitCost').value = '';
            document.getElementById('itemLowThreshold').value = '10.00';
            document.getElementById('itemModal').classList.remove('hidden');
        }

        function editItem(itemId) {
            hideAlert();
            const item = itemsData.find(i => i.id === itemId);
            if (!item) return;

            document.getElementById('itemModalTitle').textContent = 'Edit Inventory Item';
            document.getElementById('itemId').value = item.id;
            document.getElementById('itemName').value = item.item_name;
            document.getElementById('itemUnit').value = item.unit;
            document.getElementById('itemUnitCost').value = parseFloat(item.unit_cost || 0).toFixed(2);
            document.getElementById('itemLowThreshold').value = parseFloat(item.low_stock_threshold || 10).toFixed(2);
            document.getElementById('itemModal').classList.remove('hidden');
        }

        function closeItemModal() {
            document.getElementById('itemModal').classList.add('hidden');
        }

        async function handleItemSubmit(e) {
            e.preventDefault();
            hideAlert();

            const btn = document.getElementById('itemSubmitBtn');
            btn.disabled = true;
            btn.textContent = 'Saving...';

            const payload = {
                action: 'item_save',
                id: parseInt(document.getElementById('itemId').value),
                branch_id: CURRENT_BRANCH_ID,
                item_name: document.getElementById('itemName').value.trim(),
                unit: document.getElementById('itemUnit').value.trim(),
                unit_cost: parseFloat(document.getElementById('itemUnitCost').value),
                low_stock_threshold: parseFloat(document.getElementById('itemLowThreshold').value)
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
                    showAlert(data.error || 'Failed to save item.', true);
                    return;
                }

                closeItemModal();
                showAlert('Item successfully saved.');
                await fetchStockList();
            } catch (err) {
                showAlert('Network error saving item: ' + err.message, true);
            } finally {
                btn.disabled = false;
                btn.textContent = 'Save Item';
            }
        }

        async function deactivateItem(itemId, itemName) {
            if (!confirm(`Are you sure you want to deactivate "${itemName}"? It will no longer appear in stock lists.`)) {
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
                        action: 'item_deactivate',
                        id: itemId
                    })
                });
                const data = await res.json();

                if (!res.ok || !data.success) {
                    showAlert(data.error || 'Failed to deactivate item.', true);
                    return;
                }

                showAlert(`Item "${itemName}" deactivated.`);
                await fetchStockList();
            } catch (err) {
                showAlert('Network error: ' + err.message, true);
            }
        }
    </script>
    </div>
    </div>
</body>
</html>
