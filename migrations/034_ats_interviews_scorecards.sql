-- ==========================================================
-- Migration: 034_ats_interviews_scorecards.sql
-- Description: Create interviews, interview_scorecards, applicant_logs
--              and add branch_id, stage, category to applicants
-- ==========================================================

SET @colExists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'branch_id'
);
SET @stmt = IF(@colExists = 0, 'ALTER TABLE `applicants` ADD COLUMN `branch_id` INT NULL DEFAULT NULL AFTER `job_opening_id`', 'SELECT 1');
PREPARE ddlStmt FROM @stmt; EXECUTE ddlStmt; DEALLOCATE PREPARE ddlStmt;

SET @colExists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'stage'
);
SET @stmt = IF(@colExists = 0, 'ALTER TABLE `applicants` ADD COLUMN `stage` VARCHAR(50) NOT NULL DEFAULT \'New\' AFTER `status`', 'SELECT 1');
PREPARE ddlStmt FROM @stmt; EXECUTE ddlStmt; DEALLOCATE PREPARE ddlStmt;

SET @colExists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'employment_category'
);
SET @stmt = IF(@colExists = 0, 'ALTER TABLE `applicants` ADD COLUMN `employment_category` VARCHAR(50) NOT NULL DEFAULT \'Full-Time\' AFTER `stage`', 'SELECT 1');
PREPARE ddlStmt FROM @stmt; EXECUTE ddlStmt; DEALLOCATE PREPARE ddlStmt;

SET @colExists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'rejection_reason'
);
SET @stmt = IF(@colExists = 0, 'ALTER TABLE `applicants` ADD COLUMN `rejection_reason` VARCHAR(255) NULL DEFAULT NULL AFTER `employment_category`', 'SELECT 1');
PREPARE ddlStmt FROM @stmt; EXECUTE ddlStmt; DEALLOCATE PREPARE ddlStmt;

CREATE TABLE IF NOT EXISTS `interviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `applicant_id` INT NOT NULL,
    `interviewer_id` INT NULL,
    `interview_date` DATE NULL,
    `interview_time` TIME NULL,
    `end_time` TIME NULL,
    `scheduled_at` DATETIME NULL,
    `stage` VARCHAR(50) DEFAULT 'Initial Interview',
    `status` ENUM('Scheduled','Completed','Cancelled','AWOL') DEFAULT 'Scheduled',
    `notes` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_interviews_applicant` (`applicant_id`),
    KEY `idx_interviews_interviewer` (`interviewer_id`),
    KEY `idx_interviews_date` (`interview_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `interview_scorecards` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `interview_id` INT NOT NULL,
    `technical_score` INT DEFAULT 0,
    `communication_score` INT DEFAULT 0,
    `culture_score` INT DEFAULT 0,
    `reliability_score` INT DEFAULT 0,
    `problem_solving_score` INT DEFAULT 0,
    `strengths` TEXT NULL,
    `concerns` TEXT NULL,
    `recommendation` VARCHAR(50) NULL,
    `hourly_wage` DECIMAL(10,2) NULL,
    `monthly_wage` DECIMAL(10,2) NULL,
    `global_pool_reason` VARCHAR(255) NULL,
    `created_by` VARCHAR(150) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_scorecard_interview` (`interview_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `applicant_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `applicant_id` INT NOT NULL,
    `user_name` VARCHAR(150) NULL,
    `action` VARCHAR(255) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_logs_applicant` (`applicant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
