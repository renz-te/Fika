-- ==========================================================
-- Migration: 006_settings_leaves.sql
-- Description: System settings and leave types
-- ==========================================================

CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
    `setting_value` TEXT NOT NULL,
    `updated_by` INT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_settings_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `leave_types` (
    `code` VARCHAR(50) NOT NULL PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `is_paid` TINYINT(1) NOT NULL DEFAULT 0,
    `annual_days` INT NOT NULL DEFAULT 0,
    `min_service_months` INT NOT NULL DEFAULT 0,
    `statutory` TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Settings
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES 
('late_grace_minutes', '15');

-- Seed Leave Types
INSERT IGNORE INTO `leave_types` (`code`, `name`, `is_paid`, `annual_days`, `min_service_months`, `statutory`) VALUES 
('SIL', 'Service Incentive Leave', 1, 5, 12, 0),
('VACATION', 'Vacation Leave', 0, 0, 0, 0),
('SICK', 'Sick Leave', 0, 0, 0, 0),
('EMERGENCY', 'Emergency Leave', 0, 0, 0, 0),
('MATERNITY', 'Maternity Leave', 0, 105, 0, 1),
('PATERNITY', 'Paternity Leave', 0, 7, 0, 1),
('SOLO_PARENT', 'Solo Parent Leave', 0, 7, 0, 1);
