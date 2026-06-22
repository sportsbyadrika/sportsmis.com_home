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

    /**
     * Insert a Unit-user-managed athlete. The new athletes row stores a
     * NULL user_id when the unit user didn't supply an email — managed
     * athletes can exist without a login. created_by_unit_id +
     * created_by_role provide the audit trail.
     */
    public static function createManaged(array $data, ?int $userId, int $createdByUnitId): int
    {
        $row = array_merge($data, [
            'user_id'            => $userId,
            'created_by_unit_id' => $createdByUnitId,
            'created_by_role'    => 'unit',
        ]);
        return static::insert('athletes', $row);
    }

    /**
     * Strong dedupe lookup for Unit-driven creation. Aadhaar is the
     * primary key when supplied; otherwise we fall back to mobile and
     * then email. Returns the first hit or null.
     */
    public static function findExistingForUnitDedupe(?string $aadhaar, ?string $mobile, ?string $email): ?array
    {
        $aadhaar = trim((string)$aadhaar);
        $mobile  = trim((string)$mobile);
        $email   = trim((string)$email);
        if ($aadhaar !== '') {
            $r = static::row(
                "SELECT id, name, mobile, date_of_birth, id_proof_number
                   FROM athletes
                  WHERE id_proof_number = ? LIMIT 1", [$aadhaar]);
            if ($r) return $r;
        }
        if ($mobile !== '') {
            $r = static::row(
                "SELECT id, name, mobile, date_of_birth, id_proof_number
                   FROM athletes
                  WHERE mobile = ? LIMIT 1", [$mobile]);
            if ($r) return $r;
        }
        if ($email !== '') {
            $r = static::row(
                "SELECT a.id, a.name, a.mobile, a.date_of_birth, a.id_proof_number
                   FROM athletes a JOIN users u ON u.id = a.user_id
                  WHERE u.email = ? LIMIT 1", [$email]);
            if ($r) return $r;
        }
        return null;
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
     * Super-Admin athletes listing with optional filters and pagination.
     * The query joins users (email/status) and the original signup queue
     * (athlete_registrations.created_at → "submitted" date) plus state /
     * district names for display.
     *
     * @param array{q?:string,email?:string,mobile?:string,address?:string,whatsapp?:string,profile?:string,status?:string} $filters
     * @return array{rows:array,total:int}
     */
    public static function adminSearch(array $filters, int $page = 1, int $perPage = 25): array
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

        $total = (int)(static::row(
            "SELECT COUNT(*) AS c
               FROM athletes a
          LEFT JOIN users u                 ON u.id  = a.user_id
          LEFT JOIN states s                ON s.id  = a.state_id
          LEFT JOIN districts d             ON d.id  = a.district_id
          LEFT JOIN athlete_registrations ar ON ar.id = a.registration_id
          LEFT JOIN event_units eu          ON eu.id = a.created_by_unit_id"
            . $whereSql, $params)['c'] ?? 0);

        $perPage = max(5, min(200, $perPage));
        $page    = max(1, $page);
        $offset  = ($page - 1) * $perPage;

        $rows = static::rows(
            "SELECT a.*, u.email, u.status AS user_status,
                    s.name AS state_name, d.name AS district_name,
                    eu.name AS created_by_unit_name,
                    ar.created_at AS submitted_at,
                    (SELECT COUNT(*) FROM event_registrations er
                       WHERE er.athlete_id = a.id) AS reg_count,
                    (SELECT GROUP_CONCAT(
                                CONCAT(e.name, ' — ',
                                       UPPER(LEFT(COALESCE(er.admin_review_status,'draft'),1)),
                                       SUBSTRING(COALESCE(er.admin_review_status,'draft'),2))
                                ORDER BY e.name SEPARATOR '\n')
                       FROM event_registrations er
                       JOIN events e ON e.id = er.event_id
                      WHERE er.athlete_id = a.id) AS reg_summary
               FROM athletes a
          LEFT JOIN users u                 ON u.id  = a.user_id
          LEFT JOIN states s                ON s.id  = a.state_id
          LEFT JOIN districts d             ON d.id  = a.district_id
          LEFT JOIN athlete_registrations ar ON ar.id = a.registration_id
          LEFT JOIN event_units eu          ON eu.id = a.created_by_unit_id"
            . $whereSql
            . ' ORDER BY a.created_at DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset,
            $params
        );

        return ['rows' => $rows, 'total' => $total];
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
    /**
     * Decide which age categories the athlete is eligible to register for,
     * driven by the `age_categories` master table (Super-Admin → Settings →
     * Sports Settings).
     *
     * Per-category resolution rule (first non-empty wins):
     *
     *   1. If min_age_year and/or max_age_year are set, the athlete is
     *      eligible iff their date_of_birth YEAR is between the two bounds
     *      (NULL on either side ⇒ that side is unbounded).
     *   2. Else if min_age and/or max_age are set, the athlete is eligible
     *      iff their current age (years since DOB) is between the bounds.
     *   3. Otherwise the category is unconfigured and skipped.
     *
     * Inactive categories are excluded. Returns the array of eligible
     * category names (lower-cased lookups are done case-insensitively in
     * the consumer JS).
     */
    public static function eligibleAgeCategories(?string $dob): array
    {
        if (empty($dob)) return [];
        try {
            $birth = new \DateTimeImmutable($dob);
        } catch (\Throwable $e) {
            return [];
        }
        $birthYear = (int)$birth->format('Y');
        $today     = new \DateTimeImmutable('today');
        $age       = (int)$today->diff($birth)->y;

        $cats = static::rows(
            "SELECT id, name, min_age, max_age, min_age_year, max_age_year
               FROM age_categories
              WHERE status = 'active'
              ORDER BY sort_order, name"
        );

        // Build a id→name index so we can resolve upgrade targets cheaply.
        $byId      = [];
        $baseIds   = [];
        foreach ($cats as $c) {
            $byId[(int)$c['id']] = $c['name'];
            $miny = $c['min_age_year'];
            $maxy = $c['max_age_year'];
            $min  = $c['min_age'];
            $max  = $c['max_age'];

            // Tier 1: birth-year bounds (preferred when admin configured them).
            if ($miny !== null || $maxy !== null) {
                $lo = $miny !== null ? (int)$miny : -PHP_INT_MAX;
                $hi = $maxy !== null ? (int)$maxy : PHP_INT_MAX;
                if ($birthYear >= $lo && $birthYear <= $hi) {
                    $baseIds[] = (int)$c['id'];
                }
                continue;
            }

            // Tier 2: years-of-age bounds.
            if ($min !== null || $max !== null) {
                $lo = $min !== null ? (int)$min : -PHP_INT_MAX;
                $hi = $max !== null ? (int)$max : PHP_INT_MAX;
                if ($age >= $lo && $age <= $hi) {
                    $baseIds[] = (int)$c['id'];
                }
                continue;
            }
            // Tier 3: unconfigured → skip.
        }

        if (!$baseIds) return [];

        // Pull the "also eligible" graph once and walk every base category's
        // upgrade targets. The result is the union of base + upgrades; any
        // duplicates collapse into a single appearance.
        $upgradeMap = AgeCategory::upgradeMap();
        $finalIds   = [];
        foreach ($baseIds as $bid) {
            $finalIds[$bid] = true;
            foreach ($upgradeMap[$bid] ?? [] as $tid) {
                if (isset($byId[$tid])) $finalIds[$tid] = true;
            }
        }

        return array_values(array_filter(array_map(fn($id) => $byId[$id] ?? null, array_keys($finalIds))));
    }

    /**
     * Only the BASE age categories — the one(s) the athlete falls into via
     * year/age bounds, BEFORE walking the "also eligible" upgrade graph.
     * Used by the registration UI to label the athlete with a single primary
     * bracket while still allowing event picks across upgrades.
     */
    public static function baseAgeCategories(?string $dob): array
    {
        if (empty($dob)) return [];
        try {
            $birth = new \DateTimeImmutable($dob);
        } catch (\Throwable $e) {
            return [];
        }
        $birthYear = (int)$birth->format('Y');
        $today     = new \DateTimeImmutable('today');
        $age       = (int)$today->diff($birth)->y;

        $cats = static::rows(
            "SELECT name, min_age, max_age, min_age_year, max_age_year
               FROM age_categories
              WHERE status = 'active'
              ORDER BY sort_order, name"
        );
        $base = [];
        foreach ($cats as $c) {
            $miny = $c['min_age_year']; $maxy = $c['max_age_year'];
            $min  = $c['min_age'];      $max  = $c['max_age'];
            if ($miny !== null || $maxy !== null) {
                $lo = $miny !== null ? (int)$miny : -PHP_INT_MAX;
                $hi = $maxy !== null ? (int)$maxy : PHP_INT_MAX;
                if ($birthYear >= $lo && $birthYear <= $hi) $base[] = $c['name'];
                continue;
            }
            if ($min !== null || $max !== null) {
                $lo = $min !== null ? (int)$min : -PHP_INT_MAX;
                $hi = $max !== null ? (int)$max : PHP_INT_MAX;
                if ($age >= $lo && $age <= $hi) $base[] = $c['name'];
            }
        }
        return $base;
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
