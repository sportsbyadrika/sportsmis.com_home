<?php
namespace Models;

use Core\Model;

class AgeCategory extends Model
{
    public static function all(): array
    {
        return static::rows("SELECT * FROM age_categories ORDER BY sort_order, name");
    }

    public static function active(): array
    {
        return static::rows("SELECT * FROM age_categories WHERE status='active' ORDER BY sort_order, name");
    }

    /**
     * Categories belonging to a specific Set ('master', 'cbse', ...) —
     * used by the event edit form and the sport_events catalog form to
     * scope the age-category picker to the set the event is using.
     * Falls back to the 'master' set when the caller passes an empty
     * value, so existing call sites that don't yet know about sets
     * preserve their behaviour.
     */
    public static function forSet(string $set = 'master'): array
    {
        $set = $set !== '' ? $set : 'master';
        return static::rows(
            "SELECT * FROM age_categories
              WHERE status = 'active' AND set_code = ?
              ORDER BY sort_order, name",
            [$set]
        );
    }

    /** Distinct set codes present in the master list (always includes 'master'). */
    public static function sets(): array
    {
        $rows = static::rows(
            "SELECT DISTINCT set_code FROM age_categories
              WHERE set_code IS NOT NULL AND set_code <> ''
              ORDER BY (set_code = 'master') DESC, set_code"
        );
        $sets = array_map(fn($r) => $r['set_code'], $rows);
        if (!in_array('master', $sets, true)) array_unshift($sets, 'master');
        return $sets;
    }

    /**
     * Built-in age-category sets the system ships with → display label.
     * Sets created purely by adding DB rows still work; listing them here
     * just guarantees the built-in sets are always selectable (so an admin
     * can create the first category under a new set that has no rows yet).
     */
    public static function knownSets(): array
    {
        return [
            'master'       => 'Master (default)',
            'cbse'         => 'CBSE School Sports',
            'cbse_skating' => 'CBSE Skating',
        ];
    }

    /**
     * Sets present in the DB merged with the built-in sets, so a brand-new
     * built-in set appears in the admin dropdowns before it has any rows.
     * 'master' is always first; the rest follow alphabetically.
     */
    public static function setsWithKnown(): array
    {
        $all = array_values(array_unique(array_merge(
            array_keys(static::knownSets()),
            static::sets()
        )));
        usort($all, fn($a, $b) => $a === 'master' ? -1 : ($b === 'master' ? 1 : strcmp($a, $b)));
        return $all;
    }

    /** Friendly display label for a set code (falls back to a title-cased slug). */
    public static function setLabel(string $code): string
    {
        return static::knownSets()[$code] ?? ucwords(str_replace('_', ' ', $code));
    }

    public static function find(int $id): ?array
    {
        return static::row("SELECT * FROM age_categories WHERE id = ?", [$id]);
    }

    public static function create(array $data): int
    {
        return static::insert('age_categories', $data);
    }

    public static function updateRow(int $id, array $data): void
    {
        static::update('age_categories', $data, ['id' => $id]);
    }

    public static function deleteRow(int $id): void
    {
        static::query("DELETE FROM age_categories WHERE id = ?", [$id]);
    }

    /** IDs of categories that the given category is "also eligible" to play in. */
    public static function upgradesFor(int $id): array
    {
        $rows = static::rows(
            "SELECT to_age_category_id FROM age_category_upgrades WHERE from_age_category_id = ?",
            [$id]
        );
        return array_map(fn($r) => (int)$r['to_age_category_id'], $rows);
    }

    /** Replace the upgrade list for $id with the given target IDs (self excluded). */
    public static function setUpgrades(int $id, array $targetIds): void
    {
        $targetIds = array_values(array_unique(array_filter(array_map('intval', $targetIds),
            fn($t) => $t > 0 && $t !== $id)));
        static::query("DELETE FROM age_category_upgrades WHERE from_age_category_id = ?", [$id]);
        foreach ($targetIds as $t) {
            try {
                static::query(
                    "INSERT IGNORE INTO age_category_upgrades (from_age_category_id, to_age_category_id) VALUES (?, ?)",
                    [$id, $t]
                );
            } catch (\Throwable $e) {
                error_log('[AgeCategory::setUpgrades] ' . $e->getMessage());
            }
        }
    }

    /** Full graph map: from_id ⇒ [to_id, ...] for every configured row. */
    public static function upgradeMap(): array
    {
        $rows = static::rows("SELECT from_age_category_id, to_age_category_id FROM age_category_upgrades");
        $map  = [];
        foreach ($rows as $r) {
            $map[(int)$r['from_age_category_id']][] = (int)$r['to_age_category_id'];
        }
        return $map;
    }
}
