-- Migration 050: Add device_exit_pin setting for manager exit trigger on terminals
INSERT INTO `settings` (`setting_key`, `setting_value`, `branch_id`, `updated_at`)
VALUES ('device_exit_pin', '123456', NULL, NOW())
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;
