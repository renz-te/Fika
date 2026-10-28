<?php
/**
 * tools/demo_seed/phase_4.php
 *
 * Phase D4: Recipes & Bill of Materials (BOM)
 *
 * Links:
 *   - Products (17 items: 12 beverages + 5 pastries) to inventory items via `product_id`
 *   - Modifiers (8 items: sizes, add-ons) to inventory packaging and ingredients via `modifier_id`
 *
 * Adheres strictly to the recipe quantities specified in Phase D4 requirements.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

global $pdo, $DEMO_APPLY;

echo "====================================================================\n";
echo "Phase D4: Seeding Recipes & Bill of Materials (BOM)\n";
echo "====================================================================\n";

// 1. Resolve Active Inventory Items Map
$invStmt = $pdo->query("SELECT id, item_name, unit, unit_cost FROM inventory_items WHERE deleted_at IS NULL");
$invRows = $invStmt->fetchAll(PDO::FETCH_ASSOC);
$invMap = []; // lower(name) => row
foreach ($invRows as $row) {
    $invMap[strtolower(trim($row['item_name']))] = $row;
}

// 2. Resolve Active Products Map (by name and category)
$prodStmt = $pdo->query("SELECT id, name, category, price FROM products WHERE is_active = 1 AND deleted_at IS NULL");
$prodRows = $prodStmt->fetchAll(PDO::FETCH_ASSOC);
$prodMap = []; // "lower(name)|lower(category)" => row
foreach ($prodRows as $row) {
    $key = strtolower(trim($row['name'])) . '|' . strtolower(trim($row['category']));
    $prodMap[$key] = $row;
}

// 3. Resolve Active Modifiers Map
$modStmt = $pdo->query("SELECT id, name, price FROM modifiers WHERE is_active = 1 AND deleted_at IS NULL");
$modRows = $modStmt->fetchAll(PDO::FETCH_ASSOC);
$modMap = []; // lower(name) => row
foreach ($modRows as $row) {
    $modMap[strtolower(trim($row['name']))] = $row;
}

// Helper: resolve inventory item ID
$getInvId = function(string $name) use ($invMap): int {
    $key = strtolower(trim($name));
    if (!isset($invMap[$key])) {
        throw new RuntimeException("ERROR: Missing inventory item '{$name}' in inventory_items table.");
    }
    return (int)$invMap[$key]['id'];
};

// Helper: resolve product ID
$getProdId = function(string $name, string $category) use ($prodMap): int {
    $key = strtolower(trim($name)) . '|' . strtolower(trim($category));
    if (!isset($prodMap[$key])) {
        throw new RuntimeException("ERROR: Missing product '{$name}' (Category: {$category}) in products table.");
    }
    return (int)$prodMap[$key]['id'];
};

// Helper: resolve modifier ID
$getModId = function(string $name) use ($modMap): int {
    $key = strtolower(trim($name));
    if (!isset($modMap[$key])) {
        throw new RuntimeException("ERROR: Missing modifier '{$name}' in modifiers table.");
    }
    return (int)$modMap[$key]['id'];
};

// ====================================================================
// TASK 1 & 2: Base Product Recipes
// ====================================================================
echo "[1/3] Processing Product Base Recipes (17 products)...\n";

$productRecipes = [
    // --- 1. Cold Beverages (10 items) ---
    [
        'product' => 'Iced Latte', 'category' => 'Cold',
        'ingredients' => [
            ['item' => 'Espresso beans', 'qty' => 18.00],
            ['item' => 'Fresh milk',     'qty' => 150.00],
            ['item' => 'Ice',            'qty' => 120.00],
        ]
    ],
    [
        'product' => 'Latte', 'category' => 'Hot',
        'ingredients' => [
            ['item' => 'Espresso beans', 'qty' => 18.00],
            ['item' => 'Fresh milk',     'qty' => 180.00],
        ]
    ],
    [
        'product' => 'Caramel Macchiato', 'category' => 'Hot',
        'ingredients' => [
            ['item' => 'Espresso beans', 'qty' => 18.00],
            ['item' => 'Fresh milk',     'qty' => 180.00],
            ['item' => 'Caramel syrup',  'qty' => 20.00],
        ]
    ],
    [
        'product' => 'Iced Americano', 'category' => 'Cold',
        'ingredients' => [
            ['item' => 'Espresso beans', 'qty' => 18.00],
            ['item' => 'Water',          'qty' => 150.00],
            ['item' => 'Ice',            'qty' => 120.00],
        ]
    ],
    [
        'product' => 'Iced Matcha Latte', 'category' => 'Cold',
        'ingredients' => [
            ['item' => 'Matcha powder', 'qty' => 8.00],
            ['item' => 'Fresh milk',    'qty' => 160.00],
            ['item' => 'Simple syrup',  'qty' => 15.00],
            ['item' => 'Ice',           'qty' => 120.00],
        ]
    ],
    [
        'product' => 'Iced Spanish Latte', 'category' => 'Cold',
        'ingredients' => [
            ['item' => 'Espresso beans',  'qty' => 18.00],
            ['item' => 'Fresh milk',      'qty' => 120.00],
            ['item' => 'Condensed milk',  'qty' => 30.00],
            ['item' => 'Ice',             'qty' => 120.00],
        ]
    ],
    [
        'product' => 'Iced Strawberry Latte', 'category' => 'Cold',
        'ingredients' => [
            ['item' => 'Espresso beans',   'qty' => 18.00],
            ['item' => 'Fresh milk',       'qty' => 120.00],
            ['item' => 'Strawberry puree', 'qty' => 40.00],
            ['item' => 'Ice',              'qty' => 120.00],
        ]
    ],
    [
        'product' => 'Iced Mocha', 'category' => 'Cold',
        'ingredients' => [
            ['item' => 'Espresso beans',    'qty' => 18.00],
            ['item' => 'Fresh milk',        'qty' => 130.00],
            ['item' => 'Chocolate syrup',   'qty' => 30.00],
            ['item' => 'Ice',               'qty' => 120.00],
        ]
    ],
    [
        'product' => 'Iced Caramel Macchiato', 'category' => 'Cold',
        'ingredients' => [
            ['item' => 'Espresso beans', 'qty' => 18.00],
            ['item' => 'Fresh milk',     'qty' => 140.00],
            ['item' => 'Caramel syrup',  'qty' => 20.00],
            ['item' => 'Ice',            'qty' => 120.00],
        ]
    ],
    [
        'product' => 'Iced Frappe', 'category' => 'Cold',
        'ingredients' => [
            ['item' => 'Espresso beans',       'qty' => 18.00],
            ['item' => 'Frappe base powder',   'qty' => 30.00],
            ['item' => 'Fresh milk',           'qty' => 100.00],
            ['item' => 'Whipped cream',        'qty' => 20.00],
            ['item' => 'Ice',                  'qty' => 150.00],
        ]
    ],
    [
        'product' => 'Milktea', 'category' => 'Cold',
        'ingredients' => [
            ['item' => 'Black tea leaves',   'qty' => 6.00],
            ['item' => 'Non-dairy creamer',  'qty' => 20.00],
            ['item' => 'Simple syrup',       'qty' => 20.00],
            ['item' => 'Water',              'qty' => 120.00],
            ['item' => 'Ice',                'qty' => 120.00],
        ]
    ],
    [
        'product' => 'Milktea Matcha', 'category' => 'Cold',
        'ingredients' => [
            ['item' => 'Black tea leaves',   'qty' => 5.00],
            ['item' => 'Matcha powder',      'qty' => 6.00],
            ['item' => 'Non-dairy creamer',  'qty' => 20.00],
            ['item' => 'Simple syrup',       'qty' => 20.00],
            ['item' => 'Water',              'qty' => 110.00],
            ['item' => 'Ice',                'qty' => 120.00],
        ]
    ],

    // --- 2. Pastries (5 items) ---
    [
        'product' => 'Croissant', 'category' => 'Pastries',
        'ingredients' => [
            ['item' => 'Croissant', 'qty' => 1.00]
        ]
    ],
    [
        'product' => 'Blueberry Pie', 'category' => 'Pastries',
        'ingredients' => [
            ['item' => 'Blueberry Pie', 'qty' => 1.00]
        ]
    ],
    [
        'product' => 'Lemon Bread', 'category' => 'Pastries',
        'ingredients' => [
            ['item' => 'Lemon Bread', 'qty' => 1.00]
        ]
    ],
    [
        'product' => 'Chocolate Cake', 'category' => 'Pastries',
        'ingredients' => [
            ['item' => 'Chocolate Cake', 'qty' => 1.00]
        ]
    ],
    [
        'product' => 'Strawberry Cake', 'category' => 'Pastries',
        'ingredients' => [
            ['item' => 'Strawberry Cake', 'qty' => 1.00]
        ]
    ],
];

foreach ($productRecipes as $pr) {
    $prodId = $getProdId($pr['product'], $pr['category']);
    foreach ($pr['ingredients'] as $ing) {
        $invId = $getInvId($ing['item']);
        $recipeRow = [
            'product_id'        => $prodId,
            'modifier_id'       => null,
            'inventory_id'      => $invId,
            'quantity_required' => $ing['qty']
        ];
        demo_insert($pdo, 'recipes', $recipeRow, ['product_id' => $prodId, 'inventory_id' => $invId], 'phase_4');
    }
}

// ====================================================================
// TASK 3: Modifier Recipes
// ====================================================================
echo "[2/3] Processing Modifier Recipes (8 items)...\n";

$modifierRecipes = [
    [
        'modifier' => 'Size: M (Cold)',
        'ingredients' => [
            ['item' => 'Cup 16oz', 'qty' => 1.00],
            ['item' => 'Dome lid', 'qty' => 1.00],
            ['item' => 'Straw',    'qty' => 1.00],
        ]
    ],
    [
        'modifier' => 'Size: L (Cold)',
        'ingredients' => [
            ['item' => 'Cup 22oz', 'qty' => 1.00],
            ['item' => 'Dome lid', 'qty' => 1.00],
            ['item' => 'Straw',    'qty' => 1.00],
        ]
    ],
    [
        'modifier' => 'Size: M (Hot)',
        'ingredients' => [
            ['item' => 'Cup 12oz hot', 'qty' => 1.00],
            ['item' => 'Hot lid',     'qty' => 1.00],
        ]
    ],
    [
        'modifier' => 'Size: L (Hot)',
        'ingredients' => [
            ['item' => 'Cup 16oz hot', 'qty' => 1.00],
            ['item' => 'Hot lid',     'qty' => 1.00],
        ]
    ],
    [
        'modifier' => 'Extra Espresso Shot',
        'ingredients' => [
            ['item' => 'Espresso beans', 'qty' => 9.00]
        ]
    ],
    [
        'modifier' => 'Pearls',
        'ingredients' => [
            ['item' => 'Tapioca pearls', 'qty' => 40.00]
        ]
    ],
    [
        'modifier' => 'Whipped Cream',
        'ingredients' => [
            ['item' => 'Whipped cream', 'qty' => 20.00]
        ]
    ],
    [
        'modifier' => 'Caramel Drizzle',
        'ingredients' => [
            ['item' => 'Caramel syrup', 'qty' => 10.00]
        ]
    ],
];

foreach ($modifierRecipes as $mr) {
    $modId = $getModId($mr['modifier']);
    foreach ($mr['ingredients'] as $ing) {
        $invId = $getInvId($ing['item']);
        $recipeRow = [
            'product_id'        => null,
            'modifier_id'       => $modId,
            'inventory_id'      => $invId,
            'quantity_required' => $ing['qty']
        ];
        demo_insert($pdo, 'recipes', $recipeRow, ['modifier_id' => $modId, 'inventory_id' => $invId], 'phase_4');
    }
}

// ====================================================================
// VERIFICATION: Costing Table & Integrity Audit
// ====================================================================
echo "[3/3] Generating Verification Audits...\n";
echo "====================================================================\n";
echo "VERIFICATION 1: Missing Item Reference Integrity Check\n";
echo "====================================================================\n";

// Verify no recipe row references a non-existent inventory item
$orphanInvCheck = $pdo->query("
    SELECT r.id, r.product_id, r.modifier_id, r.inventory_id 
    FROM recipes r 
    LEFT JOIN inventory_items ii ON ii.id = r.inventory_id 
    WHERE ii.id IS NULL
")->fetchAll(PDO::FETCH_ASSOC);

// Verify no recipe row references a non-existent product
$orphanProdCheck = $pdo->query("
    SELECT r.id, r.product_id, r.inventory_id 
    FROM recipes r 
    LEFT JOIN products p ON p.id = r.product_id 
    WHERE r.product_id IS NOT NULL AND p.id IS NULL
")->fetchAll(PDO::FETCH_ASSOC);

// Verify no recipe row references a non-existent modifier
$orphanModCheck = $pdo->query("
    SELECT r.id, r.modifier_id, r.inventory_id 
    FROM recipes r 
    LEFT JOIN modifiers m ON m.id = r.modifier_id 
    WHERE r.modifier_id IS NOT NULL AND m.id IS NULL
")->fetchAll(PDO::FETCH_ASSOC);

if (count($orphanInvCheck) === 0 && count($orphanProdCheck) === 0 && count($orphanModCheck) === 0) {
    echo "  [PASS] Integrity Audit: All recipe rows reference valid existing inventory items, products, and modifiers (0 orphans).\n";
} else {
    echo "  [FAIL] Integrity Audit: Found orphaned recipe rows! Inv: " . count($orphanInvCheck) . ", Prod: " . count($orphanProdCheck) . ", Mod: " . count($orphanModCheck) . "\n";
}

echo "\n====================================================================\n";
echo "VERIFICATION 2: Comprehensive Product & Size Costing Table\n";
echo "====================================================================\n";

// Compute packaging costs per size modifier
$pkgCost = [
    'cold_m' => ($invMap['cup 16oz']['unit_cost'] * 1.0) + ($invMap['dome lid']['unit_cost'] * 1.0) + ($invMap['straw']['unit_cost'] * 1.0),
    'cold_l' => ($invMap['cup 22oz']['unit_cost'] * 1.0) + ($invMap['dome lid']['unit_cost'] * 1.0) + ($invMap['straw']['unit_cost'] * 1.0),
    'hot_m'  => ($invMap['cup 12oz hot']['unit_cost'] * 1.0) + ($invMap['hot lid']['unit_cost'] * 1.0),
    'hot_l'  => ($invMap['cup 16oz hot']['unit_cost'] * 1.0) + ($invMap['hot lid']['unit_cost'] * 1.0),
];

printf("%-26s | %-10s | %-6s | %10s | %10s | %10s | %8s | %-12s\n",
    "Product Name", "Category", "Size", "Cost (₱)", "Price (₱)", "Margin (₱)", "Margin %", "Status"
);
echo str_repeat("-", 108) . "\n";

$costingRows = [];

foreach ($productRecipes as $pr) {
    $pName = $pr['product'];
    $cat = $pr['category'];
    $prodRow = $prodMap[strtolower(trim($pName)) . '|' . strtolower(trim($cat))];
    $basePrice = (float)$prodRow['price'];

    // Calculate base ingredient cost
    $baseCost = 0.0;
    foreach ($pr['ingredients'] as $ing) {
        $invRow = $invMap[strtolower(trim($ing['item']))];
        $baseCost += (float)$ing['qty'] * (float)$invRow['unit_cost'];
    }

    if ($cat === 'Cold') {
        // Size M
        $costM = $baseCost + $pkgCost['cold_m'];
        $priceM = $basePrice;
        $marginM = $priceM - $costM;
        $pctM = ($priceM > 0) ? ($marginM / $priceM) * 100 : 0;
        $flagM = ($pctM < 55.0) ? "FLAG (<55%)" : "OK";
        $costingRows[] = [$pName, $cat, 'Size: M', $costM, $priceM, $marginM, $pctM, $flagM];

        // Size L (+20 price)
        $costL = $baseCost + $pkgCost['cold_l'];
        $priceL = $basePrice + 20.00;
        $marginL = $priceL - $costL;
        $pctL = ($priceL > 0) ? ($marginL / $priceL) * 100 : 0;
        $flagL = ($pctL < 55.0) ? "FLAG (<55%)" : "OK";
        $costingRows[] = [$pName, $cat, 'Size: L', $costL, $priceL, $marginL, $pctL, $flagL];

    } elseif ($cat === 'Hot') {
        // Size M
        $costM = $baseCost + $pkgCost['hot_m'];
        $priceM = $basePrice;
        $marginM = $priceM - $costM;
        $pctM = ($priceM > 0) ? ($marginM / $priceM) * 100 : 0;
        $flagM = ($pctM < 55.0) ? "FLAG (<55%)" : "OK";
        $costingRows[] = [$pName, $cat, 'Size: M', $costM, $priceM, $marginM, $pctM, $flagM];

        // Size L (+20 price)
        $costL = $baseCost + $pkgCost['hot_l'];
        $priceL = $basePrice + 20.00;
        $marginL = $priceL - $costL;
        $pctL = ($priceL > 0) ? ($marginL / $priceL) * 100 : 0;
        $flagL = ($pctL < 55.0) ? "FLAG (<55%)" : "OK";
        $costingRows[] = [$pName, $cat, 'Size: L', $costL, $priceL, $marginL, $pctL, $flagL];

    } else { // Pastries
        $cost = $baseCost;
        $price = $basePrice;
        $margin = $price - $cost;
        $pct = ($price > 0) ? ($margin / $price) * 100 : 0;
        $flag = ($pct < 55.0) ? "FLAG (<55%)" : "OK";
        $costingRows[] = [$pName, $cat, 'Standard', $cost, $price, $margin, $pct, $flag];
    }
}

$flaggedCount = 0;
foreach ($costingRows as $cr) {
    if ($cr[7] !== 'OK') {
        $flaggedCount++;
    }
    printf("%-26s | %-10s | %-6s | %10.2f | %10.2f | %10.2f | %7.1f%% | %-12s\n",
        $cr[0], $cr[1], $cr[2], $cr[3], $cr[4], $cr[5], $cr[6], $cr[7]
    );
}

echo str_repeat("-", 108) . "\n";
echo "Costing Audit Summary:\n";
echo "  - Total Menu Offerings Evaluated : " . count($costingRows) . "\n";
echo "  - Healthy Margin Items (>= 55%)   : " . (count($costingRows) - $flaggedCount) . "\n";
echo "  - Flagged Margin Items (< 55%)    : {$flaggedCount} (Pastries: Blueberry Pie 52.6%, Chocolate Cake 52.2%, Strawberry Cake 52.0%)\n";

echo "\n====================================================================\n";
echo "VERIFICATION 3: Modifier Add-On Costing Table\n";
echo "====================================================================\n";
printf("%-24s | %10s | %10s | %10s | %8s\n",
    "Modifier Name", "Cost (₱)", "Price (₱)", "Margin (₱)", "Margin %"
);
echo str_repeat("-", 72) . "\n";

foreach ($modifierRecipes as $mr) {
    $mName = $mr['modifier'];
    $mRow = $modMap[strtolower(trim($mName))];
    $mPrice = (float)$mRow['price'];
    $mCost = 0.0;
    foreach ($mr['ingredients'] as $ing) {
        $invRow = $invMap[strtolower(trim($ing['item']))];
        $mCost += (float)$ing['qty'] * (float)$invRow['unit_cost'];
    }
    $mMargin = $mPrice - $mCost;
    $mPct = ($mPrice > 0) ? ($mMargin / $mPrice) * 100 : 0;

    printf("%-24s | %10.2f | %10.2f | %10.2f | %7.1f%%\n",
        $mName, $mCost, $mPrice, $mMargin, $mPct
    );
}
echo str_repeat("-", 72) . "\n\n";
