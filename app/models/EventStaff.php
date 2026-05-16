<?php
namespace Models;

use Core\Model;

/**
 * Per-event Event Staff login accounts + privileges. Auth is independent of
 * the main users table — uniqueness is per (event_id, email).
 *
 * Privileges gate the staff dashboard menu:
 *   team_entry · lane_allocation · scoring · result_reports
 */
class EventStaff extends Model
{
    public const PRIVILEGES = [
        'team_entry'      => 'Team Entry',
        'lane_allocation' => 'Lane Allocation — Admin',
        'scoring'         => 'Scoring',
        'result_reports'  => 'Result Reports',
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
