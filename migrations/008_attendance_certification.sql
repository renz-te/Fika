-- ==========================================================
-- Migration: 008_attendance_certification.sql
-- Description: Table for Attendance Certification
-- ==========================================================

CREATE TABLE IF NOT EXISTS `attendance_certifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `scope` ENUM('BRANCH', 'OFFICIALS', 'HQ') NOT NULL,
    `branch_id` INT DEFAULT NULL,
    `employee_id` INT DEFAULT NULL,
    `period_start` DATE NOT NULL,
    `period_end` DATE NOT NULL,
    `certified_by` INT NOT NULL,
    `certified_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('CERTIFIED', 'REOPENED') NOT NULL DEFAULT 'CERTIFIED',
    
    CONSTRAINT `fk_att_cert_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_att_cert_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_att_cert_user` FOREIGN KEY (`certified_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
