<?php
/**
 * Fika Rebuild Core - Payment Reconciliation Dashboard
 * Audits digital transactions against payments and highlights 4 financial anomaly categories.
 * Enforces branch scoping, live pending filtering, and Maker-Checker anomaly resolution.
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/core/Output.php';

Auth::requireLogin();
Rbac::require_permission('finance.view');

$user = Auth::user();
$userId = (int)$user['id'];
$userBranchId = isset($user['branch_id']) ? (int)$user['branch_id'] : null;
$isAdminOrGa = in_array($user['role_name'] ?? '', ['ADMIN', 'Super Admin', 'GA', 'Global Accountant'], true) 
               || in_array((int)($user['role_id'] ?? 0), [1, 3], true);

$csrfToken = Csrf::getToken();
global $pdo;

// Date range filters
$startDate = trim($_GET['start_date'] ?? date('Y-m-01'));
$endDate = trim($_GET['end_date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $startDate = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $endDate = date('Y-m-d');
}

// Branch Scoping check
if (!$isAdminOrGa) {
    if (isset($_GET['branch_id']) && $_GET['branch_id'] !== '' && (int)$_GET['branch_id'] !== $userBranchId) {
        http_response_code(403);
        die("Forbidden: Cannot view reconciliation for another branch.");
    }
    $selectedBranchId = $userBranchId;
} else {
    $selectedBranchId = !empty($_GET['branch_id']) ? (int)$_GET['branch_id'] : null;
}

// Fetch branches for filter
if ($isAdminOrGa) {
    $branches = $pdo->query("SELECT id, name FROM branches ORDER BY name ASC")->fetchAll();
} else {
    $bStmt = $pdo->prepare("SELECT id, name FROM branches WHERE id = ?");
    $bStmt->execute([$userBranchId]);
    $branches = $bStmt->fetchAll();
}

$activeTab = trim($_GET['tab'] ?? 'paid_without_payment');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Reconciliation - Fika Finance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <style>
        .recon-tab.active {
            border-bottom: 2px solid #2563eb;
            color: #2563eb;
            font-weight: 600;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-100 text-slate-800 font-sans antialiased min-h-screen flex">

    <?php require_once __DIR__ . '/../fika_hrms/includes/header.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto">
        <!-- Module Header & Sub-Nav -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-xl shadow-sm">
                        <i class="fa-solid fa-chart-line text-base"></i>
                    </div>
                    <div>
                        <span class="text-base font-extrabold text-slate-900 tracking-tight block leading-none">Payment Reconciliation</span>
                        <span class="text-[10px] uppercase font-bold text-emerald-700 tracking-wider">Audit & Anomaly Resolution</span>
                    </div>
                </div>

                <nav class="flex items-center gap-6 text-sm font-semibold text-slate-600">
                    <a href="fika_finance_dashboard.php" class="hover:text-slate-900 transition-colors py-5">Dashboard</a>
                    <a href="fika_finance_budgets.php" class="hover:text-slate-900 transition-colors py-5">Budgets & Expenses</a>
                    <a href="fika_finance_reconcile.php" class="text-emerald-600 font-bold border-b-2 border-emerald-600 py-5">Reconciliation</a>
                    <a href="fika_finance_reports.php" class="hover:text-slate-900 transition-colors py-5">Financial Reports</a>
                </nav>
            </div>
        </header>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 w-full flex-1">

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Filter Bar -->
        <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6 flex flex-wrap items-end justify-between gap-4">
            <input type="hidden" name="tab" id="activeTabInput" value="<?= e($activeTab) ?>">
            
            <div class="flex flex-wrap items-center gap-4 text-xs">
                <div>
                    <label class="block font-semibold text-gray-500 mb-1">Start Date</label>
                    <input type="date" id="filterStartDate" name="start_date" value="<?= e($startDate) ?>" class="border border-gray-300 rounded-md p-2 bg-white font-medium">
                </div>

                <div>
                    <label class="block font-semibold text-gray-500 mb-1">End Date</label>
                    <input type="date" id="filterEndDate" name="end_date" value="<?= e($endDate) ?>" class="border border-gray-300 rounded-md p-2 bg-white font-medium">
                </div>

                <?php if ($isAdminOrGa): ?>
                <div>
                    <label class="block font-semibold text-gray-500 mb-1">Branch Scope</label>
                    <select id="filterBranchId" name="branch_id" class="border border-gray-300 rounded-md p-2 bg-white font-medium">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $br): ?>
                            <option value="<?= (int)$br['id'] ?>" <?= $selectedBranchId === (int)$br['id'] ? 'selected' : '' ?>>
                                <?= e($br['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <div>
                    <label class="block font-semibold text-gray-500 mb-1">Branch</label>
                    <input type="text" readonly value="<?= e($branches[0]['name'] ?? 'Assigned Branch') ?>" class="border border-gray-200 rounded-md p-2 bg-gray-100 text-gray-500 font-medium">
                    <input type="hidden" id="filterBranchId" name="branch_id" value="<?= (int)$selectedBranchId ?>">
                </div>
                <?php endif; ?>

                <div>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 font-semibold shadow-sm">
                        Filter Anomalies
                    </button>
                </div>
            </div>

            <!-- Quick Ranges -->
            <div class="flex items-center space-x-2 text-xs">
                <span class="text-gray-400 font-semibold">Quick:</span>
                <a href="?tab=<?= e($activeTab) ?>&start_date=<?= date('Y-m-d') ?>&end_date=<?= date('Y-m-d') ?><?= $selectedBranchId ? '&branch_id=' . $selectedBranchId : '' ?>" class="px-2.5 py-1 bg-gray-100 hover:bg-gray-200 rounded text-gray-700 font-medium">Today</a>
                <a href="?tab=<?= e($activeTab) ?>&start_date=<?= date('Y-m-01') ?>&end_date=<?= date('Y-m-d') ?><?= $selectedBranchId ? '&branch_id=' . $selectedBranchId : '' ?>" class="px-2.5 py-1 bg-gray-100 hover:bg-gray-200 rounded text-gray-700 font-medium">This Month</a>
            </div>
        </form>

        <!-- KPI Metric Cards (Constraint 5: live pending counts) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div onclick="switchReconTab('paid_without_payment')" class="cursor-pointer bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:border-amber-400 transition-all">
                <div class="flex justify-between items-start">
                    <span class="text-xs font-semibold uppercase text-gray-500">Paid Without Payment</span>
                    <span class="text-lg"><i class="fa-solid fa-triangle-exclamation text-amber-500"></i></span>
                </div>
                <div id="countMissingPayment" class="text-2xl font-bold text-amber-600 mt-2">0</div>
                <span class="text-[11px] text-gray-400 mt-1 block">PAID digital order with no payment record</span>
            </div>

            <div onclick="switchReconTab('payment_without_order')" class="cursor-pointer bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:border-red-400 transition-all">
                <div class="flex justify-between items-start">
                    <span class="text-xs font-semibold uppercase text-gray-500">Payment Without Paid Order</span>
                    <span class="text-lg"><i class="fa-solid fa-circle-xmark text-red-500"></i></span>
                </div>
                <div id="countUnpaidOrder" class="text-2xl font-bold text-red-600 mt-2">0</div>
                <span class="text-[11px] text-gray-400 mt-1 block">Payment is paid but order is still UNPAID</span>
            </div>

            <div onclick="switchReconTab('amount_mismatch')" class="cursor-pointer bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:border-purple-400 transition-all">
                <div class="flex justify-between items-start">
                    <span class="text-xs font-semibold uppercase text-gray-500">Amount Mismatch</span>
                    <span class="text-lg"><i class="fa-solid fa-bolt text-purple-500"></i></span>
                </div>
                <div id="countMismatch" class="text-2xl font-bold text-purple-600 mt-2">0</div>
                <span class="text-[11px] text-gray-400 mt-1 block">Gross payment amount ≠ Order total</span>
            </div>

            <div onclick="switchReconTab('stuck_pending')" class="cursor-pointer bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:border-blue-400 transition-all">
                <div class="flex justify-between items-start">
                    <span class="text-xs font-semibold uppercase text-gray-500">Stuck Pending</span>
                    <span class="text-lg">⏳</span>
                </div>
                <div id="countStuckPending" class="text-2xl font-bold text-blue-600 mt-2">0</div>
                <span class="text-[11px] text-gray-400 mt-1 block">Unconfirmed payment older than 30 mins</span>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 mb-6">
            <nav class="flex space-x-8" aria-label="Tabs">
                <button type="button" onclick="switchReconTab('paid_without_payment')" id="tabBtn_paid_without_payment" class="recon-tab active py-3 px-1 text-sm font-medium text-gray-500 hover:text-gray-700">
                    1. Paid without Payment Row
                </button>
                <button type="button" onclick="switchReconTab('payment_without_order')" id="tabBtn_payment_without_order" class="recon-tab py-3 px-1 text-sm font-medium text-gray-500 hover:text-gray-700">
                    2. Payment Row without Paid Order
                </button>
                <button type="button" onclick="switchReconTab('amount_mismatch')" id="tabBtn_amount_mismatch" class="recon-tab py-3 px-1 text-sm font-medium text-gray-500 hover:text-gray-700">
                    3. Amount Mismatch
                </button>
                <button type="button" onclick="switchReconTab('stuck_pending')" id="tabBtn_stuck_pending" class="recon-tab py-3 px-1 text-sm font-medium text-gray-500 hover:text-gray-700">
                    4. Stuck Pending (> 30 Mins)
                </button>
            </nav>
        </div>

        <!-- Table Container -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                <h2 id="currentTabTitle" class="text-sm font-bold text-gray-700">Pending Anomalies</h2>
                <span class="text-xs text-gray-400">All resolved anomalies are automatically filtered out (Constraint 5)</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-gray-100 text-gray-600 text-xs uppercase border-b border-gray-200">
                            <th class="p-3.5 font-semibold">Order / Ref</th>
                            <th class="p-3.5 font-semibold">Branch</th>
                            <th class="p-3.5 font-semibold">Cashier</th>
                            <th class="p-3.5 font-semibold">Method</th>
                            <th class="p-3.5 font-semibold text-right">Order Amount</th>
                            <th class="p-3.5 font-semibold text-right">Payment Amount</th>
                            <th class="p-3.5 font-semibold">Timestamp</th>
                            <th class="p-3.5 font-semibold text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="anomaliesTableBody" class="divide-y divide-gray-200 text-xs">
                        <tr><td colspan="8" class="p-8 text-center text-gray-400">Loading reconciliation anomalies...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Resolution Modal -->
    <div id="resolveModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6 space-y-4">
            <div class="flex justify-between items-center border-b border-gray-200 pb-3">
                <h3 class="text-base font-bold text-gray-900">Resolve Payment Anomaly</h3>
                <button type="button" onclick="closeResolveModal()" class="text-gray-400 hover:text-gray-600 text-lg font-bold">&times;</button>
            </div>

            <div id="modalOrderDetails" class="bg-gray-50 p-3 rounded-lg text-xs space-y-1 text-gray-600">
                <!-- Dynamically populated -->
            </div>

            <div id="modalError" class="hidden text-xs text-red-600 bg-red-50 p-3 rounded border border-red-200"></div>

            <form id="resolveForm" onsubmit="submitResolve(event)" class="space-y-4">
                <input type="hidden" id="modalAnomalyType" name="anomaly_type">
                <input type="hidden" id="modalOrderId" name="order_id">
                <input type="hidden" id="modalPaymentId" name="payment_id">

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Resolution Note <span class="text-red-500">*</span>
                    </label>
                    <textarea id="modalResolutionNote" name="resolution_note" rows="3" required placeholder="e.g., Verified manually via PayMongo dashboard. Payment confirmed." class="w-full border border-gray-300 rounded-md p-2.5 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none"></textarea>
                    <span class="text-[11px] text-gray-400 mt-1 block">Explain how this anomaly was verified and resolved. Note is audited.</span>
                </div>

                <div class="flex justify-end space-x-3 pt-2">
                    <button type="button" onclick="closeResolveModal()" class="px-4 py-2 border border-gray-300 rounded-md text-xs font-semibold text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" id="resolveSubmitBtn" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md text-xs font-semibold shadow-sm">
                        Mark Resolved
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentAnomaliesData = {};
        let currentTab = '<?= e($activeTab) ?>';
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const currentUserId = <?= (int)$userId ?>;

        document.addEventListener('DOMContentLoaded', () => {
            loadAnomalies();
        });

        async function loadAnomalies() {
            const startDate = document.getElementById('filterStartDate').value;
            const endDate = document.getElementById('filterEndDate').value;
            const branchId = document.getElementById('filterBranchId') ? document.getElementById('filterBranchId').value : '';

            const params = new URLSearchParams({
                action: 'list_anomalies',
                start_date: startDate,
                end_date: endDate
            });
            if (branchId) params.append('branch_id', branchId);

            try {
                const res = await fetch(`api/fika_finance_reconcile.php?${params.toString()}`);
                const data = await res.json();
                if (!data.success) {
                    alert(data.error || 'Failed to load anomalies');
                    return;
                }

                currentAnomaliesData = data.anomalies;

                // Update KPI Badges
                document.getElementById('countMissingPayment').textContent = data.counts.paid_without_payment;
                document.getElementById('countUnpaidOrder').textContent = data.counts.payment_without_order;
                document.getElementById('countMismatch').textContent = data.counts.amount_mismatch;
                document.getElementById('countStuckPending').textContent = data.counts.stuck_pending;

                renderTable();
            } catch (err) {
                console.error(err);
                document.getElementById('anomaliesTableBody').innerHTML = `
                    <tr><td colspan="8" class="p-8 text-center text-red-500">Failed to communicate with reconciliation service.</td></tr>
                `;
            }
        }

        function switchReconTab(tab) {
            currentTab = tab;
            document.querySelectorAll('.recon-tab').forEach(b => b.classList.remove('active'));
            const activeBtn = document.getElementById(`tabBtn_${tab}`);
            if (activeBtn) activeBtn.classList.add('active');
            renderTable();
        }

        function renderTable() {
            const tbody = document.getElementById('anomaliesTableBody');
            const items = currentAnomaliesData[currentTab] || [];

            const titles = {
                'paid_without_payment': 'Paid Orders Without Payment Record',
                'payment_without_order': 'Payment Rows Without Paid Order',
                'amount_mismatch': 'Payment vs Order Amount Mismatches',
                'stuck_pending': 'Stuck Pending Payments (> 30 Mins)'
            };
            document.getElementById('currentTabTitle').textContent = titles[currentTab] || 'Anomalies';

            if (items.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="p-12 text-center text-gray-400">
                            <div class="text-3xl mb-2 text-emerald-500"><i class="fa-solid fa-circle-check"></i></div>
                            <div class="font-semibold text-gray-700">Zero Anomalies Detected</div>
                            <div class="text-xs text-gray-400 mt-1">All digital transactions for this period are fully balanced.</div>
                        </td>
                    </tr>
                `;
                return;
            }

            let html = '';
            for (const item of items) {
                const orderAmount = parseFloat(item.order_amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2 });
                const payAmount = parseFloat(item.payment_amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2 });
                const orderCreatorId = parseInt(item.cashier_id || 0);
                const isMaker = (orderCreatorId === currentUserId);

                html += `
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="p-3.5 font-bold font-mono text-gray-900">
                            #${item.order_number || item.order_id || '—'}
                            <div class="text-[10px] text-gray-400 font-sans font-normal">${item.anomaly_desc}</div>
                        </td>
                        <td class="p-3.5 text-gray-600">${item.branch_name || 'Branch'}</td>
                        <td class="p-3.5 text-gray-700 font-medium">
                            ${item.cashier_username || 'Cashier #' + (item.cashier_id || '—')}
                            ${isMaker ? '<span class="ml-1 text-[10px] text-amber-600 font-bold bg-amber-50 px-1 py-0.5 rounded">(You)</span>' : ''}
                        </td>
                        <td class="p-3.5">
                            <span class="inline-block px-2 py-0.5 rounded font-bold text-[10px] bg-indigo-100 text-indigo-800 uppercase">
                                ${item.payment_method || 'DIGITAL'}
                            </span>
                        </td>
                        <td class="p-3.5 text-right font-mono font-semibold text-gray-900">₱${orderAmount}</td>
                        <td class="p-3.5 text-right font-mono font-semibold text-gray-900">₱${payAmount}</td>
                        <td class="p-3.5 text-gray-500 font-mono text-[11px]">${item.created_at}</td>
                        <td class="p-3.5 text-center">
                            ${isMaker ? `
                                <span class="text-[11px] text-gray-400 italic font-medium" title="Maker-Checker: You created this order, another accountant must resolve it.">
                                    Creator (Locked)
                                </span>
                            ` : `
                                <button type="button" onclick='openResolveModal(${JSON.stringify(item)})' class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded font-semibold text-xs shadow-sm transition-colors">
                                    Mark Resolved
                                </button>
                            `}
                        </td>
                    </tr>
                `;
            }
            tbody.innerHTML = html;
        }

        function openResolveModal(item) {
            document.getElementById('modalError').classList.add('hidden');
            document.getElementById('modalAnomalyType').value = item.anomaly_type;
            document.getElementById('modalOrderId').value = item.order_id;
            document.getElementById('modalPaymentId').value = item.payment_id || '';
            document.getElementById('modalResolutionNote').value = '';

            const details = document.getElementById('modalOrderDetails');
            details.innerHTML = `
                <div><strong>Order:</strong> #${item.order_number || item.order_id}</div>
                <div><strong>Anomaly:</strong> ${item.anomaly_type}</div>
                <div><strong>Recorded Amounts:</strong> Order ₱${parseFloat(item.order_amount || 0).toFixed(2)} | Payment ₱${parseFloat(item.payment_amount || 0).toFixed(2)}</div>
                <div><strong>Cashier:</strong> ${item.cashier_username || 'ID #' + item.cashier_id}</div>
            `;

            document.getElementById('resolveModal').classList.remove('hidden');
        }

        function closeResolveModal() {
            document.getElementById('resolveModal').classList.add('hidden');
        }

        async function submitResolve(e) {
            e.preventDefault();
            const btn = document.getElementById('resolveSubmitBtn');
            const errBox = document.getElementById('modalError');
            errBox.classList.add('hidden');

            const payload = {
                action: 'resolve',
                csrf_token: csrfToken,
                anomaly_type: document.getElementById('modalAnomalyType').value,
                order_id: document.getElementById('modalOrderId').value,
                payment_id: document.getElementById('modalPaymentId').value,
                resolution_note: document.getElementById('modalResolutionNote').value
            };

            btn.disabled = true;
            btn.textContent = 'Resolving...';

            try {
                const res = await fetch('api/fika_finance_reconcile.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (!res.ok || !data.success) {
                    errBox.textContent = data.error || 'Failed to resolve anomaly.';
                    errBox.classList.remove('hidden');
                    btn.disabled = false;
                    btn.textContent = 'Mark Resolved';
                    return;
                }

                closeResolveModal();
                // Reload live anomalies so the resolved anomaly disappears immediately (Constraint 5)
                await loadAnomalies();
            } catch (err) {
                console.error(err);
                errBox.textContent = 'Communication error while resolving anomaly.';
                errBox.classList.remove('hidden');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Mark Resolved';
            }
        }
    </script>
    </div>
    </div>
</body>
</html>
