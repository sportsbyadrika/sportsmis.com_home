<?php
namespace Models;

use Core\Model;

/**
 * Registry of issued Athletics / Skating certificates (Merit / Appreciation).
 * Each row carries a stable certificate number and generated / printed flags.
 */
class TrackCertificate extends Model
{
    public static function find(int $eventId, string $type, string $key): ?array
    {
        return static::row(
            "SELECT * FROM track_certificates WHERE event_id = ? AND cert_type = ? AND cert_key = ?",
            [$eventId, $type, $key]
        );
    }

    public static function maxSeq(int $eventId, string $type): int
    {
        $r = static::row(
            "SELECT COALESCE(MAX(seq), 0) AS mx FROM track_certificates WHERE event_id = ? AND cert_type = ?",
            [$eventId, $type]
        );
        return (int)($r['mx'] ?? 0);
    }

    public static function create(array $data): int
    {
        return static::insert('track_certificates', $data);
    }

    public static function markPrinted(int $eventId, array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) return 0;
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = static::query(
            "UPDATE track_certificates
                SET is_printed = 1, printed_at = ?
              WHERE event_id = ? AND id IN ({$in})",
            array_merge([date('Y-m-d H:i:s'), $eventId], $ids)
        );
        return $stmt->rowCount();
    }

    /** All issued certificates for an event, newest first. */
    public static function forEvent(int $eventId, string $type = ''): array
    {
        $sql = "SELECT * FROM track_certificates WHERE event_id = ?";
        $params = [$eventId];
        if ($type !== '') { $sql .= " AND cert_type = ?"; $params[] = $type; }
        $sql .= " ORDER BY cert_type, (seq IS NULL), seq, id";
        return static::rows($sql, $params);
    }

    /** Counts + printed counts per type for the summary line. */
    public static function statsForEvent(int $eventId): array
    {
        $rows = static::rows(
            "SELECT cert_type,
                    COUNT(*) AS total,
                    SUM(CASE WHEN is_printed = 1 THEN 1 ELSE 0 END) AS printed
               FROM track_certificates WHERE event_id = ? GROUP BY cert_type",
            [$eventId]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(string)$r['cert_type']] = ['total' => (int)$r['total'], 'printed' => (int)$r['printed']];
        }
        return $out;
    }

    public static function deleteAllForEvent(int $eventId, string $type = ''): int
    {
        if ($type !== '') {
            return static::query("DELETE FROM track_certificates WHERE event_id = ? AND cert_type = ?",
                [$eventId, $type])->rowCount();
        }
        return static::query("DELETE FROM track_certificates WHERE event_id = ?", [$eventId])->rowCount();
    }
}
