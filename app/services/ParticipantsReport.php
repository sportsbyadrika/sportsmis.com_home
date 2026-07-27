<?php
namespace Services;

use Models\{Event, TeamRegistration, Institution};

/**
 * Builds and streams the "Approved Participants List" PDF for one unit on an
 * event — approved athletes (with race ticks), approved team entries and the
 * unit's payment transactions. Shared by the Unit Portal (UnitController) and
 * the Event Admin (InstitutionController) so both produce an identical report.
 *
 * Callers are responsible for auth / visibility checks and for running the
 * relevant Schema::ensure* migrations before invoking this.
 */
class ParticipantsReport
{
    /** Build the report context + stream it as a PDF (this method exits). */
    public static function stream(array $event, array $eu): void
    {
        $eid  = (int)$event['id'];
        $uid  = (int)$eu['id'];
        $inst = Institution::findById((int)$event['institution_id'])
              ?? ['name' => '', 'logo' => ''];

        // ── Approved athletes ──
        $athletes = [];
        $aRows = Event::rowsRaw(
            "SELECT er.id AS reg_id, er.competitor_number, a.name, a.date_of_birth, a.gender, a.mobile,
                    a.address, a.communication_address,
                    a.passport_photo, a.id_proof_number, a.dob_proof_number,
                    ip.name AS id_proof_type_name, dp.name AS dob_proof_type_name,
                    u.email AS athlete_email,
                    (SELECT GROUP_CONCAT(DISTINCT ac.name ORDER BY ac.name SEPARATOR ', ')
                       FROM event_registration_items eri2
                       JOIN event_sports es2       ON es2.id = eri2.event_sport_id
                  LEFT JOIN sport_events se2        ON se2.id = es2.sport_event_id
                  LEFT JOIN age_categories ac       ON ac.id  = se2.age_category_id
                      WHERE eri2.registration_id = er.id) AS age_category_names,
                    (SELECT MIN(ac3.sort_order)
                       FROM event_registration_items eri3
                       JOIN event_sports es3        ON es3.id = eri3.event_sport_id
                  LEFT JOIN sport_events se3         ON se3.id = es3.sport_event_id
                  LEFT JOIN age_categories ac3       ON ac3.id = se3.age_category_id
                      WHERE eri3.registration_id = er.id) AS age_sort
               FROM event_registrations er
               JOIN athletes a           ON a.id  = er.athlete_id
          LEFT JOIN users u              ON u.id  = a.user_id
          LEFT JOIN id_proof_types  ip   ON ip.id = a.id_proof_type_id
          LEFT JOIN id_proof_types  dp   ON dp.id = a.dob_proof_type_id
              WHERE er.event_id = ? AND er.unit_id = ? AND er.admin_review_status = 'approved'
              ORDER BY a.name",
            [$eid, $uid]
        );
        foreach ($aRows as $r) {
            $events = [];
            // Race ticks: event CATEGORY abbreviation 1..6 → columns I..VI
            // (Quad = 1/2/3, Inline = 4/5/6). One tick per registered event.
            $ticks = [1=>false,2=>false,3=>false,4=>false,5=>false,6=>false];
            $itemRows = Event::rowsRaw(
                "SELECT se.name AS sport_event_name, es.event_code,
                        sc.abbreviation AS cat_abbr, sc.name AS cat_name
                   FROM event_registration_items eri
                   JOIN event_sports es        ON es.id = eri.event_sport_id
              LEFT JOIN sport_events se         ON se.id = es.sport_event_id
              LEFT JOIN sport_categories sc     ON sc.id = se.category_id
                  WHERE eri.registration_id = ?",
                [(int)$r['reg_id']]
            );
            foreach ($itemRows as $it) {
                $label = trim((string)($it['sport_event_name'] ?? '')) ?: trim((string)($it['event_code'] ?? ''));
                if ($label !== '') $events[] = $label;
                $abbr = trim((string)($it['cat_abbr'] ?? ''));
                if (ctype_digit($abbr)) {
                    $n = (int)$abbr;
                    if ($n >= 1 && $n <= 6) $ticks[$n] = true;
                }
            }
            $gRaw = strtolower(trim((string)($r['gender'] ?? '')));
            $genderRank = in_array($gRaw, ['male','men','m'], true) ? 0
                        : (in_array($gRaw, ['female','women','f'], true) ? 1 : 2);
            $dob = $r['date_of_birth'] ?? null;
            $doc   = trim((string)($r['id_proof_type_name'] ?? '')) ?: trim((string)($r['dob_proof_type_name'] ?? ''));
            $docNo = trim((string)($r['id_proof_number'] ?? ''))    ?: trim((string)($r['dob_proof_number'] ?? ''));
            $compNum = (int)($r['competitor_number'] ?? 0);
            $athletes[] = [
                'competitor_no' => $compNum > 0 ? (string)$compNum : '',
                'name'   => $r['name'] ?? '',
                'dob'    => $dob,
                'age_category' => trim((string)($r['age_category_names'] ?? '')),
                'age_sort' => $r['age_sort'] !== null ? (int)$r['age_sort'] : PHP_INT_MAX,
                'gender_rank' => $genderRank,
                'gender' => \genderLabel((string)($r['gender'] ?? ''), $event),
                'photo'  => $r['passport_photo'] ?? '',
                'doc'    => $doc,
                'doc_no' => $docNo,
                'events' => $events,
                'race_ticks' => $ticks,
            ];
        }
        // Sort by age-category sort order, then gender, then athlete name.
        usort($athletes, function ($x, $y) {
            $c = ($x['age_sort'] ?? PHP_INT_MAX) <=> ($y['age_sort'] ?? PHP_INT_MAX);
            if ($c !== 0) return $c;
            $c2 = ($x['gender_rank'] ?? 2) <=> ($y['gender_rank'] ?? 2);
            if ($c2 !== 0) return $c2;
            return strcasecmp((string)$x['name'], (string)$y['name']);
        });

        // ── Approved team entries (only when team entry is enabled) ──
        $teamEnabled = !empty($event['team_entry_enabled']);
        $teams = [];
        if ($teamEnabled) {
            try {
                $tRows = Event::rowsRaw(
                    "SELECT tr.id, tr.team_name, es.event_code,
                            sp.name AS sport_name, se.name AS sport_event_name
                       FROM team_registrations tr
                  LEFT JOIN event_sports es ON es.id = tr.event_sport_id
                  LEFT JOIN sports sp       ON sp.id = es.sport_id
                  LEFT JOIN sport_events se ON se.id = es.sport_event_id
                      WHERE tr.event_id = ? AND tr.unit_id = ? AND tr.admin_review_status = 'approved'
                      ORDER BY tr.team_name",
                    [$eid, $uid]
                );
                foreach ($tRows as $t) {
                    $members = [];
                    foreach (TeamRegistration::members((int)$t['id']) as $m) {
                        $members[] = (string)($m['athlete_name'] ?? '');
                    }
                    $evLabel = trim((string)($t['sport_name'] ?? ''));
                    if (!empty($t['sport_event_name'])) $evLabel .= ' · ' . $t['sport_event_name'];
                    if (!empty($t['event_code']))       $evLabel .= ' (' . $t['event_code'] . ')';
                    $teams[] = [
                        'team_name'    => $t['team_name'] ?? '',
                        'event'        => $evLabel,
                        'relay_code'   => trim((string)($eu['relay_code'] ?? '')),
                        'member_count' => count($members),
                        'members'      => $members,
                    ];
                }
            } catch (\Throwable $e) { /* team tables absent */ }
        }

        // ── Payment transactions (non-rejected) with status ──
        $txns = [];
        foreach (Event::rowsRaw(
            "SELECT p.transaction_date AS d, p.transaction_number AS ref, p.amount, p.status,
                    a.name AS who
               FROM event_registration_payments p
               JOIN event_registrations er ON er.id = p.registration_id
               JOIN athletes a             ON a.id = er.athlete_id
              WHERE er.event_id = ? AND er.unit_id = ?
                AND COALESCE(p.payment_method,'manual') <> 'demand' AND p.status <> 'rejected'
              ORDER BY p.transaction_date, p.id", [$eid, $uid]) as $p) {
            $txns[] = ['date'=>$p['d'], 'channel'=>'Individual — ' . ($p['who'] ?? ''),
                       'reference'=>$p['ref'] ?? '', 'amount'=>$p['amount'], 'status'=>$p['status']];
        }
        try {
            foreach (Event::rowsRaw(
                "SELECT pp.transaction_date AS d, pp.transaction_number AS ref, pp.amount, pp.status,
                        tr.team_name AS who
                   FROM team_registration_payments pp
                   JOIN team_registrations tr ON tr.id = pp.team_registration_id
                  WHERE tr.event_id = ? AND tr.unit_id = ? AND pp.status <> 'rejected'
                  ORDER BY pp.transaction_date, pp.id", [$eid, $uid]) as $p) {
                $txns[] = ['date'=>$p['d'], 'channel'=>'Team — ' . ($p['who'] ?? ''),
                           'reference'=>$p['ref'] ?? '', 'amount'=>$p['amount'], 'status'=>$p['status']];
            }
        } catch (\Throwable $e) {}
        try {
            foreach (Event::rowsRaw(
                "SELECT transaction_date AS d, reference_number AS ref, amount, status
                   FROM event_unit_payments
                  WHERE event_id = ? AND unit_id = ? AND status <> 'rejected'
                  ORDER BY transaction_date, id", [$eid, $uid]) as $p) {
                $txns[] = ['date'=>$p['d'], 'channel'=>'Unit (bulk)',
                           'reference'=>$p['ref'] ?? '', 'amount'=>$p['amount'], 'status'=>$p['status']];
            }
        } catch (\Throwable $e) {}

        \Core\ParticipantsReportPdf::stream([
            'event'        => $event,
            'institution'  => $inst,
            'unit'         => $eu,
            'athletes'     => $athletes,
            'teams'        => $teams,
            'txns'         => $txns,
            'team_enabled' => $teamEnabled,
            'show_events'  => (int)($event['unit_report_events_column_enabled'] ?? 1) === 1,
            'show_races'   => (int)($event['unit_report_race_columns_enabled'] ?? 1) === 1,
        ]);
    }
}
