<?php
// Migration: Add multi-step approval workflow columns to payroll and payroll_runs
$config = require __DIR__ . '/config.php';

try {
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset={$config['db']['charset']}";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    echo "Running migration on database {$config['db']['name']}...\n";

    // 1. Update status on payroll table to support Draft, Pending Approval, Approved, Released, Rejected
    $pdo->exec("ALTER TABLE `payroll` MODIFY COLUMN `status` ENUM('Draft', 'Pending Approval', 'Approved', 'Released', 'Rejected') NOT NULL DEFAULT 'Draft'");

    // 2. Add workflow tracking columns to payroll if they don't exist
    $cols = $pdo->query("SHOW COLUMNS FROM `payroll`")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('submitted_by', $cols)) {
        $pdo->exec("ALTER TABLE `payroll` ADD COLUMN `submitted_by` INT NULL AFTER `run_id`");
        echo "Added submitted_by to payroll\n";
    }
    if (!in_array('approved_by', $cols)) {
        $pdo->exec("ALTER TABLE `payroll` ADD COLUMN `approved_by` INT NULL AFTER `submitted_by`");
        echo "Added approved_by to payroll\n";
    }
    if (!in_array('released_by', $cols)) {
        $pdo->exec("ALTER TABLE `payroll` ADD COLUMN `released_by` INT NULL AFTER `approved_by`");
        echo "Added released_by to payroll\n";
    }
    if (!in_array('rejection_reason', $cols)) {
        $pdo->exec("ALTER TABLE `payroll` ADD COLUMN `rejection_reason` TEXT NULL AFTER `released_by`");
        echo "Added rejection_reason to payroll\n";
    }

    // 3. Update status on payroll_runs table
    $runCols = $pdo->query("SHOW COLUMNS FROM `payroll_runs`")->fetchAll(PDO::FETCH_COLUMN);
    $pdo->exec("ALTER TABLE `payroll_runs` MODIFY COLUMN `status` ENUM('Draft', 'Pending Approval', 'Approved', 'Released', 'Rejected') NOT NULL DEFAULT 'Draft'");

    if (!in_array('submitted_by', $runCols)) {
        $pdo->exec("ALTER TABLE `payroll_runs` ADD COLUMN `submitted_by` INT NULL AFTER `created_by`");
        echo "Added submitted_by to payroll_runs\n";
    }
    if (!in_array('rejection_reason', $runCols)) {
        $pdo->exec("ALTER TABLE `payroll_runs` ADD COLUMN `rejection_reason` TEXT NULL AFTER `released_by`");
        echo "Added rejection_reason to payroll_runs\n";
    }

    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
