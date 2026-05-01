-- Migration: sport hierarchy (sport_categories, age_categories, sport_events)
-- Each statement is idempotent enough that re-running on a partially-applied
-- DB only errors with "already exists" / "Duplicate column" — safe to ignore.

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

ALTER TABLE event_sports ADD COLUMN sport_event_id INT UNSIGNED NULL AFTER sport_id;
ALTER TABLE event_sports DROP INDEX uq_event_sport;
ALTER TABLE event_sports ADD UNIQUE KEY uq_event_sport_event (event_id, sport_id, sport_event_id);
ALTER TABLE event_sports ADD CONSTRAINT fk_event_sports_sport_event
  FOREIGN KEY (sport_event_id) REFERENCES sport_events(id) ON DELETE SET NULL;

INSERT IGNORE INTO age_categories (name, min_age, max_age, sort_order) VALUES
('Sub Youth',     NULL,  14, 1),
('Youth',           14,  17, 2),
('Junior',          17,  20, 3),
('Senior',          20,  35, 4),
('Master',          35,  50, 5),
('Senior Master',   50, NULL, 6);
