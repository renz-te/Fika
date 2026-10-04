<?php
require_once __DIR__ . '/../app/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

echo "Starting data encryption migration...\n";

global $pdo;

// Identify fields that should be encrypted
$fieldsToEncrypt = ['bank_account', 'tin', 'sss', 'philhealth', 'pagibig'];

try {
    $pdo->beginTransaction();
    
    // Fetch all employees
    $stmt = $pdo->query("SELECT id, bank_account, tin, sss, philhealth, pagibig FROM employees");
    $employees = $stmt->fetchAll();
    
    $updateStmt = $pdo->prepare("
        UPDATE employees 
        SET bank_account = ?, tin = ?, sss = ?, philhealth = ?, pagibig = ? 
        WHERE id = ?
    ");
    
    $encryptedCount = 0;
    foreach ($employees as $emp) {
        $updates = [];
        $needsUpdate = false;
        
        foreach ($fieldsToEncrypt as $field) {
            $val = $emp[$field];
            // Only encrypt if it is not null, not empty, and not already seemingly encrypted (base64 with minimum length)
            // A crude heuristic to check if it's already an AES-256-GCM payload: 
            // - It will be base64 encoded
            // - After decode, length must be > 16 (tag) + 12 (iv)
            if (!empty($val)) {
                $decoded = base64_decode($val, true);
                if ($decoded !== false && strlen($decoded) > 28 && Crypto::decrypt($val) !== null) {
                    // Already properly encrypted
                    $updates[] = $val;
                } else {
                    // Not encrypted or malformed, encrypt it!
                    $updates[] = Crypto::encrypt($val);
                    $needsUpdate = true;
                }
            } else {
                $updates[] = $val; // Null or empty stays null/empty
            }
        }
        
        if ($needsUpdate) {
            $updates[] = $emp['id'];
            $updateStmt->execute($updates);
            $encryptedCount++;
        }
    }
    
    $pdo->commit();
    echo "Successfully encrypted sensitive fields for {$encryptedCount} employee(s).\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Encryption migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
