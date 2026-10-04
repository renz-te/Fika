<?php
require_once __DIR__ . '/../app/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

echo "Starting migrations...\n";

$migrationsDir = __DIR__ . '/../migrations';
$files = glob($migrationsDir . '/*.sql');
sort($files);

global $pdo;

foreach ($files as $file) {
    echo "Running migration: " . basename($file) . "\n";
    $sql = file_get_contents($file);
    
    try {
        $pdo->exec($sql);
        echo " -> Success\n";
    } catch (PDOException $e) {
        echo " -> Failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "All migrations completed successfully.\n";
