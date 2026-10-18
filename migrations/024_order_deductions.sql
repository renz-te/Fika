-- Migration 024: Order Deductions & Recipe Mapping Support

-- 1. Create order_ledger_deductions for atomic idempotency
CREATE TABLE IF NOT EXISTS `order_ledger_deductions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `order_id` INT NOT NULL,
  `branch_id` INT NOT NULL,
  `deducted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_deductions_order_id` (`order_id`),
  KEY `idx_order_deductions_branch` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Ensure order_id and updated enum in inventory_ledger
-- Add order_id if not present
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() AND table_name = 'inventory_ledger' AND column_name = 'order_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE inventory_ledger ADD COLUMN order_id INT DEFAULT NULL AFTER remarks', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update type ENUM to include 'SALE' and 'VOID_RESTOCK'
ALTER TABLE `inventory_ledger` 
  MODIFY COLUMN `type` ENUM('Restock','Spoilage','Sold','Transfer','Adjustment','SALE','VOID_RESTOCK') NOT NULL;

-- 3. Ensure recipes unique keys
SET @idx_prod_exists = (SELECT COUNT(*) FROM information_schema.statistics 
    WHERE table_schema = DATABASE() AND table_name = 'recipes' AND index_name = 'unique_product_inventory');
SET @sql_prod = IF(@idx_prod_exists = 0, 'ALTER TABLE recipes ADD UNIQUE KEY unique_product_inventory (product_id, inventory_id)', 'SELECT 1');
PREPARE stmt_prod FROM @sql_prod;
EXECUTE stmt_prod;
DEALLOCATE PREPARE stmt_prod;

SET @idx_mod_exists = (SELECT COUNT(*) FROM information_schema.statistics 
    WHERE table_schema = DATABASE() AND table_name = 'recipes' AND index_name = 'unique_modifier_inventory');
SET @sql_mod = IF(@idx_mod_exists = 0, 'ALTER TABLE recipes ADD UNIQUE KEY unique_modifier_inventory (modifier_id, inventory_id)', 'SELECT 1');
PREPARE stmt_mod FROM @sql_mod;
EXECUTE stmt_mod;
DEALLOCATE PREPARE stmt_mod;
