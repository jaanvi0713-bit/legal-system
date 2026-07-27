-- Per-day working hours and slot intervals for each calendar week (Mon–Sat).
CREATE TABLE IF NOT EXISTS lawyer_availability_day_settings (
    lawyer_id INT UNSIGNED NOT NULL,
    week_start DATE NOT NULL,
    day_of_week TINYINT UNSIGNED NOT NULL COMMENT '1=Mon ... 6=Sat',
    start_time TIME NOT NULL DEFAULT '09:00:00',
    end_time TIME NOT NULL DEFAULT '17:00:00',
    interval_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    PRIMARY KEY (lawyer_id, week_start, day_of_week),
    INDEX idx_lawyer_day_week (lawyer_id, week_start),
    CONSTRAINT fk_lawyer_availability_day_settings_lawyer
        FOREIGN KEY (lawyer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
