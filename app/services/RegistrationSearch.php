<?php
namespace Services;

use Models\Event;

/**
 * Shared competitor search for an event — used by the Event-Staff search page
 * and the Institution (event admin) event view. Searches by competitor number
 * (typed or a scanned QR-card URL), name, unit, mobile, and the event's dynamic
 * registration fields (Employee No / Designation), and resolves each result's
 * dynamic-field values for display.
 */
class RegistrationSearch
{
    /** Which dynamic fields the event has, plus their labels. */
    public static function meta(array $event): array
    {
        $glKey   = Event::customFieldRoleKey($event, 'employee_no');
        $rankKey = Event::customFieldRoleKey($event, 'designation');
        $labelFor = function (string $key, string $fallback) use ($event): string {
            if ($key === '') return $fallback;
            foreach (Event::customFieldDefs($event) as $d) {
                if (($d['key'] ?? '') === $key) return (string)$d['label'];
            }
            return $fallback;
        };
        return [
            'gl_key'    => $glKey,
            'rank_key'  => $rankKey,
            'has_emp'   => $glKey   !== '',
            'has_des'   => $rankKey !== '',
            'emp_label' => $glKey   !== '' ? $labelFor($glKey, 'Employee No') : '',
            'des_label' => $rankKey !== '' ? $labelFor($rankKey, 'Designation') : '',
        ];
    }

    /**
     * Run a search. Returns ['results','notice','searched'] merged with meta().
     * Each result row carries 'employee' and 'designation' values.
     */
    public static function run(int $eid, string $by, string $q, int $unitId, array $event): array
    {
        $meta = self::meta($event);
        $results = []; $notice = ''; $searched = false;

        $valid = ['competitor', 'name', 'unit', 'mobile'];
        if ($meta['has_emp']) $valid[] = 'employee';
        if ($meta['has_des']) $valid[] = 'designation';

        if (in_array($by, $valid, true)) {
            $searched = true;
            $where = ['er.event_id = ?']; $params = [$eid];

            if ($by === 'competitor') {
                if ($q !== '' && preg_match('#/athlete/registrations/([A-Za-z0-9]+)/card#', $q, $m)) {
                    $regId = \hid_reg_decode($m[1]);
                    if ($regId > 0) { $where[] = 'er.id = ?'; $params[] = $regId; }
                    else { $where[] = '1 = 0'; $notice = 'The scanned QR code could not be matched to a registration.'; }
                } elseif ($q !== '') {
                    $where[] = 'er.competitor_number = ?';
                    $params[] = (int)preg_replace('/\D+/', '', $q);
                } else { $where[] = '1 = 0'; }
            } elseif ($by === 'name') {
                if ($q !== '') { $where[] = 'a.name LIKE ?'; $params[] = '%' . $q . '%'; } else { $where[] = '1 = 0'; }
            } elseif ($by === 'unit') {
                if ($unitId > 0) { $where[] = 'er.unit_id = ?'; $params[] = $unitId; } else { $where[] = '1 = 0'; }
            } elseif ($by === 'mobile') {
                if ($q !== '') { $where[] = 'a.mobile LIKE ?'; $params[] = '%' . $q . '%'; } else { $where[] = '1 = 0'; }
            } elseif ($by === 'employee' || $by === 'designation') {
                $k = $by === 'employee' ? $meta['gl_key'] : $meta['rank_key'];
                // Keys are 'cf1'..'cf5' (validated) so the JSON path is safe to inline.
                if ($k !== '' && preg_match('/^cf[1-9]$/', $k) && $q !== '') {
                    $where[] = "JSON_UNQUOTE(JSON_EXTRACT(er.custom_fields, '$." . $k . "')) LIKE ?";
                    $params[] = '%' . $q . '%';
                } else { $where[] = '1 = 0'; }
            }

            $results = Event::rowsRaw(
                "SELECT er.id AS registration_id, er.competitor_number,
                        er.admin_review_status, er.custom_fields,
                        a.name AS athlete_name, a.passport_photo, a.mobile,
                        eu.name AS unit_name, eu.address AS unit_address,
                        er.unit_name_other
                   FROM event_registrations er
                   JOIN athletes a       ON a.id  = er.athlete_id
              LEFT JOIN event_units eu   ON eu.id = er.unit_id
                  WHERE " . implode(' AND ', $where) . "
                  ORDER BY a.name
                  LIMIT 200",
                $params
            );
            foreach ($results as &$r) {
                $emp = ''; $des = '';
                if (!empty($r['custom_fields'])) {
                    $cf = json_decode((string)$r['custom_fields'], true);
                    if (is_array($cf)) {
                        if ($meta['gl_key']   !== '') $emp = trim((string)($cf[$meta['gl_key']]   ?? ''));
                        if ($meta['rank_key'] !== '') $des = trim((string)($cf[$meta['rank_key']] ?? ''));
                    }
                }
                $r['employee'] = $emp; $r['designation'] = $des;
            }
            unset($r);
        }

        return ['results' => $results, 'notice' => $notice, 'searched' => $searched] + $meta;
    }
}
