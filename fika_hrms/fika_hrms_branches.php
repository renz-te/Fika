<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/core/Output.php';

Auth::requireLogin();
Rbac::require_permission('branches.view');

global $pdo;

// Fetch all branches
$stmt = $pdo->query("SELECT id, name, address, contact_number, status, created_at, updated_at FROM branches WHERE deleted_at IS NULL ORDER BY id ASC");
$branches = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

$totalCount = count($branches);
$activeCount = count(array_filter($branches, fn($b) => ($b['status'] ?? 'Active') === 'Active'));
$inactiveCount = $totalCount - $activeCount;

$csrfToken = Csrf::getToken();
$canManage = Rbac::can('branches.manage');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branch Management &middot; Fika Café</title>
    <link rel="icon" type="image/svg+xml" href="../public/assets/logo.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans antialiased min-h-screen flex">

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto">
        <!-- Top Nav Header -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-600 text-white flex items-center justify-center font-black text-lg shadow-xs">
                        <i class="fa-solid fa-store text-base"></i>
                    </div>
                    <div>
                        <h1 class="font-bold text-lg text-slate-900 leading-tight">Branch Management</h1>
                        <p class="text-xs text-slate-500">Configure café locations, addresses, contact details, and active status</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <?php if ($canManage): ?>
                    <button type="button" 
                            id="btnAddBranch" 
                            onclick="openAddBranchModal()" 
                            class="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-sm font-bold shadow-sm transition hover:shadow cursor-pointer">
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>Add New Branch</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

            <!-- Alert notification box -->
            <div id="alertBox" class="hidden p-4 rounded-xl text-sm font-bold border transition"></div>

            <!-- Stats Overview Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-amber-50 border border-amber-200/60 text-amber-600 flex items-center justify-center text-xl">
                        <i class="fa-solid fa-shop"></i>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Locations</div>
                        <div class="text-2xl font-black text-slate-800" id="statTotal"><?= $totalCount ?></div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 border border-emerald-200/60 text-emerald-600 flex items-center justify-center text-xl">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Active Branches</div>
                        <div class="text-2xl font-black text-emerald-600" id="statActive"><?= $activeCount ?></div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-slate-100 border border-slate-200 text-slate-500 flex items-center justify-center text-xl">
                        <i class="fa-solid fa-circle-pause"></i>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Deactivated</div>
                        <div class="text-2xl font-black text-slate-600" id="statInactive"><?= $inactiveCount ?></div>
                    </div>
                </div>
            </div>

            <!-- Branches List Card -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h2 class="font-bold text-slate-900 text-base">All Registered Branches</h2>
                        <p class="text-xs text-slate-500">Live directory of coffee shop locations and operational states</p>
                    </div>
                    <div class="w-full sm:w-64">
                        <div class="relative">
                            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                            <input type="text" 
                                   id="branchSearch" 
                                   onkeyup="filterBranches()" 
                                   placeholder="Search branches..." 
                                   class="w-full pl-9 pr-4 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse" id="branchesTable">
                        <thead>
                            <tr class="bg-slate-50/75 border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                <th class="py-3 px-6">ID</th>
                                <th class="py-3 px-6">Branch Name</th>
                                <th class="py-3 px-6">Address</th>
                                <th class="py-3 px-6">Contact Number</th>
                                <th class="py-3 px-6">Status</th>
                                <th class="py-3 px-6 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm font-normal">
                            <?php if (empty($branches)): ?>
                            <tr id="emptyRow">
                                <td colspan="6" class="py-12 text-center text-slate-400">
                                    <i class="fa-solid fa-store-slash text-3xl mb-3 block text-slate-300"></i>
                                    No branches found. Click "+ Add New Branch" to create one.
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($branches as $b): 
                                $isActive = ($b['status'] ?? 'Active') === 'Active';
                            ?>
                            <tr class="hover:bg-slate-50/70 transition branch-row" id="branch-row-<?= (int)$b['id'] ?>"
                                data-id="<?= (int)$b['id'] ?>"
                                data-name="<?= e($b['name']) ?>"
                                data-address="<?= e($b['address'] ?? '') ?>"
                                data-contact="<?= e($b['contact_number'] ?? '') ?>"
                                data-status="<?= e($b['status'] ?? 'Active') ?>">
                                <td class="py-4 px-6 text-xs font-mono font-bold text-slate-400">
                                    #<?= (int)$b['id'] ?>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="font-bold text-slate-900 branch-name"><?= e($b['name']) ?></div>
                                    <div class="text-[11px] text-slate-400">Created: <?= date('M d, Y', strtotime($b['created_at'])) ?></div>
                                </td>
                                <td class="py-4 px-6 text-slate-600 text-xs branch-address">
                                    <?php if (!empty($b['address'])): ?>
                                        <div class="flex items-start gap-1.5 max-w-xs">
                                            <i class="fa-solid fa-location-dot text-slate-400 mt-0.5 text-xs flex-shrink-0"></i>
                                            <span class="truncate"><?= e($b['address']) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic">No address provided</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-6 text-slate-600 text-xs branch-contact">
                                    <?php if (!empty($b['contact_number'])): ?>
                                        <div class="flex items-center gap-1.5">
                                            <i class="fa-solid fa-phone text-slate-400 text-xs"></i>
                                            <span><?= e($b['contact_number']) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-6 branch-status-cell">
                                    <?php if ($isActive): ?>
                                    <span class="status-badge inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200/80">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Active
                                    </span>
                                    <?php else: ?>
                                    <span class="status-badge inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                        Inactive
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-6 text-right">
                                    <?php if ($canManage): ?>
                                    <div class="inline-flex items-center gap-2">
                                        <button type="button" 
                                                id="btnEditBranch-<?= (int)$b['id'] ?>"
                                                onclick="openEditBranchModal(<?= (int)$b['id'] ?>)" 
                                                class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-slate-700 hover:text-amber-700 hover:border-amber-300 hover:bg-amber-50 text-xs font-semibold transition cursor-pointer"
                                                title="Edit Branch">
                                            <i class="fa-solid fa-pen-to-square mr-1"></i>Edit
                                        </button>
                                        <button type="button" 
                                                id="btnToggleStatus-<?= (int)$b['id'] ?>"
                                                onclick="toggleBranchStatus(<?= (int)$b['id'] ?>)" 
                                                class="btn-toggle-status px-2.5 py-1.5 rounded-lg border text-xs font-semibold transition cursor-pointer <?= $isActive ? 'border-red-200 text-red-600 hover:bg-red-50 hover:border-red-300' : 'border-emerald-200 text-emerald-600 hover:bg-emerald-50 hover:border-emerald-300' ?>"
                                                title="<?= $isActive ? 'Deactivate Branch' : 'Activate Branch' ?>">
                                            <i class="fa-solid <?= $isActive ? 'fa-ban' : 'fa-check' ?> mr-1"></i>
                                            <span class="toggle-text"><?= $isActive ? 'Deactivate' : 'Activate' ?></span>
                                        </button>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-xs text-slate-400 italic">View Only</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Add / Edit Branch Modal -->
    <div id="branchModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 max-w-lg w-full overflow-hidden transition-all transform">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-600 flex items-center justify-center">
                        <i class="fa-solid fa-store"></i>
                    </div>
                    <h3 id="modalTitle" class="font-bold text-slate-900 text-lg">Add New Branch</h3>
                </div>
                <button type="button" onclick="closeBranchModal()" class="text-slate-400 hover:text-slate-600 transition p-1">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form id="branchForm" onsubmit="saveBranch(event)" class="p-6 space-y-4">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="branch_id" id="modalBranchId" value="0">

                <div id="modalAlert" class="hidden p-3 rounded-xl text-xs font-bold border"></div>

                <!-- Branch Name -->
                <div>
                    <label for="branchName" class="block text-xs font-bold uppercase text-slate-600 mb-1">
                        Branch Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           name="name" 
                           id="branchName" 
                           required 
                           class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none" 
                           placeholder="e.g. Makati Branch">
                    <p class="text-[11px] text-slate-400 mt-1">Must be unique across café locations.</p>
                </div>

                <!-- Address -->
                <div>
                    <label for="branchAddress" class="block text-xs font-bold uppercase text-slate-600 mb-1">
                        Physical Address
                    </label>
                    <textarea name="address" 
                              id="branchAddress" 
                              rows="3" 
                              class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none" 
                              placeholder="e.g. 123 Ayala Avenue, Makati City"></textarea>
                </div>

                <!-- Contact Number -->
                <div>
                    <label for="branchContact" class="block text-xs font-bold uppercase text-slate-600 mb-1">
                        Contact Number
                    </label>
                    <input type="text" 
                           name="contact_number" 
                           id="branchContact" 
                           class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none" 
                           placeholder="e.g. +63 917 123 4567 or (02) 8123-4567">
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" 
                            onclick="closeBranchModal()" 
                            class="px-4 py-2 border border-slate-300 rounded-xl text-slate-700 hover:bg-slate-50 text-sm font-semibold transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" 
                            id="btnSaveBranch" 
                            class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-sm font-bold shadow-sm transition hover:shadow cursor-pointer flex items-center gap-2">
                        <i class="fa-solid fa-floppy-disk text-xs"></i>
                        <span id="btnSaveText">Save Branch</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toggle Status Confirmation Modal -->
    <div id="statusModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 max-w-md w-full overflow-hidden p-6 space-y-4 text-center">
            <div id="statusModalIconWrap" class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl mx-auto">
                <i id="statusModalIcon" class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div>
                <h3 id="statusModalTitle" class="font-bold text-slate-900 text-lg">Confirm Status Change</h3>
                <p id="statusModalDesc" class="text-xs text-slate-500 mt-1">Are you sure you want to change the status of this branch?</p>
            </div>
            <div class="flex justify-center gap-3 pt-2">
                <button type="button" onclick="closeStatusModal()" class="px-4 py-2 border border-slate-300 rounded-xl text-slate-700 hover:bg-slate-50 text-sm font-semibold transition cursor-pointer">
                    Cancel
                </button>
                <button type="button" id="btnConfirmStatus" onclick="executeToggleStatus()" class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-sm font-bold shadow-sm transition cursor-pointer flex items-center gap-2">
                    <span id="btnConfirmStatusText">Confirm</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Vanilla JavaScript for Modal and CRUD interactions -->
    <script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function showAlert(message, type = 'success') {
        const box = document.getElementById('alertBox');
        if (!box) return;
        box.classList.remove('hidden', 'bg-emerald-50', 'border-emerald-200', 'text-emerald-800', 'bg-red-50', 'border-red-200', 'text-red-800');
        if (type === 'success') {
            box.classList.add('bg-emerald-50', 'border-emerald-200', 'text-emerald-800');
            box.innerHTML = '<i class="fa-solid fa-circle-check mr-2"></i>' + message;
        } else {
            box.classList.add('bg-red-50', 'border-red-200', 'text-red-800');
            box.innerHTML = '<i class="fa-solid fa-triangle-exclamation mr-2"></i>' + message;
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
        setTimeout(() => box.classList.add('hidden'), 5000);
    }

    function showModalAlert(message, type = 'error') {
        const box = document.getElementById('modalAlert');
        if (!box) return;
        box.classList.remove('hidden', 'bg-red-50', 'border-red-200', 'text-red-700', 'bg-emerald-50', 'border-emerald-200', 'text-emerald-700');
        if (type === 'error') {
            box.classList.add('bg-red-50', 'border-red-200', 'text-red-700');
            box.innerHTML = '<i class="fa-solid fa-triangle-exclamation mr-1.5"></i>' + message;
        } else {
            box.classList.add('bg-emerald-50', 'border-emerald-200', 'text-emerald-700');
            box.innerHTML = '<i class="fa-solid fa-circle-check mr-1.5"></i>' + message;
        }
    }

    function openAddBranchModal() {
        document.getElementById('modalBranchId').value = '0';
        document.getElementById('branchName').value = '';
        document.getElementById('branchAddress').value = '';
        document.getElementById('branchContact').value = '';
        document.getElementById('modalTitle').textContent = 'Add New Branch';
        document.getElementById('btnSaveText').textContent = 'Save Branch';
        document.getElementById('modalAlert').classList.add('hidden');
        document.getElementById('branchModal').classList.remove('hidden');
        document.getElementById('branchName').focus();
    }

    function openEditBranchModal(id) {
        const row = document.getElementById('branch-row-' + id);
        if (!row) return;

        const name = row.dataset.name || '';
        const address = row.dataset.address || '';
        const contact = row.dataset.contact || '';

        document.getElementById('modalBranchId').value = id;
        document.getElementById('branchName').value = name;
        document.getElementById('branchAddress').value = address;
        document.getElementById('branchContact').value = contact;
        document.getElementById('modalTitle').textContent = 'Edit Branch';
        document.getElementById('btnSaveText').textContent = 'Update Branch';
        document.getElementById('modalAlert').classList.add('hidden');
        document.getElementById('branchModal').classList.remove('hidden');
        document.getElementById('branchName').focus();
    }

    function closeBranchModal() {
        document.getElementById('branchModal').classList.add('hidden');
    }

    async function saveBranch(e) {
        e.preventDefault();
        const branchId = parseInt(document.getElementById('modalBranchId').value) || 0;
        const name = document.getElementById('branchName').value.trim();
        const address = document.getElementById('branchAddress').value.trim();
        const contactNumber = document.getElementById('branchContact').value.trim();
        const btnSave = document.getElementById('btnSaveBranch');

        if (!name) {
            showModalAlert('Branch Name is required.', 'error');
            return;
        }

        btnSave.disabled = true;
        btnSave.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-xs"></i> Saving...';

        try {
            const res = await fetch('api/fika_hrms_branches.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    action: 'save',
                    csrf_token: csrfToken,
                    branch_id: branchId,
                    name: name,
                    address: address,
                    contact_number: contactNumber
                })
            });

            const data = await res.json();
            if (!res.ok || !data.success) {
                showModalAlert(data.error || 'Failed to save branch.', 'error');
                btnSave.disabled = false;
                btnSave.innerHTML = '<i class="fa-solid fa-floppy-disk text-xs"></i> <span id="btnSaveText">' + (branchId > 0 ? 'Update Branch' : 'Save Branch') + '</span>';
                return;
            }

            closeBranchModal();
            showAlert(data.message, 'success');
            // Refresh table
            setTimeout(() => window.location.reload(), 600);
        } catch (err) {
            showModalAlert('A network error occurred. Please try again.', 'error');
            btnSave.disabled = false;
            btnSave.innerHTML = '<i class="fa-solid fa-floppy-disk text-xs"></i> <span id="btnSaveText">' + (branchId > 0 ? 'Update Branch' : 'Save Branch') + '</span>';
        }
    }

    let pendingToggleBranchId = null;

    function toggleBranchStatus(branchId) {
        const row = document.getElementById('branch-row-' + branchId);
        if (!row) return;

        const currentStatus = row.dataset.status || 'Active';
        const branchName = row.dataset.name || 'Branch';
        const isDeactivating = (currentStatus === 'Active');

        pendingToggleBranchId = branchId;

        const titleEl = document.getElementById('statusModalTitle');
        const descEl = document.getElementById('statusModalDesc');
        const iconWrap = document.getElementById('statusModalIconWrap');
        const iconEl = document.getElementById('statusModalIcon');
        const btn = document.getElementById('btnConfirmStatus');
        const btnText = document.getElementById('btnConfirmStatusText');

        if (isDeactivating) {
            titleEl.textContent = 'Deactivate Branch?';
            descEl.innerHTML = `Are you sure you want to deactivate <strong>"${branchName}"</strong>?<br>Deactivated branches cannot be assigned to new users or hardware terminals.`;
            iconWrap.className = 'w-12 h-12 rounded-xl bg-red-50 text-red-600 flex items-center justify-center text-xl mx-auto';
            iconEl.className = 'fa-solid fa-ban';
            btn.className = 'px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-sm font-bold shadow-sm transition cursor-pointer flex items-center gap-2';
            btnText.textContent = 'Deactivate Branch';
        } else {
            titleEl.textContent = 'Activate Branch?';
            descEl.innerHTML = `Are you sure you want to reactivate <strong>"${branchName}"</strong>?<br>This branch will become immediately available for user assignment and device enrollment.`;
            iconWrap.className = 'w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl mx-auto';
            iconEl.className = 'fa-solid fa-circle-check';
            btn.className = 'px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-bold shadow-sm transition cursor-pointer flex items-center gap-2';
            btnText.textContent = 'Activate Branch';
        }

        document.getElementById('statusModal').classList.remove('hidden');
    }

    function closeStatusModal() {
        document.getElementById('statusModal').classList.add('hidden');
        pendingToggleBranchId = null;
    }

    async function executeToggleStatus() {
        if (!pendingToggleBranchId) return;
        const branchId = pendingToggleBranchId;
        const btn = document.getElementById('btnConfirmStatus');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-xs"></i> Processing...';

        try {
            const res = await fetch('api/fika_hrms_branches.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    action: 'toggle_status',
                    csrf_token: csrfToken,
                    branch_id: branchId
                })
            });

            const data = await res.json();
            closeStatusModal();

            if (!res.ok || !data.success) {
                showAlert(data.error || 'Failed to update branch status.', 'error');
                return;
            }

            showAlert(data.message, 'success');
            setTimeout(() => window.location.reload(), 500);
        } catch (err) {
            closeStatusModal();
            showAlert('Network error while updating status.', 'error');
        }
    }

    function filterBranches() {
        const q = (document.getElementById('branchSearch').value || '').toLowerCase();
        const rows = document.querySelectorAll('.branch-row');
        rows.forEach(r => {
            const text = (r.textContent || '').toLowerCase();
            r.style.display = text.includes(q) ? '' : 'none';
        });
    }

    // Close modal on Escape
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeBranchModal();
    });
    </script>
</body>
</html>
