-- Multi-lawyer case teams and assignable case tasks
CREATE TABLE IF NOT EXISTS case_lawyers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    lawyer_id INT UNSIGNED NOT NULL,
    role ENUM('lead','associate') NOT NULL DEFAULT 'associate',
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT UNSIGNED DEFAULT NULL,
    UNIQUE KEY uniq_case_lawyer (case_id, lawyer_id),
    INDEX idx_lawyer (lawyer_id),
    CONSTRAINT fk_case_lawyers_case FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    CONSTRAINT fk_case_lawyers_lawyer FOREIGN KEY (lawyer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO case_lawyers (case_id, lawyer_id, role)
SELECT id, lawyer_id, 'lead' FROM cases WHERE lawyer_id IS NOT NULL;

CREATE TABLE IF NOT EXISTS case_tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    assigned_to INT UNSIGNED DEFAULT NULL,
    due_date DATE DEFAULT NULL,
    status ENUM('open','in_progress','done','cancelled') NOT NULL DEFAULT 'open',
    created_by INT UNSIGNED DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_case (case_id),
    INDEX idx_assignee (assigned_to, status),
    CONSTRAINT fk_case_tasks_case FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    CONSTRAINT fk_case_tasks_assignee FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
