<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=cafe_pos;charset=utf8mb4', 'root', '');
$stmt = $pdo->query("SHOW COLUMNS FROM inventory_stock");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
