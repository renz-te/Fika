-- ==========================================================
-- Migration: 009_attendance_mode.sql
-- Description: Employee attendance mode and Absence status
-- ==========================================================

SET FOREIGN_KEY_CHECKS = 0;

DELIMITER //

DROP PROCEDURE IF EXISTS AddAttendanceMode //
CREATE PROCEDURE AddAttendanceMode()
BEGIN
    DECLARE colExists INT;
    SELECT COUNT(*) INTO colExists
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'employees'
      AND COLUMN_NAME = 'attendance_mode'
      AND TABLE_SCHEMA = DATABASE();

    IF colExists = 0 THEN
        ALTER TABLE `employees` ADD COLUMN `attendance_mode` ENUM('CLOCK', 'FIXED') NOT NULL DEFAULT 'CLOCK' AFTER `status`;
    END IF;
END //

DELIMITER ;

CALL AddAttendanceMode();
DROP PROCEDURE IF EXISTS AddAttendanceMode;

ALTER TABLE `attendance_logs`
MODIFY COLUMN `status` ENUM('OPEN', 'CLOSED', 'ADJUSTED', 'ABSENT') NOT NULL DEFAULT 'OPEN';

SET FOREIGN_KEY_CHECKS = 1;
