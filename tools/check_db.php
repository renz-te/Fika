<?php
require_once __DIR__ . '/../app/bootstrap.php';
global $pdo;

echo "--- Migrations in Folder ---\n";
$files = glob(__DIR__ . '/../migrations/*.sql');
foreach($files as $f) {
    echo basename($f) . "\n";
}

echo "\n--- Tables Schema ---\n";
$tables = ['employees', 'attendance', 'leaves', 'payroll_runs', 'payroll_items'];
foreach($tables as $t) {
    echo "\n=== Table: $t ===\n";
    try {
        $stmt = $pdo->query("DESCRIBE $t");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($cols as $col) {
            echo "{$col['Field']} - {$col['Type']} (Null: {$col['Null']}, Default: {$col['Default']})\n";
        }
    } catch (Exception $e) {
        echo "Table not found or error: " . $e->getMessage() . "\n";
    }
}
