-- Switch firm currency to Mauritian rupees and enable language setting.
-- Safe to run on an existing legal_system database.

INSERT INTO settings (setting_key, setting_value) VALUES ('payment_currency', 'MUR')
ON DUPLICATE KEY UPDATE setting_value = 'MUR';

INSERT INTO settings (setting_key, setting_value) VALUES ('app_language', 'en')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
