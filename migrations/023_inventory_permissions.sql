-- ==========================================================
-- Migration: 023_inventory_permissions.sql
-- Description: Inventory permissions, adjustments table, and settings
-- ==========================================================

-- 1. Ensure permissions table exists
CREATE TABLE IF NOT EXISTS `permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`name`, `description`) VALUES 
('inventory.view', 'View inventory items, stock, and ledger'),
('inventory.adjust', 'Submit manual inventory stock adjustments'),
('inventory.approve', 'Approve or reject manual inventory stock adjustments')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- 2. Seed role_permissions
-- ADMIN (Role 1): gets all
INSERT INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(1, 'inventory.view'),
(1, 'inventory.adjust'),
(1, 'inventory.approve')
ON DUPLICATE KEY UPDATE `permission_name` = VALUES(`permission_name`);

-- BM (Role 6): gets view and adjust
INSERT INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(6, 'inventory.view'),
(6, 'inventory.adjust')
ON DUPLICATE KEY UPDATE `permission_name` = VALUES(`permission_name`);

-- BA (Role 4): gets view and approve
INSERT INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(4, 'inventory.view'),
(4, 'inventory.approve')
ON DUPLICATE KEY UPDATE `permission_name` = VALUES(`permission_name`);

-- 3. Add low_stock_threshold to inventory_stock if missing
SET @colExists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'inventory_stock' 
      AND COLUMN_NAME = 'low_stock_threshold'
);

SET @stmt = IF(@colExists = 0, 
    'ALTER TABLE `inventory_stock` ADD COLUMN `low_stock_threshold` DECIMAL(10,2) NOT NULL DEFAULT 10.00 AFTER `current_stock`', 
    'SELECT 1'
);
PREPARE ddlStmt FROM @stmt;
EXECUTE ddlStmt;
DEALLOCATE PREPARE ddlStmt;

-- 4. Create inventory_adjustments table for Maker-Checker workflow
CREATE TABLE IF NOT EXISTS `inventory_adjustments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL,
    `inventory_id` INT NOT NULL,
    `quantity` DECIMAL(10,2) NOT NULL,
    `unit_cost_snapshot` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total_cost_cents` INT NOT NULL DEFAULT 0,
    `reason` TEXT NOT NULL,
    `status` ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
    `created_by` INT NOT NULL,
    `approved_by` INT NULL DEFAULT NULL,
    `approved_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_adj_branch` (`branch_id`),
    INDEX `idx_adj_inventory` (`inventory_id`),
    INDEX `idx_adj_status` (`status`),
    INDEX `idx_adj_created_by` (`created_by`),
    CONSTRAINT `fk_adj_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_adj_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_adj_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Seed threshold and negative stock settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('inventory_approval_threshold_cents', '50000'),
('inventory_allow_negative', '0')
ON DUPLICATE KEY UPDATE `setting_key` = VALUES(`setting_key`);
