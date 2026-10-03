<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=cafe_pos;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $pdo->exec("ALTER TABLE pos_inventory_transactions ADD COLUMN financial_impact DECIMAL(10,2) DEFAULT 0.00");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS pos_purchase_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        branch_id INT NOT NULL,
        inventory_id INT NOT NULL,
        requested_qty DECIMAL(10,2) NOT NULL,
        status ENUM('Pending', 'Approved', 'Ordered') DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    echo "Migration successful.\n";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
