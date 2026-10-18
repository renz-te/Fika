-- ==========================================================
-- Migration: 021_receipt_settings.sql
-- Description: Seed default 58mm thermal receipt and BIR settings
-- ==========================================================

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('receipt_business_name', 'Fika Coffee & Pastries'),
('receipt_address', '123 Coffee St., BGC, Taguig City'),
('receipt_tin', 'TIN: 123-456-789-00000 VAT'),
('receipt_footer', 'Thank you for your visit!'),
('receipt_bir_registered', '1'),
('bir_registered', '1')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
