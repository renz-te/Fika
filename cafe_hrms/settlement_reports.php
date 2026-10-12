<?php
require_once __DIR__ . '/init.php';

// Access Control: Super Admin, Exec, Global Accountant, Branch Accountant, Branch Manager
if (empty($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['Super Admin', 'Admin', 'Executive', 'Global Accountant', 'Branch Accountant', 'Branch Manager', 'Head Barista'])) {
    redirect('dashboard');
}

$pageTitle = 'Daily Shift & Digital Settlement Report';
require_once __DIR__ . '/includes/header.php';

$branchId = (int)($_GET['branch_id'] ?? ($_SESSION['user']['branch_id'] ?? 1));
$dateFilter = $_GET['date'] ?? date('Y-m-d');

// 1. Fetch Cash Sessions (Cash Drawer Variance for Head Barista sign-off)
$cashSessionsStmt = $pdo->prepare("
    SELECT cs.*, 
           CONCAT(e.first_name, ' ', e.last_name) as cashier_name,
           u.username as cashier_username
    FROM cash_sessions cs
    LEFT JOIN users u ON cs.cashier_id = u.id
    LEFT JOIN employees e ON u.employee_id = e.id
    WHERE cs.branch_id = ? AND (DATE(cs.opened_at) = ? OR DATE(cs.closed_at) = ?)
    ORDER BY cs.opened_at DESC
");
$cashSessionsStmt->execute([$branchId, $dateFilter, $dateFilter]);
$cashSessions = $cashSessionsStmt->fetchAll();

// 2. Fetch Digital Payments (PayMongo GCash/Maya for BA/GA Digital Reconciliation)
$digitalPaymentsStmt = $pdo->prepare("
    SELECT p.*, o.order_number, o.branch_id, o.payment_status as order_pay_status
    FROM payments p
    JOIN orders o ON p.order_id = o.id
    WHERE o.branch_id = ? AND DATE(p.created_at) = ?
    ORDER BY p.created_at DESC
");
$digitalPaymentsStmt->execute([$branchId, $dateFilter]);
$digitalPayments = $digitalPaymentsStmt->fetchAll();

// 3. Totals
$totalCashExpected = 0.00;
$totalCashActual = 0.00;
$totalCashVariance = 0.00;

foreach ($cashSessions as $cs) {
    $totalCashExpected += (float)$cs['expected_cash'];
    $totalCashActual += (float)$cs['closing_cash'];
    $totalCashVariance += (float)$cs['variance'];
}

$totalDigitalGross = 0.00;
$totalDigitalFee = 0.00;
$totalDigitalNet = 0.00;

foreach ($digitalPayments as $dp) {
    if ($dp['status'] === 'paid') {
        $totalDigitalGross += (float)$dp['gross_amount'];
        $totalDigitalFee += (float)$dp['fee_amount'];
        $totalDigitalNet += (float)$dp['net_amount'];
    }
}
?>

<div class="px-6 py-4 border-b border-slate-200 bg-white flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Shift & Digital Settlement Report</h1>
        <p class="text-slate-500 text-sm">Separates physical cash drawer variance (Head Barista sign-off) from PayMongo digital settlement (BA/GA reconciliation).</p>
    </div>
    
    <form method="GET" class="flex items-center gap-3">
        <input type="date" name="date" value="<?= htmlspecialchars($dateFilter) ?>" class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm bg-white">
        <input type="hidden" name="branch_id" value="<?= $branchId ?>">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded-lg text-sm font-medium">Filter</button>
    </form>
</div>

<div class="p-6 max-w-7xl mx-auto space-y-8">

    <!-- KPI Summary Row -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Physical Cash Expected</span>
            <div class="text-2xl font-extrabold text-slate-900 mt-1">₱<?= number_format($totalCashExpected, 2) ?></div>
            <div class="text-xs text-slate-500 mt-1">Float + Cash Sales - Refunds</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Cash Drawer Variance</span>
            <div class="text-2xl font-extrabold <?= $totalCashVariance < 0 ? 'text-red-600' : ($totalCashVariance > 0 ? 'text-emerald-600' : 'text-slate-900') ?> mt-1">
                <?= ($totalCashVariance > 0 ? '+' : '') ?>₱<?= number_format($totalCashVariance, 2) ?>
            </div>
            <div class="text-xs text-slate-500 mt-1">Head Barista physical count sign-off</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">PayMongo Digital Gross</span>
            <div class="text-2xl font-extrabold text-blue-600 mt-1">₱<?= number_format($totalDigitalGross, 2) ?></div>
            <div class="text-xs text-slate-500 mt-1">GCash & Maya settled payments</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Digital Net Deposit</span>
            <div class="text-2xl font-extrabold text-emerald-600 mt-1">₱<?= number_format($totalDigitalNet, 2) ?></div>
            <div class="text-xs text-slate-500 mt-1">Less ₱<?= number_format($totalDigitalFee, 2) ?> gateway fees (BA/GA)</div>
        </div>
    </div>

    <!-- Section 1: Head Barista Cash Drawer Sign-off -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-5 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-cash-register"></i>
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-800">1. Physical Cash Variance (Head Barista Sign-off)</h2>
                    <p class="text-xs text-slate-500">Excludes online e-wallets. Compares drawer cash count against expected cash.</p>
                </div>
            </div>
            <span class="px-2.5 py-1 text-xs font-bold bg-amber-100 text-amber-800 rounded-full">Cashier Station Only</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-xs uppercase border-b border-slate-200">
                        <th class="p-4 font-semibold">Shift #</th>
                        <th class="p-4 font-semibold">Head Barista / Cashier</th>
                        <th class="p-4 font-semibold">Opened</th>
                        <th class="p-4 font-semibold">Closed</th>
                        <th class="p-4 font-semibold text-right">Opening Float</th>
                        <th class="p-4 font-semibold text-right">Expected Cash</th>
                        <th class="p-4 font-semibold text-right">Closing Cash</th>
                        <th class="p-4 font-semibold text-right">Variance</th>
                        <th class="p-4 font-semibold text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($cashSessions)): ?>
                        <tr><td colspan="9" class="p-6 text-center text-slate-400">No register shifts recorded for this date.</td></tr>
                    <?php else: ?>
                        <?php foreach ($cashSessions as $cs): 
                            $var = (float)$cs['variance'];
                            $varColor = $var < 0 ? 'text-red-600 font-bold' : ($var > 0 ? 'text-emerald-600 font-bold' : 'text-slate-600');
                        ?>
                            <tr class="hover:bg-slate-50">
                                <td class="p-4 font-mono font-bold text-slate-800">#<?= $cs['id'] ?></td>
                                <td class="p-4 font-medium text-slate-800"><?= htmlspecialchars($cs['cashier_name'] ?: ($cs['cashier_username'] ?: 'Cashier #' . $cs['cashier_id'])) ?></td>
                                <td class="p-4 text-xs text-slate-500"><?= date('h:i A', strtotime($cs['opened_at'])) ?></td>
                                <td class="p-4 text-xs text-slate-500"><?= $cs['closed_at'] ? date('h:i A', strtotime($cs['closed_at'])) : '<span class="text-emerald-600 font-semibold">Active</span>' ?></td>
                                <td class="p-4 text-right font-mono text-slate-600">₱<?= number_format((float)$cs['opening_float'], 2) ?></td>
                                <td class="p-4 text-right font-mono text-slate-600">₱<?= number_format((float)$cs['expected_cash'], 2) ?></td>
                                <td class="p-4 text-right font-mono text-slate-600"><?= $cs['closing_cash'] !== null ? '₱' . number_format((float)$cs['closing_cash'], 2) : '-' ?></td>
                                <td class="p-4 text-right font-mono <?= $varColor ?>"><?= $cs['variance'] !== null ? ($var > 0 ? '+' : '') . '₱' . number_format($var, 2) : '-' ?></td>
                                <td class="p-4 text-center">
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold <?= $cs['status'] === 'CLOSED' ? 'bg-slate-100 text-slate-700' : 'bg-emerald-100 text-emerald-800' ?>">
                                        <?= $cs['status'] ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section 2: BA/GA Digital Settlement Reconciliation -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-5 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-credit-card"></i>
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-800">2. Digital Payment Batch Settlement (BA / GA Reconciliation)</h2>
                    <p class="text-xs text-slate-500">PayMongo transactions (GCash / Maya) reconciled against gateway batch payouts.</p>
                </div>
            </div>
            <span class="px-2.5 py-1 text-xs font-bold bg-blue-100 text-blue-800 rounded-full">Accounting Reconciliation</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-xs uppercase border-b border-slate-200">
                        <th class="p-4 font-semibold">Payment ID</th>
                        <th class="p-4 font-semibold">Order #</th>
                        <th class="p-4 font-semibold">Shift #</th>
                        <th class="p-4 font-semibold">Method</th>
                        <th class="p-4 font-semibold">Gateway Intent</th>
                        <th class="p-4 font-semibold text-right">Gross Amount</th>
                        <th class="p-4 font-semibold text-right">Fee</th>
                        <th class="p-4 font-semibold text-right">Net Deposit</th>
                        <th class="p-4 font-semibold text-center">Settlement Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($digitalPayments)): ?>
                        <tr><td colspan="9" class="p-6 text-center text-slate-400">No PayMongo digital payments processed for this date.</td></tr>
                    <?php else: ?>
                        <?php foreach ($digitalPayments as $dp): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="p-4 font-mono font-bold text-slate-800">#<?= $dp['id'] ?></td>
                                <td class="p-4 font-semibold text-blue-600">Order #<?= htmlspecialchars($dp['order_number']) ?></td>
                                <td class="p-4 font-mono text-slate-600">Shift #<?= $dp['shift_id'] ?></td>
                                <td class="p-4">
                                    <span class="px-2 py-0.5 rounded text-xs font-bold uppercase <?= $dp['method'] === 'gcash' ? 'bg-blue-100 text-blue-800' : 'bg-emerald-100 text-emerald-800' ?>">
                                        <?= htmlspecialchars($dp['method']) ?>
                                    </span>
                                </td>
                                <td class="p-4 font-mono text-xs text-slate-500"><?= htmlspecialchars(substr($dp['intent_id'], 0, 18)) ?>...</td>
                                <td class="p-4 text-right font-mono font-medium text-slate-800">₱<?= number_format((float)$dp['gross_amount'], 2) ?></td>
                                <td class="p-4 text-right font-mono text-xs text-slate-500">₱<?= number_format((float)$dp['fee_amount'], 2) ?></td>
                                <td class="p-4 text-right font-mono font-bold text-emerald-600">₱<?= number_format((float)$dp['net_amount'], 2) ?></td>
                                <td class="p-4 text-center">
                                    <span class="px-2 py-0.5 rounded text-xs font-bold uppercase <?= $dp['status'] === 'paid' ? 'bg-emerald-100 text-emerald-800' : ($dp['status'] === 'refunded' ? 'bg-purple-100 text-purple-800' : 'bg-amber-100 text-amber-800') ?>">
                                        <?= htmlspecialchars($dp['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
