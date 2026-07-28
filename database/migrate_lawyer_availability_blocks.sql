-- Lawyer availability as date + time range blocks (available / not available)
CREATE TABLE IF NOT EXISTS lawyer_availability_blocks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lawyer_id INT UNSIGNED NOT NULL,
    block_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_lawyer_date (lawyer_id, block_date),
    CONSTRAINT fk_lawyer_availability_blocks_lawyer
        FOREIGN KEY (lawyer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
