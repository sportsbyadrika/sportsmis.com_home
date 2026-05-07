<?php
namespace Models;

use Core\Model;

/**
 * Athlete's "Sports Items / Weapons Sharing Details" attached to a single
 * event_registrations row. Each row carries a model + serial number for
 * the chosen sport_item.
 */
class RegistrationSportItem extends Model
{
    public static function forRegistration(int $registrationId): array
    {
        return static::rows(
            "SELECT rsi.*,
                    si.name        AS item_name,
                    si.sport_id,
                    s.name         AS sport_name
               FROM registration_sport_items rsi
               JOIN sport_items si ON si.id = rsi.sport_item_id
               JOIN sports s       ON s.id  = si.sport_id
              WHERE rsi.registration_id = ?
              ORDER BY s.name, si.name, rsi.id",
            [$registrationId]
        );
    }

    public static function find(int $id): ?array
    {
        return static::row("SELECT * FROM registration_sport_items WHERE id = ?", [$id]);
    }

    public static function create(array $data): int
    {
        return static::insert('registration_sport_items', $data);
    }

    public static function updateRow(int $id, array $data): void
    {
        static::update('registration_sport_items', $data, ['id' => $id]);
    }

    public static function deleteRow(int $id): void
    {
        static::query("DELETE FROM registration_sport_items WHERE id = ?", [$id]);
    }
}
