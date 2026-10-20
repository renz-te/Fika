-- Migration: 033_payroll_workflow.sql
-- Idempotent schema update for Payroll Approval Workflow (BHR -> BA -> BM) and Checksum Lock

-- 1. Update status ENUM on payroll and payroll_runs
ALTER TABLE `payroll` MODIFY COLUMN `status` ENUM('Draft', 'Pending Approval', 'Approved', 'Released', 'Rejected') NOT NULL DEFAULT 'Draft';

-- Check if payroll_runs table exists before modifying status
SET @run_table_exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_runs');
SET @alter_run_status = IF(@run_table_exists > 0, "ALTER TABLE `payroll_runs` MODIFY COLUMN `status` ENUM('Draft', 'Pending Approval', 'Approved', 'Released', 'Rejected') NOT NULL DEFAULT 'Draft'", "SELECT 1");
PREPARE stmt_run_status FROM @alter_run_status;
EXECUTE stmt_run_status;
DEALLOCATE PREPARE stmt_run_status;

-- 2. Helper procedure for idempotent column addition
DROP PROCEDURE IF EXISTS add_column_if_not_exists;
DELIMITER //
CREATE PROCEDURE add_column_if_not_exists(
    IN p_table_name VARCHAR(64),
    IN p_column_name VARCHAR(64),
    IN p_column_def TEXT
)
BEGIN
    DECLARE table_exists INT;
    DECLARE col_exists INT;

    SELECT COUNT(*) INTO table_exists
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table_name;

    IF table_exists > 0 THEN
        SELECT COUNT(*) INTO col_exists
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table_name
          AND COLUMN_NAME = p_column_name;

        IF col_exists = 0 THEN
            SET @sql = CONCAT('ALTER TABLE `', p_table_name, '` ADD COLUMN `', p_column_name, '` ', p_column_def);
            PREPARE alter_stmt FROM @sql;
            EXECUTE alter_stmt;
            DEALLOCATE PREPARE alter_stmt;
        END IF;
    END IF;
END //
DELIMITER ;

-- 3. Add workflow and checksum lock columns to payroll table
CALL add_column_if_not_exists('payroll', 'submitted_by', 'INT NULL');
CALL add_column_if_not_exists('payroll', 'approved_by', 'INT NULL');
CALL add_column_if_not_exists('payroll', 'released_by', 'INT NULL');
CALL add_column_if_not_exists('payroll', 'rejected_by', 'INT NULL');
CALL add_column_if_not_exists('payroll', 'rejection_reason', 'TEXT NULL');
CALL add_column_if_not_exists('payroll', 'approved_total_centavos', 'BIGINT NULL');
CALL add_column_if_not_exists('payroll', 'approved_checksum', 'CHAR(64) NULL');
CALL add_column_if_not_exists('payroll', 'approved_at', 'DATETIME NULL');
CALL add_column_if_not_exists('payroll', 'released_at', 'DATETIME NULL');

-- 4. Add workflow and checksum lock columns to payroll_runs table
CALL add_column_if_not_exists('payroll_runs', 'submitted_by', 'INT NULL');
CALL add_column_if_not_exists('payroll_runs', 'approved_by', 'INT NULL');
CALL add_column_if_not_exists('payroll_runs', 'released_by', 'INT NULL');
CALL add_column_if_not_exists('payroll_runs', 'rejected_by', 'INT NULL');
CALL add_column_if_not_exists('payroll_runs', 'rejection_reason', 'TEXT NULL');
CALL add_column_if_not_exists('payroll_runs', 'approved_total_centavos', 'BIGINT NULL');
CALL add_column_if_not_exists('payroll_runs', 'approved_checksum', 'CHAR(64) NULL');
CALL add_column_if_not_exists('payroll_runs', 'approved_at', 'DATETIME NULL');
CALL add_column_if_not_exists('payroll_runs', 'released_at', 'DATETIME NULL');

-- Clean up helper procedure
DROP PROCEDURE IF EXISTS add_column_if_not_exists;
