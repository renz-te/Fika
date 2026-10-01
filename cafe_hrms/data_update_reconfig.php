<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=cafe_pos;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $pdo->beginTransaction();

    // 1. Delete Order Type modifier group
    // First, delete recipes associated with these modifiers
    $pdo->exec("DELETE FROM recipes WHERE modifier_id IN (SELECT id FROM modifiers WHERE modifier_group = 'Order Type')");
    $pdo->exec("DELETE FROM modifiers WHERE modifier_group = 'Order Type'");

    // 2. Rename Cups
    $pdo->exec("UPDATE inventory SET item_name = 'Tall Paper Cup' WHERE item_name = 'Tall Cup'");
    $pdo->exec("UPDATE inventory SET item_name = 'Venti Paper Cup' WHERE item_name = 'Venti Cup'");

    // 3. Insert Plastic Cups if they don't exist
    $stmt = $pdo->prepare("INSERT IGNORE INTO inventory (item_name, unit, unit_cost, stock, low_stock_level) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['Tall Plastic Cup', 'pcs', 5.00, 100, 20]);
    $stmt->execute(['Venti Plastic Cup', 'pcs', 6.00, 100, 20]);

    // Fetch all inventory IDs for quick mapping
    $invStmt = $pdo->query("SELECT id, item_name FROM inventory");
    $inv = [];
    while ($row = $invStmt->fetch(PDO::FETCH_ASSOC)) {
        $inv[$row['item_name']] = $row['id'];
    }

    // 4. Products: Rename Latte to Hot Latte, create Iced Latte
    $pdo->exec("UPDATE products SET name = 'Hot Latte', category = 'Hot Beverage' WHERE name = 'Latte'");
    
    // Fetch Hot Latte ID
    $hotLatteStmt = $pdo->query("SELECT id, price FROM products WHERE name = 'Hot Latte' LIMIT 1");
    $hotLatte = $hotLatteStmt->fetch(PDO::FETCH_ASSOC);
    $hotLatteId = $hotLatte['id'];
    
    // Create Iced Latte
    $pdo->exec("INSERT INTO products (name, category, price, allowed_modifier_groups) VALUES ('Iced Latte', 'Cold Beverage', {$hotLatte['price']}, '') ON DUPLICATE KEY UPDATE category='Cold Beverage'");
    
    $icedLatteStmt = $pdo->query("SELECT id FROM products WHERE name = 'Iced Latte' LIMIT 1");
    $icedLatteId = $icedLatteStmt->fetch(PDO::FETCH_ASSOC)['id'];

    // 5. Clean Base Recipes for Hot Latte and Iced Latte (Remove ALL cups)
    $cupIds = implode(',', array_filter([
        $inv['Tall Paper Cup'] ?? null,
        $inv['Venti Paper Cup'] ?? null,
        $inv['Tall Plastic Cup'] ?? null,
        $inv['Venti Plastic Cup'] ?? null
    ]));
    if ($cupIds) {
        $pdo->exec("DELETE FROM recipes WHERE product_id IN ($hotLatteId, $icedLatteId) AND inventory_id IN ($cupIds)");
    }

    // Ensure Iced Latte has the Coffee and Milk from Hot Latte
    // (Clear existing Iced Latte recipes first to avoid duplicates)
    $pdo->exec("DELETE FROM recipes WHERE product_id = $icedLatteId");
    $pdo->exec("INSERT INTO recipes (product_id, inventory_id, quantity) SELECT $icedLatteId, inventory_id, quantity FROM recipes WHERE product_id = $hotLatteId");

    // 6. Create Modifiers
    // Hot Drink Size & Style
    $hotGroup = 'Hot Drink Size & Style';
    $pdo->exec("DELETE FROM recipes WHERE modifier_id IN (SELECT id FROM modifiers WHERE modifier_group = '$hotGroup')");
    $pdo->exec("DELETE FROM modifiers WHERE modifier_group = '$hotGroup'");

    $stmtMod = $pdo->prepare("INSERT INTO modifiers (name, modifier_group, price_adjustment) VALUES (?, ?, ?)");
    
    $stmtMod->execute(['Tall - Dine-In (Mug)', $hotGroup, 0.00]);
    $stmtMod->execute(['Tall - Takeaway', $hotGroup, 0.00]);
    $tallTakeawayId = $pdo->lastInsertId();
    
    $stmtMod->execute(['Venti - Dine-In (Mug)', $hotGroup, 40.00]);
    $ventiDineInId = $pdo->lastInsertId();

    $stmtMod->execute(['Venti - Takeaway', $hotGroup, 40.00]);
    $ventiTakeawayId = $pdo->lastInsertId();

    // Cold Drink Size
    $coldGroup = 'Cold Drink Size';
    $pdo->exec("DELETE FROM recipes WHERE modifier_id IN (SELECT id FROM modifiers WHERE modifier_group = '$coldGroup')");
    $pdo->exec("DELETE FROM modifiers WHERE modifier_group = '$coldGroup'");

    $stmtMod->execute(['Tall (16oz)', $coldGroup, 0.00]);
    $coldTallId = $pdo->lastInsertId();

    $stmtMod->execute(['Venti (24oz)', $coldGroup, 40.00]);
    $coldVentiId = $pdo->lastInsertId();

    // 7. Map Modifier Recipes
    $stmtRecipe = $pdo->prepare("INSERT INTO recipes (modifier_id, inventory_id, quantity) VALUES (?, ?, ?)");
    
    // Tall Takeaway Hot = 1x Tall Paper Cup
    if (isset($inv['Tall Paper Cup'])) $stmtRecipe->execute([$tallTakeawayId, $inv['Tall Paper Cup'], 1]);

    // Venti Dine-In Hot = +10g Coffee Bean
    if (isset($inv['Coffee Bean'])) $stmtRecipe->execute([$ventiDineInId, $inv['Coffee Bean'], 10]);

    // Venti Takeaway Hot = +10g Coffee Bean, 1x Venti Paper Cup
    if (isset($inv['Coffee Bean'])) $stmtRecipe->execute([$ventiTakeawayId, $inv['Coffee Bean'], 10]);
    if (isset($inv['Venti Paper Cup'])) $stmtRecipe->execute([$ventiTakeawayId, $inv['Venti Paper Cup'], 1]);

    // Cold Tall = 1x Tall Plastic Cup
    if (isset($inv['Tall Plastic Cup'])) $stmtRecipe->execute([$coldTallId, $inv['Tall Plastic Cup'], 1]);

    // Cold Venti = +10g Coffee Bean, 1x Venti Plastic Cup
    if (isset($inv['Coffee Bean'])) $stmtRecipe->execute([$coldVentiId, $inv['Coffee Bean'], 10]);
    if (isset($inv['Venti Plastic Cup'])) $stmtRecipe->execute([$coldVentiId, $inv['Venti Plastic Cup'], 1]);

    // 8. Map to Products
    $pdo->exec("UPDATE products SET allowed_modifier_groups = 'Hot Drink Size & Style,Milk,Add-ons' WHERE category = 'Hot Beverage'");
    $pdo->exec("UPDATE products SET allowed_modifier_groups = 'Cold Drink Size,Milk,Add-ons' WHERE category IN ('Cold Beverage', 'Specials')");
    $pdo->exec("UPDATE products SET allowed_modifier_groups = '' WHERE category = 'Pastries'");

    $pdo->commit();
    echo "Database reconfiguration successful!\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
