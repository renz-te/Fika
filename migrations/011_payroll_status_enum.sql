-- ==========================================================
-- Migration: 011_payroll_status_enum.sql
-- Description: Update payroll_runs status ENUM to match requirements
-- ==========================================================

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `payroll_runs`
MODIFY COLUMN `status` ENUM('DRAFT', 'GENERATED', 'APPROVED', 'RELEASED') NOT NULL DEFAULT 'DRAFT';

SET FOREIGN_KEY_CHECKS = 1;
