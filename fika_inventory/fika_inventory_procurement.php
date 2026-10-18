<?php
require_once __DIR__ . '/../app/bootstrap.php';

Auth::requireLogin();
Rbac::require_permission('procurement.view');

$user = Auth::user();
$canCreate = Rbac::can('procurement.create');
$canApprove = Rbac::can('procurement.approve');
$canReceive = Rbac::can('procurement.receive');
$csrfToken = Csrf::getToken();

// Fetch active inventory items for PO item selection
global $pdo;
$invStmt = $pdo->prepare("
    SELECT id, item_name, unit, sku, cost_price, unit_cost
    FROM inventory_items 
    WHERE deleted_at IS NULL 
    ORDER BY item_name ASC
");
$invStmt->execute();
$inventoryItems = $invStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procurement & Purchase Orders - Fika</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <style>
        .tab-btn.active {
            border-bottom: 2px solid #2563eb;
            color: #2563eb;
            font-weight: 600;
        }
        .modal {
            transition: opacity 0.2s ease, visibility 0.2s ease;
        }
        .modal.hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 font-sans min-h-screen">
    <!-- Navbar -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-30 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5 flex flex-wrap justify-between items-center gap-4">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold text-lg shadow-sm">
                    P
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900 leading-tight">Procurement & Purchase Orders</h1>
                    <p class="text-xs text-gray-500">Suppliers, PO Approvals, Maker-Checker & Idempotent Receiving</p>
                </div>
            </div>

            <div class="flex items-center space-x-4">
                <a href="fika_inventory_stock.php" class="text-xs font-semibold text-blue-600 hover:text-blue-800">
                    ← Stock Ledger
                </a>
                <a href="fika_inventory_recipes.php" class="text-xs font-semibold text-blue-600 hover:text-blue-800">
                    Recipes & BOM
                </a>
                <div class="h-5 w-px bg-gray-200"></div>
                <div class="text-xs text-right">
                    <div class="font-semibold text-gray-900"><?= e($user['username'] ?? 'User #' . $user['id']) ?></div>
                    <div class="text-gray-500">
                        <?= e($user['role_name'] ?? 'Staff') ?> • Branch #<?= (int)($user['branch_id'] ?? 1) ?>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Toast / Alerts -->
        <div id="alertBox" class="hidden mb-6 p-4 rounded-lg text-sm border shadow-sm transition-all" role="alert"></div>

        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 mb-6 flex justify-between items-center">
            <nav class="flex space-x-8" aria-label="Tabs">
                <button type="button" onclick="switchTab('pos')" id="tabBtnPos" class="tab-btn active py-3 px-1 text-sm font-medium text-gray-500 hover:text-gray-700">
                    Purchase Orders
                </button>
                <button type="button" onclick="switchTab('suppliers')" id="tabBtnSuppliers" class="tab-btn py-3 px-1 text-sm font-medium text-gray-500 hover:text-gray-700">
                    Suppliers Directory
                </button>
                <?php if ($canApprove): ?>
                <button type="button" onclick="switchTab('approvals')" id="tabBtnApprovals" class="tab-btn py-3 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 flex items-center gap-1.5">
                    <span>Pending Approvals</span>
                    <span id="approvalBadge" class="hidden bg-amber-100 text-amber-800 text-xs px-2 py-0.5 rounded-full font-bold">0</span>
                </button>
                <?php endif; ?>
            </nav>

            <div class="flex items-center space-x-3">
                <?php if ($canCreate): ?>
                <button type="button" onclick="openCreatePoModal()" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium text-xs shadow-sm flex items-center gap-1.5">
                    <span>+ New Purchase Order</span>
                </button>
                <button type="button" onclick="openSupplierModal()" class="px-3 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-medium text-xs shadow-sm">
                    + Add Supplier
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAB 1: PURCHASE ORDERS -->
        <section id="sectionPos" class="space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-4 border-b border-gray-200 flex flex-wrap justify-between items-center gap-3 bg-gray-50">
                    <div class="flex items-center space-x-2">
                        <span class="text-xs font-semibold text-gray-500 uppercase">Filter Status:</span>
                        <select id="statusFilter" onchange="loadPurchaseOrders()" class="text-xs border-gray-300 rounded-md shadow-sm border px-2.5 py-1.5 bg-white">
                            <option value="">All Statuses</option>
                            <option value="DRAFT">Draft</option>
                            <option value="APPROVED">Approved</option>
                            <option value="PARTIALLY_RECEIVED">Partially Received</option>
                            <option value="RECEIVED">Received</option>
                            <option value="CANCELLED">Cancelled</option>
                        </select>
                    </div>
                    <div class="text-xs text-gray-500">
                        Total POs: <span id="poCount" class="font-bold text-gray-700">0</span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600 text-xs uppercase border-b border-gray-200">
                                <th class="p-3.5 font-semibold">PO Number</th>
                                <th class="p-3.5 font-semibold">Supplier</th>
                                <th class="p-3.5 font-semibold">Branch</th>
                                <th class="p-3.5 font-semibold text-right">Total Cost</th>
                                <th class="p-3.5 font-semibold text-center">Items (Recv / Ord)</th>
                                <th class="p-3.5 font-semibold">Status</th>
                                <th class="p-3.5 font-semibold">Created By</th>
                                <th class="p-3.5 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="poTableBody" class="divide-y divide-gray-200 text-xs">
                            <tr>
                                <td colspan="8" class="p-8 text-center text-gray-500">Loading purchase orders...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- TAB 2: SUPPLIERS DIRECTORY -->
        <section id="sectionSuppliers" class="hidden space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                    <h2 class="text-sm font-bold text-gray-700">Authorized Suppliers</h2>
                    <?php if ($canCreate): ?>
                    <button type="button" onclick="openSupplierModal()" class="px-3 py-1.5 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 font-medium text-xs">
                        + Add Supplier
                    </button>
                    <?php endif; ?>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600 text-xs uppercase border-b border-gray-200">
                                <th class="p-3.5 font-semibold">Supplier Name</th>
                                <th class="p-3.5 font-semibold">Contact Info</th>
                                <th class="p-3.5 font-semibold">Address</th>
                                <th class="p-3.5 font-semibold">Scope</th>
                                <th class="p-3.5 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="suppliersTableBody" class="divide-y divide-gray-200 text-xs">
                            <tr>
                                <td colspan="5" class="p-8 text-center text-gray-500">Loading suppliers...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- TAB 3: PENDING APPROVALS QUEUE (BA / ADMIN) -->
        <?php if ($canApprove): ?>
        <section id="sectionApprovals" class="hidden space-y-4">
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-xs text-amber-800 flex items-start gap-2.5">
                <span class="text-base">⚖️</span>
                <div>
                    <span class="font-bold">Maker-Checker Compliance:</span> As an Approver/Accountant, you can review and approve purchase orders submitted by Branch Managers. You cannot approve purchase orders that you created yourself.
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-4 border-b border-gray-200 bg-gray-50">
                    <h2 class="text-sm font-bold text-gray-700">DRAFT Purchase Orders Awaiting Approval</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600 text-xs uppercase border-b border-gray-200">
                                <th class="p-3.5 font-semibold">PO Number</th>
                                <th class="p-3.5 font-semibold">Supplier</th>
                                <th class="p-3.5 font-semibold">Created By</th>
                                <th class="p-3.5 font-semibold text-right">Total Cost</th>
                                <th class="p-3.5 font-semibold text-center">Items</th>
                                <th class="p-3.5 font-semibold text-right">Approval Action</th>
                            </tr>
                        </thead>
                        <tbody id="approvalTableBody" class="divide-y divide-gray-200 text-xs">
                            <tr>
                                <td colspan="6" class="p-8 text-center text-gray-500">Loading pending approvals...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <!-- MODAL 1: CREATE PURCHASE ORDER -->
    <div id="modalCreatePo" class="modal hidden fixed inset-0 z-50 bg-gray-900 bg-opacity-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl border border-gray-200 max-w-3xl w-full max-h-[90vh] flex flex-col overflow-hidden">
            <div class="p-5 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                <h3 class="text-base font-bold text-gray-900">Create New Purchase Order (DRAFT)</h3>
                <button type="button" onclick="closeModal('modalCreatePo')" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
            </div>
            
            <form id="formCreatePo" onsubmit="handleCreatePo(event)" class="flex-1 overflow-y-auto p-6 space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Select Supplier *</label>
                        <select id="poSupplierSelect" required class="w-full text-xs border border-gray-300 rounded-lg p-2.5 bg-white">
                            <option value="">-- Choose Supplier --</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Branch Scope</label>
                        <input type="text" readonly value="Branch #<?= (int)($user['branch_id'] ?? 1) ?>" class="w-full text-xs border border-gray-200 bg-gray-100 rounded-lg p-2.5 text-gray-500 font-medium">
                    </div>
                </div>

                <!-- Line Items Section -->
                <div class="border-t border-gray-200 pt-4">
                    <div class="flex justify-between items-center mb-3">
                        <h4 class="text-xs font-bold uppercase text-gray-600 tracking-wider">Line Items</h4>
                        <button type="button" onclick="addPoItemRow()" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                            + Add Line Item
                        </button>
                    </div>

                    <div class="space-y-3" id="poItemsContainer">
                        <!-- Dynamic item rows injected here -->
                    </div>
                </div>

                <!-- Summary / Total -->
                <div class="border-t border-gray-200 pt-4 flex justify-between items-center bg-gray-50 p-4 rounded-lg">
                    <div class="text-xs text-gray-500">
                        Server-calculated total (in Centavos): <span class="font-mono text-gray-700" id="rawCentsTotal">0</span>
                    </div>
                    <div class="text-right">
                        <span class="text-xs text-gray-500">Estimated Total:</span>
                        <div class="text-lg font-bold text-gray-900" id="formattedGrandTotal">₱0.00</div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-2">
                    <button type="button" onclick="closeModal('modalCreatePo')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-xs font-medium hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" id="btnSubmitPo" class="px-5 py-2 bg-indigo-600 text-white rounded-lg text-xs font-semibold hover:bg-indigo-700 shadow-sm">
                        Create Draft PO
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: RECEIVE PURCHASE ORDER ITEMS -->
    <div id="modalReceivePo" class="modal hidden fixed inset-0 z-50 bg-gray-900 bg-opacity-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl border border-gray-200 max-w-3xl w-full max-h-[90vh] flex flex-col overflow-hidden">
            <div class="p-5 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                <div>
                    <h3 class="text-base font-bold text-gray-900">Receive Purchase Order Items</h3>
                    <p class="text-xs text-gray-500" id="receivePoSubtitle">PO Number: ...</p>
                </div>
                <button type="button" onclick="closeModal('modalReceivePo')" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
            </div>

            <form id="formReceivePo" onsubmit="handleReceivePo(event)" class="flex-1 overflow-y-auto p-6 space-y-4">
                <input type="hidden" id="receivePoId" value="">
                <input type="hidden" id="receiveIdempotencyKey" value="">

                <div class="text-xs text-gray-600 bg-blue-50 border border-blue-200 p-3 rounded-lg">
                    Enter the quantity physically received. Partial receiving is supported. If receiving more than ordered, an <strong>Over-Receipt Reason</strong> is strictly required.
                </div>

                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-gray-100 text-gray-600 uppercase">
                            <tr>
                                <th class="p-3">Item</th>
                                <th class="p-3 text-right">Ordered</th>
                                <th class="p-3 text-right">Prior Recv</th>
                                <th class="p-3 text-right">Remaining</th>
                                <th class="p-3 w-28 text-right">Recv Now</th>
                                <th class="p-3">Over-Receipt Reason</th>
                            </tr>
                        </thead>
                        <tbody id="receiveItemsTableBody" class="divide-y divide-gray-200">
                            <!-- Injected rows -->
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
                    <button type="button" onclick="closeModal('modalReceivePo')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-xs font-medium hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" id="btnSubmitReceive" class="px-5 py-2 bg-emerald-600 text-white rounded-lg text-xs font-semibold hover:bg-emerald-700 shadow-sm">
                        Confirm Receipt & Update Stock
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 3: VIEW PO DETAILS -->
    <div id="modalPoDetails" class="modal hidden fixed inset-0 z-50 bg-gray-900 bg-opacity-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl border border-gray-200 max-w-3xl w-full max-h-[90vh] flex flex-col overflow-hidden">
            <div class="p-5 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                <div>
                    <h3 class="text-base font-bold text-gray-900" id="detailsPoNumber">PO-XXXX</h3>
                    <p class="text-xs text-gray-500" id="detailsPoStatus">Status: ...</p>
                </div>
                <button type="button" onclick="closeModal('modalPoDetails')" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
            </div>

            <div class="flex-1 overflow-y-auto p-6 space-y-5 text-xs" id="detailsPoContent">
                <!-- Injected dynamically -->
            </div>

            <div class="p-4 border-t border-gray-200 bg-gray-50 flex justify-end">
                <button type="button" onclick="closeModal('modalPoDetails')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-xs font-medium hover:bg-gray-100">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL 4: ADD/EDIT SUPPLIER -->
    <div id="modalSupplier" class="modal hidden fixed inset-0 z-50 bg-gray-900 bg-opacity-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl border border-gray-200 max-w-md w-full overflow-hidden">
            <div class="p-5 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                <h3 class="text-base font-bold text-gray-900" id="supplierModalTitle">Add Supplier</h3>
                <button type="button" onclick="closeModal('modalSupplier')" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
            </div>

            <form id="formSupplier" onsubmit="handleSaveSupplier(event)" class="p-6 space-y-4 text-xs">
                <input type="hidden" id="supplierId" value="">
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Supplier Name *</label>
                    <input type="text" id="supplierName" required class="w-full border border-gray-300 rounded-lg p-2.5">
                </div>
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Contact Info (Phone, Email, Person)</label>
                    <input type="text" id="supplierContact" class="w-full border border-gray-300 rounded-lg p-2.5">
                </div>
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Address</label>
                    <textarea id="supplierAddress" rows="2" class="w-full border border-gray-300 rounded-lg p-2.5"></textarea>
                </div>

                <div class="flex justify-end space-x-3 pt-3">
                    <button type="button" onclick="closeModal('modalSupplier')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-xs font-medium hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 text-white rounded-lg text-xs font-semibold hover:bg-indigo-700">
                        Save Supplier
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Embedded Inventory Items JSON for dynamic row builder -->
    <script>
        const inventoryCatalog = <?= json_encode($inventoryItems) ?>;
        const currentUserId = <?= (int)$user['id'] ?>;
        const userCanApprove = <?= $canApprove ? 'true' : 'false' ?>;
        const userCanReceive = <?= $canReceive ? 'true' : 'false' ?>;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        let suppliersList = [];
        let purchaseOrdersList = [];

        // Tab Switching
        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('sectionPos').classList.add('hidden');
            document.getElementById('sectionSuppliers').classList.add('hidden');
            const secApp = document.getElementById('sectionApprovals');
            if (secApp) secApp.classList.add('hidden');

            if (tab === 'pos') {
                document.getElementById('tabBtnPos').classList.add('active');
                document.getElementById('sectionPos').classList.remove('hidden');
                loadPurchaseOrders();
            } else if (tab === 'suppliers') {
                document.getElementById('tabBtnSuppliers').classList.add('active');
                document.getElementById('sectionSuppliers').classList.remove('hidden');
                loadSuppliers();
            } else if (tab === 'approvals') {
                document.getElementById('tabBtnApprovals').classList.add('active');
                if (secApp) secApp.classList.remove('hidden');
                loadApprovalsQueue();
            }
        }

        function showAlert(msg, isError = false) {
            const box = document.getElementById('alertBox');
            box.className = isError 
                ? 'mb-6 p-4 rounded-lg text-sm border shadow-sm transition-all bg-red-50 border-red-200 text-red-800'
                : 'mb-6 p-4 rounded-lg text-sm border shadow-sm transition-all bg-emerald-50 border-emerald-200 text-emerald-800';
            box.textContent = msg;
            box.classList.remove('hidden');
            box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            setTimeout(() => box.classList.add('hidden'), 5000);
        }

        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
        }
        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
        }

        // =================================================================
        // LOAD DATA
        // =================================================================
        async function loadSuppliers() {
            try {
                const res = await fetch('api/fika_inventory_procurement.php?action=list_suppliers');
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Failed to load suppliers');
                suppliersList = json.data || [];
                renderSuppliersTable();
                populateSupplierDropdown();
            } catch (err) {
                showAlert(err.message, true);
            }
        }

        function renderSuppliersTable() {
            const tbody = document.getElementById('suppliersTableBody');
            if (suppliersList.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="p-6 text-center text-gray-500">No suppliers found.</td></tr>';
                return;
            }
            tbody.innerHTML = suppliersList.map(s => `
                <tr class="hover:bg-gray-50">
                    <td class="p-3.5 font-bold text-gray-900">${escapeHtml(s.name)}</td>
                    <td class="p-3.5 text-gray-600">${escapeHtml(s.contact || '—')}</td>
                    <td class="p-3.5 text-gray-600">${escapeHtml(s.address || '—')}</td>
                    <td class="p-3.5 text-gray-500">${s.branch_name ? escapeHtml(s.branch_name) : 'All Branches'}</td>
                    <td class="p-3.5 text-right space-x-2">
                        <button type="button" onclick="editSupplier(${s.id})" class="text-blue-600 hover:text-blue-800 font-medium">Edit</button>
                        <button type="button" onclick="deactivateSupplier(${s.id})" class="text-red-600 hover:text-red-800 font-medium">Deactivate</button>
                    </td>
                </tr>
            `).join('');
        }

        function populateSupplierDropdown() {
            const sel = document.getElementById('poSupplierSelect');
            sel.innerHTML = '<option value="">-- Choose Supplier --</option>' + 
                suppliersList.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
        }

        async function loadPurchaseOrders() {
            try {
                const status = document.getElementById('statusFilter').value;
                const url = 'api/fika_inventory_procurement.php?action=list_pos' + (status ? `&status=${encodeURIComponent(status)}` : '');
                const res = await fetch(url);
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Failed to load purchase orders');
                purchaseOrdersList = json.data || [];
                document.getElementById('poCount').textContent = purchaseOrdersList.length;
                renderPoTable();
                updateApprovalBadge();
            } catch (err) {
                showAlert(err.message, true);
            }
        }

        function renderPoTable() {
            const tbody = document.getElementById('poTableBody');
            if (purchaseOrdersList.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="p-8 text-center text-gray-500">No purchase orders found.</td></tr>';
                return;
            }

            tbody.innerHTML = purchaseOrdersList.map(po => {
                const totalPesos = (po.total_cost_cents / 100).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });
                const ordQty = parseFloat(po.total_ordered_qty || 0).toFixed(1);
                const recvQty = parseFloat(po.total_received_qty || 0).toFixed(1);

                return `
                    <tr class="hover:bg-gray-50">
                        <td class="p-3.5 font-bold font-mono text-gray-900">${escapeHtml(po.po_number)}</td>
                        <td class="p-3.5 font-medium text-gray-800">${escapeHtml(po.supplier_name)}</td>
                        <td class="p-3.5 text-gray-500">${escapeHtml(po.branch_name)}</td>
                        <td class="p-3.5 text-right font-semibold text-gray-900">${totalPesos}</td>
                        <td class="p-3.5 text-center font-mono text-gray-700">${recvQty} / ${ordQty}</td>
                        <td class="p-3.5">${renderStatusBadge(po.status)}</td>
                        <td class="p-3.5 text-gray-500">${escapeHtml(po.creator_username || 'User #' + po.created_by)}</td>
                        <td class="p-3.5 text-right space-x-2">
                            <button type="button" onclick="viewPoDetails(${po.id})" class="text-indigo-600 hover:text-indigo-800 font-medium">View</button>
                            ${po.status === 'DRAFT' && userCanApprove && parseInt(po.created_by) !== currentUserId ? `
                                <button type="button" onclick="approvePo(${po.id})" class="text-emerald-600 hover:text-emerald-800 font-semibold">Approve</button>
                            ` : ''}
                            ${(po.status === 'APPROVED' || po.status === 'PARTIALLY_RECEIVED') && userCanReceive ? `
                                <button type="button" onclick="openReceiveModal(${po.id})" class="text-blue-600 hover:text-blue-800 font-semibold">Receive</button>
                            ` : ''}
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function renderStatusBadge(st) {
            switch (st) {
                case 'DRAFT':
                    return '<span class="bg-gray-100 text-gray-700 border border-gray-300 px-2 py-0.5 rounded text-xs font-semibold">DRAFT</span>';
                case 'APPROVED':
                    return '<span class="bg-amber-100 text-amber-800 border border-amber-300 px-2 py-0.5 rounded text-xs font-semibold">APPROVED</span>';
                case 'PARTIALLY_RECEIVED':
                    return '<span class="bg-blue-100 text-blue-800 border border-blue-300 px-2 py-0.5 rounded text-xs font-semibold">PARTIAL</span>';
                case 'RECEIVED':
                    return '<span class="bg-emerald-100 text-emerald-800 border border-emerald-300 px-2 py-0.5 rounded text-xs font-semibold">RECEIVED</span>';
                case 'CANCELLED':
                    return '<span class="bg-red-100 text-red-800 border border-red-300 px-2 py-0.5 rounded text-xs font-semibold">CANCELLED</span>';
                default:
                    return `<span class="bg-gray-100 text-gray-700 px-2 py-0.5 rounded text-xs">${escapeHtml(st)}</span>`;
            }
        }

        function updateApprovalBadge() {
            const badge = document.getElementById('approvalBadge');
            if (!badge) return;
            const pending = purchaseOrdersList.filter(p => p.status === 'DRAFT' && parseInt(p.created_by) !== currentUserId);
            if (pending.length > 0) {
                badge.textContent = pending.length;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }

        function loadApprovalsQueue() {
            const tbody = document.getElementById('approvalTableBody');
            if (!tbody) return;
            const pending = purchaseOrdersList.filter(p => p.status === 'DRAFT');

            if (pending.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="p-8 text-center text-gray-500">No purchase orders currently pending approval.</td></tr>';
                return;
            }

            tbody.innerHTML = pending.map(po => {
                const totalPesos = (po.total_cost_cents / 100).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });
                const isCreator = parseInt(po.created_by) === currentUserId;

                return `
                    <tr class="hover:bg-gray-50">
                        <td class="p-3.5 font-bold font-mono text-gray-900">${escapeHtml(po.po_number)}</td>
                        <td class="p-3.5 font-medium text-gray-800">${escapeHtml(po.supplier_name)}</td>
                        <td class="p-3.5 text-gray-600">${escapeHtml(po.creator_username || 'User #' + po.created_by)} ${isCreator ? '<span class="text-xs text-amber-600 font-bold">(You)</span>' : ''}</td>
                        <td class="p-3.5 text-right font-semibold text-gray-900">${totalPesos}</td>
                        <td class="p-3.5 text-center text-gray-600">${po.item_count || 1} items</td>
                        <td class="p-3.5 text-right">
                            ${isCreator ? `
                                <span class="text-xs text-gray-400 italic">Self-approval blocked</span>
                            ` : `
                                <button type="button" onclick="approvePo(${po.id})" class="px-3 py-1 bg-emerald-600 text-white rounded text-xs font-semibold hover:bg-emerald-700">Approve PO</button>
                            `}
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // =================================================================
        // DYNAMIC PO CREATION ROW BUILDER
        // =================================================================
        function openCreatePoModal() {
            document.getElementById('formCreatePo').reset();
            document.getElementById('poItemsContainer').innerHTML = '';
            addPoItemRow(); // start with one blank item row
            calculateGrandTotal();
            openModal('modalCreatePo');
        }

        function addPoItemRow() {
            const container = document.getElementById('poItemsContainer');
            const rowId = Date.now() + Math.random().toString(36).substring(2, 5);

            const div = document.createElement('div');
            div.id = 'itemRow_' + rowId;
            div.className = 'grid grid-cols-12 gap-2 items-center bg-gray-50 p-2.5 rounded-lg border border-gray-200 text-xs';

            const opts = inventoryCatalog.map(it => `
                <option value="${it.id}" data-unit="${escapeHtml(it.unit)}" data-cost="${it.unit_cost || it.cost_price || 0}">
                    ${escapeHtml(it.item_name)} (${escapeHtml(it.sku || it.unit)})
                </option>
            `).join('');

            div.innerHTML = `
                <div class="col-span-5">
                    <label class="block text-[10px] font-semibold text-gray-500 mb-0.5">Item *</label>
                    <select class="item-select w-full border border-gray-300 rounded p-1.5 bg-white" onchange="onItemSelectChanged('${rowId}')" required>
                        <option value="">-- Choose Item --</option>
                        ${opts}
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-[10px] font-semibold text-gray-500 mb-0.5">Qty *</label>
                    <input type="number" step="0.01" min="0.01" class="item-qty w-full border border-gray-300 rounded p-1.5 text-right" oninput="calculateGrandTotal()" required>
                </div>
                <div class="col-span-3">
                    <label class="block text-[10px] font-semibold text-gray-500 mb-0.5">Unit Cost (₱) *</label>
                    <input type="number" step="0.01" min="0" class="item-cost w-full border border-gray-300 rounded p-1.5 text-right" oninput="calculateGrandTotal()" required>
                </div>
                <div class="col-span-2 text-right pt-3">
                    <button type="button" onclick="removePoItemRow('${rowId}')" class="text-red-500 hover:text-red-700 font-bold text-base">&times;</button>
                </div>
            `;
            container.appendChild(div);
        }

        function removePoItemRow(rowId) {
            const container = document.getElementById('poItemsContainer');
            if (container.children.length <= 1) {
                showAlert('Purchase order must have at least one line item', true);
                return;
            }
            const el = document.getElementById('itemRow_' + rowId);
            if (el) el.remove();
            calculateGrandTotal();
        }

        function onItemSelectChanged(rowId) {
            const row = document.getElementById('itemRow_' + rowId);
            const select = row.querySelector('.item-select');
            const selectedOpt = select.options[select.selectedIndex];
            const defaultCost = selectedOpt.getAttribute('data-cost') || 0;
            const costInput = row.querySelector('.item-cost');
            if (!costInput.value) {
                costInput.value = parseFloat(defaultCost).toFixed(2);
            }
            calculateGrandTotal();
        }

        function calculateGrandTotal() {
            let grandCents = 0;
            document.querySelectorAll('#poItemsContainer > div').forEach(row => {
                const qty = parseFloat(row.querySelector('.item-qty').value || 0);
                const cost = parseFloat(row.querySelector('.item-cost').value || 0);
                if (qty > 0 && cost >= 0) {
                    const costCents = Math.round(cost * 100);
                    grandCents += Math.round(qty * costCents);
                }
            });

            document.getElementById('rawCentsTotal').textContent = grandCents;
            document.getElementById('formattedGrandTotal').textContent = (grandCents / 100).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });
        }

        async function handleCreatePo(e) {
            e.preventDefault();
            const supplierId = parseInt(document.getElementById('poSupplierSelect').value);
            if (!supplierId) {
                showAlert('Please select a supplier', true);
                return;
            }

            const items = [];
            let valid = true;
            document.querySelectorAll('#poItemsContainer > div').forEach(row => {
                const invId = parseInt(row.querySelector('.item-select').value);
                const qty = parseFloat(row.querySelector('.item-qty').value);
                const cost = parseFloat(row.querySelector('.item-cost').value);

                if (!invId || isNaN(qty) || qty <= 0 || isNaN(cost) || cost < 0) {
                    valid = false;
                    return;
                }
                items.push({
                    inventory_id: invId,
                    quantity_ordered: qty,
                    unit_cost_cents: Math.round(cost * 100)
                });
            });

            if (!valid || items.length === 0) {
                showAlert('Please complete all line item fields with valid positive quantities', true);
                return;
            }

            try {
                const res = await fetch('api/fika_inventory_procurement.php?action=create_po', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        supplier_id: supplierId,
                        items: items
                    })
                });
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Failed to create purchase order');
                showAlert(`Purchase Order ${json.po_number} created successfully in DRAFT status!`);
                closeModal('modalCreatePo');
                loadPurchaseOrders();
            } catch (err) {
                showAlert(err.message, true);
            }
        }

        // =================================================================
        // APPROVE PURCHASE ORDER (MAKER-CHECKER)
        // =================================================================
        async function approvePo(poId) {
            if (!confirm('Are you sure you want to approve this purchase order?')) return;
            try {
                const res = await fetch('api/fika_inventory_procurement.php?action=approve_po', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({ po_id: poId })
                });
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Approval failed');
                showAlert('Purchase order approved successfully!');
                loadPurchaseOrders();
            } catch (err) {
                showAlert(err.message, true);
            }
        }

        // =================================================================
        // RECEIVE ITEMS MODAL & ACTION
        // =================================================================
        async function openReceiveModal(poId) {
            try {
                const res = await fetch(`api/fika_inventory_procurement.php?action=get_po&id=${poId}`);
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Failed to load PO details');
                const po = json.data;

                document.getElementById('receivePoId').value = po.id;
                document.getElementById('receivePoSubtitle').textContent = `PO: ${po.po_number} • Supplier: ${po.supplier_name}`;
                // Fresh idempotency key for this receive modal session
                document.getElementById('receiveIdempotencyKey').value = 'rec_' + Date.now() + '_' + Math.random().toString(36).substring(2, 10);

                const tbody = document.getElementById('receiveItemsTableBody');
                tbody.innerHTML = (po.items || []).map(it => {
                    const ord = parseFloat(it.quantity_ordered);
                    const recv = parseFloat(it.quantity_received);
                    const rem = Math.max(0, ord - recv);

                    return `
                        <tr class="hover:bg-gray-50" data-item-id="${it.id}" data-ordered="${ord}" data-prior="${recv}">
                            <td class="p-3 font-semibold text-gray-800">${escapeHtml(it.item_name)} (${escapeHtml(it.unit)})</td>
                            <td class="p-3 text-right font-mono text-gray-600">${ord.toFixed(2)}</td>
                            <td class="p-3 text-right font-mono text-gray-600">${recv.toFixed(2)}</td>
                            <td class="p-3 text-right font-mono font-bold text-gray-800">${rem.toFixed(2)}</td>
                            <td class="p-3 text-right">
                                <input type="number" step="0.01" min="0" value="${rem.toFixed(2)}" class="recv-now-input w-24 border border-gray-300 rounded p-1 text-right font-mono" oninput="checkOverReceipt(this)">
                            </td>
                            <td class="p-3">
                                <input type="text" placeholder="Required if receiving > remaining" class="recv-reason-input w-full border border-gray-300 rounded p-1 text-xs">
                            </td>
                        </tr>
                    `;
                }).join('');

                openModal('modalReceivePo');
            } catch (err) {
                showAlert(err.message, true);
            }
        }

        function checkOverReceipt(input) {
            const tr = input.closest('tr');
            const ord = parseFloat(tr.getAttribute('data-ordered'));
            const prior = parseFloat(tr.getAttribute('data-prior'));
            const now = parseFloat(input.value || 0);
            const reasonInput = tr.querySelector('.recv-reason-input');

            if ((prior + now) > ord) {
                input.classList.add('border-amber-500', 'bg-amber-50');
                reasonInput.classList.add('border-amber-500', 'bg-amber-50');
                reasonInput.setAttribute('required', 'required');
            } else {
                input.classList.remove('border-amber-500', 'bg-amber-50');
                reasonInput.classList.remove('border-amber-500', 'bg-amber-50');
                reasonInput.removeAttribute('required');
            }
        }

        async function handleReceivePo(e) {
            e.preventDefault();
            const poId = parseInt(document.getElementById('receivePoId').value);
            const idempotencyKey = document.getElementById('receiveIdempotencyKey').value;

            const items = [];
            let error = null;

            document.querySelectorAll('#receiveItemsTableBody > tr').forEach(tr => {
                const itemId = parseInt(tr.getAttribute('data-item-id'));
                const ord = parseFloat(tr.getAttribute('data-ordered'));
                const prior = parseFloat(tr.getAttribute('data-prior'));
                const now = parseFloat(tr.querySelector('.recv-now-input').value || 0);
                const reason = tr.querySelector('.recv-reason-input').value.trim();

                if (now > 0) {
                    if ((prior + now) > ord && !reason) {
                        error = 'Over-receiving requires an explanation reason';
                    }
                    items.push({
                        item_id: itemId,
                        receive_qty: now,
                        reason: reason
                    });
                }
            });

            if (error) {
                showAlert(error, true);
                return;
            }

            if (items.length === 0) {
                showAlert('No items specified to receive', true);
                return;
            }

            try {
                const res = await fetch('api/fika_inventory_procurement.php?action=receive_po', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        po_id: poId,
                        idempotency_key: idempotencyKey,
                        items: items
                    })
                });
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Failed to receive items');
                showAlert('Items successfully received and stock ledger updated!');
                closeModal('modalReceivePo');
                loadPurchaseOrders();
            } catch (err) {
                showAlert(err.message, true);
            }
        }

        // =================================================================
        // VIEW PO DETAILS MODAL
        // =================================================================
        async function viewPoDetails(poId) {
            try {
                const res = await fetch(`api/fika_inventory_procurement.php?action=get_po&id=${poId}`);
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Failed to load PO details');
                const po = json.data;

                document.getElementById('detailsPoNumber').textContent = po.po_number;
                document.getElementById('detailsPoStatus').innerHTML = `Status: ${renderStatusBadge(po.status)} • Branch: ${escapeHtml(po.branch_name)}`;

                const totalPesos = (po.total_cost_cents / 100).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });

                document.getElementById('detailsPoContent').innerHTML = `
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <div>
                            <span class="text-gray-500 font-semibold block">Supplier</span>
                            <span class="text-gray-900 font-bold">${escapeHtml(po.supplier_name)}</span>
                        </div>
                        <div>
                            <span class="text-gray-500 font-semibold block">Total Cost</span>
                            <span class="text-gray-900 font-bold">${totalPesos}</span>
                        </div>
                        <div>
                            <span class="text-gray-500 font-semibold block">Created By</span>
                            <span class="text-gray-900">${escapeHtml(po.creator_username || 'User #' + po.created_by)}</span>
                        </div>
                        <div>
                            <span class="text-gray-500 font-semibold block">Approved By</span>
                            <span class="text-gray-900">${escapeHtml(po.approver_username || '—')}</span>
                        </div>
                    </div>

                    <div>
                        <h4 class="font-bold text-gray-700 uppercase mb-2">Order Line Items</h4>
                        <table class="w-full text-left border-collapse border border-gray-200 rounded">
                            <thead class="bg-gray-100 text-gray-600">
                                <tr>
                                    <th class="p-2 border-b">Item</th>
                                    <th class="p-2 border-b text-right">Ordered</th>
                                    <th class="p-2 border-b text-right">Received</th>
                                    <th class="p-2 border-b text-right">Unit Cost</th>
                                    <th class="p-2 border-b">Over-Receipt Reason</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                ${(po.items || []).map(it => `
                                    <tr>
                                        <td class="p-2 font-medium">${escapeHtml(it.item_name)} (${escapeHtml(it.unit)})</td>
                                        <td class="p-2 text-right font-mono">${parseFloat(it.quantity_ordered).toFixed(2)}</td>
                                        <td class="p-2 text-right font-mono font-bold">${parseFloat(it.quantity_received).toFixed(2)}</td>
                                        <td class="p-2 text-right">${(it.unit_cost_cents / 100).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' })}</td>
                                        <td class="p-2 text-gray-500 italic">${escapeHtml(it.over_receipt_reason || '—')}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;

                openModal('modalPoDetails');
            } catch (err) {
                showAlert(err.message, true);
            }
        }

        // =================================================================
        // SUPPLIER MANAGEMENT
        // =================================================================
        function openSupplierModal() {
            document.getElementById('formSupplier').reset();
            document.getElementById('supplierId').value = '';
            document.getElementById('supplierModalTitle').textContent = 'Add Supplier';
            openModal('modalSupplier');
        }

        function editSupplier(id) {
            const s = suppliersList.find(x => x.id === id);
            if (!s) return;
            document.getElementById('supplierId').value = s.id;
            document.getElementById('supplierName').value = s.name;
            document.getElementById('supplierContact').value = s.contact || '';
            document.getElementById('supplierAddress').value = s.address || '';
            document.getElementById('supplierModalTitle').textContent = 'Edit Supplier';
            openModal('modalSupplier');
        }

        async function handleSaveSupplier(e) {
            e.preventDefault();
            const id = document.getElementById('supplierId').value;
            const name = document.getElementById('supplierName').value.trim();
            const contact = document.getElementById('supplierContact').value.trim();
            const address = document.getElementById('supplierAddress').value.trim();

            try {
                const res = await fetch('api/fika_inventory_procurement.php?action=save_supplier', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        id: id ? parseInt(id) : null,
                        name: name,
                        contact: contact,
                        address: address
                    })
                });
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Failed to save supplier');
                showAlert('Supplier saved successfully!');
                closeModal('modalSupplier');
                loadSuppliers();
            } catch (err) {
                showAlert(err.message, true);
            }
        }

        async function deactivateSupplier(id) {
            if (!confirm('Are you sure you want to deactivate this supplier?')) return;
            try {
                const res = await fetch('api/fika_inventory_procurement.php?action=deactivate_supplier', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({ id: id })
                });
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Failed to deactivate supplier');
                showAlert('Supplier deactivated.');
                loadSuppliers();
            } catch (err) {
                showAlert(err.message, true);
            }
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', () => {
            loadSuppliers();
            loadPurchaseOrders();
        });
    </script>
</body>
</html>
