-- ==========================================================
-- Migration: 047_audit_logs_branch_id.sql
-- Description: Add branch_id column and index to audit_logs
--              to support branch-scoped and mutual audit trails.
-- ==========================================================

ALTER TABLE `audit_logs`
ADD COLUMN `branch_id` INT NULL AFTER `user_id`,
ADD INDEX `idx_audit_logs_branch_id` (`branch_id`);
