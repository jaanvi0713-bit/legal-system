-- Lawyer weekly time-slot availability (Mon–Sat), per calendar week
CREATE TABLE IF NOT EXISTS lawyer_availability_slots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lawyer_id INT UNSIGNED NOT NULL,
    week_start DATE NOT NULL COMMENT 'Monday of the week',
    day_of_week TINYINT UNSIGNED NOT NULL COMMENT '1=Mon ... 6=Sat',
    slot_time TIME NOT NULL,
    UNIQUE KEY uniq_lawyer_week_day_slot (lawyer_id, week_start, day_of_week, slot_time),
    INDEX idx_lawyer_week (lawyer_id, week_start),
    CONSTRAINT fk_lawyer_availability_lawyer
        FOREIGN KEY (lawyer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Upgrade legacy installs (single repeating week → per-week rows)
-- Run only if week_start column is missing:
-- ALTER TABLE lawyer_availability_slots ADD COLUMN week_start DATE NOT NULL DEFAULT '1970-01-01' AFTER lawyer_id;
-- UPDATE lawyer_availability_slots SET week_start = DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY);
-- ALTER TABLE lawyer_availability_slots DROP INDEX uniq_lawyer_day_slot;
-- ALTER TABLE lawyer_availability_slots ADD UNIQUE KEY uniq_lawyer_week_day_slot (lawyer_id, week_start, day_of_week, slot_time);
-- ALTER TABLE lawyer_availability_slots ADD INDEX idx_lawyer_week (lawyer_id, week_start);
