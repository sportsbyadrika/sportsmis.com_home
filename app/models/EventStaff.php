<?php
namespace Models;

use Core\Model;

/**
 * Per-event Event Staff login accounts + privileges. Auth is independent of
 * the main users table — uniqueness is per (event_id, email).
 *
 * Privileges gate the staff dashboard menu:
 *   order_of_events · lane_allocation · scoring · result_reports · team_entry
 */
class EventStaff extends Model
{
    public const PRIVILEGES = [
        'order_of_events' => 'Order of Events',
        'lane_allocation' => 'Lane Allocation — Admin',
        'scoring'         => 'Scoring',
        'result_reports'  => 'Result Reports',
        'team_entry'      => 'Team Entry',
    ];

    public static function findById(int $id): ?array
    {
        return static::row("SELECT * FROM event_staff WHERE id = ?", [$id]);
    }

    public static function findByEventEmail(int $eventId, string $email): ?array
    {
        return static::row(
            "SELECT * FROM event_staff WHERE event_id = ? AND email = ?",
            [$eventId, strtolower($email)]
        );
    }

    /**
     * Every ACTIVE event-staff account that shares this email address, across
     * all events, hydrated with the event details needed for a dashboard card
     * and the account's privilege set.
     *
     * Used to surface an "Event Staff Access" card on a main-account
     * (athlete/institution) dashboard: when the signed-in user's login email
     * matches a staff account, they can open the staff area directly instead of
     * using the separate event-code login. Email is stored lowercased, so match
     * on the lowercased account email.
     */
    public static function activeForEmail(string $email): array
    {
        $email = strtolower(trim($email));
        if ($email === '') return [];
        $rows = static::rows(
            "SELECT es.id, es.event_id, es.name, es.email,
                    e.name AS event_name, e.logo AS event_logo, e.location,
                    e.event_date_from, e.event_date_to, e.status AS event_status
               FROM event_staff es
               JOIN events e ON e.id = es.event_id
              WHERE es.email = ? AND es.status = 'active'
              ORDER BY e.event_date_from DESC, e.id DESC",
            [$email]
        );
        foreach ($rows as &$r) {
            $r['privileges'] = static::privilegesFor((int)$r['id']);
        }
        unset($r);
        return $rows;
    }

    /** All staff for an event, with privileges hydrated. */
    public static function forEvent(int $eventId): array
    {
        $rows = static::rows(
            "SELECT * FROM event_staff WHERE event_id = ? ORDER BY name",
            [$eventId]
        );
        foreach ($rows as &$r) {
            $r['privileges'] = static::privilegesFor((int)$r['id']);
        }
        unset($r);
        return $rows;
    }

    public static function create(array $data): int
    {
        $data['email'] = strtolower((string)($data['email'] ?? ''));
        return static::insert('event_staff', $data);
    }

    public static function updateRow(int $id, array $data): void
    {
        if (isset($data['email'])) $data['email'] = strtolower((string)$data['email']);
        static::update('event_staff', $data, ['id' => $id]);
    }

    public static function deleteRow(int $id): void
    {
        static::query("DELETE FROM event_staff WHERE id = ?", [$id]);
    }

    // ── Privileges ───────────────────────────────────────────────────────────

    public static function privilegesFor(int $staffId): array
    {
        $rows = static::rows(
            "SELECT privilege FROM event_staff_privileges WHERE event_staff_id = ?",
            [$staffId]
        );
        return array_map(fn($r) => $r['privilege'], $rows);
    }

    /** Replace the staff member's privilege set. */
    public static function setPrivileges(int $staffId, array $privileges): void
    {
        static::query("DELETE FROM event_staff_privileges WHERE event_staff_id = ?", [$staffId]);
        foreach (array_unique($privileges) as $p) {
            if (!isset(self::PRIVILEGES[$p])) continue;
            try {
                static::insert('event_staff_privileges', [
                    'event_staff_id' => $staffId,
                    'privilege'      => $p,
                ]);
            } catch (\Throwable $e) {
                error_log('[EventStaff::setPrivileges] ' . $e->getMessage());
            }
        }
    }

    // ── Auth ─────────────────────────────────────────────────────────────────

    public static function attempt(string $eventCode, string $email, string $password): ?array
    {
        if ($eventCode === '' || $email === '' || $password === '') return null;
        $event = static::row("SELECT id FROM events WHERE event_code = ? LIMIT 1", [trim($eventCode)]);
        if (!$event) return null;
        $staff = static::findByEventEmail((int)$event['id'], $email);
        if (!$staff || $staff['status'] !== 'active') return null;
        if (!password_verify($password, $staff['password'])) return null;
        static::update('event_staff', ['last_login_at' => date('Y-m-d H:i:s')], ['id' => $staff['id']]);
        return $staff;
    }

    public static function updatePassword(int $id, string $hash): void
    {
        static::update('event_staff', ['password' => $hash], ['id' => $id]);
    }
}
