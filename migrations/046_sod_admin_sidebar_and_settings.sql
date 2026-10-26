-- ==========================================================
-- Migration: 046_sod_admin_sidebar_and_settings.sql
-- Description: Revoke operational schedule, attendance, and leave
--              permissions from Admin (Role 1). Ensure BM (Role 6)
--              has finance.view and revoke settings.manage from BM.
-- ==========================================================

-- 1. Revoke operational permissions from Role 1 (Admin)
DELETE FROM `role_permissions`
WHERE `role_id` = 1
  AND `permission_name` IN (
      'schedule.manage',
      'schedule.view',
      'hr.attendance.view',
      'hr.leave.view',
      'hr.leave.manage'
  );

-- 2. Revoke Global Settings management from Role 6 (Branch Manager)
DELETE FROM `role_permissions`
WHERE `role_id` = 6
  AND `permission_name` = 'settings.manage';

-- 3. Ensure Role 6 (Branch Manager) has finance.view
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(6, 'finance.view');
