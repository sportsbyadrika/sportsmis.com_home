-- Idempotent: adds the competitor_number column and per-event uniqueness.
-- Safe to paste into phpMyAdmin → SQL.

DROP PROCEDURE IF EXISTS sportsmis_add_competitor_number;
DELIMITER $$
CREATE PROCEDURE sportsmis_add_competitor_number()
BEGIN
    DECLARE has_col INT DEFAULT 0;
    DECLARE has_idx INT DEFAULT 0;

    SELECT COUNT(*) INTO has_col FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_registrations'
       AND COLUMN_NAME='competitor_number';
    IF has_col = 0 THEN
        ALTER TABLE event_registrations ADD COLUMN competitor_number INT UNSIGNED NULL;
    END IF;

    SELECT COUNT(*) INTO has_idx FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_registrations'
       AND INDEX_NAME='uq_event_competitor';
    IF has_idx = 0 THEN
        ALTER TABLE event_registrations
          ADD UNIQUE KEY uq_event_competitor (event_id, competitor_number);
    END IF;
END$$
DELIMITER ;

CALL sportsmis_add_competitor_number();
DROP PROCEDURE sportsmis_add_competitor_number;
