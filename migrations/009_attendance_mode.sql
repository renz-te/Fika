-- ==========================================================
-- Migration: 009_attendance_mode.sql
-- Description: Employee attendance mode and Absence status
-- ==========================================================

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `employees` 
ADD COLUMN `attendance_mode` ENUM('CLOCK', 'FIXED') NOT NULL DEFAULT 'CLOCK' AFTER `status`;

ALTER TABLE `attendance_logs`
MODIFY COLUMN `status` ENUM('OPEN', 'CLOSED', 'ADJUSTED', 'ABSENT') NOT NULL DEFAULT 'OPEN';

SET FOREIGN_KEY_CHECKS = 1;
