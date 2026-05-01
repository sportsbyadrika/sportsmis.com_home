-- Idempotent: adds Units / NOC / multi-item registration columns and tables.
-- Safe to paste into phpMyAdmin → SQL and re-run.

CREATE TABLE IF NOT EXISTS event_units (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id   INT UNSIGNED NOT NULL,
    name       VARCHAR(255) NOT NULL,
    address    TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS event_registration_items (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    registration_id INT UNSIGNED NOT NULL,
    event_sport_id  INT UNSIGNED NOT NULL,
    fee             DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_reg_item (registration_id, event_sport_id),
    FOREIGN KEY (registration_id) REFERENCES event_registrations(id) ON DELETE CASCADE,
    FOREIGN KEY (event_sport_id)  REFERENCES event_sports(id)        ON DELETE CASCADE
) ENGINE=InnoDB;

DROP PROCEDURE IF EXISTS sportsmis_fix_registration_flow;
DELIMITER $$
CREATE PROCEDURE sportsmis_fix_registration_flow()
BEGIN
    DECLARE has_col INT;
    DECLARE is_null VARCHAR(3);
    DECLARE has_idx INT;

    -- events.noc_required
    SELECT COUNT(*) INTO has_col FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND COLUMN_NAME='noc_required';
    IF has_col = 0 THEN
        ALTER TABLE events ADD COLUMN noc_required ENUM('none','optional','mandatory')
              NOT NULL DEFAULT 'optional' AFTER bank_qr_code;
    END IF;

    -- event_registrations: header columns
    SELECT COUNT(*) INTO has_col FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_registrations' AND COLUMN_NAME='unit_id';
    IF has_col = 0 THEN ALTER TABLE event_registrations ADD COLUMN unit_id INT UNSIGNED NULL; END IF;

    SELECT COUNT(*) INTO has_col FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_registrations' AND COLUMN_NAME='noc_letter';
    IF has_col = 0 THEN ALTER TABLE event_registrations ADD COLUMN noc_letter VARCHAR(500) NULL; END IF;

    SELECT COUNT(*) INTO has_col FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_registrations' AND COLUMN_NAME='total_amount';
    IF has_col = 0 THEN ALTER TABLE event_registrations ADD COLUMN total_amount DECIMAL(10,2) NULL; END IF;

    SELECT COUNT(*) INTO has_col FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_registrations' AND COLUMN_NAME='transaction_date';
    IF has_col = 0 THEN ALTER TABLE event_registrations ADD COLUMN transaction_date DATE NULL; END IF;

    SELECT COUNT(*) INTO has_col FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_registrations' AND COLUMN_NAME='transaction_number';
    IF has_col = 0 THEN ALTER TABLE event_registrations ADD COLUMN transaction_number VARCHAR(100) NULL; END IF;

    SELECT COUNT(*) INTO has_col FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_registrations' AND COLUMN_NAME='transaction_proof';
    IF has_col = 0 THEN ALTER TABLE event_registrations ADD COLUMN transaction_proof VARCHAR(500) NULL; END IF;

    -- Make sport_id nullable (line items now hold per-sport refs).
    SELECT IS_NULLABLE INTO is_null FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_registrations' AND COLUMN_NAME='sport_id';
    IF UPPER(is_null) = 'NO' THEN
        ALTER TABLE event_registrations MODIFY sport_id INT UNSIGNED NULL;
    END IF;

    -- Swap (event,athlete,sport) unique for (event,athlete) — one registration per event per athlete.
    SELECT COUNT(*) INTO has_idx FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_registrations' AND INDEX_NAME='uq_reg';
    IF has_idx > 0 THEN ALTER TABLE event_registrations DROP INDEX uq_reg; END IF;

    SELECT COUNT(*) INTO has_idx FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_registrations' AND INDEX_NAME='uq_reg_event_athlete';
    IF has_idx = 0 THEN
        ALTER TABLE event_registrations ADD UNIQUE KEY uq_reg_event_athlete (event_id, athlete_id);
    END IF;
END$$
DELIMITER ;

CALL sportsmis_fix_registration_flow();
DROP PROCEDURE sportsmis_fix_registration_flow;
