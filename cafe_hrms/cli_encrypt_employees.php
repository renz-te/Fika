<?php
$config = require __DIR__ . '/config.php';
try {
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset={$config['db']['charset']}";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+08:00'"
    ]);
} catch (PDOException $ex) {
    die('Database connection failed: ' . htmlspecialchars($ex->getMessage()));
}

if (!defined('APP_KEY')) {
    die('Fatal Error: APP_KEY is not defined in config.php. System cannot safely initialize.');
}
require_once __DIR__ . '/functions.php';

if (php_sapi_name() !== 'cli' && php_sapi_name() !== 'cgi-fcgi') {
    die("This script can only be run from the command line.");
}

echo "Starting encryption of existing employee data...\n";

$stmt = $pdo->query("SELECT id, bank_account, tin, sss, philhealth, pagibig, government_ids FROM employees");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$updated = 0;
foreach ($employees as $emp) {
    // Check if they are already encrypted (they start with the base64 encoded format that has '::' after decode)
    $updateFields = [];
    $params = [];
    
    $fieldsToEncrypt = ['bank_account', 'tin', 'sss', 'philhealth', 'pagibig', 'government_ids'];
    foreach ($fieldsToEncrypt as $field) {
        $val = $emp[$field];
        if (!empty($val)) {
            $decoded = base64_decode($val, true);
            // If it's not valid base64 or doesn't have '::', we assume it's plain text and encrypt it.
            if ($decoded === false || strpos($decoded, '::') === false) {
                $updateFields[] = "{$field} = ?";
                $params[] = encryptData($val);
            }
        }
    }
    
    if (!empty($updateFields)) {
        $sql = "UPDATE employees SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $params[] = $emp['id'];
        $upd = $pdo->prepare($sql);
        $upd->execute($params);
        $updated++;
    }
}

echo "Encryption complete. Updated {$updated} employee records.\n";
