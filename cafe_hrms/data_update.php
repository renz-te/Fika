<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=cafe_pos;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    // Step 3: Data Corrections
    $pdo->exec("UPDATE inventory SET unit_cost = 0.80 WHERE item_name = 'Coffee Bean'");
    $pdo->exec("UPDATE inventory SET unit_cost = 80.00 WHERE item_name = 'Milk'");

    // Update Latte recipe
    // Find Latte product_id
    $stmt = $pdo->query("SELECT id FROM products WHERE name = 'Latte' LIMIT 1");
    $latte = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($latte) {
        $latteId = $latte['id'];
        
        // Find Milk inventory_id
        $stmt = $pdo->query("SELECT id FROM inventory WHERE item_name = 'Milk' LIMIT 1");
        $milk = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($milk) {
            $pdo->exec("UPDATE recipes SET quantity = 0.2 WHERE product_id = $latteId AND inventory_id = " . $milk['id']);
        }
        
        // Remove Tall Cup from Latte base recipe
        $stmt = $pdo->query("SELECT id FROM inventory WHERE item_name = 'Tall Cup' LIMIT 1");
        $cup = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($cup) {
            $pdo->exec("DELETE FROM recipes WHERE product_id = $latteId AND inventory_id = " . $cup['id']);
        }
    }

    // Step 4: Order Type Modifiers
    // Insert Dine-In
    $stmt = $pdo->prepare("INSERT INTO modifiers (name, modifier_group, price_adjustment) VALUES (?, ?, ?)");
    $stmt->execute(['Dine-In', 'Order Type', 0.00]);
    
    // Insert Takeaway
    $stmt = $pdo->prepare("INSERT INTO modifiers (name, modifier_group, price_adjustment) VALUES (?, ?, ?)");
    $stmt->execute(['Takeaway', 'Order Type', 0.00]);
    $takeawayId = $pdo->lastInsertId();

    // Map Tall Cup to Takeaway modifier
    if (isset($cup)) {
        $stmt = $pdo->prepare("INSERT INTO recipes (modifier_id, inventory_id, quantity) VALUES (?, ?, ?)");
        $stmt->execute([$takeawayId, $cup['id'], 1]);
    }

    // Update products to allow "Order Type" modifier group
    // Let's add 'Order Type' to allowed_modifier_groups for all beverages
    $stmt = $pdo->query("SELECT id, allowed_modifier_groups FROM products WHERE category IN ('Hot Beverage', 'Cold Beverage', 'Specials')");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $updateStmt = $pdo->prepare("UPDATE products SET allowed_modifier_groups = ? WHERE id = ?");
    foreach ($products as $p) {
        $allowed = $p['allowed_modifier_groups'] ? explode(',', $p['allowed_modifier_groups']) : [];
        if (!in_array('Order Type', $allowed)) {
            $allowed[] = 'Order Type';
            $updateStmt->execute([implode(',', $allowed), $p['id']]);
        }
    }

    echo "Data updates successful!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
