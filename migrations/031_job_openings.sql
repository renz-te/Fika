-- ==========================================================
-- Migration: 031_job_openings.sql
-- Description: Create job_openings table, seed default positions,
--              ensure applicants table with resume_path, and rate limiting.
-- ==========================================================

CREATE TABLE IF NOT EXISTS `job_openings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(150) NOT NULL,
    `department` VARCHAR(100) NOT NULL DEFAULT 'Operations',
    `location` VARCHAR(100) NOT NULL DEFAULT 'Main Branch',
    `type` VARCHAR(50) NOT NULL DEFAULT 'Full-Time',
    `description` TEXT NOT NULL,
    `requirements` TEXT NOT NULL,
    `status` ENUM('OPEN', 'CLOSED') NOT NULL DEFAULT 'OPEN',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed 3 default job openings if not already seeded
INSERT INTO `job_openings` (`id`, `title`, `department`, `location`, `type`, `description`, `requirements`, `status`)
SELECT 1, 'Head Barista', 'Operations', 'Flagship Downtown', 'Full-Time',
'Lead specialty espresso extraction, dial-in daily recipes, mentor baristas, and ensure exceptional beverage craftsmanship during rush hours.',
'Minimum 2 years specialty cafe experience, deep understanding of espresso sensory analysis, latte art, and grinder calibration.',
'OPEN'
WHERE NOT EXISTS (SELECT 1 FROM `job_openings` WHERE `id` = 1);

INSERT INTO `job_openings` (`id`, `title`, `department`, `location`, `type`, `description`, `requirements`, `status`)
SELECT 2, 'Barista', 'Operations', 'Multiple Branches', 'Full-Time',
'Deliver high-touch guest hospitality, pull balanced espresso shots, pour silky milk beverages, and maintain coffee bar cleanliness.',
'Strong passion for specialty coffee, friendly communication, and willingness to learn standard operating procedures. Experience preferred.',
'OPEN'
WHERE NOT EXISTS (SELECT 1 FROM `job_openings` WHERE `id` = 2);

INSERT INTO `job_openings` (`id`, `title`, `department`, `location`, `type`, `description`, `requirements`, `status`)
SELECT 3, 'Branch Accountant', 'Finance', 'Central Office', 'Full-Time',
'Audit daily branch cash reconciliations, oversee vendor invoices and procurement matching, and track real-time store operating expenses.',
'Bachelor degree in Accounting, Finance, or related field, proficiency with ledger systems and spreadsheets, high attention to detail.',
'OPEN'
WHERE NOT EXISTS (SELECT 1 FROM `job_openings` WHERE `id` = 3);

-- Create applicants table IF NOT EXISTS
CREATE TABLE IF NOT EXISTS `applicants` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `job_opening_id` INT NULL,
    `first_name` VARCHAR(150) NOT NULL,
    `last_name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(50) NOT NULL,
    `position` VARCHAR(150) NOT NULL,
    `position_applied` VARCHAR(150) NULL,
    `resume` VARCHAR(255) NULL,
    `resume_path` VARCHAR(255) NULL,
    `status` ENUM('NEW', 'REVIEWING', 'SHORTLISTED', 'REJECTED', 'HIRED') NOT NULL DEFAULT 'NEW',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create applicant rate limits table
CREATE TABLE IF NOT EXISTS `applicant_rate_limits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ip_created` (`ip_address`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
