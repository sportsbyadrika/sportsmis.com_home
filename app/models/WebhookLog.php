<?php
namespace Models;

use Core\Model;

/**
 * Audit table for incoming Razorpay webhook callbacks. The webhook
 * UNIQUE indexes on rzp_event_id, so re-deliveries are suppressed at
 * the DB level — write through INSERT IGNORE here to avoid surfacing
 * that as an error.
 */
class WebhookLog extends Model
{
    public static function record(array $data): void
    {
        $cols = array_keys($data);
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        try {
            static::query(
                "INSERT IGNORE INTO webhook_log (" . implode(',', $cols) . ") VALUES ({$placeholders})",
                array_values($data)
            );
        } catch (\Throwable $e) {
            error_log('[WebhookLog::record] ' . $e->getMessage());
        }
    }

    public static function existsByEventId(string $rzpEventId): bool
    {
        if ($rzpEventId === '') return false;
        $row = static::row("SELECT id FROM webhook_log WHERE rzp_event_id = ?", [$rzpEventId]);
        return (bool)$row;
    }
}
