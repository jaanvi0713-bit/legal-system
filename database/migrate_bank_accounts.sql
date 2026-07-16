-- Bank account selection on invoices (safe to re-run)
ALTER TABLE invoices
    ADD COLUMN IF NOT EXISTS bank_account_id TINYINT UNSIGNED DEFAULT NULL AFTER created_by;
