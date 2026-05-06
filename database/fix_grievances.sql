-- Idempotent: athlete grievances + threaded replies, scoped per event.
-- Safe to paste into phpMyAdmin → SQL.

CREATE TABLE IF NOT EXISTS event_grievances (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id    INT UNSIGNED NOT NULL,
    athlete_id  INT UNSIGNED NOT NULL,
    subject     VARCHAR(255) NOT NULL,
    message     TEXT NOT NULL,
    status      ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY ix_event_status (event_id, status),
    FOREIGN KEY (event_id)   REFERENCES events(id)   ON DELETE CASCADE,
    FOREIGN KEY (athlete_id) REFERENCES athletes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS event_grievance_replies (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    grievance_id  INT UNSIGNED NOT NULL,
    author_user_id INT UNSIGNED NULL,
    author_role   ENUM('athlete','institution_admin','super_admin') NOT NULL,
    message       TEXT NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (grievance_id)   REFERENCES event_grievances(id) ON DELETE CASCADE,
    FOREIGN KEY (author_user_id) REFERENCES users(id)            ON DELETE SET NULL
) ENGINE=InnoDB;
