-- ==========================================================
-- Migration: 030_shift_board.sql
-- Description: Shift scheduling tables (shifts, open_shifts)
--              and schedule permissions.
-- ==========================================================

-- 1. Create shifts table
CREATE TABLE IF NOT EXISTS `shifts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL,
    `employee_id` INT NOT NULL,
    `shift_date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `shift_type` VARCHAR(50) NOT NULL DEFAULT 'Regular',
    `status` ENUM('DRAFT', 'PUBLISHED') NOT NULL DEFAULT 'DRAFT',
    `edit_reason` TEXT NULL DEFAULT NULL,
    `created_by` INT NOT NULL,
    `updated_by` INT NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_shifts_branch_date` (`branch_id`, `shift_date`),
    INDEX `idx_shifts_employee_date` (`employee_id`, `shift_date`),
    INDEX `idx_shifts_status` (`status`),
    CONSTRAINT `fk_shifts_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_shifts_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_shifts_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create open_shifts table for shift dropping & claiming
CREATE TABLE IF NOT EXISTS `open_shifts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `shift_id` INT NOT NULL,
    `branch_id` INT NOT NULL,
    `original_employee_id` INT NOT NULL,
    `claimed_by` INT NULL DEFAULT NULL,
    `drop_reason` TEXT NULL DEFAULT NULL,
    `status` ENUM('OPEN', 'PENDING_APPROVAL', 'APPROVED', 'REJECTED', 'CANCELLED') NOT NULL DEFAULT 'OPEN',
    `approved_by` INT NULL DEFAULT NULL,
    `approved_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_open_shifts_shift` (`shift_id`),
    INDEX `idx_open_shifts_branch_status` (`branch_id`, `status`),
    INDEX `idx_open_shifts_claimed_by` (`claimed_by`),
    CONSTRAINT `fk_open_shifts_shift` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_open_shifts_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_open_shifts_orig_emp` FOREIGN KEY (`original_employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Seed permissions in permissions table
INSERT INTO `permissions` (`name`, `description`) VALUES 
('schedule.view', 'View branch shift schedules and boards'),
('schedule.manage', 'Draft, edit, publish, and delete branch shift schedules'),
('schedule.claim', 'Drop own shifts and claim open branch shifts')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- 4. Seed role_permissions
-- ADMIN (Role 1): gets all
INSERT INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(1, 'schedule.view'),
(1, 'schedule.manage'),
(1, 'schedule.claim')
ON DUPLICATE KEY UPDATE `permission_name` = VALUES(`permission_name`);

-- BM (Role 6): gets view, manage, claim
INSERT INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(6, 'schedule.view'),
(6, 'schedule.manage'),
(6, 'schedule.claim')
ON DUPLICATE KEY UPDATE `permission_name` = VALUES(`permission_name`);

-- BHR (Role 5): gets view
INSERT INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(5, 'schedule.view')
ON DUPLICATE KEY UPDATE `permission_name` = VALUES(`permission_name`);

-- BA (Role 4): gets view
INSERT INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(4, 'schedule.view')
ON DUPLICATE KEY UPDATE `permission_name` = VALUES(`permission_name`);

-- STAFF (Role 7): gets view, claim
INSERT INTO `role_permissions` (`role_id`, `permission_name`) VALUES
(7, 'schedule.view'),
(7, 'schedule.claim')
ON DUPLICATE KEY UPDATE `permission_name` = VALUES(`permission_name`);
