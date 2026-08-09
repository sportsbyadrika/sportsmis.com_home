<?php
namespace Models;

use Core\Model;

/**
 * Capability requests for the unified account ("one account, many hats").
 * type = 'organiser' (super-admin approves) | 'unit' (event admin approves).
 */
class AccessRequest extends Model
{
    public static function create(array $data): int
    {
        return static::insert('access_requests', [
            'user_id'  => $data['user_id']  ?? null,
            'email'    => strtolower(trim((string)($data['email'] ?? ''))),
            'type'     => (string)($data['type'] ?? 'organiser'),
            'event_id' => $data['event_id'] ?? null,
            'org_name' => $data['org_name'] ?? null,
            'sport'    => $data['sport'] ?? null,
            'message'  => $data['message']  ?? null,
            'status'   => 'pending',
        ]);
    }

    public static function findById(int $id): ?array
    {
        return static::row("SELECT * FROM access_requests WHERE id = ?", [$id]);
    }

    /** The user's most recent request of a type (any status), or null. */
    public static function latestForUser(int $userId, string $type): ?array
    {
        return static::row(
            "SELECT * FROM access_requests WHERE user_id = ? AND type = ?
              ORDER BY id DESC LIMIT 1",
            [$userId, $type]
        );
    }

    /** True if the user already has an undecided request of this type. */
    public static function hasPending(int $userId, string $type): bool
    {
        return (bool)static::row(
            "SELECT id FROM access_requests WHERE user_id = ? AND type = ? AND status = 'pending' LIMIT 1",
            [$userId, $type]
        );
    }

    /** Pending requests of a type, newest first (for the admin/event queue). */
    public static function pending(string $type): array
    {
        return static::rows(
            "SELECT * FROM access_requests WHERE type = ? AND status = 'pending' ORDER BY id DESC",
            [$type]
        );
    }

    /** Recently decided requests of a type (for context under the queue). */
    public static function recentDecided(string $type, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        return static::rows(
            "SELECT * FROM access_requests WHERE type = ? AND status <> 'pending'
              ORDER BY decided_at DESC, id DESC LIMIT {$limit}",
            [$type]
        );
    }

    public static function countPending(string $type): int
    {
        $r = static::row(
            "SELECT COUNT(*) AS c FROM access_requests WHERE type = ? AND status = 'pending'",
            [$type]
        );
        return (int)($r['c'] ?? 0);
    }

    public static function decide(int $id, string $status, ?int $adminId, string $note = ''): void
    {
        static::query(
            "UPDATE access_requests
                SET status = ?, admin_note = ?, decided_by = ?, decided_at = NOW()
              WHERE id = ?",
            [$status, $note !== '' ? $note : null, $adminId, $id]
        );
    }
}
