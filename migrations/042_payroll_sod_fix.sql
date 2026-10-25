-- Migration 042: Payroll Separation of Duties (SoD) Permissions & Tracking
-- Workflow: HR Generates (Maker) -> Accountant Approves (Checker 1) -> Branch Manager Releases (Checker 2)

-- 1. Ensure approved_by column exists in payroll_runs
SET @colExists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_runs' AND COLUMN_NAME = 'approved_by'
);

SET @stmt = IF(@colExists = 0, 
    'ALTER TABLE `payroll_runs` ADD COLUMN `approved_by` INT NULL DEFAULT NULL AFTER `processed_by`', 
    'SELECT 1'
);
PREPARE ddlStmt FROM @stmt; EXECUTE ddlStmt; DEALLOCATE PREPARE ddlStmt;

-- 2. Revoke payroll.generate from Role 3 (GA) and Role 4 (BA)
DELETE FROM `role_permissions` WHERE `role_id` IN (3, 4) AND `permission_name` = 'payroll.generate';

-- 3. Ensure Role 2 (CHR) and Role 5 (BHR) have payroll.generate
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(2, 'payroll.generate'),
(5, 'payroll.generate');

-- 4. Revoke payroll.approve from Role 6 (BM)
DELETE FROM `role_permissions` WHERE `role_id` = 6 AND `permission_name` = 'payroll.approve';

-- 5. Ensure Role 3 (GA) and Role 4 (BA) have payroll.approve
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(3, 'payroll.approve'),
(4, 'payroll.approve');

-- 6. Revoke payroll.release from Role 3 (GA) and Role 4 (BA)
DELETE FROM `role_permissions` WHERE `role_id` IN (3, 4) AND `permission_name` = 'payroll.release';

-- 7. Ensure Role 6 (BM) has payroll.release
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(6, 'payroll.release');
