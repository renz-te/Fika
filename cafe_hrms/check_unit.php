<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=cafe_pos;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $pdo->query("SELECT id, item_name, unit FROM inventory WHERE item_name = 'Coffee Bean'");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
