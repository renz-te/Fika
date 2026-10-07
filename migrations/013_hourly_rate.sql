-- ==========================================================
-- Migration: 013_hourly_rate.sql
-- Description: Add hourly_rate to employees table for part-time workers
-- ==========================================================

SET FOREIGN_KEY_CHECKS = 0;

DELIMITER //

DROP PROCEDURE IF EXISTS AddHourlyRateColumn //
CREATE PROCEDURE AddHourlyRateColumn()
BEGIN
    DECLARE colExists INT;
    SELECT COUNT(*) INTO colExists
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'employees'
      AND COLUMN_NAME = 'hourly_rate'
      AND TABLE_SCHEMA = DATABASE();

    IF colExists = 0 THEN
        ALTER TABLE employees ADD COLUMN hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER basic_salary;
    END IF;
END //

DELIMITER ;

CALL AddHourlyRateColumn();
DROP PROCEDURE IF EXISTS AddHourlyRateColumn;

SET FOREIGN_KEY_CHECKS = 1;
