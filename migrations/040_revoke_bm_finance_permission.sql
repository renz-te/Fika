-- ==========================================================
-- Migration: 040_revoke_bm_finance_permission.sql
-- Description: Revoke finance.view from Branch Manager (Role 6)
--              and assign user.manage to Admin (Role 1)
-- ==========================================================

-- Revoke operational finance from Branch Manager (Role 6)
DELETE FROM `role_permissions` 
WHERE `role_id` = 6 AND `permission_name` = 'finance.view';

-- Ensure Admin (Role 1) has user.manage permission
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_name`) 
VALUES (1, 'user.manage');
