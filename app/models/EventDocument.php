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
