-- ==========================================================
-- Migration: 027_payroll_employer_cols.sql
-- Description: Add gross_pay and employer contributions to payroll_items
-- ==========================================================

SET @colExists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_items' AND COLUMN_NAME = 'gross_pay'
);
SET @stmt = IF(@colExists = 0, 'ALTER TABLE `payroll_items` ADD COLUMN `gross_pay` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `bonus_pay`', 'SELECT 1');
PREPARE ddlStmt FROM @stmt; EXECUTE ddlStmt; DEALLOCATE PREPARE ddlStmt;

SET @colExists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_items' AND COLUMN_NAME = 'employer_sss'
);
SET @stmt = IF(@colExists = 0, 'ALTER TABLE `payroll_items` ADD COLUMN `employer_sss` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `pagibig_deduction`', 'SELECT 1');
PREPARE ddlStmt FROM @stmt; EXECUTE ddlStmt; DEALLOCATE PREPARE ddlStmt;

SET @colExists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_items' AND COLUMN_NAME = 'employer_philhealth'
);
SET @stmt = IF(@colExists = 0, 'ALTER TABLE `payroll_items` ADD COLUMN `employer_philhealth` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `employer_sss`', 'SELECT 1');
PREPARE ddlStmt FROM @stmt; EXECUTE ddlStmt; DEALLOCATE PREPARE ddlStmt;

SET @colExists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_items' AND COLUMN_NAME = 'employer_pagibig'
);
SET @stmt = IF(@colExists = 0, 'ALTER TABLE `payroll_items` ADD COLUMN `employer_pagibig` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `employer_philhealth`', 'SELECT 1');
PREPARE ddlStmt FROM @stmt; EXECUTE ddlStmt; DEALLOCATE PREPARE ddlStmt;
