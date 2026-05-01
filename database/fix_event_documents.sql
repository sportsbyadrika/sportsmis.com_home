-- Idempotent: creates the per-event Documents table.
-- Safe to paste into phpMyAdmin → SQL.

CREATE TABLE IF NOT EXISTS event_documents (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id   INT UNSIGNED NOT NULL,
    name       VARCHAR(255) NOT NULL,
    purpose    TEXT NULL,
    file       VARCHAR(500) NULL,
    status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB;
