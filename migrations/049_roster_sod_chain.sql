-- Migration 049: Implement 4-step Separation of Duties (SoD) Chain for Roster Scheduling
-- Flow: BHR Drafts -> BM Manpower Review -> BA Budget Approval -> BM Publishes

-- 1. Update status ENUM on shifts table
ALTER TABLE `shifts` 
MODIFY COLUMN `status` ENUM('DRAFT', 'PENDING_BM', 'PENDING_BA', 'APPROVED', 'PUBLISHED', 'REJECTED') NOT NULL DEFAULT 'DRAFT';

-- 2. Add bm_screened_by column if not exists
SET @colBm = (
    SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'shifts' 
      AND COLUMN_NAME = 'bm_screened_by'
);
SET @sqlBm = IF(@colBm = 0, 'ALTER TABLE `shifts` ADD COLUMN `bm_screened_by` INT NULL DEFAULT NULL AFTER `edit_reason`', 'SELECT 1');
PREPARE stmtBm FROM @sqlBm;
EXECUTE stmtBm;
DEALLOCATE PREPARE stmtBm;

-- 3. Add ba_approved_by column if not exists
SET @colBa = (
    SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'shifts' 
      AND COLUMN_NAME = 'ba_approved_by'
);
SET @sqlBa = IF(@colBa = 0, 'ALTER TABLE `shifts` ADD COLUMN `ba_approved_by` INT NULL DEFAULT NULL AFTER `bm_screened_by`', 'SELECT 1');
PREPARE stmtBa FROM @sqlBa;
EXECUTE stmtBa;
DEALLOCATE PREPARE stmtBa;

-- 4. Add rejection_reason column if not exists
SET @colRej = (
    SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'shifts' 
      AND COLUMN_NAME = 'rejection_reason'
);
SET @sqlRej = IF(@colRej = 0, 'ALTER TABLE `shifts` ADD COLUMN `rejection_reason` TEXT NULL DEFAULT NULL AFTER `ba_approved_by`', 'SELECT 1');
PREPARE stmtRej FROM @sqlRej;
EXECUTE stmtRej;
DEALLOCATE PREPARE stmtRej;
