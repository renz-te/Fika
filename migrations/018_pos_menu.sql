-- ==========================================================
-- Migration: 018_pos_menu.sql
-- Description: Menu management, branch availability, centavos pricing, and permissions
-- ==========================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Categories Table
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Branch Products Table (Per-branch availability)
CREATE TABLE IF NOT EXISTS `branch_products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_branch_product` (`branch_id`, `product_id`),
    CONSTRAINT `fk_bp_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bp_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Procedure to safely add missing columns
DELIMITER //

DROP PROCEDURE IF EXISTS AddMenuColumns //
CREATE PROCEDURE AddMenuColumns()
BEGIN
    DECLARE colExists INT;

    -- products.price_cents
    SELECT COUNT(*) INTO colExists FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'products' AND COLUMN_NAME = 'price_cents' AND TABLE_SCHEMA = DATABASE();
    IF colExists = 0 THEN
        ALTER TABLE `products` ADD COLUMN `price_cents` INT NOT NULL DEFAULT 0 AFTER `price`;
    END IF;

    -- products.image_path
    SELECT COUNT(*) INTO colExists FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'products' AND COLUMN_NAME = 'image_path' AND TABLE_SCHEMA = DATABASE();
    IF colExists = 0 THEN
        ALTER TABLE `products` ADD COLUMN `image_path` VARCHAR(255) NULL AFTER `price_cents`;
    END IF;

    -- products.image_alt
    SELECT COUNT(*) INTO colExists FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'products' AND COLUMN_NAME = 'image_alt' AND TABLE_SCHEMA = DATABASE();
    IF colExists = 0 THEN
        ALTER TABLE `products` ADD COLUMN `image_alt` VARCHAR(160) NULL AFTER `image_path`;
    END IF;

    -- products.deleted_at
    SELECT COUNT(*) INTO colExists FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'products' AND COLUMN_NAME = 'deleted_at' AND TABLE_SCHEMA = DATABASE();
    IF colExists = 0 THEN
        ALTER TABLE `products` ADD COLUMN `deleted_at` DATETIME NULL;
    END IF;

    -- order_items.price_snapshot_cents
    SELECT COUNT(*) INTO colExists FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'order_items' AND COLUMN_NAME = 'price_snapshot_cents' AND TABLE_SCHEMA = DATABASE();
    IF colExists = 0 THEN
        ALTER TABLE `order_items` ADD COLUMN `price_snapshot_cents` INT NOT NULL DEFAULT 0 AFTER `price_snapshot`;
    END IF;

END //

DELIMITER ;

CALL AddMenuColumns();
DROP PROCEDURE IF EXISTS AddMenuColumns;

-- 4. Sync centavos for existing data
UPDATE `products` SET `price_cents` = ROUND(`price` * 100) WHERE `price_cents` = 0 AND `price` > 0;
UPDATE `order_items` SET `price_snapshot_cents` = ROUND(`price_snapshot` * 100) WHERE `price_snapshot_cents` = 0 AND `price_snapshot` > 0;

-- 5. Populate initial categories from products
INSERT IGNORE INTO `categories` (`name`)
SELECT DISTINCT `category` FROM `products` WHERE `category` IS NOT NULL AND TRIM(`category`) != '';

-- 6. Populate branch_products for existing active products
INSERT IGNORE INTO `branch_products` (`branch_id`, `product_id`, `is_active`)
SELECT b.id, p.id, p.is_active
FROM `branches` b
CROSS JOIN `products` p;

-- 7. Seed Role Permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(1, 'pos.menu.view'),
(1, 'pos.menu.manage'),
(6, 'pos.menu.view'),
(6, 'pos.menu.manage'),
(7, 'pos.menu.view');

SET FOREIGN_KEY_CHECKS = 1;
