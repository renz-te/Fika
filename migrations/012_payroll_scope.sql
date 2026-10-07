-- ==========================================================
-- Migration: 012_payroll_scope.sql
-- Description: Add scope to payroll_runs to distinguish run types
-- ==========================================================

SET FOREIGN_KEY_CHECKS = 0;

DELIMITER //

DROP PROCEDURE IF EXISTS AddPayrollScope //
CREATE PROCEDURE AddPayrollScope()
BEGIN
    DECLARE colExists INT;
    SELECT COUNT(*) INTO colExists
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'payroll_runs'
      AND COLUMN_NAME = 'scope'
      AND TABLE_SCHEMA = DATABASE();

    IF colExists = 0 THEN
        ALTER TABLE `payroll_runs` ADD COLUMN `scope` ENUM('BRANCH', 'OFFICIALS', 'HQ') NOT NULL DEFAULT 'BRANCH' AFTER `id`;
    END IF;
END //

DELIMITER ;

CALL AddPayrollScope();
DROP PROCEDURE IF EXISTS AddPayrollScope;

SET FOREIGN_KEY_CHECKS = 1;
