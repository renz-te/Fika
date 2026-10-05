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
        // Handle DELIMITER commands for procedures
        if (strpos($sql, 'DELIMITER') !== false) {
            $statements = [];
            $currentStmt = '';
            $delimiter = ';';
            
            $lines = explode("\n", $sql);
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (preg_match('/^DELIMITER\s+(.+)$/i', $trimmed, $matches)) {
                    $delimiter = trim($matches[1]);
                    continue;
                }
                $currentStmt .= $line . "\n";
                if (substr(rtrim($currentStmt), -strlen($delimiter)) === $delimiter) {
                    // Remove delimiter from the end
                    $stmtToRun = substr(rtrim($currentStmt), 0, -strlen($delimiter));
                    if (trim($stmtToRun) !== '') {
                        $pdo->exec($stmtToRun);
                    }
                    $currentStmt = '';
                }
            }
            if (trim($currentStmt) !== '') {
                $pdo->exec($currentStmt);
            }
        } else {
            $pdo->exec($sql);
        }
        echo " -> Success\n";
    } catch (PDOException $e) {
        echo " -> Failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "All migrations completed successfully.\n";
