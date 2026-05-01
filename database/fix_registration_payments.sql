-- Idempotent: adds the multi-transaction + admin-review schema.
-- Safe to paste into phpMyAdmin → SQL.

CREATE TABLE IF NOT EXISTS event_registration_payments (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    registration_id    INT UNSIGNED NOT NULL,
    transaction_date   DATE NOT NULL,
    transaction_number VARCHAR(100) NOT NULL,
    amount             DECIMAL(10,2) NOT NULL,
    proof_file         VARCHAR(500) NULL,
    status             ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    rejection_reason   TEXT NULL,
    reviewed_by        INT UNSIGNED NULL,
    reviewed_at        TIMESTAMP NULL,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (registration_id) REFERENCES event_registrations(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by)     REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

DROP PROCEDURE IF EXISTS sportsmis_add_review_columns;
DELIMITER $$
CREATE PROCEDURE sportsmis_add_review_columns()
BEGIN
    DECLARE has_col INT DEFAULT 0;

    SELECT COUNT(*) INTO has_col FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_registrations' AND COLUMN_NAME='admin_review_status';
    IF has_col = 0 THEN
        ALTER TABLE event_registrations ADD COLUMN admin_review_status ENUM('pending','approved','rejected','returned') NULL;
    END IF;

    SELECT COUNT(*) INTO has_col FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_registrations' AND COLUMN_NAME='admin_review_notes';
    IF has_col = 0 THEN
        ALTER TABLE event_registrations ADD COLUMN admin_review_notes TEXT NULL;
    END IF;

    SELECT COUNT(*) INTO has_col FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_registrations' AND COLUMN_NAME='admin_reviewed_by';
    IF has_col = 0 THEN
        ALTER TABLE event_registrations ADD COLUMN admin_reviewed_by INT UNSIGNED NULL;
    END IF;

    SELECT COUNT(*) INTO has_col FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_registrations' AND COLUMN_NAME='admin_reviewed_at';
    IF has_col = 0 THEN
        ALTER TABLE event_registrations ADD COLUMN admin_reviewed_at TIMESTAMP NULL;
    END IF;

    SELECT COUNT(*) INTO has_col FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_registrations' AND COLUMN_NAME='submitted_at';
    IF has_col = 0 THEN
        ALTER TABLE event_registrations ADD COLUMN submitted_at TIMESTAMP NULL;
    END IF;
END$$
DELIMITER ;

CALL sportsmis_add_review_columns();
DROP PROCEDURE sportsmis_add_review_columns;
