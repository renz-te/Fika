<?php
require 'init.php';
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS branch_budgets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            branch_id INT NOT NULL,
            budget_month VARCHAR(7) NOT NULL COMMENT 'YYYY-MM format',
            allocated_labor_budget DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_branch_month (branch_id, budget_month),
            FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "branch_budgets table created successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
