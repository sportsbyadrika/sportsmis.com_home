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

    /**
     * Super-Admin athletes listing with optional filters. The query joins
     * users (email/status) and the original signup queue
     * (athlete_registrations.created_at → "submitted" date) plus state /
     * district names for display.
     *
     * @param array{q?:string,email?:string,mobile?:string,address?:string,whatsapp?:string,profile?:string,status?:string} $filters
     */
    public static function adminSearch(array $filters): array
    {
        $where = []; $params = [];
        if (!empty($filters['q'])) {
            $where[] = '(a.name LIKE ?)'; $params[] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['email'])) {
            $where[] = 'u.email LIKE ?'; $params[] = '%' . $filters['email'] . '%';
        }
        if (!empty($filters['mobile'])) {
            $where[] = 'a.mobile LIKE ?'; $params[] = '%' . $filters['mobile'] . '%';
        }
        if (!empty($filters['address'])) {
            $where[] = '(a.address LIKE ? OR a.communication_address LIKE ?)';
            $like = '%' . $filters['address'] . '%';
            $params[] = $like; $params[] = $like;
        }
        if (!empty($filters['whatsapp'])) {
            $where[] = 'a.whatsapp_number LIKE ?'; $params[] = '%' . $filters['whatsapp'] . '%';
        }
        if (in_array($filters['profile'] ?? '', ['complete','incomplete'], true)) {
            $where[] = 'a.profile_completed = ?';
            $params[] = $filters['profile'] === 'complete' ? 1 : 0;
        }
        if (in_array($filters['status'] ?? '', ['active','pending','blocked','suspended'], true)) {
            $where[] = 'u.status = ?';
            $params[] = $filters['status'];
        }
        $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

        return static::rows(
            "SELECT a.*, u.email, u.status AS user_status,
                    s.name AS state_name, d.name AS district_name,
                    ar.created_at AS submitted_at
               FROM athletes a
               JOIN users u                 ON u.id  = a.user_id
          LEFT JOIN states s                ON s.id  = a.state_id
          LEFT JOIN districts d             ON d.id  = a.district_id
          LEFT JOIN athlete_registrations ar ON ar.id = a.registration_id"
            . $whereSql
            . ' ORDER BY a.created_at DESC LIMIT 1000',
            $params
        );
    }

    /**
     * State × Gender pivot for the Super-Admin landing panel.
     * Returns one row per state with male / female / other / total counts.
     */
    public static function stateGenderPivot(): array
    {
        return static::rows(
            "SELECT COALESCE(s.name, '— Unspecified —') AS state_name,
                    COUNT(*)                                                          AS total,
                    COUNT(CASE WHEN LOWER(a.gender) IN ('male','men','m')   THEN 1 END) AS male,
                    COUNT(CASE WHEN LOWER(a.gender) IN ('female','women','f') THEN 1 END) AS female,
                    COUNT(CASE WHEN LOWER(a.gender) NOT IN ('male','men','m','female','women','f') OR a.gender IS NULL THEN 1 END) AS other
               FROM athletes a
          LEFT JOIN states s ON s.id = a.state_id
              GROUP BY s.id
              ORDER BY state_name"
        );
    }

    public static function getAllIdProofTypes(): array
    {
        return static::rows('SELECT * FROM id_proof_types');
    }

    /** Only Aadhaar — used for the first (mandatory) ID proof slot. */
    public static function getAadhaarProofType(): ?array
    {
        return static::row("SELECT * FROM id_proof_types WHERE name = 'Aadhaar Card' LIMIT 1");
    }

    /** DOB-proof options — used when Aadhaar doesn't carry DOB. */
    public static function getDobProofTypes(): array
    {
        return static::rows(
            "SELECT * FROM id_proof_types
              WHERE name IN ('Driving Licence', 'Birth Certificate', 'School Certificate', 'Passport')
              ORDER BY FIELD(name, 'Birth Certificate', 'School Certificate', 'Passport', 'Driving Licence')"
        );
    }

    public static function getAllSports(): array
    {
        return static::rows("SELECT * FROM sports WHERE status = 'active' ORDER BY name");
    }

    /**
     * Sports that are surfaced to institutions (event editor) and athletes
     * (sports preferences). The visibility is admin-configurable via
     * /admin/settings/sports — the `enabled_for_events` flag on `sports`.
     * Falls back to the original hardcoded trio if the column doesn't exist
     * yet (i.e. before Schema::ensureSportHierarchy() has run).
     */
    public static function getEventSports(): array
    {
        try {
            return static::rows(
                "SELECT * FROM sports
                  WHERE enabled_for_events = 1 AND status = 'active'
                  ORDER BY name"
            );
        } catch (\Throwable $e) {
            return static::rows(
                "SELECT * FROM sports
                  WHERE name IN ('Athletics', 'Softball', 'Shooting') AND status = 'active'
                  ORDER BY name"
            );
        }
    }

    public static function setSportEnabled(int $sportId, bool $enabled): void
    {
        static::query(
            "UPDATE sports SET enabled_for_events = ? WHERE id = ?",
            [$enabled ? 1 : 0, $sportId]
        );
    }

    /**
     * Profiles lock the moment an event admin approves any of the athlete's
     * registrations — that registration's competitor card is now committed
     * to the saved snapshot, so the underlying athlete record can't change.
     */
    public static function isProfileLocked(int $athleteId): bool
    {
        $r = static::row(
            "SELECT 1 FROM event_registrations
              WHERE athlete_id = ? AND admin_review_status = 'approved'
              LIMIT 1",
            [$athleteId]
        );
        return (bool)$r;
    }

    /**
     * Map an athlete's age into the eligible age-category names. Rules:
     *   Sub Youth     → Sub Youth, Youth, Junior, Senior
     *   Youth         → Youth, Junior, Senior
     *   Junior        → Junior, Senior
     *   Senior        → Senior only
     *   Master        → Master, Senior
     *   Senior Master → Senior Master, Master, Senior
     */
    public static function eligibleAgeCategories(?int $age): array
    {
        if ($age === null) return [];
        // Resolve the athlete's "own" bracket from the seeded list.
        $bracket = match (true) {
            $age <  14 => 'Sub Youth',
            $age <  17 => 'Youth',
            $age <  20 => 'Junior',
            $age <  35 => 'Senior',
            $age <  50 => 'Master',
            default    => 'Senior Master',
        };
        return match ($bracket) {
            'Sub Youth'     => ['Sub Youth', 'Youth', 'Junior', 'Senior'],
            'Youth'         => ['Youth', 'Junior', 'Senior'],
            'Junior'        => ['Junior', 'Senior'],
            'Senior'        => ['Senior'],
            'Master'        => ['Master', 'Senior'],
            'Senior Master' => ['Senior Master', 'Master', 'Senior'],
            default         => [],
        };
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
