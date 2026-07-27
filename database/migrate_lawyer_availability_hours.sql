-- Per-lawyer bookable hours (start, end, slot interval)
ALTER TABLE users ADD COLUMN IF NOT EXISTS availability_start TIME NOT NULL DEFAULT '09:00:00' AFTER availability;
ALTER TABLE users ADD COLUMN IF NOT EXISTS availability_end TIME NOT NULL DEFAULT '17:00:00' AFTER availability_start;
ALTER TABLE users ADD COLUMN IF NOT EXISTS availability_interval SMALLINT UNSIGNED NOT NULL DEFAULT 30 AFTER availability_end;
