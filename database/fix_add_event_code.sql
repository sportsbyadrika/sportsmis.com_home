-- Idempotent: adds event_sports.event_code if it is missing.
-- Safe to paste into phpMyAdmin → SQL and re-run.

DROP PROCEDURE IF EXISTS sportsmis_add_event_code;
DELIMITER $$
CREATE PROCEDURE sportsmis_add_event_code()
BEGIN
    DECLARE has_col INT DEFAULT 0;
    SELECT COUNT(*) INTO has_col
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'event_sports'
       AND COLUMN_NAME  = 'event_code';
    IF has_col = 0 THEN
        ALTER TABLE event_sports ADD COLUMN event_code VARCHAR(50) NULL AFTER sport_event_id;
    END IF;
END$$
DELIMITER ;

CALL sportsmis_add_event_code();
DROP PROCEDURE sportsmis_add_event_code;
