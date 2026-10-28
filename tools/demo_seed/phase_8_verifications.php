<?php
/**
 * tools/demo_seed/phase_8_verifications.php
 *
 * Verification suite for Phase D8: Sales (POS Orders) and Restocks
 *
 * Verifies:
 * 1. Orders and revenue per branch per month (Sept & Oct 2026).
 * 2. 5 Sample orders with items, modifiers, total, payment, and exact stock deducted.
 * 3. Stock check for 5 items: opening - sold + restocked = current (with exact math).
 * 4. Zero negative stock across all branches and items.
 * 5. Cashier attendance matching & zero future orders.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

global $pdo, $DEMO_APPLY;

echo "\n====================================================================\n";
echo "Phase D8: Verification & Audit Suite\n";
echo "====================================================================\n";

$ordersCount = (int)$pdo->query("SELECT COUNT(*) FROM demo_seed WHERE phase = 'phase_8' AND table_name = 'orders'")->fetchColumn();

if ($ordersCount === 0 && !$DEMO_APPLY) {
    echo "Notice: Running in DRY-RUN mode before initial apply.\n";
    echo "Live database verifications will execute upon running with --apply.\n";
    
    // Check current baseline stock for any negative stock
    $negStock = (int)$pdo->query("SELECT COUNT(*) FROM inventory_stock WHERE current_stock < 0")->fetchColumn();
    $minStock = (float)$pdo->query("SELECT MIN(current_stock) FROM inventory_stock")->fetchColumn();
    echo "\nBaseline Stock Check:\n";
    echo "  - Negative stock rows: {$negStock}\n";
    echo "  - Minimum current stock: {$minStock}\n";
    if ($negStock === 0) {
        echo "  [PASS] Baseline stock contains 0 negative stock rows.\n";
    }
    return;
}

// ====================================================================
// VERIFICATION 1: Orders and Revenue per Branch per Month
// ====================================================================
echo "\n--- VERIFICATION 1: Orders and Revenue per Branch per Month ---\n";

$revStmt = $pdo->query("
    SELECT 
        b.id AS branch_id,
        b.name AS branch_name,
        DATE_FORMAT(o.created_at, '%Y-%m') AS order_month,
        COUNT(o.id) AS total_orders,
        SUM(o.final_amount) AS total_revenue,
        SUM(CASE WHEN o.payment_method = 'CASH' THEN o.final_amount ELSE 0 END) AS cash_revenue,
        SUM(CASE WHEN o.payment_method = 'GCASH' THEN o.final_amount ELSE 0 END) AS gcash_revenue,
        SUM(CASE WHEN o.payment_method = 'MAYA' THEN o.final_amount ELSE 0 END) AS maya_revenue
    FROM orders o
    JOIN branches b ON b.id = o.branch_id
    JOIN demo_seed ds ON ds.row_id = o.id AND ds.table_name = 'orders' AND ds.phase = 'phase_8'
    GROUP BY b.id, b.name, DATE_FORMAT(o.created_at, '%Y-%m')
    ORDER BY b.id ASC, order_month ASC
");
$revenueRows = $revStmt->fetchAll(PDO::FETCH_ASSOC);

printf("%-3s | %-22s | %-7s | %-6s | %-12s | %-11s | %-11s | %-11s\n",
    "BID", "Branch Name", "Month", "Orders", "Total (PHP)", "Cash (PHP)", "GCash (PHP)", "Maya (PHP)"
);
echo str_repeat("-", 98) . "\n";

$grandOrders = 0;
$grandTotal = 0.0;
$grandCash = 0.0;
$grandGcash = 0.0;
$grandMaya = 0.0;

foreach ($revenueRows as $row) {
    $grandOrders += (int)$row['total_orders'];
    $grandTotal += (float)$row['total_revenue'];
    $grandCash += (float)$row['cash_revenue'];
    $grandGcash += (float)$row['gcash_revenue'];
    $grandMaya += (float)$row['maya_revenue'];

    printf("%-3d | %-22s | %-7s | %-6d | %12s | %11s | %11s | %11s\n",
        $row['branch_id'],
        substr($row['branch_name'], 0, 22),
        $row['order_month'],
        $row['total_orders'],
        number_format((float)$row['total_revenue'], 2),
        number_format((float)$row['cash_revenue'], 2),
        number_format((float)$row['gcash_revenue'], 2),
        number_format((float)$row['maya_revenue'], 2)
    );
}

echo str_repeat("-", 98) . "\n";
printf("%-3s | %-22s | %-7s | %-6d | %12s | %11s | %11s | %11s\n",
    "ALL", "Grand Total", "ALL", $grandOrders,
    number_format($grandTotal, 2),
    number_format($grandCash, 2),
    number_format($grandGcash, 2),
    number_format($grandMaya, 2)
);
echo "\n";

// ====================================================================
// VERIFICATION 2: 5 Sample Orders (Items, Modifiers, Deductions)
// ====================================================================
echo "\n--- VERIFICATION 2: 5 Sample Orders (Items, Modifiers, Deductions) ---\n";

$sampleStmt = $pdo->query("
    SELECT o.id, o.order_number, o.branch_id, b.name AS branch_name, o.cashier_id, u.username AS cashier_user,
           o.order_status, o.payment_status, o.created_at, o.payment_method, o.payment_reference, o.final_amount
    FROM orders o
    JOIN branches b ON b.id = o.branch_id
    JOIN users u ON u.id = o.cashier_id
    JOIN demo_seed ds ON ds.row_id = o.id AND ds.table_name = 'orders' AND ds.phase = 'phase_8'
    ORDER BY o.id ASC
    LIMIT 5
");
$samples = $sampleStmt->fetchAll(PDO::FETCH_ASSOC);

$itemStmt = $pdo->prepare("
    SELECT oi.id, oi.product_id, p.name AS product_name, oi.quantity, oi.price_snapshot, oi.subtotal
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = ?
    ORDER BY oi.id ASC
");

$modStmt = $pdo->prepare("
    SELECT oim.modifier_id, m.name AS modifier_name, oim.price_snapshot
    FROM order_item_modifiers oim
    JOIN modifiers m ON m.id = oim.modifier_id
    WHERE oim.order_item_id = ?
    ORDER BY oim.id ASC
");

$deductStmt = $pdo->prepare("
    SELECT il.inventory_id, ii.item_name, ABS(il.quantity) AS qty_deducted, ii.unit
    FROM inventory_ledger il
    JOIN inventory_items ii ON ii.id = il.inventory_id
    WHERE il.order_id = ? AND il.type = 'SALE'
    ORDER BY il.id ASC
");

foreach ($samples as $idx => $s) {
    $num = $idx + 1;
    echo "Sample Order #{$num}:\n";
    echo "  Order Number  : {$s['order_number']} (ID: {$s['id']})\n";
    echo "  Branch        : Branch #{$s['branch_id']} - {$s['branch_name']}\n";
    echo "  Cashier       : {$s['cashier_user']} (User ID: {$s['cashier_id']})\n";
    echo "  Timestamp     : {$s['created_at']}\n";
    echo "  Status / Pay  : {$s['order_status']} ({$s['payment_status']}) | {$s['payment_method']} (Ref: {$s['payment_reference']})\n";
    echo "  Total Amount  : PHP " . number_format((float)$s['final_amount'], 2) . "\n";
    echo "  Items & Modifiers:\n";

    $itemStmt->execute([$s['id']]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as $item) {
        echo "    * {$item['quantity']}x {$item['product_name']} @ PHP " . number_format((float)$item['price_snapshot'], 2) . " = PHP " . number_format((float)$item['subtotal'], 2) . "\n";
        
        $modStmt->execute([$item['id']]);
        $mods = $modStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($mods as $mod) {
            echo "      + Modifier: {$mod['modifier_name']} (+PHP " . number_format((float)$mod['price_snapshot'], 2) . ")\n";
        }
    }

    echo "  Stock Deducted via InventoryDeduction (Live POS Engine):\n";
    $deductStmt->execute([$s['id']]);
    $deductions = $deductStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($deductions)) {
        echo "    (No recipe ingredients mapped for deduction)\n";
    } else {
        foreach ($deductions as $d) {
            echo "    - {$d['item_name']}: -{$d['qty_deducted']} {$d['unit']}\n";
        }
    }
    echo "\n";
}

// ====================================================================
// VERIFICATION 3: Stock Check for 5 Items (Arithmetic Proof)
// ====================================================================
echo "--- VERIFICATION 3: Stock Check for 5 Items: Opening - Sold + Restocked = Current ---\n";

$targetCheckItems = [
    'Espresso beans',
    'Fresh milk',
    'Ice',
    'Cup 16oz',
    'Croissant'
];

$checkItemStmt = $pdo->prepare("SELECT id, item_name, unit FROM inventory_items WHERE item_name = ?");

// We check Branch 1 (General Trias) as representative, and also aggregate
echo "Target Branch: Branch #1 (General Trias Branch)\n";
printf("%-18s | %-6s | %12s | %12s | %12s | %12s | %-8s\n",
    "Item Name", "Unit", "Opening", "Sold (-)", "Restock (+)", "Current", "Formula Match"
);
echo str_repeat("-", 90) . "\n";

foreach ($targetCheckItems as $itemName) {
    $checkItemStmt->execute([$itemName]);
    $itemInfo = $checkItemStmt->fetch(PDO::FETCH_ASSOC);
    if (!$itemInfo) continue;

    $invId = (int)$itemInfo['id'];
    $unit = $itemInfo['unit'];

    // Opening stock from Phase 2
    $openStmt = $pdo->prepare("
        SELECT COALESCE(SUM(il.quantity), 0)
        FROM inventory_ledger il
        JOIN demo_seed ds ON ds.row_id = il.id AND ds.table_name = 'inventory_ledger' AND ds.phase = 'phase_2'
        WHERE il.branch_id = 1 AND il.inventory_id = ?
    ");
    $openStmt->execute([$invId]);
    $opening = (float)$openStmt->fetchColumn();

    // Restocked from Phase 8
    $restockStmt = $pdo->prepare("
        SELECT COALESCE(SUM(il.quantity), 0)
        FROM inventory_ledger il
        JOIN demo_seed ds ON ds.row_id = il.id AND ds.table_name = 'inventory_ledger' AND ds.phase = 'phase_8'
        WHERE il.branch_id = 1 AND il.inventory_id = ? AND il.type = 'Restock'
    ");
    $restockStmt->execute([$invId]);
    $restocked = (float)$restockStmt->fetchColumn();

    // Sold from Phase 8
    $soldStmt = $pdo->prepare("
        SELECT COALESCE(SUM(ABS(il.quantity)), 0)
        FROM inventory_ledger il
        JOIN demo_seed ds ON ds.row_id = il.id AND ds.table_name = 'inventory_ledger' AND ds.phase = 'phase_8'
        WHERE il.branch_id = 1 AND il.inventory_id = ? AND il.type = 'SALE'
    ");
    $soldStmt->execute([$invId]);
    $sold = (float)$soldStmt->fetchColumn();

    // Current stock from inventory_stock table
    $currStmt = $pdo->prepare("SELECT current_stock FROM inventory_stock WHERE branch_id = 1 AND inventory_id = ?");
    $currStmt->execute([$invId]);
    $current = (float)$currStmt->fetchColumn();

    $calculated = $opening - $sold + $restocked;
    $diff = abs($calculated - $current);
    $status = ($diff < 0.001) ? "EXACT" : "MISMATCH";

    printf("%-18s | %-6s | %12.2f | %12.2f | %12.2f | %12.2f | %-8s\n",
        $itemName, $unit, $opening, $sold, $restocked, $current, $status
    );
    echo "  Math: {$opening} (opening) - {$sold} (sold) + {$restocked} (restocked) = {$calculated} == {$current} [{$status}]\n";
}
echo "\n";

// ====================================================================
// VERIFICATION 4: Zero Negative Stock Across All Branches & Items
// ====================================================================
echo "--- VERIFICATION 4: Negative Stock Audit ---\n";

$negStockCount = (int)$pdo->query("SELECT COUNT(*) FROM inventory_stock WHERE current_stock < 0")->fetchColumn();
$minStockVal = (float)$pdo->query("SELECT MIN(current_stock) FROM inventory_stock")->fetchColumn();
$totalStockRows = (int)$pdo->query("SELECT COUNT(*) FROM inventory_stock")->fetchColumn();

echo "  Total stock records evaluated : {$totalStockRows}\n";
echo "  Negative stock records found  : {$negStockCount}\n";
echo "  Lowest stock level recorded   : {$minStockVal}\n";

if ($negStockCount === 0 && $minStockVal >= 0) {
    echo "  [PASS] ZERO negative stock anywhere across all branches and items.\n";
} else {
    echo "  [FAIL] Negative stock detected! Count: {$negStockCount}, Min: {$minStockVal}\n";
}
echo "\n";

// ====================================================================
// VERIFICATION 5: Cashier Attendance & Future Date Audit
// ====================================================================
echo "--- VERIFICATION 5: Cashier Attendance & Future Date Audit ---\n";

// 5A: Future Orders
$futureOrders = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM orders o
    JOIN demo_seed ds ON ds.row_id = o.id AND ds.table_name = 'orders' AND ds.phase = 'phase_8'
    WHERE o.created_at > '2026-10-28 09:30:00'
")->fetchColumn();

echo "  Audit 5A: Future Orders Audit (> 2026-10-28 09:30:00): {$futureOrders} orders.\n";
if ($futureOrders === 0) {
    echo "    [PASS] No order dated in the future.\n";
} else {
    echo "    [FAIL] Found {$futureOrders} future orders!\n";
}

// 5B: Cashier Attendance Validation
// Verify every single order cashier was clocked in and active on that day and time
$orphanedCashierOrders = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM orders o
    JOIN demo_seed ds ON ds.row_id = o.id AND ds.table_name = 'orders' AND ds.phase = 'phase_8'
    LEFT JOIN attendance_logs al ON al.employee_id = (SELECT employee_id FROM users WHERE id = o.cashier_id)
      AND al.branch_id = o.branch_id
      AND al.work_date = DATE(o.created_at)
      AND al.status != 'ABSENT'
      AND al.clock_in <= o.created_at
      AND (al.clock_out >= o.created_at OR (al.work_date = '2026-10-28' AND al.clock_out IS NULL))
    WHERE al.id IS NULL
")->fetchColumn();

echo "  Audit 5B: Cashier Attendance Matching: {$orphanedCashierOrders} unverified cashier orders.\n";
if ($orphanedCashierOrders === 0) {
    echo "    [PASS] 100% of orders matched to real Crew employees actively clocked in at the order time.\n";
} else {
    echo "    [FAIL] Found {$orphanedCashierOrders} orders without active cashier attendance!\n";
}

echo "====================================================================\n";
