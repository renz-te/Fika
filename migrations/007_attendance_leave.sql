-- ==========================================================
-- Migration: 007_attendance_leave.sql
-- Description: Upgrade attendance to attendance_logs, add leave_requests and holidays
-- ==========================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Upgrade Attendance -> Attendance Logs
-- Drop the old table since we are completely replacing the schema design
DROP TABLE IF EXISTS `attendance`;

CREATE TABLE IF NOT EXISTS `attendance_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT NOT NULL,
    `branch_id` INT NOT NULL,
    `device_id` INT DEFAULT NULL,
    `clock_in` DATETIME NOT NULL,
    `clock_out` DATETIME DEFAULT NULL,
    `work_date` DATE NOT NULL,
    `source` ENUM('DEVICE','MANUAL') NOT NULL DEFAULT 'DEVICE',
    `status` ENUM('OPEN','CLOSED','ADJUSTED') NOT NULL DEFAULT 'OPEN',
    `adjusted_by` INT DEFAULT NULL,
    `adjust_reason` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Virtual column to enforce exactly ONE open log per employee
    `open_employee_id` INT GENERATED ALWAYS AS (IF(`status` = 'OPEN', `employee_id`, NULL)) VIRTUAL,
    UNIQUE KEY `uq_open_log` (`open_employee_id`),
    
    CONSTRAINT `fk_alog_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_alog_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_alog_device` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_alog_adj` FOREIGN KEY (`adjusted_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Upgrade Leaves -> Leave Requests
DROP TABLE IF EXISTS `leaves`;

CREATE TABLE IF NOT EXISTS `leave_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `date_from` DATE NOT NULL,
    `date_to` DATE NOT NULL,
    `days` DECIMAL(5,2) NOT NULL,
    `reason` TEXT NOT NULL,
    `status` ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    `decided_by` INT DEFAULT NULL,
    `decided_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT `fk_lreq_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_lreq_type` FOREIGN KEY (`type`) REFERENCES `leave_types` (`code`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_lreq_decider` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Holidays
CREATE TABLE IF NOT EXISTS `holidays` (
    `date` DATE NOT NULL PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `type` ENUM('REGULAR','SPECIAL') NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
