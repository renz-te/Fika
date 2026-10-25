<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/core/Output.php';

Auth::requireLogin();
Rbac::require_permission('inventory.view');

global $pdo;
$currentUser = Auth::user();

// 1. KPI Queries
$totalItems = 0;
$lowStockAlerts = 0;
$pendingPOs = 0;
$totalStockValue = 0.0;

try {
    $itemStmt = $pdo->query("SELECT COUNT(*) FROM inventory_items WHERE deleted_at IS NULL");
    $totalItems = (int)($itemStmt ? $itemStmt->fetchColumn() : 0);

    $alertStmt = $pdo->query("
        SELECT COUNT(*) 
        FROM inventory_stock s 
        JOIN inventory_items i ON s.inventory_id = i.id 
        WHERE i.deleted_at IS NULL 
          AND s.current_stock <= s.low_stock_threshold
    ");
    $lowStockAlerts = (int)($alertStmt ? $alertStmt->fetchColumn() : 0);

    $poStmt = $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('DRAFT', 'PENDING')");
    $pendingPOs = (int)($poStmt ? $poStmt->fetchColumn() : 0);

    $valStmt = $pdo->query("
        SELECT COALESCE(SUM(s.current_stock * i.unit_cost), 0) 
        FROM inventory_stock s 
        JOIN inventory_items i ON s.inventory_id = i.id 
        WHERE i.deleted_at IS NULL
    ");
    $totalStockValue = (float)($valStmt ? $valStmt->fetchColumn() : 0.0);
} catch (Throwable $e) {
    // Graceful fallback
}

// 2. Low Stock Items Table (items where stock <= threshold)
$lowStockItems = [];
try {
    $lowStmt = $pdo->query("
        SELECT i.id, i.item_name, i.unit, i.unit_cost, s.current_stock, s.low_stock_threshold, b.name as branch_name 
        FROM inventory_stock s 
        JOIN inventory_items i ON s.inventory_id = i.id 
        LEFT JOIN branches b ON s.branch_id = b.id 
        WHERE i.deleted_at IS NULL 
          AND s.current_stock <= s.low_stock_threshold 
        ORDER BY (s.current_stock - s.low_stock_threshold) ASC 
        LIMIT 10
    ");
    if ($lowStmt) {
        $lowStockItems = $lowStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $lowStockItems = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory & Supply Chain Dashboard &middot; Fika Café</title>
    <link rel="icon" type="image/svg+xml" href="../public/assets/logo.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                        <span class="text-base font-extrabold text-slate-900 tracking-tight block leading-none">Inventory & Supply Chain</span>
                        <span class="text-[10px] uppercase font-bold text-blue-700 tracking-wider">Raw Ingredients & Procurement</span>
                    </div>
                </div>

                <nav class="flex items-center gap-6 text-sm font-semibold text-slate-600">
                    <a href="fika_inventory_dashboard.php" class="text-blue-600 font-bold border-b-2 border-blue-600 py-5">Dashboard</a>
                    <a href="fika_inventory_stock.php" class="hover:text-slate-900 transition-colors py-5">Stock Ledger</a>
                    <a href="fika_inventory_procurement.php" class="hover:text-slate-900 transition-colors py-5 flex items-center gap-1.5">
                        <span>Procurement</span>
                        <?php if ($pendingPOs > 0): ?>
                            <span class="px-1.5 py-0.5 text-[10px] font-bold bg-amber-100 text-amber-800 rounded-full"><?= $pendingPOs ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="fika_inventory_recipes.php" class="hover:text-slate-900 transition-colors py-5">Recipe COGS</a>
                </nav>
            </div>
        </header>

    <!-- Main Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Header & Date Banner -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Inventory Overview</h1>
                <p class="text-sm text-slate-500 mt-1">Multi-branch ingredient levels, replenishment alerts, and total stock asset valuation.</p>
            </div>
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-semibold text-slate-600 shadow-sm">
                <i class="fa-regular fa-clock text-blue-600"></i>
                <span>Live Perpetual Ledger</span>
            </div>
        </div>

        <!-- 4 KPI Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            <!-- 1. Total Items -->
            <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Items</span>
                    <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-box-archive"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <span class="text-3xl font-black text-slate-900 tracking-tight"><?= number_format($totalItems) ?></span>
                    <span class="text-xs font-semibold text-slate-500 ml-1">SKUs</span>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <a href="fika_inventory_stock.php" class="text-blue-600 font-semibold hover:underline flex items-center gap-1">
                        <span>View catalog</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                </div>
            </div>

            <!-- 2. Low Stock Alerts -->
            <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Low Stock Alerts</span>
                    <div class="w-10 h-10 rounded-lg <?= $lowStockAlerts > 0 ? 'bg-rose-50 text-rose-600' : 'bg-emerald-50 text-emerald-600' ?> flex items-center justify-center text-lg">
                        <i class="fa-solid <?= $lowStockAlerts > 0 ? 'fa-triangle-exclamation' : 'fa-circle-check' ?>"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <span class="text-3xl font-black <?= $lowStockAlerts > 0 ? 'text-rose-600' : 'text-slate-900' ?> tracking-tight">
                        <?= number_format($lowStockAlerts) ?>
                    </span>
                    <span class="text-xs font-semibold text-slate-500 ml-1">below reorder</span>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="text-slate-400 font-medium"><?= $lowStockAlerts > 0 ? 'Needs reordering' : 'All stock healthy' ?></span>
                </div>
            </div>

            <!-- 3. Pending POs -->
            <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Pending POs</span>
                    <div class="w-10 h-10 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-truck-ramp-box"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <span class="text-3xl font-black text-slate-900 tracking-tight"><?= number_format($pendingPOs) ?></span>
                    <span class="text-xs font-semibold text-slate-500 ml-1">POs awaiting</span>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <a href="fika_inventory_procurement.php" class="text-amber-600 font-semibold hover:underline flex items-center gap-1">
                        <span>Procurement orders</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                </div>
            </div>

            <!-- 4. Total Stock Value -->
            <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Stock Value</span>
                    <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-coins"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <span class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                        <?= Money::format(Money::toCentavos($totalStockValue)) ?>
                    </span>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                    <span>Valued at weighted unit cost</span>
                </div>
            </div>
        </div>

        <!-- Quick Operations Hub -->
        <div class="mb-8 bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-600 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-bolt text-blue-600"></i>
                <span>Inventory Modules</span>
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <a href="fika_inventory_stock.php" class="flex items-center gap-3.5 p-3.5 rounded-lg border border-slate-200 hover:border-blue-400 hover:bg-blue-50/40 transition-all group">
                    <div class="w-10 h-10 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center text-base group-hover:scale-105 transition-transform">
                        <i class="fa-solid fa-cubes-stacked"></i>
                    </div>
                    <div>
                        <p class="font-bold text-slate-900 text-sm">Stock Ledger</p>
                        <p class="text-xs text-slate-500">Real-time counts, adjustments & transfers</p>
                    </div>
                </a>

                <a href="fika_inventory_procurement.php" class="flex items-center gap-3.5 p-3.5 rounded-lg border border-slate-200 hover:border-blue-400 hover:bg-blue-50/40 transition-all group">
                    <div class="w-10 h-10 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center text-base group-hover:scale-105 transition-transform">
                        <i class="fa-solid fa-file-invoice"></i>
                    </div>
                    <div>
                        <p class="font-bold text-slate-900 text-sm">Procurement & POs</p>
                        <p class="text-xs text-slate-500">Purchase orders, receiving & vendor bills</p>
                    </div>
                </a>

                <a href="fika_inventory_recipes.php" class="flex items-center gap-3.5 p-3.5 rounded-lg border border-slate-200 hover:border-blue-400 hover:bg-blue-50/40 transition-all group">
                    <div class="w-10 h-10 rounded-lg bg-purple-100 text-purple-700 flex items-center justify-center text-base group-hover:scale-105 transition-transform">
                        <i class="fa-solid fa-kitchen-set"></i>
                    </div>
                    <div>
                        <p class="font-bold text-slate-900 text-sm">Recipe Formulations</p>
                        <p class="text-xs text-slate-500">Ingredient yield ratios & audited COGS</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- Low Stock Items Table -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-slate-900 text-base">Low Stock Alerts &amp; Critical Levels</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Ingredients reaching or below minimum safety stock thresholds</p>
                </div>
                <a href="fika_inventory_procurement.php" class="text-xs font-semibold px-3 py-1.5 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg transition-colors">
                    Create PO
                </a>
            </div>

            <?php if (!empty($lowStockItems)): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-700">
                        <thead class="bg-slate-50 text-xs uppercase font-bold text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-3">Item Description</th>
                                <th class="px-6 py-3">Branch</th>
                                <th class="px-6 py-3 text-right">Current Stock</th>
                                <th class="px-6 py-3 text-right">Reorder Threshold</th>
                                <th class="px-6 py-3 text-right">Unit Cost</th>
                                <th class="px-6 py-3 text-center">Deficit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($lowStockItems as $item): 
                                $deficit = (float)$item['low_stock_threshold'] - (float)$item['current_stock'];
                            ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="px-6 py-3.5 text-xs font-bold text-slate-900">
                                        <?= e($item['item_name']) ?>
                                    </td>
                                    <td class="px-6 py-3.5 text-xs text-slate-600">
                                        <?= e($item['branch_name'] ?? 'All Branches') ?>
                                    </td>
                                    <td class="px-6 py-3.5 text-right font-black text-rose-600 text-xs">
                                        <?= number_format((float)$item['current_stock'], 2) ?> <?= e($item['unit']) ?>
                                    </td>
                                    <td class="px-6 py-3.5 text-right text-xs text-slate-500">
                                        <?= number_format((float)$item['low_stock_threshold'], 2) ?> <?= e($item['unit']) ?>
                                    </td>
                                    <td class="px-6 py-3.5 text-right text-xs text-slate-700">
                                        <?= Money::format(Money::toCentavos($item['unit_cost'])) ?>
                                    </td>
                                    <td class="px-6 py-3.5 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800">
                                            -<?= number_format(max(0, $deficit), 2) ?> <?= e($item['unit']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <!-- Clean Empty State (Constraint 3) -->
                <div class="p-8 text-center">
                    <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-3 text-lg">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <p class="font-semibold text-slate-700 text-sm">No low stock alerts at this time</p>
                    <p class="text-xs text-slate-400 mt-1">All inventory items are currently above their minimum safety thresholds.</p>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- Footer -->
    <footer class="mt-auto bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-400">
        &copy; <?= date('Y') ?> Fika Café &middot; Inventory & Supply Chain Management
    </footer>
    </div>
</body>
</html>
