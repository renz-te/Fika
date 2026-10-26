-- ==========================================================
-- Migration: 045_admin_sod_restrictions.sql
-- Description: Restrict ADMIN (Role 1) from daily operations
--              and enforce operational scope for BM (Role 6).
-- ==========================================================

-- 1. Revoke operational permissions from Role 1 (Admin)
DELETE FROM `role_permissions`
WHERE `role_id` = 1
  AND `permission_name` IN (
      'pos.access',
      'pos.void_order',
      'pos.refund_order',
      'inventory.adjust',
      'inventory.approve',
      'payroll.generate',
      'payroll.approve',
      'payroll.release',
      'finance.expense.create',
      'finance.expense.approve'
  );

-- 2. Ensure Role 6 (Branch Manager) has operational scope
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(6, 'pos.access'),
(6, 'inventory.adjust'),
(6, 'payroll.release'),
(6, 'finance.expense.create');
