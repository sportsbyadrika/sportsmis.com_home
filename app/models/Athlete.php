<?php
namespace Models;

use Core\Model;

class Athlete extends Model
{
    // ── Registration Queue ───────────────────────────────────────────────────

    public static function createRegistration(array $data): int
    {
        return static::insert('athlete_registrations', $data);
    }

    public static function findRegistrationByEmail(string $email): ?array
    {
        return static::row('SELECT * FROM athlete_registrations WHERE email = ?', [$email]);
    }

    public static function getPendingRegistrations(): array
    {
        return static::rows(
            'SELECT * FROM athlete_registrations WHERE status = "pending" ORDER BY created_at DESC'
        );
    }

    public static function getRegistrationById(int $id): ?array
    {
        return static::row('SELECT * FROM athlete_registrations WHERE id = ?', [$id]);
    }

    public static function updateRegistrationStatus(int $id, string $status, int $adminId, ?int $userId = null): void
    {
        $data = ['status' => $status, 'verified_at' => date('Y-m-d H:i:s'), 'verified_by' => $adminId];
        if ($userId) $data['user_id'] = $userId;
        static::update('athlete_registrations', $data, ['id' => $id]);
    }

    // ── Athlete Profile ──────────────────────────────────────────────────────

    public static function create(array $data): int
    {
        return static::insert('athletes', $data);
    }

    public static function findByUserId(int $userId): ?array
    {
        return static::row(
            'SELECT a.*, c.name AS country_name, s.name AS state_name, d.name AS district_name,
                    ip.name AS id_proof_type_name
             FROM athletes a
             LEFT JOIN countries c ON c.id = a.country_id
             LEFT JOIN states s    ON s.id = a.state_id
             LEFT JOIN districts d ON d.id = a.district_id
             LEFT JOIN id_proof_types ip ON ip.id = a.id_proof_type_id
             WHERE a.user_id = ?',
            [$userId]
        );
    }

    public static function findById(int $id): ?array
    {
        return static::row(
            'SELECT a.*, c.name AS country_name, s.name AS state_name, d.name AS district_name,
                    ip.name AS id_proof_type_name, u.email
             FROM athletes a
             LEFT JOIN countries c ON c.id = a.country_id
             LEFT JOIN states s    ON s.id = a.state_id
             LEFT JOIN districts d ON d.id = a.district_id
             LEFT JOIN id_proof_types ip ON ip.id = a.id_proof_type_id
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.id = ?',
            [$id]
        );
    }

    public static function updateProfile(int $id, array $data): void
    {
        static::update('athletes', $data, ['id' => $id]);
    }

    public static function getSports(int $athleteId): array
    {
        return static::rows(
            'SELECT als.*, s.name AS sport_name FROM athlete_sports als
             JOIN sports s ON s.id = als.sport_id
             WHERE als.athlete_id = ?',
            [$athleteId]
        );
    }

    public static function syncSports(int $athleteId, array $sports): void
    {
        static::query('DELETE FROM athlete_sports WHERE athlete_id = ?', [$athleteId]);
        foreach ($sports as $sportId => $extra) {
            static::insert('athlete_sports', [
                'athlete_id'       => $athleteId,
                'sport_id'         => (int)$sportId,
                'sport_specific_id'=> $extra['sport_specific_id'] ?? null,
                'licenses'         => $extra['licenses'] ?? null,
            ]);
        }
    }

    public static function getAll(): array
    {
        return static::rows(
            'SELECT a.*, u.email, u.status AS user_status FROM athletes a
             JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC'
        );
    }

    public static function getAllIdProofTypes(): array
    {
        return static::rows('SELECT * FROM id_proof_types');
    }

    public static function getAllSports(): array
    {
        return static::rows("SELECT * FROM sports WHERE status = 'active' ORDER BY name");
    }

    public static function getEventSports(): array
    {
        return static::rows(
            "SELECT * FROM sports WHERE name IN ('Athletics', 'Baseball', 'Shooting') AND status = 'active' ORDER BY name"
        );
    }

    public static function getCountries(): array
    {
        return static::rows('SELECT * FROM countries ORDER BY name');
    }

    public static function getStatesByCountry(int $countryId): array
    {
        return static::rows('SELECT * FROM states WHERE country_id = ? ORDER BY name', [$countryId]);
    }

    public static function getDistrictsByState(int $stateId): array
    {
        return static::rows('SELECT * FROM districts WHERE state_id = ? ORDER BY name', [$stateId]);
    }
}
