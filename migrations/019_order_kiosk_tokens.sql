-- ==========================================================
-- Migration: 019_order_kiosk_tokens.sql
-- Description: Kiosk order-bound tokens, device token authentication, order-shift separation, and age limit setting
-- ==========================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Order Kiosk Tokens Table
CREATE TABLE IF NOT EXISTS `order_kiosk_tokens` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `token_hash` VARCHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_order_id` (`order_id`),
    INDEX `idx_token_hash` (`token_hash`),
    CONSTRAINT `fk_okt_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Add device_token to devices table
DELIMITER //

DROP PROCEDURE IF EXISTS AddKioskDeviceToken //
CREATE PROCEDURE AddKioskDeviceToken()
BEGIN
    DECLARE colExists INT;
    SELECT COUNT(*) INTO colExists FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'devices' AND COLUMN_NAME = 'device_token' AND TABLE_SCHEMA = DATABASE();
    IF colExists = 0 THEN
        ALTER TABLE `devices` ADD COLUMN `device_token` VARCHAR(64) NULL AFTER `mac_address`;
        ALTER TABLE `devices` ADD UNIQUE KEY `uk_devices_token` (`device_token`);
    END IF;
END //

DELIMITER ;

CALL AddKioskDeviceToken();
DROP PROCEDURE IF EXISTS AddKioskDeviceToken;

-- 3. Order-Shift Separation (Allow NULL pos_session_id and cashier_id for kiosk orders)
ALTER TABLE `orders` MODIFY COLUMN `pos_session_id` INT NULL DEFAULT NULL;
ALTER TABLE `orders` MODIFY COLUMN `cashier_id` INT NULL DEFAULT NULL;

-- 4. Seed Age Filter Setting (default 60 minutes)
INSERT INTO `settings` (`setting_key`, `setting_value`) 
VALUES ('kiosk_order_age_limit_minutes', '60')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

SET FOREIGN_KEY_CHECKS = 1;
