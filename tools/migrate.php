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

// Ensure schema_migrations table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `filename` VARCHAR(255) NOT NULL UNIQUE,
  `applied_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

// Read already-applied migrations
$stmt = $pdo->query("SELECT filename FROM schema_migrations");
$applied = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
$appliedMap = array_flip($applied);

$pending = [];
foreach ($files as $file) {
    $base = basename($file);
    if (!isset($appliedMap[$base])) {
        $pending[] = $file;
    }
}

if (empty($pending)) {
    echo "0 migrations to run.\n";
    echo "All migrations completed successfully.\n";
    exit(0);
}

foreach ($pending as $file) {
    $base = basename($file);
    echo "Running migration: " . $base . "\n";
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

        // Record in schema_migrations
        $rec = $pdo->prepare("INSERT INTO schema_migrations (filename) VALUES (?)");
        $rec->execute([$base]);

        echo " -> Success\n";
    } catch (PDOException $e) {
        echo " -> Failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "All migrations completed successfully.\n";
