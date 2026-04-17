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
        $sql = 'SELECT i.*, it.name AS type_name, u.email, ir.institution_name AS reg_name
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
        return static::rows('SELECT * FROM institution_types ORDER BY sort_order, name');
    }
}
