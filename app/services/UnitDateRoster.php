<?php
namespace Services;

use Models\Event;

/**
 * Unit-wise, date-wise athlete roster for the Order of Events programme.
 *
 * For a chosen competition date it collects every athlete a unit has entered
 * in a sport-event scheduled on that date (individual registrations + team /
 * relay members), de-duplicated per unit. Each athlete carries their BIB
 * (competitor number), name and — from the event's admin-defined custom
 * fields — employee number (GL. No) and designation (Rank). Used by the
 * printable reporting / attendance sheet the team manager signs each day.
 */
class UnitDateRoster
{
    /**
     * @return array{event:array,date:string,units:array<int,array{
     *     unit_id:int,unit_name:string,unit_logo:string,
     *     athletes:array<int,array{bib:string,name:string,photo:string,employee:string,designation:string,
     *       events:string,event_list:array<int,array{esid:int,label:string,absent:bool}>,absent:bool}>}>}
     */
    public static function gather(int $eventId, string $date): array
    {
        $event = Event::findById($eventId) ?? [];

        // Employee number / Designation come from the event's custom fields.
        $glKey   = Event::customFieldRoleKey($event, 'employee_no');
        $rankKey = Event::customFieldRoleKey($event, 'designation');

        // One employee/designation pair per athlete for this event, taken from
        // whichever of their registrations carries the custom-field answers.
        $cfByAthlete = [];
        if ($glKey !== '' || $rankKey !== '') {
            foreach (Event::rowsRaw(
                "SELECT er.athlete_id, er.custom_fields
                   FROM event_registrations er
                  WHERE er.event_id = ?
                    AND COALESCE(er.admin_review_status,'') <> 'rejected'
                    AND er.custom_fields IS NOT NULL AND er.custom_fields <> ''",
                [$eventId]
            ) as $r) {
                $aid = (int)$r['athlete_id'];
                if ($aid <= 0 || isset($cfByAthlete[$aid])) continue;
                $cf = json_decode((string)$r['custom_fields'], true);
                if (!is_array($cf)) continue;
                $cfByAthlete[$aid] = [
                    'employee'    => $glKey   !== '' ? trim((string)($cf[$glKey]   ?? '')) : '',
                    'designation' => $rankKey !== '' ? trim((string)($cf[$rankKey] ?? '')) : '',
                ];
            }
        }

        // units[uid] => ['unit_name','unit_logo','athletes'=>[aid=>row]]
        $units = [];
        $ensureUnit = function (int $uid, string $name, ?string $logo) use (&$units) {
            if (!isset($units[$uid])) {
                $units[$uid] = [
                    'unit_id'   => $uid,
                    'unit_name' => $name !== '' ? $name : ($uid === 0 ? 'No unit' : 'Unit #' . $uid),
                    'unit_logo' => (string)($logo ?? ''),
                    'athletes'  => [],
                ];
            } elseif ($units[$uid]['unit_logo'] === '' && $logo) {
                $units[$uid]['unit_logo'] = (string)$logo;
            }
        };
        $addAthlete = function (int $uid, int $aid, array $row, int $esid, string $eventLabel) use (&$units) {
            if (!isset($units[$uid]['athletes'][$aid])) {
                $row['events'] = [];
                $row['athlete_id'] = $aid;
                $units[$uid]['athletes'][$aid] = $row;
            } elseif ($units[$uid]['athletes'][$aid]['bib'] === '' && $row['bib'] !== '') {
                $units[$uid]['athletes'][$aid]['bib'] = $row['bib'];
            }
            // Accumulate the athlete's events for this date (deduped, keyed by
            // event_sport_id — attendance is tracked per event).
            $eventLabel = trim($eventLabel);
            if ($esid > 0) {
                $units[$uid]['athletes'][$aid]['events'][$esid] = $eventLabel;
            }
        };
        $cf = fn(int $aid) => $cfByAthlete[$aid] ?? ['employee' => '', 'designation' => ''];

        // 1) Individual registrations scheduled on this date.
        foreach (Event::rowsRaw(
            "SELECT er.unit_id, eu.name AS unit_name, eu.logo AS unit_logo,
                    a.id AS athlete_id, a.name AS athlete_name, a.passport_photo AS photo,
                    er.competitor_number, es.id AS event_sport_id, COALESCE(se.name, s.name) AS ev_label
               FROM event_registration_items eri
               JOIN event_sports es        ON es.id = eri.event_sport_id
          LEFT JOIN sport_events se        ON se.id = es.sport_event_id
          LEFT JOIN sports s               ON s.id  = es.sport_id
               JOIN event_registrations er ON er.id = eri.registration_id
               JOIN athletes a             ON a.id  = er.athlete_id
          LEFT JOIN event_units eu         ON eu.id = er.unit_id
              WHERE er.event_id = ? AND es.order_date = ?
                AND COALESCE(er.admin_review_status,'') <> 'rejected'",
            [$eventId, $date]
        ) as $r) {
            $uid = (int)($r['unit_id'] ?? 0);
            $aid = (int)$r['athlete_id'];
            $ensureUnit($uid, (string)($r['unit_name'] ?? ''), $r['unit_logo'] ?? '');
            $addAthlete($uid, $aid, [
                'bib'         => $r['competitor_number'] !== null ? (string)(int)$r['competitor_number'] : '',
                'name'        => (string)$r['athlete_name'],
                'photo'       => (string)($r['photo'] ?? ''),
                'employee'    => $cf($aid)['employee'],
                'designation' => $cf($aid)['designation'],
            ], (int)($r['event_sport_id'] ?? 0), (string)($r['ev_label'] ?? ''));
        }

        // 2) Team / relay members whose team event is scheduled on this date.
        try {
            foreach (Event::rowsRaw(
                "SELECT tr.unit_id, eu.name AS unit_name, eu.logo AS unit_logo,
                        a.id AS athlete_id, a.name AS athlete_name, a.passport_photo AS photo,
                        trm.competitor_number, es.id AS event_sport_id, COALESCE(se.name, s.name) AS ev_label
                   FROM team_registrations tr
                   JOIN event_sports es ON es.id = tr.event_sport_id
              LEFT JOIN sport_events se ON se.id = es.sport_event_id
              LEFT JOIN sports s        ON s.id  = es.sport_id
                   JOIN team_registration_members trm ON trm.team_registration_id = tr.id
                   JOIN athletes a ON a.id = trm.athlete_id
              LEFT JOIN event_units eu ON eu.id = tr.unit_id
                  WHERE tr.event_id = ? AND es.order_date = ?
                    AND COALESCE(tr.admin_review_status,'') <> 'rejected'",
                [$eventId, $date]
            ) as $r) {
                $uid = (int)($r['unit_id'] ?? 0);
                $aid = (int)$r['athlete_id'];
                $ensureUnit($uid, (string)($r['unit_name'] ?? ''), $r['unit_logo'] ?? '');
                $addAthlete($uid, $aid, [
                    'bib'         => $r['competitor_number'] !== null ? (string)(int)$r['competitor_number'] : '',
                    'name'        => (string)$r['athlete_name'],
                    'photo'       => (string)($r['photo'] ?? ''),
                    'employee'    => $cf($aid)['employee'],
                    'designation' => $cf($aid)['designation'],
                ], (int)($r['event_sport_id'] ?? 0), (string)($r['ev_label'] ?? ''));
            }
        } catch (\Throwable $e) { /* team tables may be absent */ }

        // Attendance is recorded per event (event_sport). Pull the whole event's
        // status map once: [event_sport_id => [athlete_id => status]].
        $statusMap = [];
        try { $statusMap = \Models\Attendance::statusMapForEvent($eventId); }
        catch (\Throwable $e) { $statusMap = []; }

        // Build each athlete's per-event list (esid, label, absent flag), a
        // comma-separated label string for display, and an athlete-level absent
        // flag that is true only when they are absent for every event that day.
        foreach ($units as &$u) {
            foreach ($u['athletes'] as &$a) {
                $evMap = $a['events'] ?? [];               // [esid => label]
                asort($evMap, SORT_NATURAL | SORT_FLAG_CASE);
                $aid = (int)($a['athlete_id'] ?? 0);
                $list = [];
                $anyPresent = false; $count = 0;
                foreach ($evMap as $esid => $label) {
                    $esid = (int)$esid;
                    $isAbsent = (($statusMap[$esid][$aid] ?? 'present') === 'absent');
                    $list[] = ['esid' => $esid, 'label' => (string)$label, 'absent' => $isAbsent];
                    if (!$isAbsent) $anyPresent = true;
                    $count++;
                }
                $a['event_list'] = $list;
                $a['events']     = implode(', ', array_map(fn($e) => $e['label'], $list));
                $a['absent']     = ($count > 0 && !$anyPresent);
            }
            unset($a);
        }
        unset($u);

        // Sort athletes (BIB, then name) and units (by name).
        foreach ($units as &$u) {
            $u['athletes'] = array_values($u['athletes']);
            usort($u['athletes'], function ($a, $b) {
                $ba = $a['bib'] !== '' ? (int)$a['bib'] : PHP_INT_MAX;
                $bb = $b['bib'] !== '' ? (int)$b['bib'] : PHP_INT_MAX;
                return $ba <=> $bb ?: strcasecmp($a['name'], $b['name']);
            });
        }
        unset($u);
        uasort($units, fn($a, $b) => strcasecmp($a['unit_name'], $b['unit_name']));

        return ['event' => $event, 'date' => $date, 'units' => array_values($units)];
    }
}
