<?php
namespace Models;

use Core\Model;

/**
 * Audit trail for Super Admin "login as institution" support sessions.
 */
class ImpersonationLog extends Model
{
    public static function start(array $data): int
    {
        return static::insert('impersonation_log', $data);
    }

    /** Stamp the end time on an open session row. */
    public static function end(int $id): void
    {
        if ($id <= 0) return;
        static::query(
            "UPDATE impersonation_log SET ended_at = NOW() WHERE id = ? AND ended_at IS NULL",
            [$id]
        );
    }

    /** Most recent sessions, with the institution name joined in. */
    public static function recent(int $limit = 200): array
    {
        $limit = max(1, min(1000, $limit));
        return static::rows(
            "SELECT l.*, i.name AS institution_name
               FROM impersonation_log l
          LEFT JOIN institutions i ON i.id = l.institution_id
              ORDER BY l.started_at DESC, l.id DESC
              LIMIT {$limit}"
        );
    }
}
