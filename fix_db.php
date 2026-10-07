<?php
require 'app/bootstrap.php';
global $pdo;
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM payroll_runs LIKE 'status'");
    print_r($stmt->fetch());
    
    // Also let's fix it!
    $pdo->exec("ALTER TABLE payroll_runs MODIFY COLUMN status ENUM('DRAFT', 'GENERATED', 'APPROVED', 'RELEASED') NOT NULL DEFAULT 'DRAFT'");
    echo "Fixed status enum.";
} catch (Exception $e) {
    echo $e->getMessage();
}
