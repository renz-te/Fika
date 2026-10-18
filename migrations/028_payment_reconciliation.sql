-- ==========================================================
-- Migration: 028_payment_reconciliation.sql
-- Description: Create reconciliation_logs table for payment reconciliation tracking
-- ==========================================================

CREATE TABLE IF NOT EXISTS `reconciliation_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `anomaly_type` VARCHAR(50) NOT NULL,
    `order_id` INT NOT NULL,
    `payment_id` INT NULL,
    `resolved_by` INT NOT NULL,
    `resolution_note` TEXT NOT NULL,
    `resolved_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_recon_order_anomaly` (`order_id`, `anomaly_type`),
    INDEX `idx_recon_payment_anomaly` (`payment_id`, `anomaly_type`),
    INDEX `idx_recon_resolved_by` (`resolved_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
