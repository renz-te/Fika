-- ==========================================================
-- Migration: 029_ess_employee_fields.sql
-- Description: Add address, emergency_contact to employees,
--              and med_cert_path to leave_requests
-- ==========================================================

SET @colExists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'address'
);
SET @stmt = IF(@colExists = 0, 'ALTER TABLE `employees` ADD COLUMN `address` TEXT NULL DEFAULT NULL AFTER `phone`', 'SELECT 1');
PREPARE ddlStmt FROM @stmt; EXECUTE ddlStmt; DEALLOCATE PREPARE ddlStmt;

SET @colExists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'emergency_contact'
);
SET @stmt = IF(@colExists = 0, 'ALTER TABLE `employees` ADD COLUMN `emergency_contact` VARCHAR(255) NULL DEFAULT NULL AFTER `address`', 'SELECT 1');
PREPARE ddlStmt FROM @stmt; EXECUTE ddlStmt; DEALLOCATE PREPARE ddlStmt;

SET @colExists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_requests' AND COLUMN_NAME = 'med_cert_path'
);
SET @stmt = IF(@colExists = 0, 'ALTER TABLE `leave_requests` ADD COLUMN `med_cert_path` VARCHAR(255) NULL DEFAULT NULL AFTER `reason`', 'SELECT 1');
PREPARE ddlStmt FROM @stmt; EXECUTE ddlStmt; DEALLOCATE PREPARE ddlStmt;
