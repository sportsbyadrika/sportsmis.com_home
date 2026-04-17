<?php
namespace Models;

use Core\Model;

class Staff extends Model
{
    public static function getByInstitution(int $institutionId): array
    {
        return static::rows(
            'SELECT st.*, u.email, u.status AS user_status,
                    GROUP_CONCAT(sr.name ORDER BY sr.name SEPARATOR ", ") AS roles
             FROM staff st
             JOIN users u ON u.id = st.user_id
             LEFT JOIN staff_role_assignments sra ON sra.staff_id = st.id
             LEFT JOIN staff_roles sr ON sr.id = sra.role_id
             WHERE st.institution_id = ?
             GROUP BY st.id ORDER BY st.name',
            [$institutionId]
        );
    }

    public static function findById(int $id): ?array
    {
        return static::row(
            'SELECT st.*, u.email FROM staff st JOIN users u ON u.id = st.user_id WHERE st.id = ?',
            [$id]
        );
    }

    public static function getRoleIds(int $staffId): array
    {
        return array_column(
            static::rows('SELECT role_id FROM staff_role_assignments WHERE staff_id = ?', [$staffId]),
            'role_id'
        );
    }

    public static function create(array $staffData, array $roleIds): int
    {
        $id = static::insert('staff', $staffData);
        static::syncRoles($id, $roleIds);
        return $id;
    }

    public static function updateStaff(int $id, array $data, array $roleIds): void
    {
        static::update('staff', $data, ['id' => $id]);
        static::syncRoles($id, $roleIds);
    }

    private static function syncRoles(int $staffId, array $roleIds): void
    {
        static::query('DELETE FROM staff_role_assignments WHERE staff_id = ?', [$staffId]);
        foreach ($roleIds as $roleId) {
            static::insert('staff_role_assignments', ['staff_id' => $staffId, 'role_id' => (int)$roleId]);
        }
    }

    public static function getAllRoles(): array
    {
        return static::rows('SELECT * FROM staff_roles ORDER BY name');
    }
}
