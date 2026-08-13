<?php
namespace Models;

use Core\Model;

class EventDocument extends Model
{
    public static function forEvent(int $eventId): array
    {
        return static::rows(
            "SELECT * FROM event_documents WHERE event_id = ? ORDER BY name",
            [$eventId]
        );
    }

    public static function activeForEvent(int $eventId): array
    {
        return static::rows(
            "SELECT * FROM event_documents
              WHERE event_id = ? AND status = 'active' AND file IS NOT NULL
              ORDER BY name",
            [$eventId]
        );
    }

    public static function find(int $id): ?array
    {
        return static::row("SELECT * FROM event_documents WHERE id = ?", [$id]);
    }

    /**
     * event_id → file URL of the active "Help" document (purpose = "Help",
     * case-insensitive) for each of the given events. Used by the public login
     * page to offer a Help download on the event cards.
     */
    public static function helpMap(array $eventIds): array
    {
        $eventIds = array_values(array_filter(array_map('intval', $eventIds)));
        if (!$eventIds) return [];
        $ph  = implode(',', array_fill(0, count($eventIds), '?'));
        $rows = static::rows(
            "SELECT event_id, file FROM event_documents
              WHERE status = 'active' AND file IS NOT NULL AND file <> ''
                AND LOWER(TRIM(purpose)) = 'help'
                AND event_id IN ($ph)
              ORDER BY id",
            $eventIds
        );
        $map = [];
        foreach ($rows as $r) {
            $eid = (int)$r['event_id'];
            if (!isset($map[$eid]) && !empty($r['file'])) $map[$eid] = (string)$r['file'];
        }
        return $map;
    }

    public static function create(array $data): int
    {
        return static::insert('event_documents', $data);
    }

    public static function updateRow(int $id, array $data): void
    {
        static::update('event_documents', $data, ['id' => $id]);
    }

    public static function deleteRow(int $id): void
    {
        static::query("DELETE FROM event_documents WHERE id = ?", [$id]);
    }
}
