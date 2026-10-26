-- Migration 044: Add user_id to devices table and ensure system roles
-- Links hardware devices directly to system users accounts.

-- 1. Add user_id column to devices if not exists
SET @colExists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'devices' 
      AND COLUMN_NAME = 'user_id'
);
SET @sqlStmt = IF(@colExists = 0, 'ALTER TABLE devices ADD COLUMN user_id INT NULL AFTER branch_id', 'SELECT 1');
PREPARE stmt FROM @sqlStmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Ensure Kiosk role (ID 13) and Cashier Terminal (9), Clock (12)
INSERT INTO `roles` (`id`, `name`, `description`) VALUES 
(9, 'Cashier Terminal', 'Point of sale cashier terminal system role'),
(12, 'Clock', 'Time and attendance clock station system role'),
(13, 'Kiosk', 'Self-service kiosk system role')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

-- 3. Ensure role permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_name`) VALUES 
(9, 'pos.access'),
(9, 'pos.order_create'),
(12, 'pos.access'),
(13, 'pos.access'),
(13, 'pos.order_create');

-- 4. Backfill user_id on existing devices based on matching username
UPDATE devices d
JOIN users u ON u.branch_id = d.branch_id 
  AND (
      (d.device_name LIKE '%Clock%' AND u.username LIKE '%.clock%')
      OR (d.device_name LIKE '%Kiosk%' AND u.username LIKE '%.kiosk%')
      OR (d.device_name LIKE '%Cashier%' AND (u.username LIKE '%.cashier%' OR u.username LIKE '%.pos%'))
  )
SET d.user_id = u.id
WHERE d.user_id IS NULL;
