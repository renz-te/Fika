-- ==========================================================
-- Migration: 010_seed_roles_permissions.sql
-- Description: Insert default roles and permissions
-- ==========================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Clear existing (if needed for idempotency)
TRUNCATE TABLE `role_permissions`;
DELETE FROM `roles`;
ALTER TABLE `roles` AUTO_INCREMENT = 1;

-- Role: Super Admin
INSERT INTO `roles` (`id`, `name`, `description`) VALUES (1, 'Super Admin', 'Default Super Admin role');
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
(1, 'employees.view'),
(1, 'employees.manage'),
(1, 'attendance.view'),
(1, 'attendance.manage'),
(1, 'payroll.view'),
(1, 'payroll.manage'),
(1, 'finance.view'),
(1, 'finance.manage');

-- Role: Admin
INSERT INTO `roles` (`id`, `name`, `description`) VALUES (2, 'Admin', 'Default Admin role');
INSERT INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(2, 'users.view'),
(2, 'users.create'),
(2, 'users.edit'),
(2, 'users.delete'),
(2, 'roles.view'),
(2, 'branches.view'),
(2, 'pos.access'),
(2, 'pos.void_order'),
(2, 'pos.refund_order'),
(2, 'inventory.view'),
(2, 'inventory.manage'),
(2, 'employees.view'),
(2, 'employees.manage'),
(2, 'attendance.view'),
(2, 'attendance.manage'),
(2, 'payroll.view'),
(2, 'payroll.manage'),
(2, 'finance.view'),
(2, 'finance.manage');

-- Role: Branch Manager
INSERT INTO `roles` (`id`, `name`, `description`) VALUES (3, 'Branch Manager', 'Default Branch Manager role');
INSERT INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(3, 'pos.access'),
(3, 'pos.void_order'),
(3, 'pos.refund_order'),
(3, 'inventory.view'),
(3, 'inventory.manage'),
(3, 'employees.view'),
(3, 'attendance.view'),
(3, 'attendance.manage');

-- Role: Cashier
INSERT INTO `roles` (`id`, `name`, `description`) VALUES (4, 'Cashier', 'Default Cashier role');
INSERT INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(4, 'pos.access');

-- Role: Barista
INSERT INTO `roles` (`id`, `name`, `description`) VALUES (5, 'Barista', 'Default Barista role');
INSERT INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(5, 'inventory.view');

-- Role: HR
INSERT INTO `roles` (`id`, `name`, `description`) VALUES (6, 'HR', 'Default HR role');
INSERT INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(6, 'employees.view'),
(6, 'employees.manage'),
(6, 'attendance.view'),
(6, 'attendance.manage'),
(6, 'payroll.view'),
(6, 'payroll.manage');

SET FOREIGN_KEY_CHECKS = 1;
