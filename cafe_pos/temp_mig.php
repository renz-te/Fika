<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=cafe_pos;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $sql = "ALTER TABLE pos_inventory_transactions 
            ADD COLUMN remarks TEXT NULL, 
            ADD COLUMN status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending', 
            ADD COLUMN evidence_img VARCHAR(255) NULL";
    
    $pdo->exec($sql);
    echo "Migration successful.\n";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
