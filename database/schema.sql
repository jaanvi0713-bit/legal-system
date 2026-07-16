-- Legal Case Management System - Database Schema
CREATE DATABASE IF NOT EXISTS `legal_system` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `legal_system`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS ai_chat_messages;
DROP TABLE IF EXISTS ai_chat_sessions;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS court_hearings;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS case_documents;
DROP TABLE IF EXISTS case_notes;
DROP TABLE IF EXISTS cases;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role ENUM('admin','lawyer','client','staff') NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(191) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    specialization VARCHAR(150) DEFAULT NULL,
    bar_number VARCHAR(50) DEFAULT NULL,
    company_name VARCHAR(150) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    permissions JSON DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    availability ENUM('available','busy','unavailable') DEFAULT 'available',
    assigned_lawyer_id INT UNSIGNED DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    last_login DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_assigned_lawyer (assigned_lawyer_id)
) ENGINE=InnoDB;

CREATE TABLE cases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_number VARCHAR(50) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    case_type VARCHAR(100) DEFAULT NULL,
    status ENUM('open','active','pending','on_hold','closed','reopened') NOT NULL DEFAULT 'open',
    priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    client_id INT UNSIGNED NOT NULL,
    lawyer_id INT UNSIGNED DEFAULT NULL,
    court_name VARCHAR(191) DEFAULT NULL,
    court_location VARCHAR(191) DEFAULT NULL,
    filing_date DATE DEFAULT NULL,
    next_hearing_date DATE DEFAULT NULL,
    closed_at DATETIME DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lawyer_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_client (client_id),
    INDEX idx_lawyer (lawyer_id)
) ENGINE=InnoDB;

CREATE TABLE case_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    note TEXT NOT NULL,
    is_private TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE case_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED DEFAULT NULL,
    client_id INT UNSIGNED DEFAULT NULL,
    uploaded_by INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100) DEFAULT NULL,
    file_size INT UNSIGNED DEFAULT 0,
    category ENUM('evidence','contract','legal','court','invoice','other') NOT NULL DEFAULT 'other',
    description TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE SET NULL,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE appointments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    appointment_type ENUM('meeting','consultation','hearing','other') NOT NULL DEFAULT 'meeting',
    case_id INT UNSIGNED DEFAULT NULL,
    client_id INT UNSIGNED DEFAULT NULL,
    lawyer_id INT UNSIGNED DEFAULT NULL,
    scheduled_at DATETIME NOT NULL,
    duration_minutes INT UNSIGNED DEFAULT 60,
    location VARCHAR(255) DEFAULT NULL,
    status ENUM('scheduled','confirmed','rescheduled','pending','completed','cancelled') NOT NULL DEFAULT 'pending',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE SET NULL,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (lawyer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE court_hearings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    hearing_date DATETIME NOT NULL,
    court_name VARCHAR(191) NOT NULL,
    court_location VARCHAR(191) DEFAULT NULL,
    judge_name VARCHAR(150) DEFAULT NULL,
    hearing_type VARCHAR(100) DEFAULT NULL,
    outcome TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    status ENUM('scheduled','completed','adjourned','cancelled') NOT NULL DEFAULT 'scheduled',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    case_id INT UNSIGNED DEFAULT NULL,
    client_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax DECIMAL(12,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('draft','sent','paid','partial','overdue','cancelled') NOT NULL DEFAULT 'draft',
    due_date DATE DEFAULT NULL,
    issued_at DATE DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    bank_account_id TINYINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE SET NULL,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE invoice_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    vat_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    INDEX idx_invoice_items_invoice (invoice_id),
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED DEFAULT NULL,
    client_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM('cash','card','bank_transfer','cheque','online','other') NOT NULL DEFAULT 'bank_transfer',
    reference_number VARCHAR(100) DEFAULT NULL,
    receipt_number VARCHAR(50) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    recorded_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_id INT UNSIGNED NOT NULL,
    receiver_id INT UNSIGNED NOT NULL,
    case_id INT UNSIGNED DEFAULT NULL,
    subject VARCHAR(255) DEFAULT NULL,
    body TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'info',
    link VARCHAR(500) DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE activity_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) DEFAULT NULL,
    entity_id INT UNSIGNED DEFAULT NULL,
    description TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE ai_chat_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    portal ENUM('admin','lawyer','client') NOT NULL,
    title VARCHAR(255) DEFAULT 'New Chat',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ai_chat_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL,
    role ENUM('user','assistant','system') NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES ai_chat_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB;
