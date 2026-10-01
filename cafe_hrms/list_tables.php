<?php
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=login;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in login:\n" . implode("\n", $tables) . "\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
