<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=cafe_pos;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $pdo->beginTransaction();

    // 1. Create inventory_stock table
    $pdo->exec("CREATE TABLE IF NOT EXISTS inventory_stock (
        inventory_id INT NOT NULL,
        branch_id INT NOT NULL,
        stock DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        low_stock_level DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        PRIMARY KEY (inventory_id, branch_id)
    )");

    // 2. Migrate existing stock data from inventory to inventory_stock (assuming branch_id = 1)
    // Check if column exists to avoid errors on multiple runs
    $stmt = $pdo->query("SHOW COLUMNS FROM inventory LIKE 'stock'");
    if ($stmt->fetch()) {
        $pdo->exec("INSERT INTO inventory_stock (inventory_id, branch_id, stock, low_stock_level)
                    SELECT id, 1, stock, low_stock_level FROM inventory
                    ON DUPLICATE KEY UPDATE stock = VALUES(stock), low_stock_level = VALUES(low_stock_level)");
        
        // 3. Drop stock and low_stock_level from inventory
        $pdo->exec("ALTER TABLE inventory DROP COLUMN stock, DROP COLUMN low_stock_level");
    }

    // 4. Add branch_id to orders
    $stmt = $pdo->query("SHOW COLUMNS FROM orders LIKE 'branch_id'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN branch_id INT NOT NULL DEFAULT 1 AFTER id");
    }

    // (Optional) add branch_id to pos_sessions just in case
    $stmt = $pdo->query("SHOW COLUMNS FROM pos_sessions LIKE 'branch_id'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE pos_sessions ADD COLUMN branch_id INT NOT NULL DEFAULT 1 AFTER id");
    }

    $pdo->commit();
    echo "Database migration completed successfully.\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Migration failed: " . $e->getMessage() . "\n";
}
