<?php
$posPdo = new PDO("mysql:host=127.0.0.1;dbname=cafe_pos;charset=utf8mb4", "root", "");
$posPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$posPdo->exec("
CREATE TABLE IF NOT EXISTS payment_reconciliations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    transaction_id VARCHAR(100) NOT NULL,
    fee_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    provider VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);
");
echo "Success!";
