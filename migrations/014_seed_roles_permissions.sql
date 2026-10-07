-- ==========================================================
-- Migration: 014_seed_roles_permissions.sql
-- Description: Insert actual PAM roles and permissions
-- ==========================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Clear existing (if needed for idempotency)
TRUNCATE TABLE `role_permissions`;
DELETE FROM `roles`;
ALTER TABLE `roles` AUTO_INCREMENT = 1;

-- Role: ADMIN
INSERT INTO `roles` (`id`, `name`, `description`) VALUES (1, 'ADMIN', 'Default ADMIN role');
INSERT INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(1, 'users.view'),
(1, 'users.create'),
(1, 'users.edit'),
(1, 'users.delete'),
(1, 'roles.view'),
(1, 'roles.manage'),
(1, 'branches.view'),
(1, 'branches.manage'),
(1, 'pos.access'),
(1, 'pos.void_order'),
(1, 'pos.refund_order'),
(1, 'inventory.view'),
(1, 'inventory.manage'),
(1, 'hr.employee.view'),
(1, 'hr.employee.create'),
(1, 'hr.employee.edit'),
(1, 'hr.employee.view_sensitive'),
(1, 'hr.attendance.view'),
(1, 'hr.attendance.adjust'),
(1, 'hr.attendance.certify'),
(1, 'hr.leave.view'),
(1, 'hr.leave.approve'),
(1, 'hr.leave.manage'),
(1, 'payroll.view'),
(1, 'payroll.generate'),
(1, 'payroll.approve'),
(1, 'payroll.release'),
(1, 'payroll.delete'),
(1, 'payroll.export'),
(1, 'settings.manage'),
(1, 'finance.view'),
(1, 'finance.manage');

-- Role: CHR
INSERT INTO `roles` (`id`, `name`, `description`) VALUES (2, 'CHR', 'Default CHR role');
INSERT INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(2, 'users.view'),
(2, 'roles.view'),
(2, 'branches.view'),
(2, 'settings.manage'),
(2, 'hr.employee.view'),
(2, 'hr.employee.create'),
(2, 'hr.employee.edit'),
(2, 'hr.employee.view_sensitive'),
(2, 'hr.attendance.view'),
(2, 'hr.attendance.adjust'),
(2, 'hr.attendance.certify'),
(2, 'hr.leave.view'),
(2, 'hr.leave.manage'),
(2, 'hr.leave.approve'),
(2, 'payroll.view'),
(2, 'payroll.generate'),
(2, 'payroll.delete'),
(2, 'payroll.export');

-- Role: GA
INSERT INTO `roles` (`id`, `name`, `description`) VALUES (3, 'GA', 'Default GA role');
INSERT INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(3, 'branches.view'),
(3, 'payroll.view'),
(3, 'payroll.export'),
(3, 'payroll.release'),
(3, 'finance.view'),
(3, 'finance.manage'),
(3, 'payroll.generate'),
(3, 'payroll.delete'),
(3, 'hr.attendance.certify'),
(3, 'payroll.approve'),
(3, 'hr.employee.view_sensitive');

-- Role: BA
INSERT INTO `roles` (`id`, `name`, `description`) VALUES (4, 'BA', 'Default BA role');
INSERT INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(4, 'branches.view'),
(4, 'payroll.view'),
(4, 'payroll.approve'),
(4, 'payroll.release'),
(4, 'payroll.export'),
(4, 'finance.view');

-- Role: BHR
INSERT INTO `roles` (`id`, `name`, `description`) VALUES (5, 'BHR', 'Default BHR role');
INSERT INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(5, 'users.view'),
(5, 'branches.view'),
(5, 'hr.employee.view'),
(5, 'hr.employee.create'),
(5, 'hr.employee.edit'),
(5, 'hr.employee.view_sensitive'),
(5, 'hr.attendance.view'),
(5, 'hr.attendance.adjust'),
(5, 'hr.leave.view'),
(5, 'hr.leave.manage'),
(5, 'payroll.view'),
(5, 'payroll.generate'),
(5, 'payroll.delete');

-- Role: BM
INSERT INTO `roles` (`id`, `name`, `description`) VALUES (6, 'BM', 'Default BM role');
INSERT INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(6, 'users.view'),
(6, 'branches.view'),
(6, 'pos.access'),
(6, 'pos.void_order'),
(6, 'pos.refund_order'),
(6, 'inventory.view'),
(6, 'inventory.manage'),
(6, 'hr.employee.view'),
(6, 'hr.attendance.view'),
(6, 'hr.attendance.adjust'),
(6, 'hr.attendance.certify'),
(6, 'hr.leave.view'),
(6, 'hr.leave.approve'),
(6, 'payroll.view'),
(6, 'finance.view');

-- Role: STAFF
INSERT INTO `roles` (`id`, `name`, `description`) VALUES (7, 'STAFF', 'Default STAFF role');
INSERT INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(7, 'pos.access'),
(7, 'inventory.view');

SET FOREIGN_KEY_CHECKS = 1;
