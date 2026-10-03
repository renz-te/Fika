<?php
require_once __DIR__ . '/init.php';
require_login();
require_role(['Admin', 'Super Admin', 'HR', 'HR Admin']);


$user_role = $_SESSION['user']['role'];
$user_branch_id = $_SESSION['user']['branch_id'] ?? null;
$branches_list = [];

if (in_array($user_role, ['Super Admin', 'Admin'])) {
    $branches_list = $pdo->query("SELECT id, name FROM branches WHERE status = 'Active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
}
$selected_branch_id = $_GET['branch_id'] ?? $user_branch_id ?? 1;

// 2. Fetch Open Register Sessions (Active Cashiers)
$sessionStmt = $pdo->prepare("
    SELECT cs.*, CONCAT(e.first_name, ' ', e.last_name) as head_barista_name 
    FROM cash_sessions cs 
    LEFT JOIN users u ON cs.cashier_id = u.id 
    LEFT JOIN employees e ON u.employee_id = e.id 
    WHERE cs.status = 'OPEN' AND cs.branch_id = ?
");
$sessionStmt->execute([$selected_branch_id]);
$activeSessions = $sessionStmt->fetchAll();

// 3. POS KPIs
$posKpiStmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM orders WHERE payment_status = 'UNPAID' AND branch_id = ? AND is_test = 0) as queued_orders,
        (SELECT COUNT(*) FROM orders WHERE payment_status = 'PAID' AND branch_id = ? AND is_test = 0) as billed_orders,
        (SELECT SUM(total_price) FROM orders WHERE payment_status = 'PAID' AND payment_method = 'CASH' AND branch_id = ? AND is_test = 0) as cash_sales,
        (SELECT SUM(total_price) FROM orders WHERE payment_status = 'PAID' AND payment_method = 'DIGITAL' AND branch_id = ? AND is_test = 0) as digital_sales
");
$posKpiStmt->execute([$selected_branch_id, $selected_branch_id, $selected_branch_id, $selected_branch_id]);
$posKpis = $posKpiStmt->fetch();
$queuedOrders = $posKpis['queued_orders'] ?? 0;
$billedOrders = $posKpis['billed_orders'] ?? 0;
$cashSales = $posKpis['cash_sales'] ?? 0;
$digitalSales = $posKpis['digital_sales'] ?? 0;

// 4. POS Best Sellers (Grouped by Category)
$bestSellersStmt = $pdo->prepare("
    SELECT p.category, p.name, SUM(oi.quantity) as sold_qty
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    WHERE o.payment_status = 'PAID' AND o.branch_id = ? AND o.is_test = 0
    GROUP BY p.category, p.name
    ORDER BY p.category ASC, sold_qty DESC
");
$bestSellersStmt->execute([$selected_branch_id]);
$bestSellersRaw = $bestSellersStmt->fetchAll();

$bestSellers = [];
foreach ($bestSellersRaw as $row) {
    $cat = $row['category'];
    if (!isset($bestSellers[$cat])) {
        $bestSellers[$cat] = [];
    }
    if (count($bestSellers[$cat]) < 5) {
        $bestSellers[$cat][] = $row;
    }
}

// 5. Fetch Closed Sessions (Shift Audits)
$auditsStmt = $pdo->prepare("
    SELECT ps.*, CONCAT(e.first_name, ' ', e.last_name) as head_barista_name 
    FROM cash_sessions ps 
    LEFT JOIN users u ON ps.cashier_id = u.id 
    LEFT JOIN employees e ON u.employee_id = e.id 
    WHERE ps.status = 'CLOSED' AND ps.branch_id = ?
    ORDER BY ps.closed_at DESC LIMIT 10
");
$auditsStmt->execute([$selected_branch_id]);
$closedSessions = $auditsStmt->fetchAll();

// 6. Fetch Recent Receipts
$receiptsStmt = $pdo->prepare("SELECT * FROM orders WHERE payment_status = 'PAID' AND branch_id = ? AND is_test = 0 ORDER BY created_at DESC LIMIT 10");
$receiptsStmt->execute([$selected_branch_id]);
$recentReceipts = $receiptsStmt->fetchAll();

$pageTitle = 'POS Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="px-6 py-4 border-b border-slate-200 bg-white flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">POS Dashboard</h1>
        <p class="text-slate-500 text-sm">Real-time point of sale metrics and live cashiers.</p>
    </div>
    
    <?php if (in_array($user_role, ['Super Admin', 'Admin'])): ?>
        <div class="flex items-center">
            <label class="text-sm font-medium text-slate-700 mr-3"><i class="fa-solid fa-location-dot mr-1 text-blue-500"></i> Scope Branch:</label>
            <select id="global-branch-selector" class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm w-64 bg-slate-50 focus:bg-white focus:ring-blue-500 focus:border-blue-500" onchange="window.location.href='?branch_id=' + this.value">
                <?php foreach ($branches_list as $branch): ?>
                    <option value="<?= $branch['id'] ?>" <?= ($branch['id'] == $selected_branch_id) ? 'selected' : '' ?>><?= htmlspecialchars($branch['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
</div>

<div class="p-6 max-w-7xl mx-auto space-y-6">

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center">
            <div class="h-12 w-12 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center text-xl mr-4">
                <i class="fa-solid fa-list-ol"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-slate-500">Queued Orders (Unpaid)</p>
                <p class="text-2xl font-bold text-slate-800"><?= number_format($queuedOrders) ?></p>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center">
            <div class="h-12 w-12 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center text-xl mr-4">
                <i class="fa-solid fa-receipt"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-slate-500">Total Billed Orders (Paid)</p>
                <p class="text-2xl font-bold text-slate-800"><?= number_format($billedOrders) ?></p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center">
            <div class="h-12 w-12 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center text-xl mr-4">
                <i class="fa-solid fa-coins"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-slate-500">Cash Revenue</p>
                <p class="text-2xl font-bold text-slate-800">₱<?= number_format($cashSales, 2) ?></p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center">
            <div class="h-12 w-12 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center text-xl mr-4">
                <i class="fa-solid fa-credit-card"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-slate-500">Digital Revenue</p>
                <p class="text-2xl font-bold text-slate-800">₱<?= number_format($digitalSales, 2) ?></p>
            </div>
        </div>
    </div>

    <!-- Active Cashiers & Best Sellers -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Active Cashiers (Left Column) -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-200 bg-slate-50 flex items-center">
                    <i class="fa-solid fa-user-clock text-primary mr-2"></i>
                    <h2 class="font-bold text-slate-800">Active Cashiers</h2>
                </div>
                <div class="p-4 space-y-3">
                    <?php if (empty($activeSessions)): ?>
                        <div class="text-center py-6 text-slate-400">
                            <i class="fa-solid fa-cash-register text-3xl mb-2"></i>
                            <p class="text-sm">No registers are currently open.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($activeSessions as $index => $session): ?>
                            <div class="flex items-center p-3 hover:bg-slate-50 rounded-lg transition-colors border border-transparent hover:border-slate-100">
                                <div class="relative mr-3">
                                    <div class="h-10 w-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold">
                                        <?= substr(h($session['head_barista_name']), 0, 1) ?>
                                    </div>
                                    <div class="absolute -bottom-1 -right-1 h-3 w-3 rounded-full bg-green-500 border-2 border-white"></div>
                                </div>
                                <div>
                                    <div class="text-xs text-indigo-500 font-bold uppercase tracking-wider mb-0.5">Register #<?= h($session['id']) ?></div>
                                    <div class="font-bold text-slate-800 text-sm">Opened by <?= h($session['head_barista_name']) ?></div>
                                    <div class="text-xs text-slate-500">Since <?= date('h:i A', strtotime($session['opened_at'])) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Best Sellers (Right Column) -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden h-full">
                <div class="p-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fa-solid fa-fire text-orange-500 mr-2"></i>
                        <h2 class="font-bold text-slate-800">Best Sellers by Category</h2>
                    </div>
                </div>
                <div class="p-6">
                    <?php if (empty($bestSellers)): ?>
                        <div class="text-center py-12 text-slate-400">
                            <i class="fa-solid fa-chart-line text-4xl mb-3"></i>
                            <p>No sales data available yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <?php foreach ($bestSellers as $category => $items): ?>
                                <div class="bg-slate-50 rounded-xl p-4 border border-slate-200">
                                    <h3 class="font-bold text-slate-800 mb-4 pb-2 border-b border-slate-200 capitalize">
                                        <?= h($category) ?>
                                    </h3>
                                    <div class="space-y-3">
                                        <?php foreach ($items as $item): ?>
                                            <div class="flex items-center justify-between">
                                                <div class="flex flex-col">
                                                    <span class="font-medium text-slate-700 text-sm"><?= h($item['name']) ?></span>
                                                    <span class="text-xs text-slate-400">Sold: <?= number_format($item['sold_qty']) ?></span>
                                                </div>
                                                <div class="text-right">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-800">
                                                        Stock: N/A
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- History Tables (Audits & Receipts) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        
        <!-- Shift Audits & Discrepancies -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fa-solid fa-clipboard-check text-indigo-500 mr-2"></i>
                    <h2 class="font-bold text-slate-800">Shift Audits & Discrepancies</h2>
                </div>
            </div>
            <div class="table-responsive" style="overflow-x: auto; white-space: nowrap;">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 text-xs uppercase border-b border-slate-200">
                            <th class="px-4 py-3 font-medium">Head Barista</th>
                            <th class="px-4 py-3 font-medium">Shift Time</th>
                            <th class="px-4 py-3 font-medium text-right">Expected</th>
                            <th class="px-4 py-3 font-medium text-right">Actual</th>
                            <th class="px-4 py-3 font-medium text-right">Discrepancy</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-slate-100">
                        <?php if (empty($closedSessions)): ?>
                            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">No recent closed shifts.</td></tr>
                        <?php else: ?>
                            <?php foreach ($closedSessions as $cs): 
                                $diff = (float)$cs['variance'];
                                $diffColor = $diff < 0 ? 'text-red-500 font-bold' : ($diff > 0 ? 'text-emerald-500 font-bold' : 'text-slate-500');
                                $start = !empty($cs['opened_at']) ? date('h:i A', strtotime($cs['opened_at'])) : 'N/A';
                                $end = !empty($cs['closed_at']) ? date('h:i A', strtotime($cs['closed_at'])) : 'Active';
                            ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 font-medium text-slate-800"><?= h($cs['head_barista_name']) ?></td>
                                    <td class="px-4 py-3 text-slate-500 text-xs"><?= $start ?> - <?= $end ?></td>
                                    <td class="px-4 py-3 text-right text-slate-600">₱<?= number_format((float)$cs['expected_cash'], 2) ?></td>
                                    <td class="px-4 py-3 text-right text-slate-600">₱<?= number_format((float)$cs['closing_cash'], 2) ?></td>
                                    <td class="px-4 py-3 text-right <?= $diffColor ?>">₱<?= number_format((float)$diff, 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Receipts -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fa-solid fa-receipt text-blue-500 mr-2"></i>
                    <h2 class="font-bold text-slate-800">Recent Receipts</h2>
                </div>
            </div>
            <div class="table-responsive" style="overflow-x: auto;">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 text-xs uppercase border-b border-slate-200">
                            <th class="px-4 py-3 font-medium">Order #</th>
                            <th class="px-4 py-3 font-medium">Date</th>
                            <th class="px-4 py-3 font-medium">Time</th>
                            <th class="px-4 py-3 font-medium">Method</th>
                            <th class="px-4 py-3 font-medium text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-slate-100">
                        <?php if (empty($recentReceipts)): ?>
                            <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">No recent transactions.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentReceipts as $rec): 
                                $time = date('h:i A', strtotime($rec['created_at']));
                                $method = $rec['payment_method'];
                                $badge = $method === 'CASH' ? 'bg-emerald-100 text-emerald-700' : 'bg-purple-100 text-purple-700';
                            ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 font-medium text-indigo-600">#<?= h($rec['order_number']) ?></td>
                                    <td class="px-4 py-3 text-slate-500 text-xs"><?php echo date('M d, Y', strtotime($rec['created_at'])); ?></td>
                                    <td class="px-4 py-3 text-slate-500 text-xs"><?= $time ?></td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?= $badge ?>">
                                            <?= h($method) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold text-slate-800">₱<?= number_format($rec['total_price'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
