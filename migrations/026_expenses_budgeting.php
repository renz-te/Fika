<?php
// Migration: Setup expense_categories and expenses tables for legacy HRMS
$config = require __DIR__ . '/../cafe_hrms/config.php';

try {
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset={$config['db']['charset']}";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // 1. Create expense_categories table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `expense_categories` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL UNIQUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        INSERT INTO `expense_categories` (`name`) VALUES 
        ('Rent'), 
        ('Utilities'), 
        ('Supplies'), 
        ('Maintenance'), 
        ('Marketing'), 
        ('Miscellaneous')
        ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
    ");

    // 2. Create expenses table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `expenses` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `branch_id` INT NOT NULL,
            `category_id` INT NULL,
            `category` VARCHAR(100) NULL,
            `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `amount_cents` BIGINT NOT NULL DEFAULT 0,
            `expense_date` DATE NOT NULL,
            `payee` VARCHAR(255) NOT NULL,
            `notes` TEXT NULL,
            `receipt_path` VARCHAR(255) NULL,
            `status` VARCHAR(50) NOT NULL DEFAULT 'Approved',
            `created_by` INT NULL,
            `approved_by` INT NULL DEFAULT NULL,
            `void_reason` TEXT NULL DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_exp_branch` (`branch_id`),
            INDEX `idx_exp_category` (`category_id`),
            INDEX `idx_exp_status` (`status`),
            INDEX `idx_exp_date` (`expense_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Sync amount and amount_cents if either was 0
    $pdo->exec("UPDATE expenses SET amount = (amount_cents / 100.0) WHERE (amount = 0 OR amount IS NULL) AND amount_cents > 0");
    $pdo->exec("UPDATE expenses SET amount_cents = ROUND(amount * 100) WHERE amount_cents = 0 AND amount > 0");

    // Ensure sample data exists if empty
    $count = $pdo->query("SELECT COUNT(*) FROM expenses")->fetchColumn();
    if ($count == 0) {
        $curMonth = date('Y-m');
        $pdo->exec("
            INSERT INTO `expenses` (`branch_id`, `category_id`, `category`, `amount`, `amount_cents`, `expense_date`, `payee`, `notes`, `status`, `created_by`) VALUES
            (1, 1, 'Rent', 35000.00, 3500000, '{$curMonth}-01', 'Commercial Realty Corp', 'Monthly café store lease', 'Approved', 1),
            (1, 2, 'Utilities', 12450.50, 1245050, '{$curMonth}-04', 'Manila Electric Co.', 'Monthly electricity bill', 'Approved', 1),
            (1, 2, 'Utilities', 3200.00, 320000, '{$curMonth}-06', 'Maynilad Water Services', 'Water consumption', 'Approved', 1),
            (1, 3, 'Supplies', 14800.00, 1480000, '{$curMonth}-08', 'Barista Supply Co.', 'Coffee filters, syrups, and napkins', 'Approved', 1),
            (1, 4, 'Maintenance', 4500.00, 450000, '{$curMonth}-11', 'Espresso Tech Pro', 'Espresso machine group head gasket servicing', 'Approved', 1),
            (1, 5, 'Marketing', 5000.00, 500000, '{$curMonth}-14', 'Meta Platforms Ads', 'Social media promo campaign for seasonal brews', 'Approved', 1),
            (1, 6, 'Miscellaneous', 2150.00, 215000, '{$curMonth}-18', 'Office Warehouse', 'Receipt paper and register rolls', 'Approved', 1)
        ");
    }

    // Ensure current month budget exists in branch_budgets for branch 1 if missing
    $curMonth = date('Y-m');
    $bStmt = $pdo->prepare("SELECT id FROM branch_budgets WHERE branch_id = 1 AND budget_month = ?");
    $bStmt->execute([$curMonth]);
    if (!$bStmt->fetchColumn()) {
        $pdo->prepare("INSERT INTO branch_budgets (branch_id, budget_month, allocated_labor_budget) VALUES (1, ?, 120000.00)")
            ->execute([$curMonth]);
    }

    echo "Migration 026 executed successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
