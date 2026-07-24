<?php
namespace Models;

use Core\Model;

/**
 * Header + items access for the new athlete registration flow.
 * The legacy single-row Event::registerAthlete() path is kept untouched so
 * existing data still works; new code goes through these helpers.
 */
class EventRegistration extends Model
{
    public static function findHeader(int $eventId, int $athleteId): ?array
    {
        return static::row(
            "SELECT * FROM event_registrations WHERE event_id = ? AND athlete_id = ?",
            [$eventId, $athleteId]
        );
    }

    public static function findById(int $id): ?array
    {
        return static::row("SELECT * FROM event_registrations WHERE id = ?", [$id]);
    }

    public static function createDraft(int $eventId, int $athleteId): int
    {
        return static::insert('event_registrations', [
            'event_id'       => $eventId,
            'athlete_id'     => $athleteId,
            'status'         => 'pending',
            'payment_status' => 'pending',
        ]);
    }

    public static function updateHeader(int $id, array $data): void
    {
        if (!$data) return;
        static::query(
            'UPDATE event_registrations SET ' . implode(',', array_map(fn($k) => "{$k}=?", array_keys($data)))
            . ' WHERE id = ?',
            [...array_values($data), $id]
        );
    }

    public static function items(int $registrationId): array
    {
        return static::rows(
            "SELECT eri.*, es.event_code, es.category, es.entry_fee AS event_fee,
                    sp.name AS sport_name, se.name AS sport_event_name
               FROM event_registration_items eri
               JOIN event_sports es ON es.id = eri.event_sport_id
          LEFT JOIN sports sp        ON sp.id = es.sport_id
          LEFT JOIN sport_events se  ON se.id = es.sport_event_id
              WHERE eri.registration_id = ?
              ORDER BY eri.id",
            [$registrationId]
        );
    }

    /**
     * Count this registration's items split by participation kind, driven
     * by the underlying event_sports.team_entry_mode:
     *   - 'team_only'  → a team event
     *   - everything else (both / individual_only) → an individual event
     * Also returns the set of event_sport ids already on the registration
     * so callers can tell an idempotent re-add from a genuinely new pick.
     */
    public static function itemModeCounts(int $registrationId): array
    {
        $rows = static::rows(
            "SELECT eri.event_sport_id, COALESCE(es.team_entry_mode,'both') AS mode
               FROM event_registration_items eri
               JOIN event_sports es ON es.id = eri.event_sport_id
              WHERE eri.registration_id = ?",
            [$registrationId]
        );
        // One bucket per team_entry_mode — each per-athlete cap targets a
        // single mode:
        //   mode_both       → Max Individual Events cap
        //   mode_team_only  → Max Team Events cap
        //   mode_individual → Max Individual-only Events cap
        $modeBoth = 0; $modeTeamOnly = 0; $modeIndividual = 0; $ids = [];
        foreach ($rows as $r) {
            $ids[] = (int)$r['event_sport_id'];
            $mode = (string)($r['mode'] ?? 'both');
            if ($mode === 'team_only')            $modeTeamOnly++;
            elseif ($mode === 'individual_only')  $modeIndividual++;
            else                                  $modeBoth++; // 'both' (default)
        }
        return [
            'mode_both'       => $modeBoth,
            'mode_team_only'  => $modeTeamOnly,
            'mode_individual' => $modeIndividual,
            'event_sport_ids' => $ids,
        ];
    }

    /**
     * Distinct age categories of the sport-events already on a registration.
     * Used to enforce the single-age-category rule (all events on one
     * registration must share the same age category). Rows without an age
     * category (legacy / unset) come back with age_category_id NULL.
     */
    public static function itemAgeCategories(int $registrationId): array
    {
        return static::rows(
            "SELECT DISTINCT sev.age_category_id AS age_category_id,
                    ac.name AS age_category_name
               FROM event_registration_items eri
               JOIN event_sports es        ON es.id  = eri.event_sport_id
          LEFT JOIN sport_events  sev       ON sev.id = es.sport_event_id
          LEFT JOIN age_categories ac       ON ac.id  = sev.age_category_id
              WHERE eri.registration_id = ?",
            [$registrationId]
        );
    }

    /**
     * Distinct non-rejected athletes already entered for one sport-event
     * under a given unit on an event. Used to enforce the per-sport-event
     * max_members_per_unit cap. $excludeRegId drops the current draft so a
     * re-save of the same registration doesn't count against itself.
     */
    public static function unitCountForSportEvent(int $eventId, int $unitId, int $eventSportId, int $excludeRegId = 0): int
    {
        $r = static::row(
            "SELECT COUNT(DISTINCT er.id) AS c
               FROM event_registrations er
               JOIN event_registration_items eri ON eri.registration_id = er.id
              WHERE er.event_id = ? AND er.unit_id = ? AND eri.event_sport_id = ?
                AND er.id <> ?
                AND COALESCE(er.admin_review_status,'') <> 'rejected'",
            [$eventId, $unitId, $eventSportId, $excludeRegId]
        );
        return (int)($r['c'] ?? 0);
    }

    /**
     * Rich Competitor-Card context for a registration: items grouped by
     * event category, the athlete's distinct age categories, approved
     * team-entry codes per category, and any allotted relay lanes (with
     * shooting-range name + address). Used by both the printable card
     * view and the email body so the two surfaces stay identical.
     *
     * Returns:
     *   [
     *     'items'              => [...EventRegistration::items()...],
     *     'category_rows'      => [catName => [events[], team_events[],
     *                              relays[], fee]],
     *     'age_category_label' => 'Senior' / 'Senior / Master' / '',
     *   ]
     */
    public static function competitorCardContext(int $registrationId): array
    {
        $reg = self::findById($registrationId);
        if (!$reg) {
            return ['items' => [], 'category_rows' => [], 'age_category_label' => ''];
        }
        $items = self::items($registrationId);

        // Item → event-category & age-category map.
        $catByItem = static::rows(
            "SELECT eri.id AS eri_id,
                    sc.name AS category_name,
                    ac.name AS age_category_name
               FROM event_registration_items eri
               JOIN event_sports     es ON es.id = eri.event_sport_id
          LEFT JOIN sport_events     se ON se.id = es.sport_event_id
          LEFT JOIN sport_categories sc ON sc.id = se.category_id
          LEFT JOIN age_categories   ac ON ac.id = se.age_category_id
              WHERE eri.registration_id = ?",
            [$registrationId]
        );
        $itemCat = [];
        $ageCategorySet = [];
        foreach ($catByItem as $c) {
            $itemCat[(int)$c['eri_id']] = (string)($c['category_name'] ?? '');
            $ac = trim((string)($c['age_category_name'] ?? ''));
            if ($ac !== '') $ageCategorySet[$ac] = true;
        }
        $ageCategoryLabel = $ageCategorySet ? implode(' / ', array_keys($ageCategorySet)) : '';

        // Approved team-entry codes for this athlete on this event,
        // bucketed by event category.
        try { Schema::ensureTeamEntry(); } catch (\Throwable $e) {}
        $teamRows = [];
        try {
            $teamRows = static::rows(
                "SELECT es.event_code, es.team_entry_fee,
                        se.name AS sport_event_name, sc.name AS category_name
                   FROM team_registration_members trm
                   JOIN team_registrations tr ON tr.id = trm.team_registration_id
              LEFT JOIN event_sports     es ON es.id = tr.event_sport_id
              LEFT JOIN sport_events     se ON se.id = es.sport_event_id
              LEFT JOIN sport_categories sc ON sc.id = se.category_id
                  WHERE trm.athlete_id = ?
                    AND tr.event_id = ?
                    AND tr.admin_review_status = 'approved'",
                [(int)$reg['athlete_id'], (int)$reg['event_id']]
            );
        } catch (\Throwable $e) {
            $teamRows = [];
        }

        // Relay lanes allotted to this registration, with range venue +
        // address so the card can show where to report.
        try { Schema::ensureLaneAllocation(); } catch (\Throwable $e) {}
        $relayByCat = [];
        try {
            $laneRows = static::rows(
                "SELECT erl.category,
                        r.relay_number, r.relay_date, r.match_time, r.order_no,
                        l.lane_number,
                        sr.name     AS range_name,
                        sr.location AS range_address,
                        d.name      AS range_distance_name,
                        d.distance_meters
                   FROM event_relay_lanes erl
                   JOIN event_relays                       r ON r.id = erl.relay_id
                   JOIN event_shooting_range_lanes         l ON l.id = erl.lane_id
                   JOIN event_shooting_range_distances     d ON d.id = r.shooting_range_distance_id
                   JOIN event_shooting_ranges             sr ON sr.id = d.shooting_range_id
                  WHERE r.event_id = ?
                    AND erl.assigned_registration_id = ?
                  ORDER BY COALESCE(r.order_no, 999999), r.id, l.lane_number",
                [(int)$reg['event_id'], $registrationId]
            );
            foreach ($laneRows as $ln) {
                $cat = (string)($ln['category'] ?? '');
                $relayByCat[$cat][] = [
                    'relay_number'   => $ln['relay_number'],
                    'relay_date'     => $ln['relay_date'],
                    'match_time'     => $ln['match_time'],
                    'lane_number'    => $ln['lane_number'],
                    'range_name'     => $ln['range_name'],
                    'range_address'  => $ln['range_address'],
                    'range_distance' => $ln['range_distance_name'],
                ];
            }
        } catch (\Throwable $e) {
            $relayByCat = [];
        }

        // Build category rows: union of (categories the athlete is
        // registered for) and (categories of approved team entries).
        $catRows = [];
        $ensure = function (&$bag, $cat) {
            if (!isset($bag[$cat])) {
                $bag[$cat] = ['events'=>[], 'team_events'=>[], 'relays'=>[], 'fee'=>0.0];
            }
        };
        foreach ($items as $it) {
            $cat = $itemCat[(int)$it['id']] ?? ($it['category'] ?? '— Uncategorised —');
            if ($cat === '') $cat = '— Uncategorised —';
            $ensure($catRows, $cat);
            $code = trim((string)($it['event_code'] ?? ''));
            if ($code !== '') $catRows[$cat]['events'][] = $code;
            $catRows[$cat]['fee'] += (float)($it['fee'] ?? 0);
        }
        foreach ($teamRows as $t) {
            $cat = (string)($t['category_name'] ?? '');
            if ($cat === '') $cat = '— Uncategorised —';
            $ensure($catRows, $cat);
            $code = trim((string)($t['event_code'] ?? ''));
            if ($code !== '') $catRows[$cat]['team_events'][] = $code;
        }
        foreach ($catRows as $cat => &$row) {
            $row['events']      = array_values(array_unique($row['events']));
            $row['team_events'] = array_values(array_unique($row['team_events']));
            $row['relays']      = $relayByCat[$cat] ?? [];
        }
        unset($row);
        ksort($catRows);

        // Sport-event-wise rows: one line per sport event (individual items +
        // approved team entries), each carrying its event category, a team-entry
        // flag and the fee. Used when the card is set to "sport event wise".
        $eventRows = [];
        $mkLabel = function (string $code, string $name): string {
            $code = trim($code); $name = trim($name);
            if ($code !== '' && $name !== '') return $code . ' - ' . $name;
            return $name !== '' ? $name : $code;
        };
        foreach ($items as $it) {
            $cat  = $itemCat[(int)$it['id']] ?? (string)($it['category'] ?? '');
            $lbl  = $mkLabel((string)($it['event_code'] ?? ''), (string)($it['sport_event_name'] ?? ''));
            if ($lbl === '') continue;
            $eventRows[] = [
                'category'    => $cat !== '' ? $cat : '— Uncategorised —',
                'sport_event' => $lbl,
                'is_team'     => false,
                'fee'         => (float)($it['fee'] ?? 0),
            ];
        }
        foreach ($teamRows as $t) {
            $lbl = $mkLabel((string)($t['event_code'] ?? ''), (string)($t['sport_event_name'] ?? ''));
            if ($lbl === '') continue;
            $cat = (string)($t['category_name'] ?? '');
            $eventRows[] = [
                'category'    => $cat !== '' ? $cat : '— Uncategorised —',
                'sport_event' => $lbl,
                'is_team'     => true,
                'fee'         => (float)($t['team_entry_fee'] ?? 0),
            ];
        }
        usort($eventRows, function ($a, $b) {
            $c = strcasecmp((string)$a['category'], (string)$b['category']);
            return $c !== 0 ? $c : strcasecmp((string)$a['sport_event'], (string)$b['sport_event']);
        });

        return [
            'items'              => $items,
            'category_rows'      => $catRows,
            'event_rows'         => $eventRows,
            'age_category_label' => $ageCategoryLabel,
        ];
    }

    /**
     * The athlete may edit Step 1 (unit / NOC / sport events) only while the
     * registration is still a draft (no admin_review_status) or has been
     * explicitly returned by the event admin for changes.
     */
    /**
     * Allocate the next competitor number for an event (starting from 1001),
     * persist it on the registration, and return the assigned number. Idempotent
     * — if the registration already has one, it's returned unchanged.
     */
    public static function allocateCompetitorNumber(int $registrationId): int
    {
        $reg = self::findById($registrationId);
        if (!$reg) return 0;
        if (!empty($reg['competitor_number'])) return (int)$reg['competitor_number'];

        $eventId = (int)$reg['event_id'];
        $r = static::row(
            "SELECT MAX(competitor_number) AS mx FROM event_registrations WHERE event_id = ?",
            [$eventId]
        );
        // Base the sequence on the event's configured start number (default
        // 1001), then continue from the current max.
        $start = 1001;
        try {
            $ev = static::row("SELECT competitor_number_start FROM events WHERE id = ?", [$eventId]);
            if ($ev && (int)($ev['competitor_number_start'] ?? 0) > 0) $start = (int)$ev['competitor_number_start'];
        } catch (\Throwable $e) { /* column may not exist yet */ }
        $next = max($start, (int)($r['mx'] ?? 0) + 1);
        // Race-safe-ish: try, on duplicate (concurrent allocations) bump and retry once.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                static::query(
                    "UPDATE event_registrations SET competitor_number = ? WHERE id = ? AND competitor_number IS NULL",
                    [$next, $registrationId]
                );
                // Keep any cached team-membership rows in sync with the
                // newly minted number so legacy consumers that read the
                // cached column directly stay correct.
                try {
                    static::query(
                        "UPDATE team_registration_members
                            SET competitor_number = ?
                          WHERE registration_id = ?",
                        [$next, $registrationId]
                    );
                } catch (\Throwable $e) { /* table may not exist on older installs */ }
                return $next;
            } catch (\Throwable $e) {
                $next++;
            }
        }
        return 0;
    }

    /**
     * Renumber every registration on an event that already has a competitor
     * number, into a contiguous sequence beginning at $start — preserving the
     * existing order (by current number, then id). Because there is a UNIQUE
     * (event_id, competitor_number) index, rows are first parked at unique
     * high temporary values so the renumber never collides mid-flight.
     * Returns the count renumbered.
     */
    public static function resetCompetitorNumbers(int $eventId, int $start): int
    {
        if ($start < 1) $start = 1;
        $rows = static::rows(
            "SELECT id FROM event_registrations
              WHERE event_id = ? AND competitor_number IS NOT NULL
              ORDER BY competitor_number, id",
            [$eventId]
        );
        if (!$rows) return 0;

        // Pass 1 — park at unique temp values (2e9+) far above any real number.
        $temp = 2000000000;
        foreach ($rows as $r) {
            static::query(
                "UPDATE event_registrations SET competitor_number = ? WHERE id = ?",
                [$temp, (int)$r['id']]
            );
            $temp++;
        }
        // Pass 2 — assign the final contiguous sequence from $start and keep the
        // cached team-membership numbers in sync.
        $n = $start;
        foreach ($rows as $r) {
            $rid = (int)$r['id'];
            static::query(
                "UPDATE event_registrations SET competitor_number = ? WHERE id = ?",
                [$n, $rid]
            );
            try {
                static::query(
                    "UPDATE team_registration_members SET competitor_number = ? WHERE registration_id = ?",
                    [$n, $rid]
                );
            } catch (\Throwable $e) { /* table may not exist on older installs */ }
            $n++;
        }
        return count($rows);
    }

    public static function isEditable(?array $reg): bool
    {
        if (!$reg) return true;
        $s = $reg['admin_review_status'] ?? null;
        return $s === null || $s === '' || $s === 'returned';
    }

    public static function withProfile(int $id): ?array
    {
        return static::row(
            "SELECT er.*, e.name AS event_name, e.event_date_from, e.event_date_to,
                    e.location, e.institution_id,
                    i.name AS institution_name,
                    a.name AS athlete_name, a.mobile AS athlete_mobile, a.gender, a.date_of_birth,
                    a.passport_photo, a.id_proof_number, a.dob_proof_number,
                    u.email AS athlete_email,
                    eu.name AS unit_name, eu.address AS unit_address
               FROM event_registrations er
               JOIN events e        ON e.id = er.event_id
               JOIN institutions i  ON i.id = e.institution_id
               JOIN athletes a      ON a.id = er.athlete_id
          LEFT JOIN users u         ON u.id = a.user_id
          LEFT JOIN event_units eu  ON eu.id = er.unit_id
              WHERE er.id = ?",
            [$id]
        );
    }

    public static function syncItems(int $registrationId, array $eventSportIds): float
    {
        static::query("DELETE FROM event_registration_items WHERE registration_id = ?", [$registrationId]);
        if (!$eventSportIds) return 0.0;

        // Pull fees in one go to compute the running total.
        $placeholders = implode(',', array_fill(0, count($eventSportIds), '?'));
        $rows = static::rows(
            "SELECT id, entry_fee FROM event_sports WHERE id IN ({$placeholders})",
            array_map('intval', $eventSportIds)
        );
        $feeById = array_column($rows, 'entry_fee', 'id');

        $total = 0.0;
        foreach ($eventSportIds as $esId) {
            $fee = (float)($feeById[(int)$esId] ?? 0);
            static::insert('event_registration_items', [
                'registration_id' => $registrationId,
                'event_sport_id'  => (int)$esId,
                'fee'             => $fee,
            ]);
            $total += $fee;
        }
        return $total;
    }

    /**
     * Append a single sport-event to the registration. Idempotent — the
     * UNIQUE (registration_id, event_sport_id) index turns duplicate
     * adds into no-ops. Returns the new running total.
     */
    public static function addItem(int $registrationId, int $eventSportId): float
    {
        if ($eventSportId <= 0) return self::sumFee($registrationId);
        $row = static::row(
            "SELECT id, entry_fee FROM event_sports WHERE id = ? LIMIT 1",
            [$eventSportId]
        );
        if (!$row) return self::sumFee($registrationId);
        try {
            static::query(
                "INSERT IGNORE INTO event_registration_items
                 (registration_id, event_sport_id, fee)
                 VALUES (?, ?, ?)",
                [$registrationId, $eventSportId, (float)$row['entry_fee']]
            );
        } catch (\Throwable $e) {
            error_log('[EventRegistration::addItem] ' . $e->getMessage());
        }
        return self::sumFee($registrationId);
    }

    /** Drop a single sport-event. Returns the new running total. */
    public static function removeItem(int $registrationId, int $eventSportId): float
    {
        if ($eventSportId > 0) {
            static::query(
                "DELETE FROM event_registration_items
                  WHERE registration_id = ? AND event_sport_id = ?",
                [$registrationId, $eventSportId]
            );
        }
        return self::sumFee($registrationId);
    }

    /** Total demand on a registration = SUM(event_registration_items.fee). */
    public static function sumFee(int $registrationId): float
    {
        $r = static::row(
            "SELECT COALESCE(SUM(fee), 0) AS s
               FROM event_registration_items WHERE registration_id = ?",
            [$registrationId]
        );
        return (float)($r['s'] ?? 0);
    }

    /**
     * Hard-delete an empty draft registration and any dependent rows
     * (items / payments). Callers MUST verify the registration is a draft
     * with no events before invoking this — the method itself only removes
     * the child rows and the header.
     */
    public static function deleteById(int $id): void
    {
        if ($id <= 0) return;
        try {
            static::query("DELETE FROM event_registration_payments WHERE registration_id = ?", [$id]);
        } catch (\Throwable $e) { /* table may not exist */ }
        try {
            static::query("DELETE FROM event_registration_items WHERE registration_id = ?", [$id]);
        } catch (\Throwable $e) { /* table may not exist */ }
        static::query("DELETE FROM event_registrations WHERE id = ?", [$id]);
    }

    /** How many registrations (any event) reference this athlete. */
    public static function countForAthlete(int $athleteId): int
    {
        $r = static::row(
            "SELECT COUNT(*) AS c FROM event_registrations WHERE athlete_id = ?",
            [$athleteId]
        );
        return (int)($r['c'] ?? 0);
    }
}
