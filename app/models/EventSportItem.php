<?php
namespace Models;

use Core\Model;

/**
 * Per-event allow-list of Sports Items / Weapons. The event administrator
 * picks which catalogue items are available for the event; athletes can
 * only declare items present in this list.
 */
class EventSportItem extends Model
{
    /** Items joined with master + sport name; includes status from master. */
    public static function forEvent(int $eventId): array
    {
        return static::rows(
            "SELECT esi.id           AS event_item_id,
                    si.id            AS sport_item_id,
                    si.sport_id,
                    si.name          AS item_name,
                    si.description   AS item_description,
                    si.status        AS item_status,
                    s.name           AS sport_name
               FROM event_sport_items esi
               JOIN sport_items si ON si.id = esi.sport_item_id
               JOIN sports s       ON s.id  = si.sport_id
              WHERE esi.event_id = ?
              ORDER BY s.name, si.name",
            [$eventId]
        );
    }

    public static function activeForEventBySport(int $eventId, int $sportId): array
    {
        return static::rows(
            "SELECT si.id, si.name
               FROM event_sport_items esi
               JOIN sport_items si ON si.id = esi.sport_item_id
              WHERE esi.event_id = ? AND si.sport_id = ? AND si.status = 'active'
              ORDER BY si.name",
            [$eventId, $sportId]
        );
    }

    public static function add(int $eventId, int $sportItemId): int
    {
        $exists = static::row(
            "SELECT id FROM event_sport_items WHERE event_id = ? AND sport_item_id = ?",
            [$eventId, $sportItemId]
        );
        if ($exists) return (int)$exists['id'];
        return static::insert('event_sport_items', [
            'event_id'      => $eventId,
            'sport_item_id' => $sportItemId,
        ]);
    }

    public static function remove(int $eventId, int $sportItemId): void
    {
        static::query(
            "DELETE FROM event_sport_items WHERE event_id = ? AND sport_item_id = ?",
            [$eventId, $sportItemId]
        );
    }
}
