-- ==========================================================
-- Migration: 026_finance_budgeting.sql
-- Description: Financial expenses, expense categories, monthly branch budgets,
--              dynamic expense approval threshold, and finance permissions.
-- ==========================================================

-- 1. Create expense_categories table
CREATE TABLE IF NOT EXISTS `expense_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `expense_categories` (`name`) VALUES 
('Rent'), 
('Utilities'), 
('Supplies'), 
('Maintenance'), 
('Marketing'), 
('Miscellaneous')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 2. Create expenses table
CREATE TABLE IF NOT EXISTS `expenses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `amount_cents` BIGINT NOT NULL,
    `expense_date` DATE NOT NULL,
    `payee` VARCHAR(255) NOT NULL,
    `notes` TEXT NULL,
    `receipt_path` VARCHAR(255) NULL,
    `status` ENUM('DRAFT', 'APPROVED', 'VOIDED') NOT NULL DEFAULT 'DRAFT',
    `created_by` INT NOT NULL,
    `approved_by` INT NULL DEFAULT NULL,
    `void_reason` TEXT NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_exp_branch` (`branch_id`),
    INDEX `idx_exp_category` (`category_id`),
    INDEX `idx_exp_status` (`status`),
    INDEX `idx_exp_date` (`expense_date`),
    CONSTRAINT `fk_exp_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_exp_category` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_exp_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create or update branch_budgets table
CREATE TABLE IF NOT EXISTS `branch_budgets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL,
    `budget_month` VARCHAR(7) NOT NULL,
    `allocated_amount_cents` BIGINT NOT NULL DEFAULT 0,
    `created_by` INT NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_branch_budget_month` (`branch_id`, `budget_month`),
    CONSTRAINT `fk_bb_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure allocated_amount_cents exists on branch_budgets
SET @colExists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'branch_budgets' 
      AND COLUMN_NAME = 'allocated_amount_cents'
);
SET @stmt = IF(@colExists = 0, 
    'ALTER TABLE `branch_budgets` ADD COLUMN `allocated_amount_cents` BIGINT NOT NULL DEFAULT 0 AFTER `budget_month`', 
    'SELECT 1'
);
PREPARE ddlStmt FROM @stmt;
EXECUTE ddlStmt;
DEALLOCATE PREPARE ddlStmt;

-- Ensure created_by exists on branch_budgets
SET @colExists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'branch_budgets' 
      AND COLUMN_NAME = 'created_by'
);
SET @stmt = IF(@colExists = 0, 
    'ALTER TABLE `branch_budgets` ADD COLUMN `created_by` INT NULL DEFAULT NULL AFTER `allocated_amount_cents`', 
    'SELECT 1'
);
PREPARE ddlStmt FROM @stmt;
EXECUTE ddlStmt;
DEALLOCATE PREPARE ddlStmt;

-- 4. Dynamic Approval Threshold in settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES 
('finance_expense_approval_threshold_cents', '100000')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- 5. Seed Permissions
CREATE TABLE IF NOT EXISTS `permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`name`, `description`) VALUES 
('finance.view', 'View financial budgets and expenses'),
('finance.expense.create', 'Create operating expenses'),
('finance.expense.approve', 'Approve or void operating expenses'),
('finance.budget.manage', 'Set and manage branch monthly budgets')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- 6. Assign Permissions to Roles
-- ADMIN (Role 1): all 4
INSERT INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(1, 'finance.view'),
(1, 'finance.expense.create'),
(1, 'finance.expense.approve'),
(1, 'finance.budget.manage')
ON DUPLICATE KEY UPDATE `permission_name` = VALUES(`permission_name`);

-- GA (Role 3): all 4
INSERT INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(3, 'finance.view'),
(3, 'finance.expense.create'),
(3, 'finance.expense.approve'),
(3, 'finance.budget.manage')
ON DUPLICATE KEY UPDATE `permission_name` = VALUES(`permission_name`);

-- BA (Role 4): view, expense.create, expense.approve
INSERT INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(4, 'finance.view'),
(4, 'finance.expense.create'),
(4, 'finance.expense.approve')
ON DUPLICATE KEY UPDATE `permission_name` = VALUES(`permission_name`);

-- BM (Role 6): view, expense.create
INSERT INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(6, 'finance.view'),
(6, 'finance.expense.create')
ON DUPLICATE KEY UPDATE `permission_name` = VALUES(`permission_name`);
