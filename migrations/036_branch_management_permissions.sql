-- ==========================================================
-- Migration: 036_branch_management_permissions.sql
-- Description: Branch management permissions for ADMIN (Role 1) only
-- ==========================================================

-- 1. Ensure permissions are registered
INSERT INTO `permissions` (`name`, `description`) VALUES 
('branches.view', 'View branches list and details'),
('branches.manage', 'Create, update, and toggle branch active status')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- 2. Revoke any legacy branch permissions from non-admin roles (specifically Role 6 / BM)
DELETE FROM `role_permissions` 
WHERE `permission_name` IN ('branches.view', 'branches.manage') 
  AND `role_id` != 1;

-- 3. Assign permissions to ADMIN (Role 1) ONLY
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(1, 'branches.view'),
(1, 'branches.manage');
