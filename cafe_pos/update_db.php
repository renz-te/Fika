<?php
$pdoPos = new PDO('mysql:host=127.0.0.1;dbname=cafe_pos;charset=utf8mb4', 'root', '');
$pdoPos->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $pdoPos->exec('ALTER TABLE pos_inventory ADD COLUMN unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00;');
    echo "Added unit_cost.\n";
} catch (Exception $e) {
    echo "unit_cost might already exist: " . $e->getMessage() . "\n";
}

$pdoPos->exec('CREATE TABLE IF NOT EXISTS pos_inventory_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inventory_id INT NOT NULL,
    branch_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inventory_id) REFERENCES pos_inventory(id) ON DELETE CASCADE
)');
echo "Created pos_inventory_transactions.\n";
