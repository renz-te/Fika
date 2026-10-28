-- Migration 050: Demo Seed Tracking Table
-- Tracks demo data inserted across phases for idempotent cleanup and resetting

CREATE TABLE IF NOT EXISTS `demo_seed` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `phase` VARCHAR(20) NOT NULL,
  `table_name` VARCHAR(64) NOT NULL,
  `row_id` BIGINT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_table_row` (`table_name`, `row_id`),
  INDEX `idx_phase` (`phase`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
