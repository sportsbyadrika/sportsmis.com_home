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
    public static function build(array $ev, int $catId = 0, int $ageId = 0): array
    {
        $eid = (int)($ev['id'] ?? 0);
        $ptsIndiv = [1 => (int)($ev['medal_pts_indiv_gold'] ?? 5),
                     2 => (int)($ev['medal_pts_indiv_silver'] ?? 3),
                     3 => (int)($ev['medal_pts_indiv_bronze'] ?? 2)];
        $ptsTeam  = [1 => (int)($ev['medal_pts_team_gold'] ?? 5),
                     2 => (int)($ev['medal_pts_team_silver'] ?? 3),
                     3 => (int)($ev['medal_pts_team_bronze'] ?? 2)];

        $eventsRaw = Event::rowsRaw(
            "SELECT es.id AS esid, es.event_code, sev.name AS sport_event_name, sev.gender AS gender,
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
                        er.competitor_number, a.name AS athlete_name, eu.name AS unit_name
                   FROM track_heat_assignments tha
                   JOIN event_registrations er ON er.id = tha.registration_id
                   JOIN athletes a             ON a.id = er.athlete_id
              LEFT JOIN event_units eu         ON eu.id = er.unit_id
                  WHERE tha.round_id IN ({$in}) AND tha.result_rank IN (1,2,3)
                    AND tha.is_published = 1",
                $ids
            );
            $esidOfRound = array_flip($finalRoundOf);
            foreach ($rows as $r) {
                $esid = $esidOfRound[(int)$r['round_id']] ?? 0;
                $rk   = (int)$r['result_rank'];
                if ($esid && $rk >= 1 && $rk <= 3 && !isset($indivWinners[$esid][$rk])) {
                    $indivWinners[$esid][$rk] = [
                        'chest'  => (int)($r['competitor_number'] ?? 0),
                        'name'   => (string)($r['athlete_name'] ?? ''),
                        'unit'   => (string)($r['unit_name'] ?? ''),
                        'reg_id' => (int)($r['registration_id'] ?? 0),
                    ];
                }
            }
        }

        // Team winners (published only).
        $teamWinners = [];
        foreach (Event::rowsRaw(
            "SELECT tr.id AS team_id, tr.event_sport_id AS esid, tr.result_rank, tr.team_name,
                    eu.name AS unit_name,
                    (SELECT GROUP_CONCAT(am.name ORDER BY m.position, m.id SEPARATOR ', ')
                       FROM team_registration_members m JOIN athletes am ON am.id = m.athlete_id
                      WHERE m.team_registration_id = tr.id) AS members
               FROM team_registrations tr
          LEFT JOIN event_units eu ON eu.id = tr.unit_id
              WHERE tr.event_id = ? AND tr.admin_review_status = 'approved'
                AND tr.result_rank IN (1,2,3) AND tr.is_published = 1",
            [$eid]) as $r) {
            $esid = (int)$r['esid']; $rk = (int)$r['result_rank'];
            if (!isset($teamWinners[$esid][$rk])) {
                $teamWinners[$esid][$rk] = [
                    'team'    => (string)($r['team_name'] ?? ''),
                    'unit'    => (string)($r['unit_name'] ?? ''),
                    'members' => (string)($r['members'] ?? ''),
                    'team_id' => (int)($r['team_id'] ?? 0),
                ];
            }
        }

        $units = []; $unitMedals = [];
        $bump = function (&$units, $unit, $rank, $pts) {
            $unit = trim((string)$unit); if ($unit === '') $unit = '—';
            if (!isset($units[$unit])) $units[$unit] = ['g'=>0,'s'=>0,'b'=>0,'points'=>0];
            $units[$unit][[1=>'g',2=>'s',3=>'b'][$rank]]++;
            $units[$unit]['points'] += (int)($pts[$rank] ?? 0);
        };
        $addMedal = function (&$unitMedals, $unit, $rank, $name, $event) {
            $unit = trim((string)$unit); if ($unit === '') $unit = '—';
            $unitMedals[$unit][] = ['rank' => $rank, 'name' => (string)$name, 'event' => (string)$event];
        };
        $events = [];
        foreach ($eventsRaw as $r) {
            $esid = (int)$r['esid'];
            $isTeam = isset($teamWinners[$esid]) && !isset($indivWinners[$esid]);
            $win = $isTeam ? ($teamWinners[$esid] ?? []) : ($indivWinners[$esid] ?? []);
            if (empty($win)) continue;
            $places = [];
            for ($rk = 1; $rk <= 3; $rk++) {
                if (!isset($win[$rk])) { $places[$rk] = null; continue; }
                $w = $win[$rk];
                $evLabel = trim((string)($r['sport_event_name'] ?? '')) ?: (string)($r['event_code'] ?? '');
                if ($isTeam) {
                    $places[$rk] = ['chest' => '', 'name' => $w['team'], 'unit' => $w['unit'], 'sub' => $w['members'],
                                    'team_id' => (int)($w['team_id'] ?? 0), 'reg_id' => 0];
                    $bump($units, $w['unit'], $rk, $ptsTeam);
                    $addMedal($unitMedals, $w['unit'], $rk, $w['team'], $evLabel);
                } else {
                    $places[$rk] = ['chest' => $w['chest'] > 0 ? (string)$w['chest'] : '', 'name' => $w['name'], 'unit' => $w['unit'], 'sub' => '',
                                    'team_id' => 0, 'reg_id' => (int)($w['reg_id'] ?? 0)];
                    $bump($units, $w['unit'], $rk, $ptsIndiv);
                    $addMedal($unitMedals, $w['unit'], $rk, $w['name'], $evLabel);
                }
            }
            $events[] = [
                'esid'        => $esid,
                'sport_event' => trim((string)($r['sport_event_name'] ?? '')) ?: (string)($r['event_code'] ?? ''),
                'category'    => (string)($r['category_name'] ?? ''),
                'age_name'    => (string)($r['age_name'] ?? ''),
                'gender'      => (string)($r['gender'] ?? ''),
                'type'        => $isTeam ? 'Team' : 'Individual',
                'places'      => $places,
            ];
        }

        $tally = [];
        foreach ($units as $name => $u) { $tally[] = ['unit' => $name] + $u; }
        usort($tally, function ($a, $b) {
            return ($b['points'] <=> $a['points']) ?: ($b['g'] <=> $a['g'])
                ?: ($b['s'] <=> $a['s']) ?: strcasecmp($a['unit'], $b['unit']);
        });

        return ['unit_tally' => $tally, 'events' => $events, 'unit_medals' => $unitMedals];
    }
}
