<?php
namespace Services;

use Models\{Event, TrackConfig};

/**
 * Athletics / Skating medal tally. Winners come from PUBLISHED final-round
 * ranks (individual) and PUBLISHED team results (team). Points use the event's
 * configured medal-point values. Shared by the Event Staff portal and the Unit
 * portal so both show identical, published-only results.
 *
 * Returns ['unit_tally' => [...], 'events' => [...], 'unit_medals' => [...]].
 */
class TrackMedal
{
    /**
     * @param bool $publishedOnly When true (medal tally / public views) only
     *   published rank 1/2/3 rows count. When false (certificate generation)
     *   any entered rank 1/2/3 counts, published or not.
     * @param bool $allEvents When true, EVERY sport-event is returned in the
     *   'events' list (even those with no winner yet) with a 'status' of
     *   'published' / 'unpublished' / 'pending' so callers can spot missing
     *   events. When false, only events with winners are returned.
     */
    public static function build(array $ev, int $catId = 0, int $ageId = 0, bool $publishedOnly = true, bool $allEvents = false): array
    {
        $pubIndiv = $publishedOnly ? ' AND tha.is_published = 1' : '';
        $pubTeam  = $publishedOnly ? ' AND tr.is_published = 1'  : '';
        $eid = (int)($ev['id'] ?? 0);
        $ptsIndiv = [1 => (int)($ev['medal_pts_indiv_gold'] ?? 5),
                     2 => (int)($ev['medal_pts_indiv_silver'] ?? 3),
                     3 => (int)($ev['medal_pts_indiv_bronze'] ?? 2)];
        $ptsTeam  = [1 => (int)($ev['medal_pts_team_gold'] ?? 5),
                     2 => (int)($ev['medal_pts_team_silver'] ?? 3),
                     3 => (int)($ev['medal_pts_team_bronze'] ?? 2)];

        $eventsRaw = Event::rowsRaw(
            "SELECT es.id AS esid, es.event_code, sev.name AS sport_event_name, sev.event_label AS event_label,
                    sev.gender AS gender,
                    sc.name AS category_name, sc.abbreviation AS category_abbr,
                    ac.name AS age_name, ac.sort_order AS age_sort
               FROM event_sports es
               JOIN sport_events     sev ON sev.id = es.sport_event_id
               JOIN sport_categories sc  ON sc.id  = sev.category_id
          LEFT JOIN age_categories   ac  ON ac.id  = sev.age_category_id
              WHERE es.event_id = ?" . ($catId > 0 ? " AND sc.id = ?" : '') . ($ageId > 0 ? " AND sev.age_category_id = ?" : '') . "
              ORDER BY (ac.sort_order IS NULL), ac.sort_order, ac.name, es.event_code, sev.gender",
            array_merge([$eid], $catId > 0 ? [$catId] : [], $ageId > 0 ? [$ageId] : [])
        );

        // Approved participant count per event-sport (individual athletes + teams).
        $participantMap = [];
        foreach (Event::rowsRaw(
            "SELECT eri.event_sport_id AS esid, COUNT(DISTINCT er.athlete_id) AS cnt
               FROM event_registration_items eri
               JOIN event_registrations er ON er.id = eri.registration_id
              WHERE er.event_id = ? AND er.admin_review_status = 'approved'
              GROUP BY eri.event_sport_id", [$eid]) as $r) {
            $participantMap[(int)$r['esid']] = (int)$r['cnt'];
        }
        foreach (Event::rowsRaw(
            "SELECT event_sport_id AS esid, COUNT(*) AS cnt
               FROM team_registrations
              WHERE event_id = ? AND admin_review_status = 'approved'
              GROUP BY event_sport_id", [$eid]) as $r) {
            $esid = (int)$r['esid'];
            $participantMap[$esid] = ($participantMap[$esid] ?? 0) + (int)$r['cnt'];
        }

        // Individual: final round per event-sport (published rows only).
        $allIds   = array_map(fn($r) => (int)$r['esid'], $eventsRaw);
        $roundMap = TrackConfig::roundsForMany($allIds);
        $finalRoundOf = [];
        foreach ($eventsRaw as $r) {
            $rounds = $roundMap[(int)$r['esid']] ?? [];
            if ($rounds) { $last = end($rounds); $finalRoundOf[(int)$r['esid']] = (int)$last['id']; }
        }
        $indivWinners = [];
        if ($finalRoundOf) {
            $ids = array_values($finalRoundOf);
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $rows = Event::rowsRaw(
                "SELECT tha.round_id, tha.result_rank, tha.registration_id, er.athlete_id,
                        er.competitor_number, a.name AS athlete_name, a.passport_photo AS photo,
                        eu.name AS unit_name, eu.logo AS unit_logo
                   FROM track_heat_assignments tha
                   JOIN event_registrations er ON er.id = tha.registration_id
                   JOIN athletes a             ON a.id = er.athlete_id
              LEFT JOIN event_units eu         ON eu.id = er.unit_id
                  WHERE tha.round_id IN ({$in}) AND tha.result_rank IN (1,2,3){$pubIndiv}",
                $ids
            );
            $esidOfRound = array_flip($finalRoundOf);
            foreach ($rows as $r) {
                $esid = $esidOfRound[(int)$r['round_id']] ?? 0;
                $rk   = (int)$r['result_rank'];
                // Collect ALL athletes at each rank (ties → multiple winners).
                if ($esid && $rk >= 1 && $rk <= 3) {
                    $indivWinners[$esid][$rk][] = [
                        'chest'     => (int)($r['competitor_number'] ?? 0),
                        'name'      => (string)($r['athlete_name'] ?? ''),
                        'unit'      => (string)($r['unit_name'] ?? ''),
                        'unit_logo' => (string)($r['unit_logo'] ?? ''),
                        'photo'     => (string)($r['photo'] ?? ''),
                        'reg_id'    => (int)($r['registration_id'] ?? 0),
                    ];
                }
            }
        }

        // Team winners (published only).
        $teamWinners = [];
        foreach (Event::rowsRaw(
            "SELECT tr.id AS team_id, tr.event_sport_id AS esid, tr.result_rank, tr.team_name,
                    eu.name AS unit_name, eu.logo AS unit_logo,
                    (SELECT GROUP_CONCAT(am.name ORDER BY m.position, m.id SEPARATOR ', ')
                       FROM team_registration_members m JOIN athletes am ON am.id = m.athlete_id
                      WHERE m.team_registration_id = tr.id) AS members
               FROM team_registrations tr
          LEFT JOIN event_units eu ON eu.id = tr.unit_id
              WHERE tr.event_id = ? AND tr.admin_review_status = 'approved'
                AND tr.result_rank IN (1,2,3){$pubTeam}",
            [$eid]) as $r) {
            $esid = (int)$r['esid']; $rk = (int)$r['result_rank'];
            // Collect ALL teams at each rank (ties → multiple winners).
            $teamWinners[$esid][$rk][] = [
                'team'      => (string)($r['team_name'] ?? ''),
                'unit'      => (string)($r['unit_name'] ?? ''),
                'unit_logo' => (string)($r['unit_logo'] ?? ''),
                'members'   => (string)($r['members'] ?? ''),
                'team_id'   => (int)($r['team_id'] ?? 0),
            ];
        }

        // For the "all events" view: esids that have ANY entered medal place
        // (rank 1/2/3), published or not — distinguishes "unpublished" from
        // "no result yet".
        $enteredMedalEsids = [];
        if ($allEvents) {
            if ($finalRoundOf) {
                $roundToEsid = array_flip($finalRoundOf);
                $rids = array_values($finalRoundOf);
                $inR  = implode(',', array_fill(0, count($rids), '?'));
                foreach (Event::rowsRaw(
                    "SELECT DISTINCT round_id FROM track_heat_assignments
                      WHERE round_id IN ($inR) AND result_rank IN (1,2,3)", $rids) as $r) {
                    $es = $roundToEsid[(int)$r['round_id']] ?? 0;
                    if ($es) $enteredMedalEsids[$es] = true;
                }
            }
            foreach (Event::rowsRaw(
                "SELECT DISTINCT event_sport_id AS esid FROM team_registrations
                  WHERE event_id = ? AND admin_review_status = 'approved' AND result_rank IN (1,2,3)",
                [$eid]) as $r) {
                $enteredMedalEsids[(int)$r['esid']] = true;
            }
        }

        $units = []; $unitMedals = []; $unitLogos = [];
        $bump = function (&$units, $unit, $rank, $pts) {
            $unit = trim((string)$unit); if ($unit === '') $unit = '—';
            if (!isset($units[$unit])) $units[$unit] = ['g'=>0,'s'=>0,'b'=>0,'points'=>0];
            $units[$unit][[1=>'g',2=>'s',3=>'b'][$rank]]++;
            $units[$unit]['points'] += (int)($pts[$rank] ?? 0);
        };
        $addMedal = function (&$unitMedals, $unit, $rank, $name, $event, $chest, $photo, $points) {
            $unit = trim((string)$unit); if ($unit === '') $unit = '—';
            $unitMedals[$unit][] = [
                'rank'   => $rank,
                'name'   => (string)$name,
                'event'  => (string)$event,
                'chest'  => (string)$chest,
                'photo'  => (string)$photo,
                'points' => (int)$points,
            ];
        };
        $events = [];
        foreach ($eventsRaw as $r) {
            $esid = (int)$r['esid'];
            $isTeam = isset($teamWinners[$esid]) && !isset($indivWinners[$esid]);
            $win = $isTeam ? ($teamWinners[$esid] ?? []) : ($indivWinners[$esid] ?? []);
            if (empty($win)) {
                if (!$allEvents) continue;
                // No winner yet — still list it so missing events are visible.
                $events[] = [
                    'esid'        => $esid,
                    'sport_event' => trim((string)($r['sport_event_name'] ?? '')) ?: (string)($r['event_code'] ?? ''),
                    'event_label' => trim((string)($r['event_label'] ?? '')),
                    'category'    => (string)($r['category_name'] ?? ''),
                    'age_name'    => (string)($r['age_name'] ?? ''),
                    'gender'      => (string)($r['gender'] ?? ''),
                    'type'        => '—',
                    'participants'=> (int)($participantMap[$esid] ?? 0),
                    'places'      => [1 => [], 2 => [], 3 => []],
                    'status'      => isset($enteredMedalEsids[$esid]) ? 'unpublished' : 'pending',
                ];
                continue;
            }
            $places = [];
            $evLabel = trim((string)($r['sport_event_name'] ?? '')) ?: (string)($r['event_code'] ?? '');
            for ($rk = 1; $rk <= 3; $rk++) {
                $winnersAtRk = $win[$rk] ?? [];
                if (!$winnersAtRk) { $places[$rk] = []; continue; }
                // Each tied winner at this rank counts for its unit's points.
                $list = [];
                foreach ($winnersAtRk as $w) {
                    $uKey = trim((string)$w['unit']); if ($uKey === '') $uKey = '—';
                    if (empty($unitLogos[$uKey]) && !empty($w['unit_logo'])) $unitLogos[$uKey] = (string)$w['unit_logo'];
                    if ($isTeam) {
                        $list[] = ['chest' => '', 'name' => $w['team'], 'unit' => $w['unit'], 'photo' => '', 'sub' => $w['members'],
                                   'team_id' => (int)($w['team_id'] ?? 0), 'reg_id' => 0];
                        $bump($units, $w['unit'], $rk, $ptsTeam);
                        $addMedal($unitMedals, $w['unit'], $rk, $w['team'], $evLabel, '', '', (int)($ptsTeam[$rk] ?? 0));
                    } else {
                        $list[] = ['chest' => $w['chest'] > 0 ? (string)$w['chest'] : '', 'name' => $w['name'], 'unit' => $w['unit'],
                                   'photo' => (string)($w['photo'] ?? ''), 'sub' => '',
                                   'team_id' => 0, 'reg_id' => (int)($w['reg_id'] ?? 0)];
                        $bump($units, $w['unit'], $rk, $ptsIndiv);
                        $addMedal($unitMedals, $w['unit'], $rk, $w['name'], $evLabel,
                            $w['chest'] > 0 ? (string)$w['chest'] : '', (string)($w['photo'] ?? ''), (int)($ptsIndiv[$rk] ?? 0));
                    }
                }
                $places[$rk] = $list;
            }
            $events[] = [
                'esid'        => $esid,
                'sport_event' => trim((string)($r['sport_event_name'] ?? '')) ?: (string)($r['event_code'] ?? ''),
                'event_label' => trim((string)($r['event_label'] ?? '')),
                'category'    => (string)($r['category_name'] ?? ''),
                'age_name'    => (string)($r['age_name'] ?? ''),
                'gender'      => (string)($r['gender'] ?? ''),
                'type'        => $isTeam ? 'Team' : 'Individual',
                'participants'=> (int)($participantMap[$esid] ?? 0),
                'places'      => $places,
                'status'      => 'published',
            ];
        }

        $tally = [];
        foreach ($units as $name => $u) { $tally[] = ['unit' => $name, 'logo' => $unitLogos[$name] ?? ''] + $u; }
        usort($tally, function ($a, $b) {
            return ($b['points'] <=> $a['points']) ?: ($b['g'] <=> $a['g'])
                ?: ($b['s'] <=> $a['s']) ?: strcasecmp($a['unit'], $b['unit']);
        });

        // Completion: events with a published winner ÷ events that have any
        // registration (participants > 0).
        $registered = 0;
        foreach ($eventsRaw as $r) { if (($participantMap[(int)$r['esid']] ?? 0) > 0) $registered++; }
        $publishedEvents = 0;
        foreach ($events as $e) { if (($e['status'] ?? '') === 'published') $publishedEvents++; }
        $completion = [
            'registered' => $registered,
            'published'  => $publishedEvents,
            'pct'        => $registered > 0 ? (int)round($publishedEvents * 100 / $registered) : 0,
        ];

        // Last updated: newest result change across this event's rounds + team results.
        $t1 = Event::rowsRaw(
            "SELECT MAX(tha.updated_at) AS ts
               FROM track_heat_assignments tha
               JOIN event_sport_rounds r ON r.id = tha.round_id
               JOIN event_sports es      ON es.id = r.event_sport_id
              WHERE es.event_id = ?", [$eid])[0]['ts'] ?? null;
        $t2 = Event::rowsRaw(
            "SELECT MAX(updated_at) AS ts FROM team_registrations
              WHERE event_id = ? AND (result_rank IS NOT NULL OR (result_time IS NOT NULL AND result_time <> ''))",
            [$eid])[0]['ts'] ?? null;
        $lastUpdated = null;
        foreach ([$t1, $t2] as $t) { if ($t && (!$lastUpdated || $t > $lastUpdated)) $lastUpdated = $t; }

        return [
            'unit_tally'   => $tally,
            'events'       => $events,
            'unit_medals'  => $unitMedals,
            'completion'   => $completion,
            'last_updated' => $lastUpdated,
        ];
    }
}
