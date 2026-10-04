-- ==========================================================
-- Migration: 003_payroll_attendance.sql
-- Description: Payroll, Attendance, Leaves, and Budget schema
-- ==========================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Attendance
CREATE TABLE IF NOT EXISTS `attendance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT NOT NULL,
    `date` DATE NOT NULL,
    `clock_in` DATETIME DEFAULT NULL,
    `clock_out` DATETIME DEFAULT NULL,
    `status` ENUM('Present', 'Absent', 'Late', 'Half-Day') NOT NULL DEFAULT 'Present',
    `total_hours` DECIMAL(5,2) DEFAULT 0.00,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_attendance_employee_id` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Leaves
CREATE TABLE IF NOT EXISTS `leaves` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT NOT NULL,
    `leave_type` VARCHAR(50) NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `status` ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
    `approved_by` INT DEFAULT NULL,
    `reason` TEXT,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_leaves_employee_id` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_leaves_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Contribution Rates (Taxes, SSS, PhilHealth, PagIBIG)
CREATE TABLE IF NOT EXISTS `contribution_rates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `type` ENUM('SSS', 'PhilHealth', 'PagIBIG', 'Tax') NOT NULL,
    `lower_bound` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `upper_bound` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `employee_share` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `employer_share` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Payroll Runs
CREATE TABLE IF NOT EXISTS `payroll_runs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT DEFAULT NULL,
    `period_start` DATE NOT NULL,
    `period_end` DATE NOT NULL,
    `status` ENUM('DRAFT', 'FINALIZED', 'PAID') NOT NULL DEFAULT 'DRAFT',
    `processed_by` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_pruns_branch_id` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_pruns_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Payroll Items
CREATE TABLE IF NOT EXISTS `payroll_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `payroll_run_id` INT NOT NULL,
    `employee_id` INT NOT NULL,
    `basic_pay` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `overtime_pay` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `bonus_pay` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `sss_deduction` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `philhealth_deduction` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `pagibig_deduction` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `tax_deduction` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `net_pay` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_pitems_run_id` FOREIGN KEY (`payroll_run_id`) REFERENCES `payroll_runs` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_pitems_employee_id` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Pending Bonuses
CREATE TABLE IF NOT EXISTS `pending_bonuses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `reason` VARCHAR(255) NOT NULL,
    `status` ENUM('PENDING', 'PAID', 'CANCELLED') NOT NULL DEFAULT 'PENDING',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_bonuses_employee_id` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Branch Budgets
CREATE TABLE IF NOT EXISTS `branch_budgets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL,
    `budget_month` VARCHAR(7) NOT NULL COMMENT 'YYYY-MM format',
    `allocated_labor_budget` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_branch_budget` (`branch_id`, `budget_month`),
    CONSTRAINT `fk_budgets_branch_id` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
