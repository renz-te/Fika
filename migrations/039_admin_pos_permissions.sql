-- Migration: 039_admin_pos_permissions.sql
-- Grant ALL POS permissions to ADMIN role (Role ID 1) and ensure roles 6 and 7 have pos.access

INSERT IGNORE INTO role_permissions (role_id, permission_name) VALUES 
(1, 'pos.access'),
(1, 'pos.menu.view'),
(1, 'pos.menu.manage'),
(1, 'pos.void_order'),
(1, 'pos.refund_order'),
(6, 'pos.access'),
(7, 'pos.access');
