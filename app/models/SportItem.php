<?php
namespace Models;

use Core\Model;

/**
 * Master "Sports Items / Weapons" — items belong to a sport.
 * The Super Admin maintains this catalogue at /admin/settings/sport-items.
 * Event Admins pick a subset per event into event_sport_items, and athletes
 * register their own copies via registration_sport_items.
 */
class SportItem extends Model
{
    /** All items for a sport (active + inactive). */
    public static function bySport(int $sportId): array
    {
        return static::rows(
            "SELECT * FROM sport_items WHERE sport_id = ? ORDER BY status DESC, name",
            [$sportId]
        );
    }

    /** Active items only — used by event-admin and athlete pickers. */
    public static function activeBySport(int $sportId): array
    {
        return static::rows(
            "SELECT * FROM sport_items WHERE sport_id = ? AND status = 'active' ORDER BY name",
            [$sportId]
        );
    }

    public static function find(int $id): ?array
    {
        return static::row("SELECT * FROM sport_items WHERE id = ?", [$id]);
    }

    public static function create(array $data): int
    {
        return static::insert('sport_items', $data);
    }

    public static function updateRow(int $id, array $data): void
    {
        static::update('sport_items', $data, ['id' => $id]);
    }

    public static function deleteRow(int $id): void
    {
        static::query("DELETE FROM sport_items WHERE id = ?", [$id]);
    }
}
