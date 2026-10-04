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

    /**
     * Materialise an approved participation: create the event_unit for the
     * requesting institution, copy its SPOC, and mark the request approved with
     * a link to the new unit. Returns the new unit id. Shared by the admin
     * "Approve" action and the auto-approve (join-without-approval) flow.
     */
    public static function approveFromRequest(int $eventId, array $req, ?int $reviewerUserId = null, ?string $notes = null): int
    {
        $unitId = static::create([
            'event_id'              => $eventId,
            'name'                  => (string)($req['proposed_unit_name'] ?? 'Unit'),
            'address'               => ($req['proposed_unit_address'] ?? null) ?: null,
            'linked_institution_id' => (int)($req['institution_id'] ?? 0),
        ]);
        static::syncSpocFromInstitution($unitId, (int)($req['institution_id'] ?? 0));
        \Models\Event::rowsRaw(
            "UPDATE event_participation_requests
                SET status='approved', reviewed_at=NOW(),
                    reviewed_by_user_id=?, reviewer_notes=?, linked_unit_id=?
              WHERE id=?",
            [$reviewerUserId, $notes, $unitId, (int)($req['id'] ?? 0)]
        );
        return $unitId;
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
