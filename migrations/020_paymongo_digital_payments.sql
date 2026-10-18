-- ==========================================================
-- Migration: 020_paymongo_digital_payments.sql
-- Description: PayMongo digital payments schema updates for payments and orders
-- ==========================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Ensure payments table exists
CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `payment_method` VARCHAR(50) NOT NULL DEFAULT 'CASH',
    `amount_paid` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `change_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `reference_number` VARCHAR(100) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_payments_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Add Digital Payment columns to payments table safely
DELIMITER //

DROP PROCEDURE IF EXISTS AddPaymentsDigitalColumns //
CREATE PROCEDURE AddPaymentsDigitalColumns()
BEGIN
    DECLARE colExists INT;

    -- shift_id
    SELECT COUNT(*) INTO colExists FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'payments' AND COLUMN_NAME = 'shift_id' AND TABLE_SCHEMA = DATABASE();
    IF colExists = 0 THEN
        ALTER TABLE `payments` ADD COLUMN `shift_id` INT NULL AFTER `order_id`;
    END IF;

    -- intent_id
    SELECT COUNT(*) INTO colExists FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'payments' AND COLUMN_NAME = 'intent_id' AND TABLE_SCHEMA = DATABASE();
    IF colExists = 0 THEN
        ALTER TABLE `payments` ADD COLUMN `intent_id` VARCHAR(100) NULL AFTER `shift_id`;
        ALTER TABLE `payments` ADD UNIQUE KEY `uk_payments_intent` (`intent_id`);
    END IF;

    -- provider_payment_id
    SELECT COUNT(*) INTO colExists FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'payments' AND COLUMN_NAME = 'provider_payment_id' AND TABLE_SCHEMA = DATABASE();
    IF colExists = 0 THEN
        ALTER TABLE `payments` ADD COLUMN `provider_payment_id` VARCHAR(100) NULL AFTER `intent_id`;
    END IF;

    -- method
    SELECT COUNT(*) INTO colExists FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'payments' AND COLUMN_NAME = 'method' AND TABLE_SCHEMA = DATABASE();
    IF colExists = 0 THEN
        ALTER TABLE `payments` ADD COLUMN `method` VARCHAR(50) NULL AFTER `provider_payment_id`;
    END IF;

    -- gross_amount
    SELECT COUNT(*) INTO colExists FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'payments' AND COLUMN_NAME = 'gross_amount' AND TABLE_SCHEMA = DATABASE();
    IF colExists = 0 THEN
        ALTER TABLE `payments` ADD COLUMN `gross_amount` DECIMAL(12,2) NULL AFTER `method`;
    END IF;

    -- fee_amount
    SELECT COUNT(*) INTO colExists FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'payments' AND COLUMN_NAME = 'fee_amount' AND TABLE_SCHEMA = DATABASE();
    IF colExists = 0 THEN
        ALTER TABLE `payments` ADD COLUMN `fee_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `gross_amount`;
    END IF;

    -- net_amount
    SELECT COUNT(*) INTO colExists FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'payments' AND COLUMN_NAME = 'net_amount' AND TABLE_SCHEMA = DATABASE();
    IF colExists = 0 THEN
        ALTER TABLE `payments` ADD COLUMN `net_amount` DECIMAL(12,2) NULL AFTER `fee_amount`;
    END IF;

    -- status
    SELECT COUNT(*) INTO colExists FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'payments' AND COLUMN_NAME = 'status' AND TABLE_SCHEMA = DATABASE();
    IF colExists = 0 THEN
        ALTER TABLE `payments` ADD COLUMN `status` ENUM('pending', 'paid', 'failed') NOT NULL DEFAULT 'paid' AFTER `net_amount`;
    END IF;

    -- paid_at
    SELECT COUNT(*) INTO colExists FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'payments' AND COLUMN_NAME = 'paid_at' AND TABLE_SCHEMA = DATABASE();
    IF colExists = 0 THEN
        ALTER TABLE `payments` ADD COLUMN `paid_at` DATETIME NULL AFTER `status`;
    END IF;

    -- raw_payload
    SELECT COUNT(*) INTO colExists FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'payments' AND COLUMN_NAME = 'raw_payload' AND TABLE_SCHEMA = DATABASE();
    IF colExists = 0 THEN
        ALTER TABLE `payments` ADD COLUMN `raw_payload` LONGTEXT NULL AFTER `paid_at`;
    END IF;

    -- Ensure orders has payment_method and payment_reference
    SELECT COUNT(*) INTO colExists FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'orders' AND COLUMN_NAME = 'payment_method' AND TABLE_SCHEMA = DATABASE();
    IF colExists = 0 THEN
        ALTER TABLE `orders` ADD COLUMN `payment_method` VARCHAR(50) NULL AFTER `payment_status`;
    END IF;

    SELECT COUNT(*) INTO colExists FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'orders' AND COLUMN_NAME = 'payment_reference' AND TABLE_SCHEMA = DATABASE();
    IF colExists = 0 THEN
        ALTER TABLE `orders` ADD COLUMN `payment_reference` VARCHAR(100) NULL AFTER `payment_method`;
    END IF;

END //

DELIMITER ;

CALL AddPaymentsDigitalColumns();
DROP PROCEDURE IF EXISTS AddPaymentsDigitalColumns;

SET FOREIGN_KEY_CHECKS = 1;
