<?php
namespace Models;

use Core\Model;

/**
 * Self-healing schema helper. Each ensure*() method is idempotent and
 * safe to call on every relevant request — checks INFORMATION_SCHEMA
 * before running ALTER / CREATE statements.
 */
class Schema extends Model
{
    private static array $applied = [];

    public static function ensureSportHierarchy(): void
    {
        if (!empty(self::$applied['sport_hierarchy'])) return;

        // Ensure the `enabled_for_events` flag exists on `sports`. This is
        // what controls which sports show up in the institution event
        // editor and the athlete profile (super admin manages it via
        // /admin/settings/sports). Defaults to off; we then turn on the
        // initial set of three.
        if (self::tableExists('sports') && !self::columnExists('sports', 'enabled_for_events')) {
            static::query("ALTER TABLE sports ADD COLUMN enabled_for_events TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
            // Make sure Softball exists, then enable the default trio.
            static::query("INSERT IGNORE INTO sports (name) VALUES ('Softball')");
            static::query("UPDATE sports SET enabled_for_events = 1
                            WHERE name IN ('Shooting', 'Softball', 'Athletics')");
        }

        if (!self::tableExists('sport_categories')) {
            static::query("
                CREATE TABLE sport_categories (
                    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    sport_id   INT UNSIGNED NOT NULL,
                    name       VARCHAR(150) NOT NULL,
                    sort_order INT NOT NULL DEFAULT 0,
                    status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_sport_category (sport_id, name),
                    FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ");
        }

        if (!self::tableExists('age_categories')) {
            static::query("
                CREATE TABLE age_categories (
                    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name       VARCHAR(100) NOT NULL UNIQUE,
                    min_age    INT UNSIGNED NULL,
                    max_age    INT UNSIGNED NULL,
                    sort_order INT NOT NULL DEFAULT 0,
                    status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB
            ");
            self::seedDefaultAgeCategories();
        }

        if (!self::tableExists('sport_events')) {
            static::query("
                CREATE TABLE sport_events (
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
                    FOREIGN KEY (sport_id)        REFERENCES sports(id)           ON DELETE CASCADE,
                    FOREIGN KEY (category_id)     REFERENCES sport_categories(id) ON DELETE CASCADE,
                    FOREIGN KEY (age_category_id) REFERENCES age_categories(id)
                ) ENGINE=InnoDB
            ");
        }

        if (self::tableExists('event_sports')) {
            // 1. Add the catalog-link column if missing.
            if (!self::columnExists('event_sports', 'sport_event_id')) {
                static::query("ALTER TABLE event_sports ADD COLUMN sport_event_id INT UNSIGNED NULL AFTER sport_id");
            }

            // 1b. Add the per-row event code (institution-supplied label, e.g. "AP-10M-SR-M").
            if (!self::columnExists('event_sports', 'event_code')) {
                static::query("ALTER TABLE event_sports ADD COLUMN event_code VARCHAR(50) NULL AFTER sport_event_id");
            }

            // 2. Add the new wider unique index FIRST. It starts with
            //    event_id so it can take over as the supporting index for
            //    the existing FK (event_id -> events.id), letting us drop
            //    the old narrow uq_event_sport without an FK error.
            if (!self::indexExists('event_sports', 'uq_event_sport_event')) {
                try {
                    static::query("ALTER TABLE event_sports
                                   ADD UNIQUE KEY uq_event_sport_event (event_id, sport_id, sport_event_id)");
                } catch (\Throwable $e) {
                    error_log('[Schema] add uq_event_sport_event failed: ' . $e->getMessage());
                }
            }

            // 3. Now drop the OLD narrow unique index. Without this, a
            //    second sport_event under the same sport throws
            //    "Duplicate entry '<event>-<sport>' for key 'uq_event_sport'".
            if (self::indexExists('event_sports', 'uq_event_sport')) {
                try {
                    static::query("ALTER TABLE event_sports DROP INDEX uq_event_sport");
                } catch (\Throwable $e) {
                    error_log('[Schema] drop uq_event_sport failed: ' . $e->getMessage());
                }
            }

            // 4. Ensure the FK to sport_events exists.
            if (!self::foreignKeyExists('event_sports', 'fk_event_sports_sport_event')) {
                try {
                    static::query("ALTER TABLE event_sports
                                   ADD CONSTRAINT fk_event_sports_sport_event
                                   FOREIGN KEY (sport_event_id) REFERENCES sport_events(id) ON DELETE SET NULL");
                } catch (\Throwable $e) {
                    error_log('[Schema] add fk_event_sports_sport_event failed: ' . $e->getMessage());
                }
            }
        }

        // Backfill default age categories on a freshly-created table only if empty.
        $count = static::row("SELECT COUNT(*) AS c FROM age_categories");
        if ((int)($count['c'] ?? 0) === 0) {
            self::seedDefaultAgeCategories();
        }

        self::ensureRegistrationFlow();
        self::ensureAthleteDobProof();
        self::ensureEventStatusV2();

        self::$applied['sport_hierarchy'] = true;
    }

    /**
     * Move events.status from the legacy 6-state lifecycle to the new
     * 4-state model (draft / active / completed / suspended).
     * - Expand the ENUM so old + new values coexist (no data loss).
     * - Map legacy rows onto the new vocabulary so the rest of the app
     *   only ever sees the four canonical states.
     * Idempotent: a no-op once migrated.
     */
    public static function ensureEventStatusV2(): void
    {
        if (!empty(self::$applied['event_status_v2'])) return;
        if (!self::tableExists('events')) return;

        // Read the current column definition so we don't keep ALTERing it
        // unnecessarily.
        $col = static::row(
            "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events'
                AND COLUMN_NAME  = 'status'"
        );
        $type = strtolower($col['COLUMN_TYPE'] ?? '');
        $hasActive    = str_contains($type, "'active'");
        $hasSuspended = str_contains($type, "'suspended'");

        if (!$hasActive || !$hasSuspended) {
            try {
                static::query("ALTER TABLE events MODIFY COLUMN status
                    ENUM('draft','pending_approval','approved','rejected','completed','cancelled','active','suspended')
                    NOT NULL DEFAULT 'draft'");
            } catch (\Throwable $e) {
                error_log('[Schema] expand events.status enum failed: ' . $e->getMessage());
            }
        }

        // Map legacy values onto the new vocabulary. Each statement is a
        // no-op once the rows have been migrated.
        try {
            static::query("UPDATE events SET status = 'active'
                            WHERE status IN ('pending_approval','approved')");
            static::query("UPDATE events SET status = 'suspended'
                            WHERE status IN ('rejected','cancelled')");
        } catch (\Throwable $e) {
            error_log('[Schema] events.status backfill failed: ' . $e->getMessage());
        }

        self::$applied['event_status_v2'] = true;
    }

    /**
     * Athletes get a second ID proof slot dedicated to Date-of-Birth proof
     * (Driving Licence / Birth Certificate / School Certificate / Passport),
     * surfaced when Aadhaar doesn't carry DOB. Idempotent.
     */
    public static function ensureAthleteDobProof(): void
    {
        if (!empty(self::$applied['athlete_dob_proof'])) return;

        if (self::tableExists('athletes')) {
            $additions = [
                'dob_proof_type_id' => "INT UNSIGNED NULL",
                'dob_proof_number'  => "VARCHAR(100) NULL",
                'dob_proof_file'    => "VARCHAR(500) NULL",
            ];
            foreach ($additions as $col => $type) {
                if (!self::columnExists('athletes', $col)) {
                    static::query("ALTER TABLE athletes ADD COLUMN {$col} {$type}");
                }
            }
        }

        if (self::tableExists('id_proof_types')) {
            // The original schema didn't put a UNIQUE on `name`, so prior
            // INSERT IGNOREs of "Birth Certificate" / "School Certificate"
            // could insert duplicates. De-dupe (keep the lowest id, repoint
            // any athletes that referenced the doomed rows), then add the
            // missing UNIQUE so future inserts are truly idempotent.
            $dupes = static::rows(
                "SELECT name, MIN(id) AS keep_id, COUNT(*) AS c
                   FROM id_proof_types GROUP BY name HAVING c > 1"
            );
            foreach ($dupes as $d) {
                $keep = (int)$d['keep_id'];
                $rows = static::rows(
                    "SELECT id FROM id_proof_types WHERE name = ? AND id <> ?",
                    [$d['name'], $keep]
                );
                foreach ($rows as $r) {
                    $rid = (int)$r['id'];
                    @static::query("UPDATE athletes SET id_proof_type_id = ? WHERE id_proof_type_id = ?", [$keep, $rid]);
                    @static::query("UPDATE athletes SET dob_proof_type_id = ? WHERE dob_proof_type_id = ?", [$keep, $rid]);
                    static::query("DELETE FROM id_proof_types WHERE id = ?", [$rid]);
                }
            }
            // Add UNIQUE(name) if not already present.
            $idx = static::row(
                "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'id_proof_types'
                    AND COLUMN_NAME = 'name' AND NON_UNIQUE = 0 LIMIT 1"
            );
            if (!$idx) {
                try { static::query("ALTER TABLE id_proof_types ADD UNIQUE KEY uq_id_proof_name (name)"); }
                catch (\Throwable $e) { error_log('[Schema] uq_id_proof_name: ' . $e->getMessage()); }
            }
            // Now insert the canonical pair (UNIQUE makes this safe).
            foreach (['Birth Certificate', 'School Certificate'] as $name) {
                static::query("INSERT IGNORE INTO id_proof_types (name) VALUES (?)", [$name]);
            }
        }

        self::$applied['athlete_dob_proof'] = true;
    }

    /**
     * Tables / columns that power the multi-step athlete registration flow:
     * Units master per event, NOC requirement on the event, header+items
     * on event_registrations.
     */
    public static function ensureRegistrationFlow(): void
    {
        if (!empty(self::$applied['registration_flow'])) return;

        // event_units (per-event Unit/Club/Institution master).
        if (!self::tableExists('event_units')) {
            static::query("
                CREATE TABLE event_units (
                    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    event_id   INT UNSIGNED NOT NULL,
                    name       VARCHAR(255) NOT NULL,
                    address    TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ");
        }

        // events.noc_required: 'none' | 'optional' | 'mandatory'.
        if (self::tableExists('events') && !self::columnExists('events', 'noc_required')) {
            static::query("ALTER TABLE events
                           ADD COLUMN noc_required ENUM('none','optional','mandatory')
                           NOT NULL DEFAULT 'optional' AFTER bank_qr_code");
        }

        // event_registrations gains the header-level columns for the new flow.
        if (self::tableExists('event_registrations')) {
            $additions = [
                'unit_id'             => "INT UNSIGNED NULL",
                'noc_letter'          => "VARCHAR(500) NULL",
                'total_amount'        => "DECIMAL(10,2) NULL",
                'transaction_date'    => "DATE NULL",
                'transaction_number'  => "VARCHAR(100) NULL",
                'transaction_proof'   => "VARCHAR(500) NULL",
            ];
            foreach ($additions as $col => $type) {
                if (!self::columnExists('event_registrations', $col)) {
                    static::query("ALTER TABLE event_registrations ADD COLUMN {$col} {$type}");
                }
            }
            // Make sport_id nullable if it isn't already (we still write it for
            // back-compat, but the new flow stores per-line sport refs in
            // event_registration_items).
            $col = static::row(
                "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_registrations'
                    AND COLUMN_NAME = 'sport_id'"
            );
            if ($col && strtoupper($col['IS_NULLABLE']) === 'NO') {
                try {
                    static::query("ALTER TABLE event_registrations MODIFY sport_id INT UNSIGNED NULL");
                } catch (\Throwable $e) {
                    error_log('[Schema] make sport_id nullable failed: ' . $e->getMessage());
                }
            }
            // The old (event,athlete,sport) unique blocks one-registration-many-sports
            // semantics. Drop it and add (event,athlete) instead.
            if (self::indexExists('event_registrations', 'uq_reg')) {
                try { static::query("ALTER TABLE event_registrations DROP INDEX uq_reg"); }
                catch (\Throwable $e) { error_log('[Schema] drop uq_reg failed: ' . $e->getMessage()); }
            }
            if (!self::indexExists('event_registrations', 'uq_reg_event_athlete')) {
                try {
                    static::query("ALTER TABLE event_registrations
                                   ADD UNIQUE KEY uq_reg_event_athlete (event_id, athlete_id)");
                } catch (\Throwable $e) {
                    error_log('[Schema] add uq_reg_event_athlete failed: ' . $e->getMessage());
                }
            }
        }

        // Per-line registration items: links a registration to one event_sports row.
        if (!self::tableExists('event_registration_items')) {
            static::query("
                CREATE TABLE event_registration_items (
                    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    registration_id INT UNSIGNED NOT NULL,
                    event_sport_id  INT UNSIGNED NOT NULL,
                    fee             DECIMAL(10,2) NOT NULL DEFAULT 0,
                    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_reg_item (registration_id, event_sport_id),
                    FOREIGN KEY (registration_id) REFERENCES event_registrations(id) ON DELETE CASCADE,
                    FOREIGN KEY (event_sport_id)  REFERENCES event_sports(id)        ON DELETE CASCADE
                ) ENGINE=InnoDB
            ");
        }

        // Per-event Documents master (Undertaking Form, Rules, etc.).
        if (!self::tableExists('event_documents')) {
            static::query("
                CREATE TABLE event_documents (
                    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    event_id   INT UNSIGNED NOT NULL,
                    name       VARCHAR(255) NOT NULL,
                    purpose    TEXT NULL,
                    file       VARCHAR(500) NULL,
                    status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ");
        }

        // Multiple manual-payment transaction records per registration, each
        // independently approvable by the event admin.
        if (!self::tableExists('event_registration_payments')) {
            static::query("
                CREATE TABLE event_registration_payments (
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
                ) ENGINE=InnoDB
            ");
        }

        // Event-admin review columns on event_registrations.
        if (self::tableExists('event_registrations')) {
            $extra = [
                'admin_review_status' => "ENUM('pending','approved','rejected','returned') NULL",
                'admin_review_notes'  => "TEXT NULL",
                'admin_reviewed_by'   => "INT UNSIGNED NULL",
                'admin_reviewed_at'   => "TIMESTAMP NULL",
                'submitted_at'        => "TIMESTAMP NULL",
                'competitor_number'   => "INT UNSIGNED NULL",
            ];
            foreach ($extra as $col => $type) {
                if (!self::columnExists('event_registrations', $col)) {
                    static::query("ALTER TABLE event_registrations ADD COLUMN {$col} {$type}");
                }
            }
            // Per-event uniqueness for the competitor number.
            if (!self::indexExists('event_registrations', 'uq_event_competitor')) {
                try {
                    static::query("ALTER TABLE event_registrations
                                   ADD UNIQUE KEY uq_event_competitor (event_id, competitor_number)");
                } catch (\Throwable $e) {
                    error_log('[Schema] uq_event_competitor: ' . $e->getMessage());
                }
            }
        }

        self::$applied['registration_flow'] = true;
    }

    private static function seedDefaultAgeCategories(): void
    {
        $rows = [
            ['Sub Youth',     null, 14, 1],
            ['Youth',           14, 17, 2],
            ['Junior',          17, 20, 3],
            ['Senior',          20, 35, 4],
            ['Master',          35, 50, 5],
            ['Senior Master',   50, null, 6],
        ];
        foreach ($rows as [$n, $mn, $mx, $so]) {
            static::query(
                "INSERT IGNORE INTO age_categories (name, min_age, max_age, sort_order) VALUES (?, ?, ?, ?)",
                [$n, $mn, $mx, $so]
            );
        }
    }

    private static function tableExists(string $name): bool
    {
        $r = static::row(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            [$name]
        );
        return (bool)$r;
    }

    private static function columnExists(string $table, string $column): bool
    {
        $r = static::row(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$table, $column]
        );
        return (bool)$r;
    }

    private static function indexExists(string $table, string $index): bool
    {
        $r = static::row(
            "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
              LIMIT 1",
            [$table, $index]
        );
        return (bool)$r;
    }

    private static function foreignKeyExists(string $table, string $constraint): bool
    {
        $r = static::row(
            "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = ?
              LIMIT 1",
            [$table, $constraint]
        );
        return (bool)$r;
    }
}
