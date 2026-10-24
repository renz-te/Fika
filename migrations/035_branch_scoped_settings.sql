-- ==========================================================
-- Migration: 035_branch_scoped_settings.sql
-- Description: Add branch_id to settings table with (setting_key, branch_id) unique key
-- ==========================================================

SET @colExists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = 'branch_id'
);

SET @hasId = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = 'id'
);

SET @stmt = IF(@hasId = 0, 
    'ALTER TABLE `settings` DROP PRIMARY KEY, ADD COLUMN `id` INT AUTO_INCREMENT PRIMARY KEY FIRST', 
    'SELECT 1'
);
PREPARE ddlStmt FROM @stmt; EXECUTE ddlStmt; DEALLOCATE PREPARE ddlStmt;

SET @stmt2 = IF(@colExists = 0, 
    'ALTER TABLE `settings` ADD COLUMN `branch_id` INT NULL DEFAULT NULL AFTER `setting_key`, ADD UNIQUE KEY `uq_setting_branch` (`setting_key`, `branch_id`)', 
    'SELECT 1'
);
PREPARE ddlStmt2 FROM @stmt2; EXECUTE ddlStmt2; DEALLOCATE PREPARE ddlStmt2;

-- Ensure existing global settings have branch_id = NULL
UPDATE `settings` SET `branch_id` = NULL WHERE `branch_id` IS NULL;

-- Assign settings.manage to ADMIN (Role 1) and BM (Role 6)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(1, 'settings.manage'),
(6, 'settings.manage');
