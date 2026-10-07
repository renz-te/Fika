-- ==========================================================
-- Migration: 005_employee_fields.sql
-- Description: Align employee fields, encrypted PII, and docs
-- ==========================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS `Migrate_005_AddCol`$$
CREATE PROCEDURE `Migrate_005_AddCol`(IN tblName VARCHAR(255), IN colName VARCHAR(255), IN colDef VARCHAR(255))
BEGIN
    DECLARE _count INT;
    SET _count = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tblName AND COLUMN_NAME = colName);
    IF _count = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', tblName, '` ADD COLUMN `', colName, '` ', colDef);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DROP PROCEDURE IF EXISTS `Migrate_005_RenameCol`$$
CREATE PROCEDURE `Migrate_005_RenameCol`(IN tblName VARCHAR(255), IN oldColName VARCHAR(255), IN newColName VARCHAR(255), IN colDef VARCHAR(255))
BEGIN
    DECLARE _count INT;
    DECLARE _count_new INT;
    SET _count = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tblName AND COLUMN_NAME = oldColName);
    SET _count_new = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tblName AND COLUMN_NAME = newColName);
    
    IF _count = 1 AND _count_new = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', tblName, '` CHANGE COLUMN `', oldColName, '` `', newColName, '` ', colDef);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DROP PROCEDURE IF EXISTS `Migrate_005_AlterEnum`$$
CREATE PROCEDURE `Migrate_005_AlterEnum`(IN tblName VARCHAR(255), IN colName VARCHAR(255), IN colDef VARCHAR(255))
BEGIN
    SET @sql = CONCAT('ALTER TABLE `', tblName, '` MODIFY COLUMN `', colName, '` ', colDef);
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
END$$

DROP PROCEDURE IF EXISTS `Migrate_005_AddUniqueIndex`$$
CREATE PROCEDURE `Migrate_005_AddUniqueIndex`(IN tblName VARCHAR(255), IN idxName VARCHAR(255), IN colName VARCHAR(255))
BEGIN
    DECLARE _count INT;
    SET _count = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tblName AND INDEX_NAME = idxName);
    IF _count = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', tblName, '` ADD UNIQUE INDEX `', idxName, '` (`', colName, '`)');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

-- Execute schema updates
CALL Migrate_005_RenameCol('employees', 'employee_id', 'employee_code', 'VARCHAR(80) NOT NULL');
CALL Migrate_005_RenameCol('employees', 'bank_account', 'bank_no_enc', 'VARCHAR(255)');
CALL Migrate_005_RenameCol('employees', 'tin', 'tin_enc', 'VARCHAR(255)');
CALL Migrate_005_RenameCol('employees', 'sss', 'sss_no_enc', 'VARCHAR(255)');
CALL Migrate_005_RenameCol('employees', 'philhealth', 'philhealth_no_enc', 'VARCHAR(255)');
CALL Migrate_005_RenameCol('employees', 'pagibig', 'pagibig_no_enc', 'VARCHAR(255)');
CALL Migrate_005_RenameCol('employees', 'hourly_rate', 'basic_salary', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00');

CALL Migrate_005_AddCol('employees', 'pin_hash', 'VARCHAR(255) DEFAULT NULL');
CALL Migrate_005_AddCol('employees', 'photo_path', 'VARCHAR(255) DEFAULT NULL');

-- Update ENUMs
CALL Migrate_005_AlterEnum('employees', 'employment_type', "ENUM('REGULAR','PROBATIONARY','PART_TIME') DEFAULT 'PROBATIONARY'");
CALL Migrate_005_AlterEnum('employees', 'status', "ENUM('ACTIVE','INACTIVE','SEPARATED') NOT NULL DEFAULT 'ACTIVE'");

-- Make employee_code unique
CALL Migrate_005_AddUniqueIndex('employees', 'uq_employee_code', 'employee_code');

-- Clean up
DROP PROCEDURE IF EXISTS `Migrate_005_AddCol`;
DROP PROCEDURE IF EXISTS `Migrate_005_RenameCol`;
DROP PROCEDURE IF EXISTS `Migrate_005_AlterEnum`;
DROP PROCEDURE IF EXISTS `Migrate_005_AddUniqueIndex`;

-- Create employee documents table
CREATE TABLE IF NOT EXISTS `employee_documents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT NOT NULL,
    `type` VARCHAR(100) NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `uploaded_by` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_emp_docs_employee_id` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_emp_docs_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
