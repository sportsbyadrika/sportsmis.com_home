<?php
namespace Models;

use Core\Model;

class Institution extends Model
{
    // ── Registration Queue ───────────────────────────────────────────────────

    public static function createRegistration(array $data): int
    {
        return static::insert('institution_registrations', $data);
    }

    public static function findRegistrationByEmail(string $email): ?array
    {
        return static::row('SELECT * FROM institution_registrations WHERE email = ?', [$email]);
    }

    public static function getPendingRegistrations(): array
    {
        return static::rows(
            'SELECT * FROM institution_registrations WHERE status = "pending" ORDER BY created_at DESC'
        );
    }

    public static function getRegistrationById(int $id): ?array
    {
        return static::row('SELECT * FROM institution_registrations WHERE id = ?', [$id]);
    }

    public static function updateRegistrationStatus(int $id, string $status, int $adminId, ?int $userId = null): void
    {
        $data = ['status' => $status, 'verified_at' => date('Y-m-d H:i:s'), 'verified_by' => $adminId];
        if ($userId) $data['user_id'] = $userId;
        static::update('institution_registrations', $data, ['id' => $id]);
    }

    // ── Institution ──────────────────────────────────────────────────────────

    /**
     * Idempotently add the SPOC/contact columns shipped after the original
     * schema. Safe to call on every profile-save request: each ALTER is
     * guarded by an INFORMATION_SCHEMA lookup, and the whole thing is a
     * no-op once the columns exist.
     */
    public static function ensureSchema(): void
    {
        static $checked = false;
        if ($checked) return;

        $expected = [
            'email'         => "VARCHAR(255) NULL",
            'website'       => "VARCHAR(255) NULL",
            'affiliated_to' => "VARCHAR(255) NULL",
            'spoc_name'     => "VARCHAR(255) NULL",
            'spoc_mobile'   => "VARCHAR(20)  NULL",
            'spoc_email'    => "VARCHAR(255) NULL",
        ];

        $existing = static::rows(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'institutions'"
        );
        $have = array_column($existing, 'COLUMN_NAME');
        $have = array_map('strtolower', $have);

        foreach ($expected as $col => $type) {
            if (!in_array(strtolower($col), $have, true)) {
                static::query("ALTER TABLE institutions ADD COLUMN {$col} {$type}");
            }
        }
        $checked = true;
    }

    public static function createInstitution(array $data): int
    {
        return static::insert('institutions', $data);
    }

    public static function findByUserId(int $userId): ?array
    {
        return static::row(
            'SELECT i.*, it.name AS type_name FROM institutions i
             LEFT JOIN institution_types it ON it.id = i.type_id
             WHERE i.user_id = ?',
            [$userId]
        );
    }

    public static function findById(int $id): ?array
    {
        return static::row(
            'SELECT i.*, it.name AS type_name FROM institutions i
             LEFT JOIN institution_types it ON it.id = i.type_id
             WHERE i.id = ?',
            [$id]
        );
    }

    public static function updateProfile(int $id, array $data): void
    {
        static::update('institutions', $data, ['id' => $id]);
    }

    /**
     * Contact bundle for notifications: display name, login email and SPOC
     * mobile. Prefers the SPOC name/mobile, falling back to the institution
     * name. Returns empty strings when unknown so callers can send to whatever
     * channels have a value.
     *
     * @return array{name:string, email:string, mobile:string}
     */
    public static function contact(int $id): array
    {
        try {
            $row = static::row(
                'SELECT i.name, i.spoc_name, i.spoc_mobile, u.email
                   FROM institutions i
              LEFT JOIN users u ON u.id = i.user_id
                  WHERE i.id = ? LIMIT 1',
                [$id]
            );
        } catch (\Throwable $e) { $row = null; }
        if (!$row) return ['name' => '', 'email' => '', 'mobile' => ''];
        return [
            'name'   => trim((string)($row['spoc_name'] ?? '')) ?: trim((string)($row['name'] ?? '')),
            'email'  => trim((string)($row['email'] ?? '')),
            'mobile' => trim((string)($row['spoc_mobile'] ?? '')),
        ];
    }

    /** Super-admin toggle for the per-institution Create Event facility. */
    public static function setEventCreationEnabled(int $id, int $enabled): void
    {
        static::update('institutions', ['event_creation_enabled' => $enabled ? 1 : 0], ['id' => $id]);
    }

    public static function approveInstitution(int $id, int $adminId, string $from, string $to): void
    {
        static::update('institutions', [
            'status'       => 'active',
            'approved_by'  => $adminId,
            'approved_at'  => date('Y-m-d H:i:s'),
            'validity_from'=> $from,
            'validity_to'  => $to,
        ], ['id' => $id]);
    }

    public static function getAll(string $status = ''): array
    {
        $sql = 'SELECT i.*, it.name AS type_name, u.email, ir.institution_name AS reg_name,
                       (SELECT COUNT(*) FROM events e WHERE e.institution_id = i.id) AS event_count,
                       (SELECT COUNT(*) FROM events e WHERE e.institution_id = i.id AND e.status = \'active\') AS active_event_count
                FROM institutions i
                LEFT JOIN institution_types it ON it.id = i.type_id
                LEFT JOIN users u ON u.id = i.user_id
                LEFT JOIN institution_registrations ir ON ir.id = i.registration_id';
        if ($status) {
            $sql .= ' WHERE i.status = ?';
            return static::rows($sql . ' ORDER BY i.created_at DESC', [$status]);
        }
        return static::rows($sql . ' ORDER BY i.created_at DESC');
    }

    public static function getTypes(): array
    {
        // Lazily ensure newer built-in types exist on already-seeded databases
        // (the table has no UNIQUE(name), so guard with a NOT EXISTS check).
        static $seeded = false;
        if (!$seeded) {
            foreach ([['State Department', 8]] as [$name, $sort]) {
                try {
                    static::query(
                        "INSERT INTO institution_types (name, sort_order)
                         SELECT ?, ? FROM DUAL
                          WHERE NOT EXISTS (SELECT 1 FROM institution_types WHERE name = ?)",
                        [$name, $sort, $name]
                    );
                } catch (\Throwable $e) { /* best-effort seed */ }
            }
            $seeded = true;
        }
        return static::rows('SELECT * FROM institution_types ORDER BY sort_order, name');
    }

    /**
     * All institutions with their owner email + how many events they own and
     * participate in — used by the super-admin "delete institution" page to
     * decide which rows are safe to delete.
     */
    public static function allWithEventCounts(): array
    {
        return static::rows(
            "SELECT i.id, i.name, i.address, it.name AS type_name, u.email AS owner_email,
                    (SELECT COUNT(*) FROM events e WHERE e.institution_id = i.id) AS event_count,
                    (SELECT COUNT(*) FROM event_units eu WHERE eu.linked_institution_id = i.id) AS participation_count
               FROM institutions i
          LEFT JOIN institution_types it ON it.id = i.type_id
          LEFT JOIN users u ON u.id = i.user_id
           ORDER BY i.name, i.id"
        );
    }
}
