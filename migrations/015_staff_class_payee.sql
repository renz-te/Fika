-- ==========================================================
-- Migration: 015_staff_class_payee.sql
-- Description: Add staff_class to employees and payee_employee_id to payroll_runs
-- ==========================================================

SET FOREIGN_KEY_CHECKS = 0;

DELIMITER //

DROP PROCEDURE IF EXISTS AddStaffClass //
CREATE PROCEDURE AddStaffClass()
BEGIN
    DECLARE colExists INT;
    SELECT COUNT(*) INTO colExists
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'employees'
      AND COLUMN_NAME = 'staff_class'
      AND TABLE_SCHEMA = DATABASE();

    IF colExists = 0 THEN
        ALTER TABLE `employees` ADD COLUMN `staff_class` ENUM('CREW', 'OFFICIAL', 'HQ') NOT NULL DEFAULT 'CREW' AFTER `attendance_mode`;
    END IF;
END //

DROP PROCEDURE IF EXISTS AddPayeeId //
CREATE PROCEDURE AddPayeeId()
BEGIN
    DECLARE colExists INT;
    SELECT COUNT(*) INTO colExists
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'payroll_runs'
      AND COLUMN_NAME = 'payee_employee_id'
      AND TABLE_SCHEMA = DATABASE();

    IF colExists = 0 THEN
        ALTER TABLE `payroll_runs` ADD COLUMN `payee_employee_id` INT NULL AFTER `scope`;
        ALTER TABLE `payroll_runs` ADD CONSTRAINT `fk_pr_payee_emp` FOREIGN KEY (`payee_employee_id`) REFERENCES `employees`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
    END IF;
END //

DELIMITER ;

CALL AddStaffClass();
CALL AddPayeeId();

DROP PROCEDURE IF EXISTS AddStaffClass;
DROP PROCEDURE IF EXISTS AddPayeeId;

SET FOREIGN_KEY_CHECKS = 1;
