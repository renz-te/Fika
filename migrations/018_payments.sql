-- Migration 018: Payments Schema
-- Purpose: Support digital payments (GCash, Maya) via PayMongo with idempotency, shift attribution, and digital reconciliation.

-- 1. Ensure pos_sessions table exists for shift attribution foreign key
CREATE TABLE IF NOT EXISTS pos_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  branch_id INT NOT NULL DEFAULT 1,
  cashier_id INT NOT NULL,
  opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  closed_at TIMESTAMP NULL,
  opening_float DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  expected_cash DECIMAL(10,2) NULL,
  closing_cash DECIMAL(10,2) NULL,
  variance DECIMAL(10,2) NULL,
  status ENUM('OPEN','CLOSED') DEFAULT 'OPEN'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sync existing cash_sessions data into pos_sessions if empty
INSERT IGNORE INTO pos_sessions (id, branch_id, cashier_id, opened_at, closed_at, opening_float, expected_cash, closing_cash, variance, status)
SELECT id, branch_id, cashier_id, opened_at, closed_at, opening_float, expected_cash, closing_cash, variance, status 
FROM cash_sessions;

-- 2. Create payments table
CREATE TABLE IF NOT EXISTS payments (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  order_id INT NOT NULL,
  shift_id INT NOT NULL,
  intent_id VARCHAR(64) NOT NULL UNIQUE, -- idempotency
  method VARCHAR(32) NOT NULL,           -- gcash, card, maya
  gross_amount DECIMAL(12,2) NOT NULL,
  fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  net_amount DECIMAL(12,2) NOT NULL,
  status VARCHAR(24) NOT NULL,           -- pending, paid, failed, refunded
  paid_at DATETIME NULL,
  raw_payload JSON NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (shift_id) REFERENCES pos_sessions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Ensure order_audit_logs supports payment mismatch audit entries
ALTER TABLE order_audit_logs 
  MODIFY COLUMN action VARCHAR(32) NOT NULL,
  MODIFY COLUMN supervisor_id INT NULL;
