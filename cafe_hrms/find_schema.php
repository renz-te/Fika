<?php
try {
    $pdo = new PDO("mysql:host=127.0.0.1;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $stmt = $pdo->query("SELECT TABLE_SCHEMA FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'employees'");
    $schemas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Schemas with 'employees' table:\n" . implode("\n", $schemas) . "\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
