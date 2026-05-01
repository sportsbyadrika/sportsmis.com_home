<?php
namespace Models;

use Core\Model;

class EventUnit extends Model
{
    public static function forEvent(int $eventId): array
    {
        return static::rows(
            "SELECT * FROM event_units WHERE event_id = ? ORDER BY name",
            [$eventId]
        );
    }

    public static function find(int $id): ?array
    {
        return static::row("SELECT * FROM event_units WHERE id = ?", [$id]);
    }

    public static function create(array $data): int
    {
        return static::insert('event_units', $data);
    }

    public static function updateRow(int $id, array $data): void
    {
        static::update('event_units', $data, ['id' => $id]);
    }

    public static function deleteRow(int $id): void
    {
        static::query("DELETE FROM event_units WHERE id = ?", [$id]);
    }
}
