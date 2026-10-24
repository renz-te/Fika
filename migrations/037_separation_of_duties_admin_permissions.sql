-- ==========================================================
-- Migration: 037_separation_of_duties_admin_permissions.sql
-- Description: Enforce strict Separation of Duties by revoking operational
--              and approval permissions from ADMIN (Role 1).
-- ==========================================================

-- 1. Ensure core admin and read permissions exist for ADMIN (Role 1)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(1, 'users.view'),
(1, 'users.create'),
(1, 'users.edit'),
(1, 'users.delete'),
(1, 'users.manage'),
(1, 'devices.view'),
(1, 'devices.manage'),
(1, 'roles.view'),
(1, 'roles.manage'),
(1, 'branches.view'),
(1, 'branches.manage'),
(1, 'settings.manage'),
(1, 'hr.employee.view'),
(1, 'payroll.view'),
(1, 'finance.view'),
(1, 'inventory.view'),
(1, 'procurement.view'),
(1, 'schedule.view'),
(1, 'hr.attendance.view'),
(1, 'hr.leave.view'),
(1, 'pos.menu.view');

-- 2. Revoke all operational execution, approval, release, and adjustment permissions from ADMIN (Role 1)
DELETE FROM `role_permissions`
WHERE `role_id` = 1
  AND `permission_name` IN (
      'payroll.approve',
      'payroll.release',
      'payroll.generate',
      'payroll.delete',
      'finance.expense.approve',
      'finance.expense.create',
      'finance.budget.manage',
      'inventory.adjust',
      'inventory.approve',
      'inventory.manage',
      'pos.void_order',
      'pos.refund_order',
      'pos.access',
      'procurement.approve',
      'procurement.create',
      'procurement.receive',
      'schedule.claim',
      'hr.attendance.adjust',
      'hr.attendance.certify',
      'hr.leave.approve'
  );
