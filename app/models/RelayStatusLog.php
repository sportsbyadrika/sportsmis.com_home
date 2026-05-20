<?php
namespace Models;

use Core\Model;

/**
 * Append-only audit log of relay result-status transitions. Read by the
 * relay list / lane list / future override-history surfaces.
 */
class RelayStatusLog extends Model
{
    public static function log(int $relayId, ?string $from, string $to, string $by, ?string $notes = null): void
    {
        static::insert('relay_status_log', [
            'relay_id'    => $relayId,
            'from_status' => $from,
            'to_status'   => $to,
            'changed_by'  => $by,
            'notes'       => $notes ?: null,
        ]);
    }

    public static function forRelay(int $relayId): array
    {
        return static::rows(
            "SELECT * FROM relay_status_log WHERE relay_id = ? ORDER BY changed_at DESC, id DESC",
            [$relayId]
        );
    }
}
