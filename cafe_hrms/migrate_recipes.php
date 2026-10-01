<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=cafe_pos;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    // 1. Group and sum duplicates, and keep the minimum ID
    $pdo->exec("
        CREATE TEMPORARY TABLE temp_recipes AS
        SELECT MIN(id) as id, product_id, modifier_id, inventory_id, SUM(quantity) as quantity
        FROM recipes
        GROUP BY product_id, modifier_id, inventory_id
    ");

    $pdo->exec("TRUNCATE TABLE recipes");

    $pdo->exec("
        INSERT INTO recipes (id, product_id, modifier_id, inventory_id, quantity)
        SELECT id, product_id, modifier_id, inventory_id, quantity FROM temp_recipes
    ");

    // 2. Add Unique Constraints
    $stmt = $pdo->query("SHOW INDEX FROM recipes WHERE Key_name = 'unique_product_inventory'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE recipes ADD UNIQUE KEY unique_product_inventory (product_id, inventory_id)");
        echo "Added unique_product_inventory constraint.\n";
    }

    $stmt = $pdo->query("SHOW INDEX FROM recipes WHERE Key_name = 'unique_modifier_inventory'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE recipes ADD UNIQUE KEY unique_modifier_inventory (modifier_id, inventory_id)");
        echo "Added unique_modifier_inventory constraint.\n";
    }

    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
