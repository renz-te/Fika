-- Migration 021: Add status and financial_impact to pos_inventory_transactions
-- Purpose: Formalize schema updates from legacy temp_mig.php for transaction status and financial impact.

CREATE TABLE IF NOT EXISTS pos_inventory_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inventory_id INT NOT NULL,
    branch_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending', 'Approved', 'Rejected', 'Completed') DEFAULT 'Completed',
    remarks TEXT NULL,
    evidence_img VARCHAR(255) NULL,
    financial_impact DECIMAL(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_purchase_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    inventory_id INT NOT NULL,
    requested_qty DECIMAL(10,2) NOT NULL,
    status ENUM('Pending', 'Approved', 'Ordered') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Standard ALTER statement for existing table installations:
-- Run if pos_inventory_transactions exists without status or financial_impact columns.
ALTER TABLE pos_inventory_transactions 
    ADD COLUMN status ENUM('Pending', 'Approved', 'Rejected', 'Completed') DEFAULT 'Completed',
    ADD COLUMN remarks TEXT NULL,
    ADD COLUMN evidence_img VARCHAR(255) NULL,
    ADD COLUMN financial_impact DECIMAL(10,2) DEFAULT 0.00;
