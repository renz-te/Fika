<?php
/**
 * tools/demo_seed/phase_3.php
 *
 * Phase D3: Menu categories, products, and global flat modifiers.
 *
 * Populates:
 *   - categories: Cold, Hot, Pastries
 *   - products  : 17 demo products (10 Cold, 2 Hot, 5 Pastries)
 *   - modifiers : 14 global flat modifiers (Sizes, Add-ons, Sugar, Ice)
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

global $pdo, $DEMO_APPLY;

echo "====================================================================\n";
echo "Phase D3: Seeding Menu Products & Modifiers\n";
echo "====================================================================\n";

// 1. Categories
$categories = ['Cold', 'Hot', 'Pastries'];
echo "[1/3] Ensuring Categories exist (" . count($categories) . ")...\n";
foreach ($categories as $cat) {
    demo_insert($pdo, 'categories', ['name' => $cat], ['name' => $cat], 'phase_3');
}

// 2. Products Specification
$products = [
    // Cold Drinks (10)
    ['name' => 'Iced Latte',             'category' => 'Cold',     'price' => 99.00],
    ['name' => 'Iced Matcha Latte',      'category' => 'Cold',     'price' => 129.00],
    ['name' => 'Iced Americano',         'category' => 'Cold',     'price' => 79.00],
    ['name' => 'Iced Spanish Latte',     'category' => 'Cold',     'price' => 119.00],
    ['name' => 'Iced Strawberry Latte',  'category' => 'Cold',     'price' => 129.00],
    ['name' => 'Iced Mocha',             'category' => 'Cold',     'price' => 119.00],
    ['name' => 'Iced Caramel Macchiato', 'category' => 'Cold',     'price' => 129.00],
    ['name' => 'Iced Frappe',            'category' => 'Cold',     'price' => 129.00], // Blended coffee frappe with whipped cream
    ['name' => 'Milktea',                'category' => 'Cold',     'price' => 89.00],
    ['name' => 'Milktea Matcha',         'category' => 'Cold',     'price' => 109.00], // Black-tea milk tea with matcha

    // Hot Drinks (2)
    ['name' => 'Latte',                  'category' => 'Hot',      'price' => 89.00],
    ['name' => 'Caramel Macchiato',      'category' => 'Hot',      'price' => 99.00],

    // Pastries (5)
    ['name' => 'Croissant',              'category' => 'Pastries', 'price' => 85.00],
    ['name' => 'Blueberry Pie',          'category' => 'Pastries', 'price' => 95.00],
    ['name' => 'Lemon Bread',            'category' => 'Pastries', 'price' => 85.00],
    ['name' => 'Chocolate Cake',         'category' => 'Pastries', 'price' => 115.00],
    ['name' => 'Strawberry Cake',        'category' => 'Pastries', 'price' => 125.00],
];

echo "\n[2/3] Processing Products (" . count($products) . " items)...\n";
foreach ($products as $prod) {
    $price = (float)$prod['price'];
    $priceCents = (int)round($price * 100);

    $row = [
        'name'        => $prod['name'],
        'category'    => $prod['category'],
        'price'       => $price,
        'price_cents' => $priceCents,
        'image_path'  => null,
        'image_alt'   => $prod['name'],
        'is_active'   => 1,
    ];
    $naturalKey = [
        'name'     => $prod['name'],
        'category' => $prod['category'],
    ];

    demo_insert($pdo, 'products', $row, $naturalKey, 'phase_3');
}

// 3. Modifiers Specification (Flat & Global)
$modifiers = [
    // Cold Sizes
    ['name' => 'Size: M (Cold)',      'price' => 0.00],
    ['name' => 'Size: L (Cold)',      'price' => 20.00],

    // Hot Sizes
    ['name' => 'Size: M (Hot)',       'price' => 0.00],
    ['name' => 'Size: L (Hot)',       'price' => 20.00],

    // Add-ons
    ['name' => 'Extra Espresso Shot', 'price' => 30.00],
    ['name' => 'Pearls',              'price' => 15.00],
    ['name' => 'Whipped Cream',       'price' => 15.00],
    ['name' => 'Caramel Drizzle',     'price' => 15.00],

    // Sugar / Ice Adjustments
    ['name' => 'Less Sugar',          'price' => 0.00],
    ['name' => 'No Sugar',            'price' => 0.00],
    ['name' => 'Extra Sugar',         'price' => 0.00],
    ['name' => 'Less Ice',            'price' => 0.00],
    ['name' => 'No Ice',              'price' => 0.00],
    ['name' => 'Extra Ice',           'price' => 0.00],
];

echo "\n[3/3] Processing Modifiers (" . count($modifiers) . " items)...\n";
foreach ($modifiers as $mod) {
    $row = [
        'name'      => $mod['name'],
        'price'     => (float)$mod['price'],
        'is_active' => 1,
    ];
    $naturalKey = ['name' => $mod['name']];

    demo_insert($pdo, 'modifiers', $row, $naturalKey, 'phase_3');
}

// 4. Output Verification Tables
echo "\n====================================================================\n";
echo "VERIFICATION: Products & Modifiers Tables\n";
echo "====================================================================\n\n";

echo "--- 1. SEEDED PRODUCTS TABLE (" . count($products) . " items) ---\n";
printf("%-4s | %-26s | %-12s | %-10s | %-12s\n", "No.", "Product Name", "Category", "Price", "Price Cents");
echo str_repeat("-", 72) . "\n";
foreach ($products as $i => $p) {
    printf("%-4d | %-26s | %-12s | %10.2f | %12d\n", $i + 1, $p['name'], $p['category'], $p['price'], round($p['price'] * 100));
}

echo "\n--- 2. SEEDED MODIFIERS TABLE (" . count($modifiers) . " items) ---\n";
printf("%-4s | %-26s | %-16s\n", "No.", "Modifier Name", "Price Adjustment");
echo str_repeat("-", 52) . "\n";
foreach ($modifiers as $i => $m) {
    $adjText = $m['price'] > 0 ? sprintf("+%0.2f", $m['price']) : sprintf("%0.2f", $m['price']);
    printf("%-4d | %-26s | %16s\n", $i + 1, $m['name'], $adjText);
}

// 5. Query Live Menu List (fika_pos_menu.php equivalent query)
echo "\n--- 3. LIVE MENU LIST QUERY OUTPUT ---\n";
$menuQuery = "
    SELECT p.id, p.name, p.category, p.price, p.price_cents, p.is_active
    FROM products p
    WHERE p.deleted_at IS NULL
    ORDER BY FIELD(p.category, 'Cold', 'Hot', 'Pastries', 'Espresso & Coffee', 'Tea & Non-Coffee', 'Pastries & Bakery'), p.name ASC
";
$menuStmt = $pdo->query($menuQuery);
$liveMenuRows = $menuStmt->fetchAll(PDO::FETCH_ASSOC);

printf("%-6s | %-26s | %-18s | %-10s | %-8s\n", "ID", "Name", "Category", "Price", "Active");
echo str_repeat("-", 76) . "\n";
foreach ($liveMenuRows as $row) {
    printf("%-6d | %-26s | %-18s | %10.2f | %-8s\n", 
        $row['id'], 
        $row['name'], 
        $row['category'], 
        $row['price'], 
        $row['is_active'] ? 'YES' : 'NO'
    );
}

// 6. Category Split Audit
echo "\n--- 4. CATEGORY BREAKDOWN AUDIT ---\n";
$coldCount = 0;
$hotCount = 0;
$pastryCount = 0;
foreach ($products as $p) {
    if ($p['category'] === 'Cold') $coldCount++;
    elseif ($p['category'] === 'Hot') $hotCount++;
    elseif ($p['category'] === 'Pastries') $pastryCount++;
}
echo "  - Cold Products     : {$coldCount} (Expected: 10)\n";
echo "  - Hot Products      : {$hotCount} (Expected: 2)\n";
echo "  - Pastries Products : {$pastryCount} (Expected: 5)\n";
echo "  - Total Menu Items  : " . count($products) . " (Expected: 17)\n";

$allIcedCold = true;
foreach ($products as $p) {
    if (strpos($p['name'], 'Iced') === 0 && $p['category'] !== 'Cold') {
        $allIcedCold = false;
    }
}
echo "  - All 'Iced' products are Cold: " . ($allIcedCold ? "VERIFIED (TRUE)" : "FAILED") . "\n";
echo "\n";
