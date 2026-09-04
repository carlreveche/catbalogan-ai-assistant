-- Catbalogan AI Assistant database schema
-- Create/select the database before importing this file.

SET NAMES utf8mb4;

CREATE TABLE users (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_verified TINYINT(1) NOT NULL DEFAULT 1,
    role VARCHAR(20) NOT NULL DEFAULT 'citizen',
    status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
    google_id VARCHAR(255) NULL,
    avatar VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_google_id (google_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permits (
    id INT NOT NULL AUTO_INCREMENT,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(150) NOT NULL,
    office VARCHAR(150) NOT NULL,
    description TEXT NULL,
    requirements TEXT NULL,
    steps TEXT NULL,
    fees VARCHAR(255) NULL,
    processing_time VARCHAR(150) NULL,
    validity VARCHAR(150) NULL,
    address VARCHAR(255) NULL,
    contact VARCHAR(150) NULL,
    verified_at DATE NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permits_code (code),
    KEY idx_permits_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE kb_entries (
    id INT NOT NULL AUTO_INCREMENT,
    permit_id INT NULL,
    intent VARCHAR(50) NOT NULL,
    keywords TEXT NOT NULL,
    answer TEXT NOT NULL,
    priority INT NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_kb_permit_intent (permit_id, intent),
    CONSTRAINT fk_kb_permit
        FOREIGN KEY (permit_id) REFERENCES permits (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE chats (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    conversation_id VARCHAR(64) NOT NULL,
    role ENUM('user', 'assistant') NOT NULL,
    message TEXT NOT NULL,
    title VARCHAR(120) NULL,
    matched_topic VARCHAR(100) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_chats_user_conversation (user_id, conversation_id, id),
    KEY idx_chats_topic (matched_topic),
    KEY idx_chats_created_at (created_at),
    CONSTRAINT fk_chats_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE chat_feedback (
    id INT NOT NULL AUTO_INCREMENT,
    chat_id INT NOT NULL,
    is_helpful TINYINT(1) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_feedback_chat (chat_id),
    CONSTRAINT fk_feedback_chat
        FOREIGN KEY (chat_id) REFERENCES chats (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_logs (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NULL,
    user_name VARCHAR(150) NULL,
    action VARCHAR(50) NOT NULL,
    details VARCHAR(500) NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_activity_created_at (created_at),
    KEY idx_activity_action (action),
    CONSTRAINT fk_activity_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
