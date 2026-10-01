<?php
$host = '127.0.0.1';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=cafe_pos;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in cafe_pos:\n" . implode("\n", $tables) . "\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
