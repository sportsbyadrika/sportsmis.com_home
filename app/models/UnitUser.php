<?php
namespace Models;

use Core\Model;

/**
 * Per-event Unit / Institution / Club login accounts. Auth is independent
 * of the main users table — uniqueness is per (event_id, email) so the
 * same email can be reused across events.
 *
 * Each unit_user can be assigned to one or more event_units; the dashboard
 * shows aggregated stats + athletes for the currently selected unit.
 */
class UnitUser extends Model
{
    public static function findById(int $id): ?array
    {
        return static::row("SELECT * FROM unit_users WHERE id = ?", [$id]);
    }

    public static function findByEventEmail(int $eventId, string $email): ?array
    {
        return static::row(
            "SELECT * FROM unit_users WHERE event_id = ? AND email = ?",
            [$eventId, strtolower($email)]
        );
    }

    /** All unit users for an event (admin management screen). */
    public static function forEvent(int $eventId): array
    {
        $rows = static::rows(
            "SELECT uu.*,
                    (SELECT COUNT(*) FROM unit_user_units
                       WHERE unit_user_id = uu.id) AS assigned_count
               FROM unit_users uu
              WHERE uu.event_id = ?
              ORDER BY uu.name",
            [$eventId]
        );
        // Hydrate the assigned units' names so the UI can render them inline.
        foreach ($rows as &$r) {
            $r['units'] = static::assignmentsFor((int)$r['id']);
        }
        unset($r);
        return $rows;
    }

    public static function create(array $data): int
    {
        $data['email'] = strtolower((string)($data['email'] ?? ''));
        return static::insert('unit_users', $data);
    }

    public static function updateRow(int $id, array $data): void
    {
        if (isset($data['email'])) $data['email'] = strtolower((string)$data['email']);
        static::update('unit_users', $data, ['id' => $id]);
    }

    public static function deleteRow(int $id): void
    {
        static::query("DELETE FROM unit_users WHERE id = ?", [$id]);
    }

    // ── Assignments ──────────────────────────────────────────────────────────

    /** Replace the unit_user's assigned units in one shot. */
    public static function setAssignments(int $unitUserId, array $eventUnitIds): void
    {
        static::query("DELETE FROM unit_user_units WHERE unit_user_id = ?", [$unitUserId]);
        $eventUnitIds = array_values(array_unique(array_map('intval', $eventUnitIds)));
        foreach ($eventUnitIds as $uid) {
            if ($uid <= 0) continue;
            try {
                static::insert('unit_user_units', [
                    'unit_user_id'  => $unitUserId,
                    'event_unit_id' => $uid,
                ]);
            } catch (\Throwable $e) {
                error_log('[UnitUser::setAssignments] ' . $e->getMessage());
            }
        }
    }

    /** List of event_units assigned to a unit_user, with names + addresses. */
    public static function assignmentsFor(int $unitUserId): array
    {
        return static::rows(
            "SELECT eu.id, eu.name, eu.address
               FROM unit_user_units uuu
               JOIN event_units    eu ON eu.id = uuu.event_unit_id
              WHERE uuu.unit_user_id = ?
              ORDER BY eu.name",
            [$unitUserId]
        );
    }

    public static function assignmentIds(int $unitUserId): array
    {
        return array_map(fn($r) => (int)$r['id'], static::assignmentsFor($unitUserId));
    }

    // ── Auth ─────────────────────────────────────────────────────────────────

    /**
     * Verify (event_code, email, password) and return the unit_user row
     * on success, null on any failure. Status must be 'active'.
     */
    public static function attempt(string $eventCode, string $email, string $password): ?array
    {
        if ($eventCode === '' || $email === '' || $password === '') return null;
        $event = static::row(
            "SELECT id FROM events WHERE event_code = ? LIMIT 1",
            [trim($eventCode)]
        );
        if (!$event) return null;
        $user = static::findByEventEmail((int)$event['id'], $email);
        if (!$user || $user['status'] !== 'active') return null;
        if (!password_verify($password, $user['password'])) return null;
        static::update('unit_users', ['last_login_at' => date('Y-m-d H:i:s')], ['id' => $user['id']]);
        return $user;
    }

    public static function updatePassword(int $id, string $hash): void
    {
        static::update('unit_users', ['password' => $hash], ['id' => $id]);
    }
}
