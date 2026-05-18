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
                    pwd_status ENUM('no','deaf','para') NOT NULL DEFAULT 'no',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_sport_category (sport_id, name),
                    FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ");
        }
        // Idempotent: add the PwD-category flag on installs that already have the table.
        if (self::tableExists('sport_categories') && !self::columnExists('sport_categories', 'pwd_status')) {
            static::query("ALTER TABLE sport_categories
                           ADD COLUMN pwd_status ENUM('no','deaf','para') NOT NULL DEFAULT 'no' AFTER status");
        }
        // Idempotent: editable short abbreviation per category (e.g. AP, PSAR, OSAR).
        if (self::tableExists('sport_categories') && !self::columnExists('sport_categories', 'abbreviation')) {
            static::query("ALTER TABLE sport_categories
                           ADD COLUMN abbreviation VARCHAR(20) NULL AFTER name");
        }

        if (!self::tableExists('age_categories')) {
            static::query("
                CREATE TABLE age_categories (
                    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name          VARCHAR(100) NOT NULL UNIQUE,
                    min_age       INT UNSIGNED NULL,
                    max_age       INT UNSIGNED NULL,
                    min_age_year  INT UNSIGNED NULL,
                    max_age_year  INT UNSIGNED NULL,
                    sort_order    INT NOT NULL DEFAULT 0,
                    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
                    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB
            ");
            self::seedDefaultAgeCategories();
        }
        // Idempotent additions for installs that already have the table.
        if (self::tableExists('age_categories')) {
            if (!self::columnExists('age_categories', 'min_age_year')) {
                static::query("ALTER TABLE age_categories
                               ADD COLUMN min_age_year INT UNSIGNED NULL AFTER max_age");
            }
            if (!self::columnExists('age_categories', 'max_age_year')) {
                static::query("ALTER TABLE age_categories
                               ADD COLUMN max_age_year INT UNSIGNED NULL AFTER min_age_year");
            }
        }

        // Per-age-category "also eligible to play in" upgrades.
        // Example: a Sub-Youth row can have upgrade targets {Youth, Junior, Senior}
        // so an athlete who falls into Sub-Youth by bounds is also offered the
        // other three when picking sport events.
        if (!self::tableExists('age_category_upgrades')) {
            static::query("
                CREATE TABLE age_category_upgrades (
                    from_age_category_id INT UNSIGNED NOT NULL,
                    to_age_category_id   INT UNSIGNED NOT NULL,
                    PRIMARY KEY (from_age_category_id, to_age_category_id),
                    FOREIGN KEY (from_age_category_id) REFERENCES age_categories(id) ON DELETE CASCADE,
                    FOREIGN KEY (to_age_category_id)   REFERENCES age_categories(id) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ");
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

            // 1c. Add the Team Entry Fee alongside the per-row Entry Fee.
            //     Production already has rows — column is nullable so existing
            //     rows continue to default-render as ₹0.00 in the UI without
            //     disturbing any saved data.
            if (!self::columnExists('event_sports', 'team_entry_fee')) {
                static::query("ALTER TABLE event_sports
                               ADD COLUMN team_entry_fee DECIMAL(10,2) NULL AFTER entry_fee");
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

        // Normalise any sport_events.gender legacy spellings: 'men'→'male',
        // 'women'→'female'. The athlete profile is the canonical source
        // (male/female/other), so the catalog has to match. Done in three
        // steps so it works whether or not the ENUM still admits the
        // legacy spellings.
        if (self::tableExists('sport_events')) {
            $col = static::row(
                "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sport_events'
                    AND COLUMN_NAME  = 'gender'"
            );
            $type = strtolower($col['COLUMN_TYPE'] ?? '');
            $hasLegacy = str_contains($type, "'men'") || str_contains($type, "'women'");

            if ($hasLegacy) {
                // Widen the ENUM temporarily so the UPDATE assignments don't
                // get coerced to '' on strict modes; then UPDATE.
                try {
                    static::query("ALTER TABLE sport_events MODIFY COLUMN gender
                                    ENUM('male','female','mixed','men','women') NOT NULL");
                } catch (\Throwable $e) {
                    error_log('[Schema] widen sport_events.gender failed: ' . $e->getMessage());
                }
            }
            try { static::query("UPDATE sport_events SET gender = 'male'   WHERE gender = 'men'"); }
            catch (\Throwable $e) { /* harmless if column doesn't admit 'men' */ }
            try { static::query("UPDATE sport_events SET gender = 'female' WHERE gender = 'women'"); }
            catch (\Throwable $e) { /* harmless */ }
            if ($hasLegacy) {
                // Narrow back. Will fail if any rows still carry a legacy
                // value (shouldn't, after the UPDATEs above).
                try {
                    static::query("ALTER TABLE sport_events MODIFY COLUMN gender
                                    ENUM('male','female','mixed') NOT NULL");
                } catch (\Throwable $e) {
                    error_log('[Schema] narrow sport_events.gender failed: ' . $e->getMessage());
                }
            }
        }

        self::ensureRegistrationFlow();
        self::ensureAthleteDobProof();
        self::ensureEventStatusV2();
        self::ensureSportItems();
        self::ensurePaymentReliability();
        self::ensureShootingRanges();
        self::ensureRelays();
        self::ensureTeamEntry();
        self::ensureUnitUsers();
        self::ensureEventStaff();
        self::ensureLaneAllocation();

        self::$applied['sport_hierarchy'] = true;
    }

    /**
     * Lane Allocation: per relay-lane unit + athlete assignment, plus the
     * per-event toggle that lets unit users self-manage their allocations.
     */
    public static function ensureLaneAllocation(): void
    {
        if (!empty(self::$applied['lane_allocation'])) return;

        if (self::tableExists('events') && !self::columnExists('events', 'unit_lane_allocation_enabled')) {
            static::query("ALTER TABLE events
                           ADD COLUMN unit_lane_allocation_enabled TINYINT(1) NOT NULL DEFAULT 0");
        }

        if (self::tableExists('event_relay_lanes')) {
            $cols = [
                'assigned_unit_id'         => "INT UNSIGNED NULL",
                'assigned_registration_id' => "INT UNSIGNED NULL",
                'allocated_by'             => "VARCHAR(255) NULL",
                'allocated_at'             => "TIMESTAMP NULL",
            ];
            foreach ($cols as $c => $type) {
                if (!self::columnExists('event_relay_lanes', $c)) {
                    static::query("ALTER TABLE event_relay_lanes ADD COLUMN {$c} {$type}");
                }
            }
            if (!self::foreignKeyExists('event_relay_lanes', 'fk_erl_unit')) {
                try {
                    static::query("ALTER TABLE event_relay_lanes
                                   ADD CONSTRAINT fk_erl_unit FOREIGN KEY (assigned_unit_id)
                                   REFERENCES event_units(id) ON DELETE SET NULL");
                } catch (\Throwable $e) { error_log('[Schema] fk_erl_unit: ' . $e->getMessage()); }
            }
            if (!self::foreignKeyExists('event_relay_lanes', 'fk_erl_reg')) {
                try {
                    static::query("ALTER TABLE event_relay_lanes
                                   ADD CONSTRAINT fk_erl_reg FOREIGN KEY (assigned_registration_id)
                                   REFERENCES event_registrations(id) ON DELETE SET NULL");
                } catch (\Throwable $e) { error_log('[Schema] fk_erl_reg: ' . $e->getMessage()); }
            }
        }

        self::$applied['lane_allocation'] = true;
    }

    /**
     * Per-event Event Staff accounts + their assigned privileges.
     * Auth mirrors unit_users (event_code + email + password) but is fully
     * independent. Privileges gate the staff dashboard menu items.
     *
     *   event_staff             account rows (event-scoped email)
     *   event_staff_privileges  one row per granted privilege
     */
    public static function ensureEventStaff(): void
    {
        if (!empty(self::$applied['event_staff'])) return;

        if (!self::tableExists('event_staff')) {
            static::query("
                CREATE TABLE event_staff (
                    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    event_id      INT UNSIGNED NOT NULL,
                    name          VARCHAR(255) NOT NULL,
                    email         VARCHAR(255) NOT NULL,
                    mobile        VARCHAR(20)  NULL,
                    password      VARCHAR(255) NOT NULL,
                    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
                    last_login_at TIMESTAMP NULL,
                    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_event_email (event_id, email),
                    KEY ix_event (event_id),
                    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ");
        }

        if (!self::tableExists('event_staff_privileges')) {
            static::query("
                CREATE TABLE event_staff_privileges (
                    event_staff_id INT UNSIGNED NOT NULL,
                    privilege      VARCHAR(40) NOT NULL,
                    PRIMARY KEY (event_staff_id, privilege),
                    FOREIGN KEY (event_staff_id) REFERENCES event_staff(id) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ");
        }

        // team_registrations: allow creation by unit users / event staff —
        // not just athletes. athlete_id becomes nullable; created_by_* records
        // the real submitter for the new Team Entry capture flow.
        if (self::tableExists('team_registrations')) {
            $col = static::row(
                "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'team_registrations'
                    AND COLUMN_NAME = 'athlete_id'"
            );
            if ($col && strtoupper($col['IS_NULLABLE']) === 'NO') {
                try {
                    static::query("ALTER TABLE team_registrations MODIFY athlete_id INT UNSIGNED NULL");
                } catch (\Throwable $e) {
                    error_log('[Schema] team_registrations.athlete_id nullable: ' . $e->getMessage());
                }
            }
            if (!self::columnExists('team_registrations', 'created_by_type')) {
                static::query("ALTER TABLE team_registrations
                               ADD COLUMN created_by_type VARCHAR(20) NULL AFTER athlete_id");
            }
            if (!self::columnExists('team_registrations', 'created_by_id')) {
                static::query("ALTER TABLE team_registrations
                               ADD COLUMN created_by_id INT UNSIGNED NULL AFTER created_by_type");
            }
        }

        self::$applied['event_staff'] = true;
    }

    /**
     * Per-event Unit/Institution/Club user accounts. Logs in with
     * (event_code, email, password) — completely separate from the main
     * users table so emails can be reused across events.
     *
     *   events.event_code              short identifier shown to admins/users
     *   unit_users                     account rows (event-scoped email)
     *   unit_user_units                many-to-many to event_units
     */
    public static function ensureUnitUsers(): void
    {
        if (!empty(self::$applied['unit_users'])) return;

        if (self::tableExists('events') && !self::columnExists('events', 'event_code')) {
            static::query("ALTER TABLE events
                           ADD COLUMN event_code VARCHAR(32) NULL AFTER name");
            try {
                static::query("ALTER TABLE events ADD UNIQUE KEY uq_event_code (event_code)");
            } catch (\Throwable $e) {
                error_log('[Schema] uq_event_code: ' . $e->getMessage());
            }
        }

        if (!self::tableExists('unit_users')) {
            static::query("
                CREATE TABLE unit_users (
                    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    event_id      INT UNSIGNED NOT NULL,
                    name          VARCHAR(255) NOT NULL,
                    email         VARCHAR(255) NOT NULL,
                    mobile        VARCHAR(20)  NULL,
                    password      VARCHAR(255) NOT NULL,
                    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
                    last_login_at TIMESTAMP NULL,
                    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_event_email (event_id, email),
                    KEY ix_event (event_id),
                    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ");
        }

        if (!self::tableExists('unit_user_units')) {
            static::query("
                CREATE TABLE unit_user_units (
                    unit_user_id  INT UNSIGNED NOT NULL,
                    event_unit_id INT UNSIGNED NOT NULL,
                    PRIMARY KEY (unit_user_id, event_unit_id),
                    KEY ix_unit (event_unit_id),
                    FOREIGN KEY (unit_user_id)  REFERENCES unit_users(id)  ON DELETE CASCADE,
                    FOREIGN KEY (event_unit_id) REFERENCES event_units(id) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ");
        }

        self::$applied['unit_users'] = true;
    }

    /**
     * Team Entry feature:
     *   - events.team_entry_enabled        toggle in Registration Settings
     *   - team_registrations               one row per submitted team
     *   - team_registration_members        up to 3 athletes per team
     *   - team_registration_payments       manual / online transactions
     */
    public static function ensureTeamEntry(): void
    {
        if (!empty(self::$applied['team_entry'])) return;

        if (self::tableExists('events') && !self::columnExists('events', 'team_entry_enabled')) {
            static::query("ALTER TABLE events
                           ADD COLUMN team_entry_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER noc_required");
        }
        // Comma-separated submission methods: athlete,unit_user,event_staff.
        if (self::tableExists('events') && !self::columnExists('events', 'team_entry_methods')) {
            static::query("ALTER TABLE events
                           ADD COLUMN team_entry_methods VARCHAR(80) NULL AFTER team_entry_enabled");
        }

        if (!self::tableExists('team_registrations')) {
            static::query("
                CREATE TABLE team_registrations (
                    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    event_id            INT UNSIGNED NOT NULL,
                    athlete_id          INT UNSIGNED NOT NULL,
                    unit_id             INT UNSIGNED NULL,
                    event_sport_id      INT UNSIGNED NULL,
                    team_name           VARCHAR(255) NOT NULL,
                    total_amount        DECIMAL(10,2) NULL,
                    payment_mode        ENUM('manual','online') NULL,
                    payment_status      ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
                    admin_review_status ENUM('pending','approved','rejected','returned') NULL,
                    admin_review_notes  TEXT NULL,
                    admin_reviewed_by   INT UNSIGNED NULL,
                    admin_reviewed_at   TIMESTAMP NULL,
                    submitted_at        TIMESTAMP NULL,
                    status              VARCHAR(32) NOT NULL DEFAULT 'draft',
                    registered_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY ix_event   (event_id),
                    KEY ix_athlete (athlete_id),
                    FOREIGN KEY (event_id)       REFERENCES events(id)       ON DELETE CASCADE,
                    FOREIGN KEY (athlete_id)     REFERENCES athletes(id)     ON DELETE CASCADE,
                    FOREIGN KEY (unit_id)        REFERENCES event_units(id)  ON DELETE SET NULL,
                    FOREIGN KEY (event_sport_id) REFERENCES event_sports(id) ON DELETE SET NULL
                ) ENGINE=InnoDB
            ");
        }

        if (!self::tableExists('team_registration_members')) {
            static::query("
                CREATE TABLE team_registration_members (
                    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    team_registration_id INT UNSIGNED NOT NULL,
                    athlete_id           INT UNSIGNED NOT NULL,
                    registration_id      INT UNSIGNED NULL,
                    competitor_number    INT UNSIGNED NOT NULL,
                    position             TINYINT UNSIGNED NOT NULL DEFAULT 1,
                    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_team_athlete (team_registration_id, athlete_id),
                    KEY ix_team (team_registration_id),
                    FOREIGN KEY (team_registration_id) REFERENCES team_registrations(id)  ON DELETE CASCADE,
                    FOREIGN KEY (athlete_id)           REFERENCES athletes(id)            ON DELETE CASCADE,
                    FOREIGN KEY (registration_id)      REFERENCES event_registrations(id) ON DELETE SET NULL
                ) ENGINE=InnoDB
            ");
        }

        if (!self::tableExists('team_registration_payments')) {
            static::query("
                CREATE TABLE team_registration_payments (
                    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    team_registration_id INT UNSIGNED NOT NULL,
                    event_id             INT UNSIGNED NULL,
                    transaction_date     DATE NOT NULL,
                    transaction_number   VARCHAR(100) NOT NULL,
                    amount               DECIMAL(10,2) NOT NULL,
                    proof_file           VARCHAR(500) NULL,
                    payment_method       ENUM('manual','epayment') NOT NULL DEFAULT 'manual',
                    razorpay_order_id    VARCHAR(255) NULL,
                    razorpay_payment_id  VARCHAR(255) NULL,
                    razorpay_signature   VARCHAR(512) NULL,
                    status               ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                    rejection_reason     TEXT NULL,
                    reviewed_by          INT UNSIGNED NULL,
                    reviewed_at          TIMESTAMP NULL,
                    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY ix_team (team_registration_id),
                    FOREIGN KEY (team_registration_id) REFERENCES team_registrations(id) ON DELETE CASCADE,
                    FOREIGN KEY (reviewed_by)          REFERENCES users(id)              ON DELETE SET NULL
                ) ENGINE=InnoDB
            ");
        }

        self::$applied['team_entry'] = true;
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
                'pwd_status'        => "ENUM('no','deaf','para') NOT NULL DEFAULT 'no'",
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

        // Structured bank-account fields used as the payout destination when
        // the event accepts Online Payment. The free-form bank_details column
        // is retained for backward compatibility.
        if (self::tableExists('events')) {
            $bank = [
                'bank_name'           => "VARCHAR(255) NULL",
                'bank_branch'         => "VARCHAR(255) NULL",
                'bank_account_number' => "VARCHAR(64)  NULL",
                'bank_ifsc'           => "VARCHAR(20)  NULL",
            ];
            foreach ($bank as $col => $type) {
                if (!self::columnExists('events', $col)) {
                    static::query("ALTER TABLE events ADD COLUMN {$col} {$type}");
                }
            }
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

        // Athlete grievances per event + threaded replies.
        if (!self::tableExists('event_grievances')) {
            static::query("
                CREATE TABLE event_grievances (
                    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    event_id    INT UNSIGNED NOT NULL,
                    athlete_id  INT UNSIGNED NOT NULL,
                    subject     VARCHAR(255) NOT NULL,
                    message     TEXT NOT NULL,
                    status      ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
                    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY ix_event_status (event_id, status),
                    FOREIGN KEY (event_id)   REFERENCES events(id)    ON DELETE CASCADE,
                    FOREIGN KEY (athlete_id) REFERENCES athletes(id)  ON DELETE CASCADE
                ) ENGINE=InnoDB
            ");
        }
        if (!self::tableExists('event_grievance_replies')) {
            static::query("
                CREATE TABLE event_grievance_replies (
                    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    grievance_id  INT UNSIGNED NOT NULL,
                    author_user_id INT UNSIGNED NULL,
                    author_role   ENUM('athlete','institution_admin','super_admin') NOT NULL,
                    message       TEXT NOT NULL,
                    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (grievance_id)   REFERENCES event_grievances(id) ON DELETE CASCADE,
                    FOREIGN KEY (author_user_id) REFERENCES users(id)            ON DELETE SET NULL
                ) ENGINE=InnoDB
            ");
        }

        // Razorpay (ePayment) columns on the same transactions table:
        // - payment_method: distinguishes 'manual' (default) from 'epayment'.
        // - razorpay_order_id / payment_id / signature: the three Razorpay
        //   round-trip values; the signature is what verify-payment HMACs.
        if (self::tableExists('event_registration_payments')) {
            $rzp = [
                'payment_method'      => "ENUM('manual','epayment') NOT NULL DEFAULT 'manual'",
                'razorpay_order_id'   => "VARCHAR(255) NULL",
                'razorpay_payment_id' => "VARCHAR(255) NULL",
                'razorpay_signature'  => "VARCHAR(512) NULL",
                'event_id'            => "INT UNSIGNED NULL",
            ];
            foreach ($rzp as $col => $type) {
                if (!self::columnExists('event_registration_payments', $col)) {
                    static::query("ALTER TABLE event_registration_payments ADD COLUMN {$col} {$type}");
                }
            }
            // Backfill event_id from the parent registration so historic rows
            // also surface in the Super-Admin epayment report.
            try {
                static::query("UPDATE event_registration_payments p
                                  JOIN event_registrations r ON r.id = p.registration_id
                                   SET p.event_id = r.event_id
                                 WHERE p.event_id IS NULL");
            } catch (\Throwable $e) {
                error_log('[Schema] backfill event_id on payments: ' . $e->getMessage());
            }
            if (!self::indexExists('event_registration_payments', 'uq_rzp_order')) {
                try {
                    static::query("ALTER TABLE event_registration_payments
                                   ADD UNIQUE KEY uq_rzp_order (razorpay_order_id)");
                } catch (\Throwable $e) {
                    error_log('[Schema] uq_rzp_order: ' . $e->getMessage());
                }
            }
            if (!self::indexExists('event_registration_payments', 'ix_event_method_status')) {
                try {
                    static::query("ALTER TABLE event_registration_payments
                                   ADD KEY ix_event_method_status (event_id, payment_method, status)");
                } catch (\Throwable $e) {
                    error_log('[Schema] ix_event_method_status: ' . $e->getMessage());
                }
            }
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
                'unit_reg_no'         => "VARCHAR(100) NULL",
                'unit_name_other'     => "VARCHAR(255) NULL",
                'card_issued_at'      => "TIMESTAMP NULL",
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

    /**
     * Sports Items / Weapons hierarchy:
     *   sport_items                 master (per sport)
     *   event_sport_items           per-event allow-list
     *   registration_sport_items    athlete declarations on a registration
     */
    public static function ensureSportItems(): void
    {
        if (!empty(self::$applied['sport_items'])) return;

        if (!self::tableExists('sport_items')) {
            static::query("
                CREATE TABLE sport_items (
                    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    sport_id    INT UNSIGNED NOT NULL,
                    name        VARCHAR(255) NOT NULL,
                    description TEXT NULL,
                    status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
                    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY ix_sport_status (sport_id, status),
                    FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ");
        }

        if (!self::tableExists('event_sport_items')) {
            static::query("
                CREATE TABLE event_sport_items (
                    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    event_id      INT UNSIGNED NOT NULL,
                    sport_item_id INT UNSIGNED NOT NULL,
                    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_event_item (event_id, sport_item_id),
                    FOREIGN KEY (event_id)      REFERENCES events(id)      ON DELETE CASCADE,
                    FOREIGN KEY (sport_item_id) REFERENCES sport_items(id) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ");
        }

        if (!self::tableExists('registration_sport_items')) {
            static::query("
                CREATE TABLE registration_sport_items (
                    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    registration_id INT UNSIGNED NOT NULL,
                    sport_item_id   INT UNSIGNED NOT NULL,
                    model           VARCHAR(255) NULL,
                    serial_number   VARCHAR(255) NULL,
                    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY ix_reg (registration_id),
                    FOREIGN KEY (registration_id) REFERENCES event_registrations(id) ON DELETE CASCADE,
                    FOREIGN KEY (sport_item_id)   REFERENCES sport_items(id)         ON DELETE CASCADE
                ) ENGINE=InnoDB
            ");
        }

        self::$applied['sport_items'] = true;
    }

    /**
     * Phase-7 Reliability for ePayment:
     *   - webhook_log:     audit row for every Razorpay webhook callback
     *   - reconciled_at:   timestamp on event_registration_payments to track
     *                      the last time we asked Razorpay about a row
     */
    public static function ensurePaymentReliability(): void
    {
        if (!empty(self::$applied['payment_reliability'])) return;

        if (!self::tableExists('webhook_log')) {
            static::query("
                CREATE TABLE webhook_log (
                    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    source          VARCHAR(32)  NOT NULL DEFAULT 'razorpay',
                    event_type      VARCHAR(64)  NULL,
                    rzp_event_id    VARCHAR(64)  NULL,
                    razorpay_order_id VARCHAR(64) NULL,
                    razorpay_payment_id VARCHAR(64) NULL,
                    signature_ok    TINYINT(1)   NOT NULL DEFAULT 0,
                    raw_payload     MEDIUMTEXT   NULL,
                    outcome         VARCHAR(255) NULL,
                    http_status     SMALLINT UNSIGNED NULL,
                    processed_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    KEY ix_event (event_type),
                    KEY ix_order (razorpay_order_id),
                    UNIQUE KEY uq_rzp_event (rzp_event_id)
                ) ENGINE=InnoDB
            ");
        }

        if (self::tableExists('event_registration_payments')
            && !self::columnExists('event_registration_payments', 'reconciled_at')) {
            static::query("ALTER TABLE event_registration_payments
                           ADD COLUMN reconciled_at TIMESTAMP NULL AFTER reviewed_at");
        }

        self::$applied['payment_reliability'] = true;
    }

    /**
     * Shooting-Range hierarchy on an event:
     *   event_shooting_ranges            facility    (name, location)
     *     event_shooting_range_distances range type  (10m / 25m / 50m …)
     *       event_shooting_range_lanes   per-lane    (number + manual / mechanical / electronic)
     *
     * Cascading FKs at every level so deleting a facility wipes its
     * distances and lanes in one shot.
     */
    public static function ensureShootingRanges(): void
    {
        if (!empty(self::$applied['shooting_ranges'])) return;

        if (!self::tableExists('event_shooting_ranges')) {
            static::query("
                CREATE TABLE event_shooting_ranges (
                    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    event_id   INT UNSIGNED NOT NULL,
                    name       VARCHAR(255) NOT NULL,
                    location   VARCHAR(500) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY ix_event (event_id),
                    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ");
        }

        if (!self::tableExists('event_shooting_range_distances')) {
            static::query("
                CREATE TABLE event_shooting_range_distances (
                    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    shooting_range_id INT UNSIGNED NOT NULL,
                    name              VARCHAR(255) NOT NULL,
                    distance_meters   INT UNSIGNED NULL,
                    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY ix_range (shooting_range_id),
                    UNIQUE KEY uq_range_name (shooting_range_id, name),
                    FOREIGN KEY (shooting_range_id) REFERENCES event_shooting_ranges(id) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ");
        }
        // Migrate older installs: add the name column, drop the now-stale
        // distance-only UNIQUE, and add a name-based UNIQUE in its place.
        if (self::tableExists('event_shooting_range_distances')) {
            if (!self::columnExists('event_shooting_range_distances', 'name')) {
                static::query("ALTER TABLE event_shooting_range_distances
                               ADD COLUMN name VARCHAR(255) NULL AFTER shooting_range_id");
                // Backfill name from the existing distance so legacy rows
                // satisfy the eventual NOT NULL / UNIQUE constraints.
                static::query("UPDATE event_shooting_range_distances
                                  SET name = CONCAT(distance_meters, 'm')
                                WHERE name IS NULL AND distance_meters IS NOT NULL");
                static::query("UPDATE event_shooting_range_distances
                                  SET name = CONCAT('Range #', id)
                                WHERE name IS NULL");
                try {
                    static::query("ALTER TABLE event_shooting_range_distances
                                   MODIFY COLUMN name VARCHAR(255) NOT NULL");
                } catch (\Throwable $e) {
                    error_log('[Schema] srdist.name NOT NULL: ' . $e->getMessage());
                }
            }
            // distance_meters used to be NOT NULL; relax it.
            $col = static::row(
                "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_shooting_range_distances'
                    AND COLUMN_NAME = 'distance_meters'"
            );
            if ($col && strtoupper($col['IS_NULLABLE']) === 'NO') {
                try {
                    static::query("ALTER TABLE event_shooting_range_distances
                                   MODIFY COLUMN distance_meters INT UNSIGNED NULL");
                } catch (\Throwable $e) {
                    error_log('[Schema] srdist.distance_meters NULL: ' . $e->getMessage());
                }
            }
            if (self::indexExists('event_shooting_range_distances', 'uq_range_distance')) {
                try {
                    static::query("ALTER TABLE event_shooting_range_distances DROP INDEX uq_range_distance");
                } catch (\Throwable $e) {
                    error_log('[Schema] drop uq_range_distance: ' . $e->getMessage());
                }
            }
            if (!self::indexExists('event_shooting_range_distances', 'uq_range_name')) {
                try {
                    static::query("ALTER TABLE event_shooting_range_distances
                                   ADD UNIQUE KEY uq_range_name (shooting_range_id, name)");
                } catch (\Throwable $e) {
                    error_log('[Schema] uq_range_name: ' . $e->getMessage());
                }
            }
        }

        if (!self::tableExists('event_shooting_range_lanes')) {
            static::query("
                CREATE TABLE event_shooting_range_lanes (
                    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    distance_id  INT UNSIGNED NOT NULL,
                    lane_number  INT UNSIGNED NOT NULL,
                    lane_type    ENUM('manual','mechanical','electronic') NOT NULL DEFAULT 'manual',
                    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY ix_distance (distance_id),
                    UNIQUE KEY uq_distance_lane (distance_id, lane_number),
                    FOREIGN KEY (distance_id) REFERENCES event_shooting_range_distances(id) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ");
        }
        // Idempotent: per-lane default Event Category (stored as the category name).
        if (self::tableExists('event_shooting_range_lanes')
            && !self::columnExists('event_shooting_range_lanes', 'default_category')) {
            static::query("ALTER TABLE event_shooting_range_lanes
                           ADD COLUMN default_category VARCHAR(255) NULL AFTER lane_type");
        }

        self::$applied['shooting_ranges'] = true;
    }

    /**
     * Per-event Relay schedule. A relay is a scheduled slot mapped to one
     * Shooting Range (the middle level of the shooting-range tree) with a
     * subset of that range's lanes marked as active for the relay.
     */
    public static function ensureRelays(): void
    {
        if (!empty(self::$applied['relays'])) return;

        if (!self::tableExists('event_relays')) {
            static::query("
                CREATE TABLE event_relays (
                    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    event_id                    INT UNSIGNED NOT NULL,
                    shooting_range_distance_id  INT UNSIGNED NOT NULL,
                    relay_number                VARCHAR(64) NOT NULL,
                    relay_date                  DATE NULL,
                    match_time                  TIME NULL,
                    reporting_time              TIME NULL,
                    created_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY ix_event (event_id),
                    KEY ix_range (shooting_range_distance_id),
                    FOREIGN KEY (event_id)                   REFERENCES events(id)                             ON DELETE CASCADE,
                    FOREIGN KEY (shooting_range_distance_id) REFERENCES event_shooting_range_distances(id)     ON DELETE CASCADE
                ) ENGINE=InnoDB
            ");
        }

        if (!self::tableExists('event_relay_lanes')) {
            static::query("
                CREATE TABLE event_relay_lanes (
                    relay_id INT UNSIGNED NOT NULL,
                    lane_id  INT UNSIGNED NOT NULL,
                    category VARCHAR(255) NULL,
                    PRIMARY KEY (relay_id, lane_id),
                    FOREIGN KEY (relay_id) REFERENCES event_relays(id)              ON DELETE CASCADE,
                    FOREIGN KEY (lane_id)  REFERENCES event_shooting_range_lanes(id) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ");
        }
        // Idempotent: legacy installs of event_relay_lanes don't have category.
        if (self::tableExists('event_relay_lanes')
            && !self::columnExists('event_relay_lanes', 'category')) {
            static::query("ALTER TABLE event_relay_lanes
                           ADD COLUMN category VARCHAR(255) NULL AFTER lane_id");
        }

        self::$applied['relays'] = true;
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
