<?php
try {
    $pdo = new PDO("mysql:host=127.0.0.1;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    // Create DB
    $pdo->exec("CREATE DATABASE IF NOT EXISTS hrms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database hrms created.\n";
    
    $pdo->exec("USE hrms");
    
    // Import SQL
    $sql = file_get_contents(__DIR__ . '/db/hrms.sql');
    if ($sql) {
        $pdo->exec($sql);
        echo "Imported hrms.sql.\n";
    } else {
        echo "Could not read hrms.sql\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
