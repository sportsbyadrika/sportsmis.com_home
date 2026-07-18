<?php
namespace Models;

use Core\Model;

class EventUnit extends Model
{
    public static function forEvent(int $eventId): array
    {
        return static::rows(
            "SELECT * FROM event_units WHERE event_id = ? ORDER BY name",
            [$eventId]
        );
    }

    public static function find(int $id): ?array
    {
        return static::row("SELECT * FROM event_units WHERE id = ?", [$id]);
    }

    public static function create(array $data): int
    {
        return static::insert('event_units', $data);
    }

    public static function updateRow(int $id, array $data): void
    {
        static::update('event_units', $data, ['id' => $id]);
    }

    public static function deleteRow(int $id): void
    {
        static::query("DELETE FROM event_units WHERE id = ?", [$id]);
    }

    /**
     * Copy the SPOC (single point of contact) details from an institution
     * onto one of its linked event_units. Prefers the institution's dedicated
     * SPOC fields, falling back to the institution's own name / email so the
     * unit always carries some contact. Returns true when a row was updated.
     */
    public static function syncSpocFromInstitution(int $unitId, int $institutionId): bool
    {
        if ($unitId <= 0 || $institutionId <= 0) return false;
        $inst = Institution::findById($institutionId);
        if (!$inst) return false;

        $name   = trim((string)($inst['spoc_name']   ?? '')) ?: trim((string)($inst['name']  ?? ''));
        $mobile = trim((string)($inst['spoc_mobile'] ?? ''));
        $email  = trim((string)($inst['spoc_email']  ?? '')) ?: trim((string)($inst['email'] ?? ''));

        try {
            static::update('event_units', [
                'spoc_name'   => $name   !== '' ? $name   : null,
                'spoc_mobile' => $mobile !== '' ? $mobile : null,
                'spoc_email'  => $email  !== '' ? $email  : null,
            ], ['id' => $unitId]);
        } catch (\Throwable $e) {
            return false; // columns not present yet
        }
        return true;
    }
}
