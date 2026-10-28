<?php
/**
 * tools/demo_seed/phase_2.php
 *
 * Phase D2: Inventory catalog, units, costs, and per-branch opening stock.
 *
 * Tables populated:
 *   - inventory_items : 27 items (raw ingredients, packaging, pastries)
 *   - inventory_stock : opening stock and reorder levels per active branch
 *   - inventory_ledger: opening stock audit restock records per active branch
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

global $pdo, $DEMO_APPLY;

echo "====================================================================\n";
echo "Phase D2: Seeding Inventory Catalog & Opening Stock\n";
echo "====================================================================\n";

// 1. Define Catalog Specification (Assumptions from prompt)
$catalog = [
    // Raw Ingredients & Packaging
    ['item_name' => 'Espresso beans',     'unit' => 'g',   'unit_cost' => 0.90,  'opening' => 3000.00,  'reorder' => 600.00],
    ['item_name' => 'Fresh milk',         'unit' => 'ml',  'unit_cost' => 0.095, 'opening' => 12000.00, 'reorder' => 2400.00],
    ['item_name' => 'Ice',                'unit' => 'g',   'unit_cost' => 0.02,  'opening' => 20000.00, 'reorder' => 4000.00],
    ['item_name' => 'Matcha powder',      'unit' => 'g',   'unit_cost' => 2.50,  'opening' => 500.00,   'reorder' => 100.00],
    ['item_name' => 'Condensed milk',     'unit' => 'g',   'unit_cost' => 0.20,  'opening' => 2000.00,  'reorder' => 400.00],
    ['item_name' => 'Caramel syrup',      'unit' => 'ml',  'unit_cost' => 0.30,  'opening' => 2000.00,  'reorder' => 400.00],
    ['item_name' => 'Chocolate syrup',    'unit' => 'ml',  'unit_cost' => 0.30,  'opening' => 2000.00,  'reorder' => 400.00],
    ['item_name' => 'Strawberry puree',   'unit' => 'ml',  'unit_cost' => 0.35,  'opening' => 2000.00,  'reorder' => 400.00],
    ['item_name' => 'Simple syrup',       'unit' => 'ml',  'unit_cost' => 0.08,  'opening' => 2000.00,  'reorder' => 400.00],
    ['item_name' => 'Frappe base powder', 'unit' => 'g',   'unit_cost' => 0.35,  'opening' => 1500.00,  'reorder' => 300.00],
    ['item_name' => 'Whipped cream',      'unit' => 'ml',  'unit_cost' => 0.25,  'opening' => 1500.00,  'reorder' => 300.00],
    ['item_name' => 'Black tea leaves',   'unit' => 'g',   'unit_cost' => 0.60,  'opening' => 800.00,   'reorder' => 160.00],
    ['item_name' => 'Tapioca pearls',     'unit' => 'g',   'unit_cost' => 0.15,  'opening' => 3000.00,  'reorder' => 600.00],
    ['item_name' => 'Non-dairy creamer',  'unit' => 'g',   'unit_cost' => 0.25,  'opening' => 2000.00,  'reorder' => 400.00],
    ['item_name' => 'Water',              'unit' => 'ml',  'unit_cost' => 0.001, 'opening' => 20000.00, 'reorder' => 4000.00],
    ['item_name' => 'Cup 16oz',           'unit' => 'pcs', 'unit_cost' => 3.50,  'opening' => 300.00,   'reorder' => 60.00],
    ['item_name' => 'Cup 22oz',           'unit' => 'pcs', 'unit_cost' => 5.00,  'opening' => 300.00,   'reorder' => 60.00],
    ['item_name' => 'Cup 12oz hot',       'unit' => 'pcs', 'unit_cost' => 3.00,  'opening' => 300.00,   'reorder' => 60.00],
    ['item_name' => 'Cup 16oz hot',       'unit' => 'pcs', 'unit_cost' => 4.00,  'opening' => 300.00,   'reorder' => 60.00],
    ['item_name' => 'Dome lid',           'unit' => 'pcs', 'unit_cost' => 1.00,  'opening' => 600.00,   'reorder' => 120.00],
    ['item_name' => 'Hot lid',            'unit' => 'pcs', 'unit_cost' => 0.80,  'opening' => 600.00,   'reorder' => 120.00],
    ['item_name' => 'Straw',              'unit' => 'pcs', 'unit_cost' => 0.50,  'opening' => 600.00,   'reorder' => 120.00],

    // Finished-Goods Pastries
    ['item_name' => 'Croissant',          'unit' => 'pcs', 'unit_cost' => 35.00, 'opening' => 12.00,    'reorder' => 2.40],
    ['item_name' => 'Blueberry Pie',      'unit' => 'pcs', 'unit_cost' => 45.00, 'opening' => 12.00,    'reorder' => 2.40],
    ['item_name' => 'Lemon Bread',        'unit' => 'pcs', 'unit_cost' => 30.00, 'opening' => 12.00,    'reorder' => 2.40],
    ['item_name' => 'Chocolate Cake',     'unit' => 'pcs', 'unit_cost' => 55.00, 'opening' => 12.00,    'reorder' => 2.40],
    ['item_name' => 'Strawberry Cake',    'unit' => 'pcs', 'unit_cost' => 60.00, 'opening' => 12.00,    'reorder' => 2.40],
];

// 2. Query Active Cafe Branches (from DISCOVERY.md Item 6)
$branchStmt = $pdo->query("SELECT id, name FROM branches WHERE status = 'Active' AND id IN (1, 2, 3) ORDER BY id");
$branches = $branchStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($branches)) {
    fwrite(STDERR, "ERROR: No active branches found (expected branches 1, 2, 3).\n");
    exit(1);
}

echo "Target Active Branches (" . count($branches) . "):\n";
foreach ($branches as $b) {
    echo "  - Branch #{$b['id']}: {$b['name']}\n";
}
echo "\n";

// Map branch managers for ledger logging
$bmUsers = [];
$bmStmt = $pdo->prepare("SELECT id FROM users WHERE branch_id = ? AND role_id = 6 LIMIT 1");
foreach ($branches as $b) {
    $bmStmt->execute([$b['id']]);
    $bmId = $bmStmt->fetchColumn();
    $bmUsers[$b['id']] = $bmId ? (int)$bmId : 10006; // Fallback to system admin (10006)
}

$openingDate = '2026-08-01 00:00:00';
$remarks = 'Opening stock balance as of 2026-08-01';

// 3. Seed Catalog and Stock
echo "[1/3] Processing Inventory Catalog (" . count($catalog) . " items)...\n";
$itemIds = [];

foreach ($catalog as $idx => $item) {
    $itemRow = [
        'item_name'  => $item['item_name'],
        'unit'       => $item['unit'],
        'unit_cost'  => $item['unit_cost'],
        'created_at' => $openingDate,
    ];
    $naturalKey = ['item_name' => $item['item_name']];

    $res = demo_insert($pdo, 'inventory_items', $itemRow, $naturalKey, 'phase_2');
    $invId = $res['id'] ?? (1000 + $idx); // Synthetic non-null ID for dry-run
    $itemIds[$item['item_name']] = $invId;
}

echo "\n[2/3] Processing Per-Branch Opening Stock & Ledger Entries...\n";

foreach ($branches as $b) {
    $bId = (int)$b['id'];
    $bmId = $bmUsers[$bId];
    echo "  -> Branch #{$bId} ({$b['name']}):\n";

    foreach ($catalog as $item) {
        $invId = $itemIds[$item['item_name']];

        // A. inventory_stock row
        $stockRow = [
            'branch_id'           => $bId,
            'inventory_id'        => $invId,
            'current_stock'       => $item['opening' ],
            'low_stock_threshold' => $item['reorder'],
            'created_at'          => $openingDate,
        ];
        $stockKey = [
            'branch_id'    => $bId,
            'inventory_id' => $invId,
        ];
        demo_insert($pdo, 'inventory_stock', $stockRow, $stockKey, 'phase_2');

        // B. inventory_ledger opening transaction
        $ledgerRow = [
            'branch_id'          => $bId,
            'inventory_id'       => $invId,
            'type'               => 'Restock',
            'quantity'           => $item['opening'],
            'unit_cost_snapshot' => $item['unit_cost'],
            'user_id'            => $bmId,
            'remarks'            => $remarks,
            'created_at'         => $openingDate,
        ];
        $ledgerKey = [
            'branch_id'    => $bId,
            'inventory_id' => $invId,
            'remarks'      => $remarks,
        ];
        demo_insert($pdo, 'inventory_ledger', $ledgerRow, $ledgerKey, 'phase_2');
    }
}

// 4. Print Verification Tables
echo "\n====================================================================\n";
echo "VERIFICATION: Inventory Catalog & Stock Summary\n";
echo "====================================================================\n\n";

echo "--- 1. INVENTORY CATALOG (27 Items) ---\n";
printf("%-4s | %-24s | %-6s | %-12s\n", "No.", "Item Name", "Unit", "Cost/Unit");
echo str_repeat("-", 52) . "\n";
foreach ($catalog as $i => $item) {
    printf("%-4d | %-24s | %-6s | %10.2f\n", $i + 1, $item['item_name'], $item['unit'], $item['unit_cost']);
}

echo "\n--- 2. STOCK LEVELS PER ACTIVE BRANCH ---\n";
printf("%-22s | %-22s | %-12s | %-6s | %-12s\n", "Branch", "Item Name", "Stock", "Unit", "Reorder Lvl");
echo str_repeat("-", 84) . "\n";
foreach ($branches as $b) {
    foreach ($catalog as $item) {
        printf("%-22s | %-22s | %12.2f | %-6s | %12.2f\n", 
            $b['name'], 
            $item['item_name'], 
            $item['opening'], 
            $item['unit'], 
            $item['reorder']
        );
    }
    echo str_repeat("-", 84) . "\n";
}

// 5. Audit Validations (Done When Checks)
echo "\n--- 3. INTEGRITY AUDIT CHECKS ---\n";

if ($DEMO_APPLY) {
    // A. Check for orphaned stock rows
    $orphanCheck = $pdo->query("
        SELECT COUNT(*) 
        FROM inventory_stock s 
        LEFT JOIN inventory_items i ON s.inventory_id = i.id 
        WHERE i.id IS NULL
    ")->fetchColumn();

    // B. Check catalog vs branch stock count agreement
    $itemCount = (int)$pdo->query("SELECT COUNT(*) FROM inventory_items WHERE deleted_at IS NULL")->fetchColumn();
    $stockCount = (int)$pdo->query("SELECT COUNT(*) FROM inventory_stock")->fetchColumn();
    $expectedStockCount = $itemCount * count($branches);

    // C. Check negative values
    $negativeStock = (int)$pdo->query("SELECT COUNT(*) FROM inventory_stock WHERE current_stock < 0 OR low_stock_threshold < 0")->fetchColumn();
    $negativeCost = (int)$pdo->query("SELECT COUNT(*) FROM inventory_items WHERE unit_cost < 0")->fetchColumn();
    $negativeLedger = (int)$pdo->query("SELECT COUNT(*) FROM inventory_ledger WHERE quantity < 0 AND type = 'Restock'")->fetchColumn();

    // D. Check ledger sync: current_stock == SUM(inventory_ledger.quantity)
    $ledgerMismatch = $pdo->query("
        SELECT COUNT(*) 
        FROM inventory_stock s
        JOIN (
            SELECT branch_id, inventory_id, SUM(quantity) as total_qty 
            FROM inventory_ledger 
            GROUP BY branch_id, inventory_id
        ) l ON s.branch_id = l.branch_id AND s.inventory_id = l.inventory_id
        WHERE s.current_stock != l.total_qty
    ")->fetchColumn();

    echo "  [AUDIT] Orphaned stock rows: {$orphanCheck} (Expected: 0)\n";
    echo "  [AUDIT] Catalog items: {$itemCount} | Total stock rows: {$stockCount} (Expected: {$expectedStockCount})\n";
    echo "  [AUDIT] Negative values found: stock={$negativeStock}, cost={$negativeCost}, ledger={$negativeLedger} (Expected: 0)\n";
    echo "  [AUDIT] Stock vs Ledger balance mismatches: {$ledgerMismatch} (Expected: 0)\n";

    if ($orphanCheck === 0 && $stockCount === $expectedStockCount && ($negativeStock + $negativeCost + $negativeLedger) === 0 && $ledgerMismatch === 0) {
        echo "  >>> ALL AUDIT CHECKS PASSED PERFECTLY <<<\n";
    } else {
        fwrite(STDERR, "WARNING: Some audit checks had discrepancies.\n");
    }
} else {
    echo "  (Audit queries skipped in DRY RUN mode)\n";
}
echo "\n";
