<?php
namespace Models;

use Core\Model;

/**
 * Event-admin → unit notice board (event_unit_messages). A message targets one
 * unit or every unit on the event (unit_id NULL = all units).
 */
class UnitMessage extends Model
{
    public static function create(array $data): int
    {
        return static::insert('event_unit_messages', $data);
    }

    public static function find(int $id): ?array
    {
        return static::row("SELECT * FROM event_unit_messages WHERE id = ?", [$id]);
    }

    public static function delete(int $id): void
    {
        static::query("DELETE FROM event_unit_messages WHERE id = ?", [$id]);
    }

    /** Messages visible to a unit: unit-specific + event-wide broadcasts. */
    public static function forUnit(int $eventId, int $unitId): array
    {
        return static::rows(
            "SELECT * FROM event_unit_messages
              WHERE event_id = ? AND (unit_id = ? OR unit_id IS NULL)
              ORDER BY (priority = 'urgent') DESC, created_at DESC, id DESC",
            [$eventId, $unitId]
        );
    }

    /** Every message on an event, with the target unit name, for the admin list. */
    public static function forEventAdmin(int $eventId): array
    {
        return static::rows(
            "SELECT m.*, eu.name AS unit_name
               FROM event_unit_messages m
          LEFT JOIN event_units eu ON eu.id = m.unit_id
              WHERE m.event_id = ?
              ORDER BY m.created_at DESC, m.id DESC",
            [$eventId]
        );
    }
}
