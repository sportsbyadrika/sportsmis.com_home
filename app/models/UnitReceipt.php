<?php
namespace Models;

use Core\Model;

/**
 * Consolidated payment-receipt numbering + data for a unit's APPROVED bulk
 * transactions on an event (event_unit_payments with status = 'approved').
 *
 * A single receipt is issued per unit and covers every approved transaction.
 * The serial number is fixed on first generation (event_unit_receipts) so the
 * receipt number never changes on re-download.
 */
class UnitReceipt extends Model
{
    /** Approved bulk transactions for a unit, oldest approval first. */
    public static function approvedTxns(int $eventId, int $unitId): array
    {
        return static::rows(
            "SELECT id, transaction_date, reference_number, amount,
                    reviewed_at, reviewed_by_name
               FROM event_unit_payments
              WHERE event_id = ? AND unit_id = ? AND status = 'approved'
              ORDER BY reviewed_at ASC, id ASC",
            [$eventId, $unitId]
        );
    }

    /**
     * Fetch (or lazily assign) the stable serial number for a unit's receipt
     * on an event. The first call for an event/unit takes the next serial for
     * that event; later calls return the same number.
     */
    public static function serialFor(int $eventId, int $unitId): int
    {
        $row = static::row(
            "SELECT serial FROM event_unit_receipts WHERE event_id = ? AND unit_id = ?",
            [$eventId, $unitId]
        );
        if ($row) return (int)$row['serial'];

        // Next serial for this event.
        $max = static::row(
            "SELECT COALESCE(MAX(serial), 0) AS m FROM event_unit_receipts WHERE event_id = ?",
            [$eventId]
        );
        $next = (int)($max['m'] ?? 0) + 1;

        try {
            static::insert('event_unit_receipts', [
                'event_id' => $eventId,
                'unit_id'  => $unitId,
                'serial'   => $next,
            ]);
        } catch (\Throwable $e) {
            // Concurrent insert — re-read the row that won the unique key.
            $row = static::row(
                "SELECT serial FROM event_unit_receipts WHERE event_id = ? AND unit_id = ?",
                [$eventId, $unitId]
            );
            if ($row) return (int)$row['serial'];
            throw $e;
        }
        return $next;
    }
}
