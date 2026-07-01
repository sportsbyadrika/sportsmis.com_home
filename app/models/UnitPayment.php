<?php
namespace Models;

use Core\Model;

/**
 * Unit-level bulk payment transactions (event_unit_payments).
 *
 * Used when the event admin sets unit_payment_mode = 'bulk'. A unit logs
 * bank transactions that cover its whole demand (individual + team) without
 * linking to any single athlete. Lifecycle: draft → submitted → approved /
 * rejected. Rejected rows are a soft delete — excluded from every total; the
 * unit simply adds a fresh transaction.
 */
class UnitPayment extends Model
{
    public static function find(int $id): ?array
    {
        return static::row("SELECT * FROM event_unit_payments WHERE id = ?", [$id]);
    }

    public static function create(array $data): int
    {
        return static::insert('event_unit_payments', $data);
    }

    public static function updateRow(int $id, array $data): void
    {
        static::update('event_unit_payments', $data, ['id' => $id]);
    }

    public static function deleteRow(int $id): void
    {
        static::query("DELETE FROM event_unit_payments WHERE id = ?", [$id]);
    }

    /**
     * Active (non-rejected) transactions for the given units on an event,
     * newest first. Rejected rows are soft-deleted so they never appear
     * here.
     */
    public static function activeForUnits(int $eventId, array $unitIds): array
    {
        if (!$unitIds) return [];
        $ph = implode(',', array_fill(0, count($unitIds), '?'));
        return static::rows(
            "SELECT p.*, eu.name AS unit_name
               FROM event_unit_payments p
          LEFT JOIN event_units eu ON eu.id = p.unit_id
              WHERE p.event_id = ? AND p.unit_id IN ($ph) AND p.status <> 'rejected'
              ORDER BY p.transaction_date DESC, p.id DESC",
            array_merge([$eventId], array_map('intval', $unitIds))
        );
    }

    /** Rejected rows — kept so the unit can read the reason and re-enter. */
    public static function rejectedForUnits(int $eventId, array $unitIds): array
    {
        if (!$unitIds) return [];
        $ph = implode(',', array_fill(0, count($unitIds), '?'));
        return static::rows(
            "SELECT p.*, eu.name AS unit_name
               FROM event_unit_payments p
          LEFT JOIN event_units eu ON eu.id = p.unit_id
              WHERE p.event_id = ? AND p.unit_id IN ($ph) AND p.status = 'rejected'
              ORDER BY p.transaction_date DESC, p.id DESC",
            array_merge([$eventId], array_map('intval', $unitIds))
        );
    }

    /**
     * Collection totals for the given units on an event.
     *   total     — draft + submitted + approved (non-rejected)
     *   draft / submitted / approved — per-bucket sums
     *   committed — submitted + approved (what the unit has actually
     *               claimed to have paid; used for the submit gate)
     */
    public static function collectionTotals(int $eventId, array $unitIds): array
    {
        $zero = ['total' => 0.0, 'draft' => 0.0, 'submitted' => 0.0, 'approved' => 0.0, 'committed' => 0.0];
        if (!$unitIds) return $zero;
        $ph = implode(',', array_fill(0, count($unitIds), '?'));
        $r = static::row(
            "SELECT
                COALESCE(SUM(CASE WHEN status IN ('draft','submitted','approved') THEN amount END), 0) AS total,
                COALESCE(SUM(CASE WHEN status = 'draft'     THEN amount END), 0) AS draft,
                COALESCE(SUM(CASE WHEN status = 'submitted' THEN amount END), 0) AS submitted,
                COALESCE(SUM(CASE WHEN status = 'approved'  THEN amount END), 0) AS approved
               FROM event_unit_payments
              WHERE event_id = ? AND unit_id IN ($ph)",
            array_merge([$eventId], array_map('intval', $unitIds))
        ) ?: [];
        return [
            'total'     => (float)($r['total']     ?? 0),
            'draft'     => (float)($r['draft']     ?? 0),
            'submitted' => (float)($r['submitted'] ?? 0),
            'approved'  => (float)($r['approved']  ?? 0),
            'committed' => (float)($r['submitted'] ?? 0) + (float)($r['approved'] ?? 0),
        ];
    }

    /**
     * Every transaction the event admin should see on an event — those that
     * have left the unit's hands (submitted / approved / rejected). Drafts
     * stay private to the unit. Newest first, carrying the unit name.
     */
    public static function forEventAdmin(int $eventId): array
    {
        return static::rows(
            "SELECT p.*, eu.name AS unit_name
               FROM event_unit_payments p
          LEFT JOIN event_units eu ON eu.id = p.unit_id
              WHERE p.event_id = ? AND p.status IN ('submitted','approved','rejected')
              ORDER BY eu.name, p.transaction_date DESC, p.id DESC",
            [$eventId]
        );
    }
}
