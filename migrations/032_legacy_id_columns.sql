-- ==========================================================
-- Migration: 032_legacy_id_columns.sql
-- Description: Add nullable legacy_id INT with UNIQUE indexes across core tables
--              for idempotent legacy data import and link tracking (Phase 17A/B).
--              Ensure applicants table has notes column for legacy interview data.
-- ==========================================================

DROP PROCEDURE IF EXISTS AddLegacyIdColumn;
DELIMITER //
CREATE PROCEDURE AddLegacyIdColumn(IN tbl VARCHAR(64))
BEGIN
    SET @col_count = (
        SELECT COUNT(*) FROM information_schema.columns 
        WHERE table_schema = DATABASE() AND table_name = tbl AND column_name = 'legacy_id'
    );
    IF @col_count = 0 THEN
        SET @stmt = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN `legacy_id` INT NULL UNIQUE');
        PREPARE s FROM @stmt;
        EXECUTE s;
        DEALLOCATE PREPARE s;
    END IF;
END //
DELIMITER ;

-- Core administrative & organizational tables
CALL AddLegacyIdColumn('branches');
CALL AddLegacyIdColumn('roles');
CALL AddLegacyIdColumn('users');

-- HRMS & Personnel
CALL AddLegacyIdColumn('employees');
CALL AddLegacyIdColumn('employee_documents');
CALL AddLegacyIdColumn('attendance_logs');
CALL AddLegacyIdColumn('leave_requests');
CALL AddLegacyIdColumn('shifts');
CALL AddLegacyIdColumn('open_shifts');
CALL AddLegacyIdColumn('payroll_runs');
CALL AddLegacyIdColumn('payroll_items');
CALL AddLegacyIdColumn('branch_budgets');
CALL AddLegacyIdColumn('applicants');

-- Supply Chain & Inventory
CALL AddLegacyIdColumn('inventory_items');
CALL AddLegacyIdColumn('inventory_stock');
CALL AddLegacyIdColumn('inventory_ledger');
CALL AddLegacyIdColumn('purchase_orders');

-- POS Catalog & Recipes
CALL AddLegacyIdColumn('categories');
CALL AddLegacyIdColumn('products');
CALL AddLegacyIdColumn('modifiers');
CALL AddLegacyIdColumn('recipes');

-- POS Registers & Transactions
CALL AddLegacyIdColumn('cash_sessions');
CALL AddLegacyIdColumn('orders');
CALL AddLegacyIdColumn('order_items');
CALL AddLegacyIdColumn('order_item_modifiers');
CALL AddLegacyIdColumn('order_audit');
CALL AddLegacyIdColumn('payments');
CALL AddLegacyIdColumn('audit_logs');

DROP PROCEDURE IF EXISTS AddLegacyIdColumn;

-- Ensure applicants table has notes column for preserving legacy interview scores & feedback
DROP PROCEDURE IF EXISTS AddApplicantNotesColumn;
DELIMITER //
CREATE PROCEDURE AddApplicantNotesColumn()
BEGIN
    SET @col_count = (
        SELECT COUNT(*) FROM information_schema.columns 
        WHERE table_schema = DATABASE() AND table_name = 'applicants' AND column_name = 'notes'
    );
    IF @col_count = 0 THEN
        ALTER TABLE `applicants` ADD COLUMN `notes` TEXT NULL AFTER `status`;
    END IF;
END //
DELIMITER ;

CALL AddApplicantNotesColumn();
DROP PROCEDURE IF EXISTS AddApplicantNotesColumn;
