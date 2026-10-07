-- ==========================================================
-- Migration: 012_payroll_scope.sql
-- Description: Add scope to payroll_runs to distinguish run types
-- ==========================================================

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `payroll_runs`
ADD COLUMN `scope` ENUM('BRANCH', 'OFFICIALS', 'HQ') NOT NULL DEFAULT 'BRANCH' AFTER `id`;

SET FOREIGN_KEY_CHECKS = 1;
