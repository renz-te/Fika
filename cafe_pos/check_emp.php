<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=hrms;charset=utf8mb4', 'root', '');
$stmt = $pdo->query('DESCRIBE employees');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
