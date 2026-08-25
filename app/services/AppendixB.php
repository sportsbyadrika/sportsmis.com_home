<?php
namespace Services;

use Models\Event;
use Models\EventUnit;
use Models\TeamRegistration;

/**
 * Gathers a unit's data for the Appendix-B athletics entry forms:
 *   B-I  Track (Men)   B-II Track (Women)
 *   B-III Field (Men)  B-IV Field (Women)
 *
 * Each sport-event's competitor / reserve slots are filled from:
 *   - individual events (team_entry_mode != team_only): the unit's athlete
 *     registrations, placed by their unit_slot (regular slots = competitors,
 *     reserve slots = reserve). Competitor count comes from Max/Unit − reserve
 *     (default 2 + 1 reserve when unset).
 *   - team events (team_entry_mode = team_only, e.g. relays): the unit's team
 *     entry members (team_member_count competitors + reserve_count reserves).
 * Events with no entries still appear, with blank cells.
 */
class AppendixB
{
    /** @return array{event:array,unit:array,sections:array,submitted:bool,submitted_at:?string} */
    public static function gather(int $eventId, int $unitId): array
    {
        $event = Event::findById($eventId) ?? [];
        $unit  = EventUnit::find($unitId) ?? [];

        $rows = Event::rowsRaw(
            "SELECT es.id, es.sport_event_id, es.track_event_type, es.event_code,
                    COALESCE(es.team_entry_mode,'both') AS team_entry_mode,
                    es.team_member_count, es.reserve_count, es.max_members_per_unit,
                    se.name AS sport_event_name, se.gender AS gender,
                    sc.name AS category
               FROM event_sports es
               JOIN sport_events se ON se.id = es.sport_event_id
          LEFT JOIN sport_categories sc ON sc.id = se.category_id
              WHERE es.event_id = ? AND es.track_event_type IN ('track','field')
              ORDER BY se.gender, es.track_event_type, se.name",
            [$eventId]
        );

        // Section buckets keyed 'type|gender'.
        $order = [
            'track|male'   => ['type' => 'track', 'gender' => 'male'],
            'track|female' => ['type' => 'track', 'gender' => 'female'],
            'field|male'   => ['type' => 'field', 'gender' => 'male'],
            'field|female' => ['type' => 'field', 'gender' => 'female'],
        ];
        $buckets = array_fill_keys(array_keys($order), []);
        $esIds   = [];

        foreach ($rows as $r) {
            $g = strtolower((string)$r['gender']);
            $g = $g === 'men' ? 'male' : ($g === 'women' ? 'female' : $g);
            $key = strtolower((string)$r['track_event_type']) . '|' . $g;
            if (!isset($buckets[$key])) continue;   // mixed / other genders skipped
            $esIds[] = (int)$r['id'];

            $isTeam  = ($r['team_entry_mode'] === 'team_only');
            $reserve = max(0, (int)$r['reserve_count']);
            if ($isTeam) {
                $compCount = max(1, (int)$r['team_member_count']);
                $resCount  = $reserve;
                [$comp, $res] = self::teamMembers($eventId, $unitId, (int)$r['id'], $compCount, $resCount);
            } else {
                $max = ($r['max_members_per_unit'] !== null && $r['max_members_per_unit'] !== '')
                     ? (int)$r['max_members_per_unit'] : 0;
                $compCount = $max > 0 ? max(1, $max - $reserve) : 2;   // default 2 competitors
                $resCount  = $reserve > 0 ? $reserve : 1;              // default 1 reserve slot
                [$comp, $res] = self::individualSlots($eventId, $unitId, (int)$r['id'], $compCount, $resCount);
            }

            $buckets[$key][] = [
                'name'        => (string)$r['sport_event_name'],
                'competitors' => $comp,   // padded to $compCount
                'reserves'    => $res,    // padded to $resCount
            ];
        }

        $sections = [];
        foreach ($order as $key => $meta) {
            $sections[] = [
                'type'   => $meta['type'],
                'gender' => $meta['gender'],
                'title'  => 'Athletics ' . ucfirst($meta['type']) . ' Events ('
                          . ($meta['gender'] === 'male' ? 'Men' : 'Women') . ')',
                'events' => $buckets[$key],
            ];
        }

        [$submitted, $submittedAt] = self::submissionStatus($eventId, $unitId, $esIds);

        return [
            'event'        => $event,
            'unit'         => $unit,
            'sections'     => $sections,
            'submitted'    => $submitted,
            'submitted_at' => $submittedAt,
        ];
    }

    /** Fill competitor + reserve slots from the unit's individual registrations. */
    private static function individualSlots(int $eventId, int $unitId, int $esId, int $compCount, int $resCount): array
    {
        $cap  = $compCount + $resCount;
        $rows = Event::rowsRaw(
            "SELECT a.name AS name, eri.unit_slot AS slot
               FROM event_registration_items eri
               JOIN event_registrations er ON er.id = eri.registration_id
               JOIN athletes a             ON a.id = er.athlete_id
              WHERE er.event_id = ? AND er.unit_id = ? AND eri.event_sport_id = ?
                AND COALESCE(er.admin_review_status,'') <> 'rejected'
              ORDER BY (eri.unit_slot IS NULL), eri.unit_slot, er.id",
            [$eventId, $unitId, $esId]
        );
        $bySlot = [];
        $spill  = [];
        foreach ($rows as $r) {
            $s = ($r['slot'] !== null && $r['slot'] !== '') ? (int)$r['slot'] : null;
            if ($s !== null && $s >= 1 && $s <= $cap && !isset($bySlot[$s])) $bySlot[$s] = (string)$r['name'];
            else $spill[] = (string)$r['name'];
        }
        for ($s = 1; $s <= $cap; $s++) {
            if (!isset($bySlot[$s]) && $spill) $bySlot[$s] = array_shift($spill);
        }
        $comp = [];
        for ($s = 1; $s <= $compCount; $s++) $comp[] = $bySlot[$s] ?? '';
        $res = [];
        for ($s = $compCount + 1; $s <= $cap; $s++) $res[] = $bySlot[$s] ?? '';
        return [$comp, $res];
    }

    /** Fill competitor + reserve slots from the unit's team entry (e.g. relay). */
    private static function teamMembers(int $eventId, int $unitId, int $esId, int $compCount, int $resCount): array
    {
        $comp = array_fill(0, $compCount, '');
        $res  = $resCount > 0 ? array_fill(0, $resCount, '') : [];
        $team = Event::rowsRaw(
            "SELECT id FROM team_registrations
              WHERE event_id = ? AND unit_id = ? AND event_sport_id = ?
                AND COALESCE(admin_review_status,'') <> 'rejected'
              ORDER BY id LIMIT 1",
            [$eventId, $unitId, $esId]
        );
        if (!$team) return [$comp, $res];

        $i = 0;
        foreach (TeamRegistration::members((int)$team[0]['id']) as $m) {
            $name = (string)($m['athlete_name'] ?? '');
            if ($i < $compCount)                        $comp[$i] = $name;
            elseif (($i - $compCount) < $resCount)      $res[$i - $compCount] = $name;
            $i++;
        }
        return [$comp, $res];
    }

    /**
     * "Submitted" when the unit has at least one entry for these sport-events
     * and every one (individual registration + team entry) has been submitted.
     * Returns [bool submitted, ?string latestSubmittedAt].
     */
    private static function submissionStatus(int $eventId, int $unitId, array $esIds): array
    {
        $esIds = array_values(array_unique(array_map('intval', $esIds)));
        if (!$esIds) return [false, null];
        $ph = implode(',', array_fill(0, count($esIds), '?'));

        $times = [];
        $anyNull = false;

        foreach (Event::rowsRaw(
            "SELECT er.id, er.submitted_at
               FROM event_registrations er
               JOIN event_registration_items eri ON eri.registration_id = er.id
              WHERE er.event_id = ? AND er.unit_id = ? AND eri.event_sport_id IN ({$ph})
                AND COALESCE(er.admin_review_status,'') <> 'rejected'
              GROUP BY er.id, er.submitted_at",
            array_merge([$eventId, $unitId], $esIds)
        ) as $r) {
            if (empty($r['submitted_at'])) $anyNull = true; else $times[] = $r['submitted_at'];
        }

        foreach (Event::rowsRaw(
            "SELECT id, submitted_at FROM team_registrations
              WHERE event_id = ? AND unit_id = ? AND event_sport_id IN ({$ph})
                AND COALESCE(admin_review_status,'') <> 'rejected'",
            array_merge([$eventId, $unitId], $esIds)
        ) as $r) {
            if (empty($r['submitted_at'])) $anyNull = true; else $times[] = $r['submitted_at'];
        }

        if (!$times || $anyNull) return [false, null];
        rsort($times);
        return [true, $times[0]];
    }
}
