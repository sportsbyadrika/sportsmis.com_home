-- Idempotent: adds DOB-proof slots on athletes + missing proof types.
-- Safe to paste into phpMyAdmin → SQL and re-run.

INSERT IGNORE INTO id_proof_types (name) VALUES ('Birth Certificate');
INSERT IGNORE INTO id_proof_types (name) VALUES ('School Certificate');

DROP PROCEDURE IF EXISTS sportsmis_add_dob_proof;
DELIMITER $$
CREATE PROCEDURE sportsmis_add_dob_proof()
BEGIN
    DECLARE has_col INT DEFAULT 0;

    SELECT COUNT(*) INTO has_col FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='athletes' AND COLUMN_NAME='dob_proof_type_id';
    IF has_col = 0 THEN
        ALTER TABLE athletes ADD COLUMN dob_proof_type_id INT UNSIGNED NULL;
    END IF;

    SELECT COUNT(*) INTO has_col FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='athletes' AND COLUMN_NAME='dob_proof_number';
    IF has_col = 0 THEN
        ALTER TABLE athletes ADD COLUMN dob_proof_number VARCHAR(100) NULL;
    END IF;

    SELECT COUNT(*) INTO has_col FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='athletes' AND COLUMN_NAME='dob_proof_file';
    IF has_col = 0 THEN
        ALTER TABLE athletes ADD COLUMN dob_proof_file VARCHAR(500) NULL;
    END IF;
END$$
DELIMITER ;

CALL sportsmis_add_dob_proof();
DROP PROCEDURE sportsmis_add_dob_proof;
