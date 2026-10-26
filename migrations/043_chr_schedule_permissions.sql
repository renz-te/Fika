-- Migration 043: CHR Schedule Permissions Scoping
-- CHR (Role 2) can view branch rosters across all branches but cannot modify schedules.
-- schedule.manage is strictly for Admin (Role 1), BHR (Role 5), and BM (Role 6).

-- 1. Ensure Role 2 (CHR) does NOT have schedule.manage
DELETE FROM `role_permissions` WHERE `role_id` = 2 AND `permission_name` = 'schedule.manage';

-- 2. Ensure Role 2 (CHR) has schedule.view
INSERT INTO `role_permissions` (`role_id`, `permission_name`) VALUES (2, 'schedule.view')
ON DUPLICATE KEY UPDATE `permission_name` = VALUES(`permission_name`);
