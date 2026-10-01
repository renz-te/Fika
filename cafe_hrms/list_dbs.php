<?php
try {
    $pdo = new PDO("mysql:host=127.0.0.1;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $stmt = $pdo->query("SHOW DATABASES");
    $dbs = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Databases:\n" . implode("\n", $dbs) . "\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
