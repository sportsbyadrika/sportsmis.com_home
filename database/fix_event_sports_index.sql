-- ─────────────────────────────────────────────────────────────────────────────
--  SportsMIS — fix duplicate-entry on event_sports + sport hierarchy schema.
--  Idempotent: safe to run multiple times. Paste the whole file into
--  phpMyAdmin → SQL, or:  mysql -u <user> -p <database> < this_file.sql
-- ─────────────────────────────────────────────────────────────────────────────

-- 1. New tables (no-op if they already exist)
CREATE TABLE IF NOT EXISTS sport_categories (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sport_id   INT UNSIGNED NOT NULL,
    name       VARCHAR(150) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sport_category (sport_id, name),
    FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS age_categories (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL UNIQUE,
    min_age    INT UNSIGNED NULL,
    max_age    INT UNSIGNED NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sport_events (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sport_id        INT UNSIGNED NOT NULL,
    category_id     INT UNSIGNED NOT NULL,
    age_category_id INT UNSIGNED NOT NULL,
    gender          ENUM('male','female','mixed') NOT NULL,
    weight          VARCHAR(50)  NULL,
    height          VARCHAR(50)  NULL,
    para            TINYINT(1)   NOT NULL DEFAULT 0,
    name            VARCHAR(255) NOT NULL,
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sport_id)        REFERENCES sports(id)            ON DELETE CASCADE,
    FOREIGN KEY (category_id)     REFERENCES sport_categories(id)  ON DELETE CASCADE,
    FOREIGN KEY (age_category_id) REFERENCES age_categories(id)
) ENGINE=InnoDB;

-- 2. Default age categories (skipped if already present)
INSERT IGNORE INTO age_categories (name, min_age, max_age, sort_order) VALUES
('Sub Youth',     NULL,  14, 1),
('Youth',           14,  17, 2),
('Junior',          17,  20, 3),
('Senior',          20,  35, 4),
('Master',          35,  50, 5),
('Senior Master',   50, NULL, 6);

-- 3. Idempotent fix-up of event_sports — order matters:
--      a) add sport_event_id column,
--      b) add the WIDER unique index (so the event_id FK can move to it),
--      c) THEN drop the old narrow index that was rejecting duplicates,
--      d) add the FK to sport_events.
DROP PROCEDURE IF EXISTS sportsmis_fix_event_sports;

DELIMITER $$
CREATE PROCEDURE sportsmis_fix_event_sports()
BEGIN
    DECLARE has_col   INT DEFAULT 0;
    DECLARE has_old   INT DEFAULT 0;
    DECLARE has_new   INT DEFAULT 0;
    DECLARE has_fk    INT DEFAULT 0;

    -- a) sport_event_id column
    SELECT COUNT(*) INTO has_col
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'event_sports'
       AND COLUMN_NAME  = 'sport_event_id';
    IF has_col = 0 THEN
        ALTER TABLE event_sports ADD COLUMN sport_event_id INT UNSIGNED NULL AFTER sport_id;
    END IF;

    -- b) wider unique index (event_id, sport_id, sport_event_id)
    SELECT COUNT(*) INTO has_new
      FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'event_sports'
       AND INDEX_NAME   = 'uq_event_sport_event';
    IF has_new = 0 THEN
        ALTER TABLE event_sports
          ADD UNIQUE KEY uq_event_sport_event (event_id, sport_id, sport_event_id);
    END IF;

    -- c) drop the OLD narrow index (now safe — wider one took over the FK)
    SELECT COUNT(*) INTO has_old
      FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'event_sports'
       AND INDEX_NAME   = 'uq_event_sport';
    IF has_old > 0 THEN
        ALTER TABLE event_sports DROP INDEX uq_event_sport;
    END IF;

    -- d) FK on sport_event_id → sport_events.id
    SELECT COUNT(*) INTO has_fk
      FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA    = DATABASE()
       AND TABLE_NAME      = 'event_sports'
       AND CONSTRAINT_TYPE = 'FOREIGN KEY'
       AND CONSTRAINT_NAME = 'fk_event_sports_sport_event';
    IF has_fk = 0 THEN
        ALTER TABLE event_sports
          ADD CONSTRAINT fk_event_sports_sport_event
          FOREIGN KEY (sport_event_id) REFERENCES sport_events(id) ON DELETE SET NULL;
    END IF;
END$$
DELIMITER ;

CALL sportsmis_fix_event_sports();
DROP PROCEDURE sportsmis_fix_event_sports;

-- 4. Quick sanity check (optional — you can run this on its own to verify).
--    Expected after success:
--      uq_event_sport_event   present
--      uq_event_sport         absent
SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX
  FROM INFORMATION_SCHEMA.STATISTICS
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME   = 'event_sports'
 ORDER BY INDEX_NAME, SEQ_IN_INDEX;
