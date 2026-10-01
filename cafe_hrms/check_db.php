<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=cafe_pos;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $pdo->query("SELECT id, item_name FROM inventory");
$inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

print_r($inventory);

$stmt = $pdo->query("SELECT id, name, category, allowed_modifier_groups FROM products");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

print_r($products);
