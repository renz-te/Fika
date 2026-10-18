-- ==========================================================
-- Migration: 025_procurement.sql
-- Description: Procurement tables (suppliers, purchase_orders, purchase_order_items, po_receipts),
--              ledger po_id tracking, and procurement permissions.
-- ==========================================================

-- 1. Create suppliers table
CREATE TABLE IF NOT EXISTS `suppliers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NULL DEFAULT NULL,
    `name` VARCHAR(255) NOT NULL,
    `contact` VARCHAR(255) NULL DEFAULT NULL,
    `address` TEXT NULL DEFAULT NULL,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_suppliers_branch` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create purchase_orders table
CREATE TABLE IF NOT EXISTS `purchase_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL,
    `supplier_id` INT NOT NULL,
    `po_number` VARCHAR(50) NOT NULL UNIQUE,
    `status` ENUM('DRAFT', 'APPROVED', 'PARTIALLY_RECEIVED', 'RECEIVED', 'CANCELLED') NOT NULL DEFAULT 'DRAFT',
    `total_cost_cents` BIGINT NOT NULL DEFAULT 0,
    `created_by` INT NOT NULL,
    `approved_by` INT NULL DEFAULT NULL,
    `received_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_po_branch` (`branch_id`),
    INDEX `idx_po_supplier` (`supplier_id`),
    INDEX `idx_po_status` (`status`),
    CONSTRAINT `fk_po_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_po_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_po_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create purchase_order_items table
CREATE TABLE IF NOT EXISTS `purchase_order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `po_id` INT NOT NULL,
    `inventory_id` INT NOT NULL,
    `quantity_ordered` DECIMAL(10,2) NOT NULL,
    `quantity_received` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `unit_cost_cents` BIGINT NOT NULL DEFAULT 0,
    `over_receipt_reason` TEXT NULL DEFAULT NULL,
    INDEX `idx_poi_po` (`po_id`),
    INDEX `idx_poi_inv` (`inventory_id`),
    CONSTRAINT `fk_poi_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_poi_inv` FOREIGN KEY (`inventory_id`) REFERENCES `inventory_items` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create po_receipts idempotency tracking table
CREATE TABLE IF NOT EXISTS `po_receipts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `po_id` INT NOT NULL,
    `received_by` INT NOT NULL,
    `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `idempotency_key` VARCHAR(64) NOT NULL UNIQUE,
    INDEX `idx_porec_po` (`po_id`),
    CONSTRAINT `fk_porec_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_porec_user` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Add po_id column to inventory_ledger if missing
SET @colExists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'inventory_ledger' 
      AND COLUMN_NAME = 'po_id'
);

SET @stmt = IF(@colExists = 0, 
    'ALTER TABLE `inventory_ledger` ADD COLUMN `po_id` INT NULL DEFAULT NULL AFTER `order_id`', 
    'SELECT 1'
);
PREPARE ddlStmt FROM @stmt;
EXECUTE ddlStmt;
DEALLOCATE PREPARE ddlStmt;

-- 6. Update inventory_ledger ENUM to include 'PURCHASE'
ALTER TABLE `inventory_ledger` MODIFY COLUMN `type` ENUM('Restock','Spoilage','Sold','Transfer','Adjustment','SALE','VOID_RESTOCK','PURCHASE') NOT NULL;

-- 7. Seed procurement permissions
CREATE TABLE IF NOT EXISTS `permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`name`, `description`) VALUES 
('procurement.view', 'View procurement suppliers and purchase orders'),
('procurement.create', 'Create suppliers and draft purchase orders'),
('procurement.approve', 'Approve or reject draft purchase orders'),
('procurement.receive', 'Receive items against approved purchase orders')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- 8. Assign permissions to roles
-- ADMIN (Role 1): all 4
INSERT INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(1, 'procurement.view'),
(1, 'procurement.create'),
(1, 'procurement.approve'),
(1, 'procurement.receive')
ON DUPLICATE KEY UPDATE `permission_name` = VALUES(`permission_name`);

-- BM (Role 6): view, create, receive
INSERT INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(6, 'procurement.view'),
(6, 'procurement.create'),
(6, 'procurement.receive')
ON DUPLICATE KEY UPDATE `permission_name` = VALUES(`permission_name`);

-- BA (Role 4): view, approve
INSERT INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(4, 'procurement.view'),
(4, 'procurement.approve')
ON DUPLICATE KEY UPDATE `permission_name` = VALUES(`permission_name`);
