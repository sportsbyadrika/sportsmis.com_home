<?php
namespace Models;

use Core\Model;

class Grievance extends Model
{
    public static function create(array $data): int
    {
        return static::insert('event_grievances', $data);
    }

    public static function find(int $id): ?array
    {
        return static::row("SELECT * FROM event_grievances WHERE id = ?", [$id]);
    }

    public static function withContext(int $id): ?array
    {
        return static::row(
            "SELECT g.*, e.name AS event_name, e.institution_id,
                    a.name AS athlete_name, a.mobile AS athlete_mobile,
                    u.email AS athlete_email
               FROM event_grievances g
               JOIN events    e ON e.id = g.event_id
               JOIN athletes  a ON a.id = g.athlete_id
          LEFT JOIN users     u ON u.id = a.user_id
              WHERE g.id = ?",
            [$id]
        );
    }

    public static function forAthlete(int $athleteId): array
    {
        return static::rows(
            "SELECT g.*, e.name AS event_name,
                    (SELECT COUNT(*) FROM event_grievance_replies r WHERE r.grievance_id = g.id) AS reply_count
               FROM event_grievances g
               JOIN events e ON e.id = g.event_id
              WHERE g.athlete_id = ?
              ORDER BY g.updated_at DESC",
            [$athleteId]
        );
    }

    public static function forEvent(int $eventId, string $status = ''): array
    {
        $where  = ['g.event_id = ?']; $params = [$eventId];
        if (in_array($status, ['open','in_progress','resolved','closed'], true)) {
            $where[]  = 'g.status = ?';
            $params[] = $status;
        }
        return static::rows(
            "SELECT g.*, a.name AS athlete_name, a.mobile AS athlete_mobile,
                    (SELECT COUNT(*) FROM event_grievance_replies r WHERE r.grievance_id = g.id) AS reply_count
               FROM event_grievances g
               JOIN athletes a ON a.id = g.athlete_id
              WHERE " . implode(' AND ', $where) . "
              ORDER BY (g.status = 'open') DESC, g.updated_at DESC",
            $params
        );
    }

    public static function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['open','in_progress','resolved','closed'], true)) return;
        static::query(
            "UPDATE event_grievances SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$status, $id]
        );
    }

    public static function bumpUpdated(int $id): void
    {
        static::query("UPDATE event_grievances SET updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$id]);
    }

    // ── Replies ─────────────────────────────────────────────────────────────

    public static function addReply(array $data): int
    {
        return static::insert('event_grievance_replies', $data);
    }

    public static function replies(int $grievanceId): array
    {
        return static::rows(
            "SELECT r.*, u.email AS author_email
               FROM event_grievance_replies r
          LEFT JOIN users u ON u.id = r.author_user_id
              WHERE r.grievance_id = ?
              ORDER BY r.id ASC",
            [$grievanceId]
        );
    }
}
