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

            // 2. Drop the OLD narrow (event_id, sport_id) unique index if it
            //    is still present. Run unconditionally — partial migrations
            //    in past runs can leave the column added but the index swap
            //    skipped, which then causes "Duplicate entry" on the second
            //    sport_event added under the same sport.
            if (self::indexExists('event_sports', 'uq_event_sport')) {
                @static::query("ALTER TABLE event_sports DROP INDEX uq_event_sport");
            }

            // 3. Ensure the new wider unique index exists.
            if (!self::indexExists('event_sports', 'uq_event_sport_event')) {
                @static::query("ALTER TABLE event_sports
                                ADD UNIQUE KEY uq_event_sport_event (event_id, sport_id, sport_event_id)");
            }

            // 4. Ensure the FK to sport_events exists (best-effort).
            if (!self::foreignKeyExists('event_sports', 'fk_event_sports_sport_event')) {
                @static::query("ALTER TABLE event_sports
                                ADD CONSTRAINT fk_event_sports_sport_event
                                FOREIGN KEY (sport_event_id) REFERENCES sport_events(id) ON DELETE SET NULL");
            }
        }

        // Backfill default age categories on a freshly-created table only if empty.
        $count = static::row("SELECT COUNT(*) AS c FROM age_categories");
        if ((int)($count['c'] ?? 0) === 0) {
            self::seedDefaultAgeCategories();
        }

        self::$applied['sport_hierarchy'] = true;
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
