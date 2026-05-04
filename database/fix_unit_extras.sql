-- Idempotent: adds unit_reg_no + unit_name_other on event_registrations.
-- Safe to paste into phpMyAdmin → SQL.

DROP PROCEDURE IF EXISTS sportsmis_add_unit_extras;
DELIMITER $$
CREATE PROCEDURE sportsmis_add_unit_extras()
BEGIN
    DECLARE has_col INT DEFAULT 0;

    SELECT COUNT(*) INTO has_col FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_registrations'
       AND COLUMN_NAME='unit_reg_no';
    IF has_col = 0 THEN
        ALTER TABLE event_registrations ADD COLUMN unit_reg_no VARCHAR(100) NULL;
    END IF;

    SELECT COUNT(*) INTO has_col FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_registrations'
       AND COLUMN_NAME='unit_name_other';
    IF has_col = 0 THEN
        ALTER TABLE event_registrations ADD COLUMN unit_name_other VARCHAR(255) NULL;
    END IF;
END$$
DELIMITER ;

CALL sportsmis_add_unit_extras();
DROP PROCEDURE sportsmis_add_unit_extras;
