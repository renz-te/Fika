-- ==========================================================
-- Migration: 033_bm_user_device_permissions.sql
-- Description: Assign branch user & device management permissions to BM (Role 6)
-- ==========================================================

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(1, 'users.manage'),
(1, 'devices.view'),
(1, 'devices.manage'),
(6, 'users.view'),
(6, 'users.manage'),
(6, 'devices.view'),
(6, 'devices.manage');
