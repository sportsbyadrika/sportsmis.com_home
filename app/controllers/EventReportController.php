<?php
namespace Controllers;

use Core\{Controller, Auth, Mailer};
use Models\{Institution, Event, EventRegistration, Athlete, User, TeamRegistration};

/**
 * Event-admin (institution_admin) reports for a single event.
 *
 * Routes are scoped under /institution/events/{eventHash}/reports/* so each
 * landing page already has a guaranteed-existing event in scope.
 */
class EventReportController extends Controller
{
    private array $institution;
    private array $event;

    private function boot(string $eventId): void
    {
        $this->requireAuth('institution_admin');
        $inst = Institution::findByUserId(Auth::id());
        if (!$inst) $this->redirect('/login', 'Institution not found.', 'error');
        $this->institution = $inst;

        $eid = \hid_event_decode($eventId);
        $event = Event::findById((int)$eid);
        if (!$event || (int)$event['institution_id'] !== (int)$inst['id']) $this->abort(404);
        $this->event = $event;
    }

    /** GET /institution/events/{id}/reports — landing page (group of buttons). */
    public function index(string $eventId): void
    {
        $this->boot($eventId);
        $this->renderWith('app', 'institution/reports/index', [
            'event'       => $this->event,
            'eventHash'   => $eventId,
            'institution' => $this->institution,
        ]);
    }

    /**
     * GET /institution/events/{id}/reports/registration-stats
     * Pre-Event report #1: Registration statistics — pivots the athletes
     * who have registered for this event by Sport-Category and by
     * Sport-Event with a gender breakdown.
     */
    public function registrationStats(string $eventId): void
    {
        $this->boot($eventId);
        $eid = (int)$this->event['id'];

        $sportFilter    = (int)($_GET['sport_id']    ?? 0);
        $categoryFilter = (string)($_GET['category'] ?? '');

        // Pull every approved registration's items so we count one row per
        // athlete-per-sport-event, not double-counting line-items.
        $where  = ['er.event_id = ?', "er.admin_review_status = 'approved'"];
        $params = [$eid];
        if ($sportFilter) {
            $where[]  = 'es.sport_id = ?';
            $params[] = $sportFilter;
        }
        if ($categoryFilter !== '') {
            $where[]  = 'sc.name = ?';
            $params[] = $categoryFilter;
        }

        $sql = "SELECT
                    es.id              AS event_sport_id,
                    es.sport_id,
                    s.name             AS sport_name,
                    sc.id              AS category_id,
                    sc.name            AS category_name,
                    se.name            AS sport_event_name,
                    se.gender          AS sport_event_gender,
                    a.gender           AS athlete_gender,
                    er.athlete_id      AS athlete_id,
                    eu.name            AS unit_name,
                    eu.address         AS unit_address,
                    er.unit_name_other AS unit_name_other
                  FROM event_registrations er
                  JOIN event_registration_items eri ON eri.registration_id = er.id
                  JOIN event_sports es              ON es.id = eri.event_sport_id
                  JOIN sports       s               ON s.id  = es.sport_id
             LEFT JOIN sport_events     se          ON se.id = es.sport_event_id
             LEFT JOIN sport_categories sc          ON sc.id = se.category_id
                  JOIN athletes     a               ON a.id  = er.athlete_id
             LEFT JOIN event_units eu               ON eu.id = er.unit_id
                 WHERE " . implode(' AND ', $where);

        $rows = Event::rowsRaw($sql, $params);

        // Pivot 1: per Category (sport_categories.name) → gender counts.
        // Pivot 2: per Sport-Event row inside each Category, with serial #.
        // Pivot 3: per Unit/Club/Institution → gender counts.
        // Pivot 4: per Unit, then per Sport-Event under that unit → gender counts.
        $byCategory  = []; // category_name => [...gender counts]
        $byCatSeen   = []; // "cat|athlete" dedupe set so By-Category counts unique athletes
        $byEvent     = []; // category_name => [ ['sl'=>i,'event_name'=>..,...], ... ]
        $eventTotals = []; // event_sport_id => [...]
        $byUnit      = []; // unit_name => [...gender counts]
        $byUnitSeen  = []; // "unit|athlete" dedupe set so By-Unit counts unique athletes
        $byUnitEvent = []; // unit_name => [ event_sport_id => [...counts] ]
        $byUnitCat   = []; // unit_name => [ category_name => unique-athlete count ]
        $byUnitCatSeen = []; // "unit|cat|athlete" dedupe set for the pivot
        $pivotCats   = []; // set of category names appearing in the pivot
        $unitMeta    = []; // unit_key => ['code' => name, 'name' => address] for the split column

        foreach ($rows as $r) {
            $cat = $r['category_name'] ?: '— Uncategorised —';
            $unitName = $r['unit_name'] ?: $r['unit_name_other'] ?: '';
            $unitAddr = $r['unit_name'] ? trim((string)($r['unit_address'] ?? '')) : '';
            if ($unitName === '') {
                $unit = '— Unspecified —';
            } else {
                $unit = $unitAddr !== '' ? ($unitName . ' - ' . $unitAddr) : $unitName;
            }
            // Track the name + address separately for the split-column display.
            // The composite $unit string remains the dedupe key so two units
            // sharing a name but with different addresses still appear apart.
            $unitMeta[$unit] = [
                'code' => $unitName !== '' ? $unitName : ($unit === '— Unspecified —' ? '— Unspecified —' : ''),
                'name' => $unitAddr,
            ];

            $byCategory[$cat] = $byCategory[$cat] ?? ['male'=>0,'female'=>0,'mixed'=>0,'other'=>0,'total'=>0];
            $byUnit[$unit]    = $byUnit[$unit]    ?? ['male'=>0,'female'=>0,'mixed'=>0,'other'=>0,'total'=>0];

            $g = $this->normGender($r['athlete_gender']);

            // By-Category counts UNIQUE athletes — one athlete registered for
            // several events in the same category is counted once.
            $catSeenKey = $cat . '|' . (int)$r['athlete_id'];
            if (!isset($byCatSeen[$catSeenKey])) {
                $byCatSeen[$catSeenKey]    = true;
                $byCategory[$cat][$g]      = ($byCategory[$cat][$g] ?? 0) + 1;
                $byCategory[$cat]['total'] = ($byCategory[$cat]['total'] ?? 0) + 1;
            }

            // By-Unit counts UNIQUE athletes — an athlete registered for
            // several events / categories under the same unit is counted
            // once. (Was over-counting one row per registration item.)
            $unitSeenKey = $unit . '|' . (int)$r['athlete_id'];
            if (!isset($byUnitSeen[$unitSeenKey])) {
                $byUnitSeen[$unitSeenKey] = true;
                $byUnit[$unit][$g]        = ($byUnit[$unit][$g] ?? 0) + 1;
                $byUnit[$unit]['total']   = ($byUnit[$unit]['total'] ?? 0) + 1;
            }

            $key = (int)$r['event_sport_id'];
            if (!isset($eventTotals[$key])) {
                $eventTotals[$key] = [
                    'category'      => $cat,
                    'event_name'    => $r['sport_event_name'] ?: $r['sport_name'],
                    'event_gender'  => $r['sport_event_gender'],
                    'male'=>0,'female'=>0,'mixed'=>0,'other'=>0,'total'=>0,
                ];
            }
            $eventTotals[$key][$g]++;
            $eventTotals[$key]['total']++;

            // Unit × Sport-Event pivot.
            $byUnitEvent[$unit] = $byUnitEvent[$unit] ?? [];
            if (!isset($byUnitEvent[$unit][$key])) {
                $byUnitEvent[$unit][$key] = [
                    'event_name' => $r['sport_event_name'] ?: $r['sport_name'],
                    'category'   => $cat,
                    'male'=>0,'female'=>0,'mixed'=>0,'other'=>0,'total'=>0,
                ];
            }
            $byUnitEvent[$unit][$key][$g]++;
            $byUnitEvent[$unit][$key]['total']++;

            // Unit × Event-Category pivot — count UNIQUE athletes, not
            // sport-event lines (one athlete with several events in the
            // same category still counts once).
            $seenKey = $unit . '|' . $cat . '|' . (int)$r['athlete_id'];
            if (!isset($byUnitCatSeen[$seenKey])) {
                $byUnitCatSeen[$seenKey]  = true;
                $byUnitCat[$unit][$cat]   = ($byUnitCat[$unit][$cat] ?? 0) + 1;
                $pivotCats[$cat]          = true;
            }
        }

        // Re-group event totals under their category for table 2.
        foreach ($eventTotals as $row) {
            $byEvent[$row['category']] = $byEvent[$row['category']] ?? [];
            $byEvent[$row['category']][] = $row;
        }
        // Stable sort.
        ksort($byCategory);
        ksort($byEvent);
        ksort($byUnit);
        ksort($byUnitEvent);
        ksort($byUnitCat);
        $pivotCategories = array_keys($pivotCats);
        sort($pivotCategories);
        foreach ($byEvent as &$rows2) {
            usort($rows2, fn($a, $b) => strcmp($a['event_name'], $b['event_name']));
        }
        unset($rows2);
        foreach ($byUnitEvent as &$evts) {
            usort($evts, fn($a, $b) => strcmp($a['category'].$a['event_name'], $b['category'].$b['event_name']));
        }
        unset($evts);

        // For the filter dropdowns.
        $sports = Event::rowsRaw("
            SELECT DISTINCT s.id, s.name
              FROM event_sports es JOIN sports s ON s.id = es.sport_id
             WHERE es.event_id = ?
             ORDER BY s.name", [$eid]);
        $categories = Event::rowsRaw("
            SELECT DISTINCT sc.name
              FROM event_sports es
              JOIN sport_events se   ON se.id = es.sport_event_id
              JOIN sport_categories sc ON sc.id = se.category_id
             WHERE es.event_id = ?
             ORDER BY sc.name", [$eid]);

        $this->renderWith('app', 'institution/reports/registration-stats', [
            'event'           => $this->event,
            'eventHash'       => $eventId,
            'by_category'     => $byCategory,
            'by_event'        => $byEvent,
            'by_unit'         => $byUnit,
            'by_unit_event'   => $byUnitEvent,
            'by_unit_category'=> $byUnitCat,
            'pivot_categories'=> $pivotCategories,
            'unit_meta'       => $unitMeta,
            'sports'          => $sports,
            'categories'      => $categories,
            'sport_filter'    => $sportFilter,
            'category_filter' => $categoryFilter,
        ]);
    }

    /**
     * GET /institution/events/{id}/reports/registration-stats.csv?table=...
     * CSV download for the two unit-keyed tables on the Registration
     * Statistics report. Honours the same sport/category filters the
     * on-screen report uses.
     *   table=unit_category — Unit × Event Category pivot
     *   table=by_unit       — By Unit / Club / Institution × gender
     */
    public function registrationStatsCsv(string $eventId): void
    {
        $this->boot($eventId);
        $eid = (int)$this->event['id'];
        $table = (string)($_GET['table'] ?? 'by_unit');

        $sportFilter    = (int)($_GET['sport_id']    ?? 0);
        $categoryFilter = (string)($_GET['category'] ?? '');

        // Rebuild via the same query the on-screen report uses.
        $where  = ['er.event_id = ?', "er.admin_review_status = 'approved'"];
        $params = [$eid];
        if ($sportFilter)         { $where[] = 'es.sport_id = ?'; $params[] = $sportFilter; }
        if ($categoryFilter !== '') { $where[] = 'sc.name = ?';     $params[] = $categoryFilter; }

        $sql = "SELECT es.id AS event_sport_id,
                       sc.name AS category_name,
                       a.gender AS athlete_gender,
                       er.athlete_id AS athlete_id,
                       eu.name AS unit_name, eu.address AS unit_address,
                       er.unit_name_other AS unit_name_other
                  FROM event_registrations er
                  JOIN event_registration_items eri ON eri.registration_id = er.id
                  JOIN event_sports es              ON es.id = eri.event_sport_id
                  JOIN sports       s               ON s.id  = es.sport_id
             LEFT JOIN sport_events     se          ON se.id = es.sport_event_id
             LEFT JOIN sport_categories sc          ON sc.id = se.category_id
                  JOIN athletes     a               ON a.id  = er.athlete_id
             LEFT JOIN event_units eu               ON eu.id = er.unit_id
                 WHERE " . implode(' AND ', $where);
        $rows = Event::rowsRaw($sql, $params);

        // Aggregate exactly like registrationStats() — only the two
        // unit-keyed pivots we ship as CSVs.
        $byUnit = []; $byUnitSeen = []; $byUnitCat = []; $byUnitCatSeen = [];
        $unitMeta = []; $pivotCats = [];
        foreach ($rows as $r) {
            $cat = $r['category_name'] ?: '— Uncategorised —';
            $unitName = $r['unit_name'] ?: $r['unit_name_other'] ?: '';
            $unitAddr = $r['unit_name'] ? trim((string)($r['unit_address'] ?? '')) : '';
            $unit = $unitName === ''
                ? '— Unspecified —'
                : ($unitAddr !== '' ? ($unitName . ' - ' . $unitAddr) : $unitName);
            $unitMeta[$unit] = [
                'code' => $unitName !== '' ? $unitName : ($unit === '— Unspecified —' ? '— Unspecified —' : ''),
                'name' => $unitAddr,
            ];
            $byUnit[$unit] = $byUnit[$unit] ?? ['male'=>0,'female'=>0,'mixed'=>0,'other'=>0,'total'=>0];
            $g = $this->normGender($r['athlete_gender']);
            $uKey = $unit . '|' . (int)$r['athlete_id'];
            if (!isset($byUnitSeen[$uKey])) {
                $byUnitSeen[$uKey]   = true;
                $byUnit[$unit][$g]   = ($byUnit[$unit][$g] ?? 0) + 1;
                $byUnit[$unit]['total'] = ($byUnit[$unit]['total'] ?? 0) + 1;
            }
            $cKey = $unit . '|' . $cat . '|' . (int)$r['athlete_id'];
            if (!isset($byUnitCatSeen[$cKey])) {
                $byUnitCatSeen[$cKey]      = true;
                $byUnitCat[$unit][$cat]    = ($byUnitCat[$unit][$cat] ?? 0) + 1;
                $pivotCats[$cat]           = true;
            }
        }
        ksort($byUnit); ksort($byUnitCat);
        $pivotCategories = array_keys($pivotCats);
        sort($pivotCategories);

        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '-',
                    strtolower((string)$this->event['name']));
        $filename = 'registration-stats-' . $table . '-' . $slug . '-' . date('Ymd-Hi') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $fh = fopen('php://output', 'w');
        fwrite($fh, "\xEF\xBB\xBF");

        if ($table === 'unit_category') {
            $header = array_merge(['Unit Code', 'Unit Name'], $pivotCategories, ['Total']);
            fputcsv($fh, $header);
            $colTot = array_fill_keys($pivotCategories, 0); $grand = 0;
            foreach ($byUnitCat as $unit => $catCounts) {
                $meta   = $unitMeta[$unit] ?? ['code' => $unit, 'name' => ''];
                $rowTot = 0;
                $line   = [$meta['code'], $meta['name']];
                foreach ($pivotCategories as $c) {
                    $v = (int)($catCounts[$c] ?? 0);
                    $line[] = $v;
                    $rowTot      += $v;
                    $colTot[$c]  += $v;
                }
                $line[] = $rowTot;
                $grand += $rowTot;
                fputcsv($fh, $line);
            }
            $tot = ['Grand Total', ''];
            foreach ($pivotCategories as $c) $tot[] = $colTot[$c];
            $tot[] = $grand;
            fputcsv($fh, $tot);
        } else { // by_unit
            // Honour the event's gender_label_set switch on the CSV
            // header so CBSE downloads read "Boys / Girls" instead of
            // "Men / Women" (the underlying counts stay keyed on the
            // canonical enum so the data is unchanged).
            fputcsv($fh, [
                'Unit Code', 'Unit Name',
                genderLabel('male',   $this->event),
                genderLabel('female', $this->event),
                genderLabel('mixed',  $this->event),
                'Other', 'Total',
            ]);
            $colTot = ['male'=>0,'female'=>0,'mixed'=>0,'other'=>0,'total'=>0];
            foreach ($byUnit as $unit => $counts) {
                $meta = $unitMeta[$unit] ?? ['code' => $unit, 'name' => ''];
                fputcsv($fh, [
                    $meta['code'], $meta['name'],
                    (int)($counts['male']   ?? 0),
                    (int)($counts['female'] ?? 0),
                    (int)($counts['mixed']  ?? 0),
                    (int)($counts['other']  ?? 0),
                    (int)($counts['total']  ?? 0),
                ]);
                foreach ($colTot as $k => $_) $colTot[$k] += (int)($counts[$k] ?? 0);
            }
            fputcsv($fh, ['Grand Total', '',
                $colTot['male'], $colTot['female'], $colTot['mixed'], $colTot['other'], $colTot['total']]);
        }
        fclose($fh);
        exit;
    }

    /**
     * GET /institution/events/{id}/reports/fee-collection
     * Pre-Event report #2: Fee collection across all athletes who
     * registered for this event. Filters: txn date range + status.
     */
    public function feeCollection(string $eventId): void
    {
        $this->boot($eventId);
        $eid = (int)$this->event['id'];

        $from   = $_GET['from']   ?? '';
        $to     = $_GET['to']     ?? '';
        $status = $_GET['status'] ?? '';
        $mode   = $_GET['mode']   ?? '';
        $type   = $_GET['type']   ?? '';   // '' | 'individual' | 'team'

        // Individual (athlete) payments.
        $individual = [];
        if ($type !== 'team' && $type !== 'unit') {
            $whereI  = ['er.event_id = ?'];
            $paramsI = [$eid];
            if ($from !== '') { $whereI[] = 'p.transaction_date >= ?'; $paramsI[] = $from; }
            if ($to   !== '') { $whereI[] = 'p.transaction_date <= ?'; $paramsI[] = $to;   }
            if (in_array($status, ['pending','approved','rejected'], true)) {
                $whereI[] = 'p.status = ?';
                $paramsI[] = $status;
            }
            if (in_array($mode, ['manual','epayment'], true)) {
                $whereI[] = 'p.payment_method = ?';
                $paramsI[] = $mode;
            }

            $sqlI = "SELECT 'Individual'    AS entry_type,
                            a.name           AS payer_name,
                            a.mobile         AS payer_mobile,
                            eu.name          AS unit_name,
                            er.unit_name_other,
                            p.payment_method,
                            p.transaction_date,
                            p.transaction_number,
                            p.amount,
                            p.status,
                            p.razorpay_payment_id,
                            p.razorpay_order_id,
                            p.id             AS payment_id
                      FROM event_registration_payments p
                      JOIN event_registrations er ON er.id = p.registration_id
                      JOIN athletes      a       ON a.id  = er.athlete_id
                 LEFT JOIN event_units   eu      ON eu.id = er.unit_id
                     WHERE " . implode(' AND ', $whereI);

            $individual = Event::rowsRaw($sqlI, $paramsI);
        }

        // Team entry payments.
        try { \Models\Schema::ensureTeamEntry(); } catch (\Throwable $e) {}

        $team = [];
        if ($type !== 'individual' && $type !== 'unit') {
            $whereT  = ['tr.event_id = ?'];
            $paramsT = [$eid];
            if ($from !== '') { $whereT[] = 'tp.transaction_date >= ?'; $paramsT[] = $from; }
            if ($to   !== '') { $whereT[] = 'tp.transaction_date <= ?'; $paramsT[] = $to;   }
            if (in_array($status, ['pending','approved','rejected'], true)) {
                $whereT[] = 'tp.status = ?';
                $paramsT[] = $status;
            }
            if (in_array($mode, ['manual','epayment'], true)) {
                $whereT[] = 'tp.payment_method = ?';
                $paramsT[] = $mode;
            }

            try {
                $sqlT = "SELECT 'Team'           AS entry_type,
                                tr.team_name     AS payer_name,
                                NULL             AS payer_mobile,
                                eu.name          AS unit_name,
                                NULL             AS unit_name_other,
                                tp.payment_method,
                                tp.transaction_date,
                                tp.transaction_number,
                                tp.amount,
                                tp.status,
                                tp.razorpay_payment_id,
                                tp.razorpay_order_id,
                                tp.id            AS payment_id
                          FROM team_registration_payments tp
                          JOIN team_registrations tr ON tr.id = tp.team_registration_id
                     LEFT JOIN event_units eu       ON eu.id = tr.unit_id
                         WHERE " . implode(' AND ', $whereT);
                $team = Event::rowsRaw($sqlT, $paramsT);
            } catch (\Throwable $e) {
                // Team entry tables may not exist on older installs.
                $team = [];
            }
        }

        // Unit-level bulk payments (event_unit_payments) — used when the event
        // runs Bulk unit payment mode. These are the approved transactions the
        // report was missing. They are manual bank transfers (never ePayment),
        // and don't belong to a single athlete/team, so they're included only
        // when the Type filter isn't narrowed to Individual or Team.
        try { \Models\Schema::ensureUnitPayments(); } catch (\Throwable $e) {}
        $unit = [];
        if ($type !== 'individual' && $type !== 'team' && $mode !== 'epayment') {
            $whereU  = ['up.event_id = ?', "up.status <> 'draft'"];
            $paramsU = [$eid];
            if ($from !== '') { $whereU[] = 'up.transaction_date >= ?'; $paramsU[] = $from; }
            if ($to   !== '') { $whereU[] = 'up.transaction_date <= ?'; $paramsU[] = $to;   }
            // Map the report's status filter onto the pool's statuses
            // (submitted == awaiting review == "pending" in this report).
            if ($status === 'approved')      { $whereU[] = "up.status = 'approved'"; }
            elseif ($status === 'rejected')  { $whereU[] = "up.status = 'rejected'"; }
            elseif ($status === 'pending')   { $whereU[] = "up.status = 'submitted'"; }

            try {
                $sqlU = "SELECT 'Unit'            AS entry_type,
                                eu.name           AS payer_name,
                                NULL              AS payer_mobile,
                                eu.name           AS unit_name,
                                NULL              AS unit_name_other,
                                'manual'          AS payment_method,
                                up.transaction_date,
                                up.reference_number AS transaction_number,
                                up.amount,
                                CASE WHEN up.status = 'submitted' THEN 'pending' ELSE up.status END AS status,
                                NULL              AS razorpay_payment_id,
                                NULL              AS razorpay_order_id,
                                up.id             AS payment_id
                          FROM event_unit_payments up
                     LEFT JOIN event_units eu ON eu.id = up.unit_id
                         WHERE " . implode(' AND ', $whereU);
                $unit = Event::rowsRaw($sqlU, $paramsU);
            } catch (\Throwable $e) {
                $unit = []; // pool table may not exist on older installs
            }
        }

        // Merge and sort by transaction date (newest first) then id.
        $rows = array_merge($individual, $team, $unit);
        usort($rows, function ($a, $b) {
            $d = strcmp((string)($b['transaction_date'] ?? ''), (string)($a['transaction_date'] ?? ''));
            if ($d !== 0) return $d;
            return ((int)($b['payment_id'] ?? 0)) <=> ((int)($a['payment_id'] ?? 0));
        });

        $grand = 0.0;
        $approved = 0.0; $pending = 0.0; $rejected = 0.0;
        $individualTotal = 0.0; $teamTotal = 0.0; $unitTotal = 0.0;
        $manualTotal = 0.0;     $onlineTotal = 0.0;
        $individualCount = 0;   $teamCount   = 0; $unitCount = 0;
        $manualCount = 0;       $onlineCount = 0;
        foreach ($rows as $r) {
            $amt = (float)$r['amount'];
            $grand += $amt;
            if ($r['status'] === 'approved')      $approved += $amt;
            elseif ($r['status'] === 'rejected')  $rejected += $amt;
            else                                  $pending  += $amt;

            $et = $r['entry_type'] ?? 'Individual';
            if ($et === 'Team') {
                $teamTotal       += $amt; $teamCount++;
            } elseif ($et === 'Unit') {
                $unitTotal       += $amt; $unitCount++;
            } else {
                $individualTotal += $amt; $individualCount++;
            }

            if (($r['payment_method'] ?? 'manual') === 'epayment') {
                $onlineTotal += $amt;
                $onlineCount++;
            } else {
                $manualTotal += $amt;
                $manualCount++;
            }
        }

        $this->renderWith('app', 'institution/reports/fee-collection', [
            'event'           => $this->event,
            'eventHash'       => $eventId,
            'rows'            => $rows,
            'grand_total'     => $grand,
            'approved_total'  => $approved,
            'pending_total'   => $pending,
            'rejected_total'  => $rejected,
            'individual_total'=> $individualTotal,
            'team_total'      => $teamTotal,
            'unit_total'      => $unitTotal,
            'manual_total'    => $manualTotal,
            'online_total'    => $onlineTotal,
            'individual_count'=> $individualCount,
            'team_count'      => $teamCount,
            'unit_count'      => $unitCount,
            'manual_count'    => $manualCount,
            'online_count'    => $onlineCount,
            'from'            => $from,
            'to'              => $to,
            'status'          => $status,
            'mode'            => $mode,
            'type'            => $type,
        ]);
    }

    private function normGender(?string $g): string
    {
        $g = strtolower(trim((string)$g));
        return match ($g) {
            'men'   => 'male',
            'women' => 'female',
            ''      => 'other',
            default => $g,
        };
    }

    /**
     * GET /institution/events/{id}/reports/competitor-list
     * Pre-Event report #3: Sport-Event-wise list of athletes (competitors)
     * intended to be printed on A4 sheets and posted on a notice board.
     * One section per (sport, sport-event) combination, starting on a
     * fresh page; thead repeats and a "Page N of M" footer is rendered
     * via @page CSS rules.
     */
    public function competitorList(string $eventId): void
    {
        $this->boot($eventId);
        $eid = (int)$this->event['id'];

        $rows = Event::rowsRaw(
            "SELECT es.id              AS event_sport_id,
                    es.sport_id,
                    es.event_code,
                    s.name             AS sport_name,
                    se.name            AS sport_event_name,
                    sc.name            AS category_name,
                    ac.name            AS age_category_name,
                    a.name             AS athlete_name,
                    a.gender           AS athlete_gender,
                    a.date_of_birth    AS athlete_dob,
                    eu.name            AS unit_name,
                    eu.address         AS unit_address,
                    er.unit_name_other AS unit_name_other,
                    er.competitor_number
               FROM event_sports es
               JOIN sports s              ON s.id = es.sport_id
          LEFT JOIN sport_events     se   ON se.id = es.sport_event_id
          LEFT JOIN sport_categories sc   ON sc.id = se.category_id
          LEFT JOIN age_categories   ac   ON ac.id = se.age_category_id
               JOIN event_registration_items eri ON eri.event_sport_id = es.id
               JOIN event_registrations      er  ON er.id = eri.registration_id
                                                AND er.admin_review_status = 'approved'
               JOIN athletes              a   ON a.id  = er.athlete_id
          LEFT JOIN event_units          eu  ON eu.id = er.unit_id
              WHERE es.event_id = ?
              ORDER BY s.name, sc.name, es.event_code, se.name, a.name",
            [$eid]
        );

        // Group by event_sport_id so each sport-event renders on its own
        // sheet. Compute athlete age at the event start date.
        $eventStart = $this->event['event_date_from'] ?: date('Y-m-d');
        $sections = [];
        foreach ($rows as $r) {
            $key = (int)$r['event_sport_id'];
            if (!isset($sections[$key])) {
                $sections[$key] = [
                    'sport_name'        => $r['sport_name'],
                    'event_code'        => $r['event_code'] ?? '',
                    'sport_event_name'  => $r['sport_event_name'] ?? $r['sport_name'],
                    'category_name'     => $r['category_name'] ?? '',
                    'age_category_name' => $r['age_category_name'] ?? '',
                    'athletes'          => [],
                ];
            }
            $age = '';
            if (!empty($r['athlete_dob'])) {
                $dob = new \DateTimeImmutable($r['athlete_dob']);
                $ref = new \DateTimeImmutable($eventStart);
                $age = (int)$dob->diff($ref)->y;
            }
            $unitDisplay = $r['unit_name']
                ? trim($r['unit_name'] . ($r['unit_address'] ? ' — ' . $r['unit_address'] : ''))
                : ($r['unit_name_other'] ?: '—');

            $sections[$key]['athletes'][] = [
                'competitor_number' => $r['competitor_number'],
                'athlete_name'      => $r['athlete_name'],
                'gender'            => genderLabel($this->normGender($r['athlete_gender']), $this->event),
                'age'               => $age === '' ? '—' : $age,
                'unit'              => $unitDisplay,
            ];
        }

        $this->renderWith('print', 'institution/reports/competitor-list', [
            'event'      => $this->event,
            'eventHash'  => $eventId,
            'sections'   => $sections,
            'event_start'=> $eventStart,
        ]);
    }

    /**
     * GET /institution/events/{id}/reports/competitor-cards
     * Lists every approved registration for this event with a checkbox so
     * the admin can bulk-generate Competitor Cards (allocate competitor
     * number if needed, email the card). Cards are no longer auto-issued
     * at approval time — this report is the explicit issuing step.
     */
    public function competitorCards(string $eventId): void
    {
        $this->boot($eventId);
        try { \Models\Schema::ensureCompetitorCardSettings(); } catch (\Throwable $e) {}
        $eid = (int)$this->event['id'];

        // Filter selections are persisted per-event in the session so they
        // survive page refreshes and bulk-Generate redirects. An explicit
        // ?reset=1 clears the stored selection.
        $sessKey = 'cc_filters_' . $eid;
        if (isset($_GET['reset'])) {
            unset($_SESSION[$sessKey]);
            $this->redirect("/institution/events/{$eventId}/reports/competitor-cards");
        }

        $filterKeys = ['unit_id', 'comp', 'noc', 'card'];
        $hasGetFilter = false;
        foreach ($filterKeys as $k) {
            if (array_key_exists($k, $_GET)) { $hasGetFilter = true; break; }
        }
        if ($hasGetFilter) {
            $_SESSION[$sessKey] = [
                'unit_id' => (int)($_GET['unit_id'] ?? 0),
                'comp'    => (string)($_GET['comp']  ?? ''),
                'noc'     => (string)($_GET['noc']   ?? ''),
                'card'    => (string)($_GET['card']  ?? ''),
            ];
        }
        $stored = $_SESSION[$sessKey] ?? [];

        $unitFilter = (int)($stored['unit_id'] ?? 0);
        $compFilter = (string)($stored['comp'] ?? '');   // '', 'yes', 'no'
        $nocFilter  = (string)($stored['noc']  ?? '');   // '', 'pending', 'accepted', 'rejected'
        $cardFilter = (string)($stored['card'] ?? '');   // '', 'issued', 'allocated', 'pending'

        $where  = ['er.event_id = ?', "er.admin_review_status = 'approved'"];
        $params = [$eid];
        if ($unitFilter > 0) {
            $where[]  = 'er.unit_id = ?';
            $params[] = $unitFilter;
        }
        if ($compFilter === 'yes') {
            $where[] = 'er.competitor_number IS NOT NULL';
        } elseif ($compFilter === 'no') {
            $where[] = 'er.competitor_number IS NULL';
        }
        if (in_array($nocFilter, ['pending','accepted','rejected'], true)) {
            // 'pending' should also include rows where the column is NULL.
            if ($nocFilter === 'pending') {
                $where[] = "(er.noc_status = 'pending' OR er.noc_status IS NULL)";
            } else {
                $where[]  = 'er.noc_status = ?';
                $params[] = $nocFilter;
            }
        }
        if ($cardFilter === 'issued') {
            $where[] = 'er.card_issued_at IS NOT NULL';
        } elseif ($cardFilter === 'allocated') {
            $where[] = 'er.card_issued_at IS NULL AND er.competitor_number IS NOT NULL';
        } elseif ($cardFilter === 'pending') {
            $where[] = 'er.card_issued_at IS NULL AND er.competitor_number IS NULL';
        }

        $rows = Event::rowsRaw(
            "SELECT er.id, er.admin_review_status, er.submitted_at,
                    er.competitor_number, er.admin_reviewed_at,
                    er.noc_status, er.card_issued_at,
                    a.name AS athlete_name, a.mobile AS athlete_mobile,
                    u.email AS athlete_email,
                    eu.name AS unit_name,
                    er.unit_name_other,
                    (SELECT COUNT(*) FROM event_registration_items
                       WHERE registration_id = er.id) AS items_count
               FROM event_registrations er
               JOIN athletes a ON a.id = er.athlete_id
          LEFT JOIN users u    ON u.id = a.user_id
          LEFT JOIN event_units eu ON eu.id = er.unit_id
              WHERE " . implode(' AND ', $where) . "
              ORDER BY (er.competitor_number IS NOT NULL), er.competitor_number, a.name",
            $params
        );

        $units = Event::rowsRaw(
            "SELECT id, name FROM event_units WHERE event_id = ? ORDER BY name",
            [$eid]
        );

        $this->renderWith('app', 'institution/reports/competitor-cards', [
            'event'        => $this->event,
            'eventHash'    => $eventId,
            'rows'         => $rows,
            'units'        => $units,
            'unit_filter'  => $unitFilter,
            'comp_filter'  => $compFilter,
            'noc_filter'   => $nocFilter,
            'card_filter'  => $cardFilter,
        ]);
    }

    /**
     * GET /institution/events/{id}/reports/competitor-cards.json —
     * download the same row set the Competitor Cards report shows
     * (respecting the active filter) as a JSON file. Each entry
     * carries the athlete's name, mobile, unit (name + address),
     * competitor number, registered email, and the relays they're
     * allotted to with one entry per (Event Category, Relay) pair.
     */
    public function competitorCardsJson(string $eventId): void
    {
        $this->boot($eventId);
        $eid = (int)$this->event['id'];

        // Same filter handling as competitorCards(), including the
        // session-persisted selection. Honour ?reset=1 silently so the
        // download link doesn't fight the report's session state.
        $sessKey = 'cc_filters_' . $eid;
        $filterKeys = ['unit_id', 'comp', 'noc', 'card'];
        $hasGetFilter = false;
        foreach ($filterKeys as $k) {
            if (array_key_exists($k, $_GET)) { $hasGetFilter = true; break; }
        }
        if ($hasGetFilter) {
            $_SESSION[$sessKey] = [
                'unit_id' => (int)($_GET['unit_id'] ?? 0),
                'comp'    => (string)($_GET['comp']  ?? ''),
                'noc'     => (string)($_GET['noc']   ?? ''),
                'card'    => (string)($_GET['card']  ?? ''),
            ];
        }
        $stored = $_SESSION[$sessKey] ?? [];

        $unitFilter = (int)($stored['unit_id'] ?? 0);
        $compFilter = (string)($stored['comp'] ?? '');
        $nocFilter  = (string)($stored['noc']  ?? '');
        $cardFilter = (string)($stored['card'] ?? '');

        $where  = ['er.event_id = ?', "er.admin_review_status = 'approved'"];
        $params = [$eid];
        if ($unitFilter > 0)             { $where[] = 'er.unit_id = ?'; $params[] = $unitFilter; }
        if ($compFilter === 'yes')       { $where[] = 'er.competitor_number IS NOT NULL'; }
        elseif ($compFilter === 'no')    { $where[] = 'er.competitor_number IS NULL'; }
        if (in_array($nocFilter, ['pending','accepted','rejected'], true)) {
            if ($nocFilter === 'pending') {
                $where[] = "(er.noc_status = 'pending' OR er.noc_status IS NULL)";
            } else {
                $where[]  = 'er.noc_status = ?';
                $params[] = $nocFilter;
            }
        }
        if     ($cardFilter === 'issued')    $where[] = 'er.card_issued_at IS NOT NULL';
        elseif ($cardFilter === 'allocated') $where[] = 'er.card_issued_at IS NULL AND er.competitor_number IS NOT NULL';
        elseif ($cardFilter === 'pending')   $where[] = 'er.card_issued_at IS NULL AND er.competitor_number IS NULL';

        $rows = Event::rowsRaw(
            "SELECT er.id, er.competitor_number,
                    a.name AS athlete_name, a.mobile AS athlete_mobile,
                    u.email AS athlete_email,
                    eu.name AS unit_name, eu.address AS unit_address,
                    er.unit_name_other
               FROM event_registrations er
               JOIN athletes a ON a.id = er.athlete_id
          LEFT JOIN users u    ON u.id = a.user_id
          LEFT JOIN event_units eu ON eu.id = er.unit_id
              WHERE " . implode(' AND ', $where) . "
              ORDER BY (er.competitor_number IS NOT NULL), er.competitor_number, a.name",
            $params
        );

        // Relay allotments per registration. Each row joined with relay
        // + range info; we'll group these onto each athlete's payload
        // as one line per (event category, relay).
        try { \Models\Schema::ensureLaneAllocation(); } catch (\Throwable $e) {}
        $regIds = array_values(array_unique(array_map(fn($r) => (int)$r['id'], $rows)));
        $relaysByReg = [];
        if ($regIds) {
            try {
                $in = implode(',', array_fill(0, count($regIds), '?'));
                $laneRows = Event::rowsRaw(
                    "SELECT erl.assigned_registration_id AS registration_id,
                            erl.category,
                            r.relay_number, r.relay_date, r.match_time,
                            r.reporting_time, r.order_no,
                            l.lane_number,
                            sr.name     AS venue_name,
                            sr.location AS venue_location,
                            d.name      AS range_distance_name,
                            d.distance_meters
                       FROM event_relay_lanes erl
                       JOIN event_relays                       r ON r.id = erl.relay_id
                       JOIN event_shooting_range_lanes         l ON l.id = erl.lane_id
                       JOIN event_shooting_range_distances     d ON d.id = r.shooting_range_distance_id
                       JOIN event_shooting_ranges             sr ON sr.id = d.shooting_range_id
                      WHERE r.event_id = ?
                        AND erl.assigned_registration_id IN ({$in})
                      ORDER BY COALESCE(r.order_no, 999999), r.id, l.lane_number",
                    array_merge([$eid], $regIds)
                );
                foreach ($laneRows as $ln) {
                    $relaysByReg[(int)$ln['registration_id']][] = [
                        'event_category'  => (string)($ln['category'] ?? ''),
                        'relay_number'    => $ln['relay_number'],
                        'relay_date'      => $ln['relay_date'],
                        'reporting_time'  => $ln['reporting_time'],
                        'match_time'      => $ln['match_time'],
                        'lane_number'     => (int)$ln['lane_number'],
                        'venue_name'      => (string)($ln['venue_name'] ?? ''),
                        'venue_location'  => (string)($ln['venue_location'] ?? ''),
                        'range_distance'  => (string)($ln['range_distance_name'] ?? ''),
                        'distance_meters' => $ln['distance_meters'] !== null
                            ? (int)$ln['distance_meters']
                            : null,
                    ];
                }
            } catch (\Throwable $e) { /* lane allocation may be absent */ }
        }

        // Build the output: one entry per registration. relay_details
        // contains an entry per (category, relay) the athlete is
        // allotted to, with the requested fields.
        $out = [];
        foreach ($rows as $r) {
            $regId = (int)$r['id'];
            $unitDisplay = $r['unit_name'] ?: ($r['unit_name_other'] ?? '');
            $out[] = [
                'name_of_athlete'   => (string)$r['athlete_name'],
                'mobile_number'     => (string)($r['athlete_mobile'] ?? ''),
                'unit_name'         => (string)$unitDisplay,
                'unit_address'      => (string)($r['unit_address'] ?? ''),
                'competitor_number' => $r['competitor_number']
                    ? str_pad((string)(int)$r['competitor_number'], 4, '0', STR_PAD_LEFT)
                    : null,
                'registered_email'  => (string)($r['athlete_email'] ?? ''),
                'relay_details'     => array_values($relaysByReg[$regId] ?? []),
            ];
        }

        $filename = 'competitor-cards-event-' . $eid . '-'
            . date('Ymd-His') . '.json';
        $body = json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen((string)$body));
            header('Cache-Control: no-store');
        }
        echo $body;
    }

    /**
     * POST /institution/events/{id}/reports/competitor-cards/generate
     * Accepts ids[] of approved registrations and allocates a competitor
     * number to each that doesn't already have one. No email is sent and the
     * card is NOT marked issued — that is the Email step's job. Returns a
     * short summary via flash.
     */
    public function competitorCardsGenerate(string $eventId): void
    {
        $this->boot($eventId);
        $this->verifyCsrf();
        $eid = (int)$this->event['id'];
        $label = \Models\Event::competitorLabel($this->event);

        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids) || !$ids) {
            $this->redirect("/institution/events/{$eventId}/reports/competitor-cards",
                'Select at least one registration to generate.', 'warning');
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));

        $generated = 0; $already = 0; $skipped = 0; $errors = 0;
        foreach ($ids as $regId) {
            $reg = EventRegistration::findById($regId);
            if (!$reg || (int)$reg['event_id'] !== $eid
                || ($reg['admin_review_status'] ?? '') !== 'approved') {
                $skipped++;
                continue;
            }
            // Allocate competitor number (idempotent).
            $num = (int)($reg['competitor_number'] ?? 0);
            if ($num) { $already++; continue; }
            $num = EventRegistration::allocateCompetitorNumber($regId);
            if (!$num) { $errors++; continue; }
            $generated++;
        }

        $parts = [];
        if ($generated) $parts[] = $generated . ' ' . $label . ($generated === 1 ? '' : 's') . ' allocated';
        if ($already)   $parts[] = $already . ' already allocated';
        if ($skipped)   $parts[] = $skipped . ' skipped';
        if ($errors)    $parts[] = $errors . ' error' . ($errors === 1 ? '' : 's');
        $msg = $parts ? implode(' · ', $parts) : 'Nothing to generate.';
        $this->redirect("/institution/events/{$eventId}/reports/competitor-cards", $msg,
            $errors ? 'warning' : 'success');
    }

    /**
     * POST /institution/events/{id}/reports/competitor-cards/email
     * Accepts ids[] of approved registrations. For each: allocate a competitor
     * number if one isn't already assigned, email the Competitor Card, and
     * mark card_issued_at. Returns a short summary via flash.
     */
    public function competitorCardsEmail(string $eventId): void
    {
        $this->boot($eventId);
        $this->verifyCsrf();
        $eid = (int)$this->event['id'];

        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids) || !$ids) {
            $this->redirect("/institution/events/{$eventId}/reports/competitor-cards",
                'Select at least one registration to email.', 'warning');
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));

        $generated = 0; $emailed = 0; $skipped = 0; $noEmail = 0; $errors = 0;
        foreach ($ids as $regId) {
            $reg = EventRegistration::withProfile($regId);
            if (!$reg || (int)$reg['event_id'] !== $eid
                || ($reg['admin_review_status'] ?? '') !== 'approved') {
                $skipped++;
                continue;
            }
            // Email is only required for this step. Athletes without an email
            // on file are skipped here (they can still be Generated) — the
            // card is not marked issued so it can be emailed once an email is
            // added.
            if (trim((string)($reg['athlete_email'] ?? '')) === '') {
                $noEmail++;
                continue;
            }
            // Allocate competitor number first if missing (idempotent).
            $num = (int)($reg['competitor_number'] ?? 0);
            if (!$num) {
                $num = EventRegistration::allocateCompetitorNumber($regId);
                if (!$num) { $errors++; continue; }
                $generated++;
            }
            // Mark the card as issued so the athlete dashboard can show it.
            EventRegistration::updateHeader($regId, [
                'card_issued_at' => date('Y-m-d H:i:s'),
            ]);
            // Send the card email.
            if ($this->emailCompetitorCard($regId)) {
                $emailed++;
            } else {
                $errors++;
            }
        }

        $parts = [];
        if ($generated) $parts[] = $generated . ' newly allocated';
        if ($emailed)   $parts[] = $emailed . ' email' . ($emailed === 1 ? '' : 's') . ' sent';
        if ($noEmail)   $parts[] = $noEmail . ' skipped (no email)';
        if ($skipped)   $parts[] = $skipped . ' skipped';
        if ($errors)    $parts[] = $errors . ' error' . ($errors === 1 ? '' : 's');
        $msg = $parts ? implode(' · ', $parts) : 'Nothing to email.';
        $this->redirect("/institution/events/{$eventId}/reports/competitor-cards", $msg,
            $errors ? 'warning' : 'success');
    }

    /**
     * POST /institution/events/{id}/reports/competitor-cards/print
     * Accepts ids[] of approved registrations and renders a print-friendly
     * sheet with one Competitor Card per page. Only registrations that are
     * approved AND already have a competitor number are included; the rest
     * are silently skipped (Generate them first). Rendered outside the app
     * layout so it prints clean.
     */
    public function competitorCardsPrint(string $eventId): void
    {
        $this->boot($eventId);
        $this->verifyCsrf();
        $eid = (int)$this->event['id'];

        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids) || !$ids) {
            $this->redirect("/institution/events/{$eventId}/reports/competitor-cards",
                'Select at least one registration to print.', 'warning');
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));

        $institution = Institution::findById((int)$this->event['institution_id'])
                     ?? ['name' => '', 'logo' => ''];

        $cards = [];
        foreach ($ids as $regId) {
            $reg = EventRegistration::withProfile($regId);
            if (!$reg || (int)$reg['event_id'] !== $eid
                || ($reg['admin_review_status'] ?? '') !== 'approved'
                || empty($reg['competitor_number'])) {
                continue;   // not printable yet — skip
            }
            $athlete = Athlete::findById((int)$reg['athlete_id']);
            if (!$athlete) continue;
            $ctx = EventRegistration::competitorCardContext($regId);
            $cards[] = [
                'athlete'            => $athlete,
                'event'              => $this->event,
                'institution'        => $institution,
                'registration'       => $reg,
                'category_rows'      => $ctx['category_rows'] ?? [],
                'event_rows'         => $ctx['event_rows'] ?? [],
                'age_category_label' => $ctx['age_category_label'] ?? '',
            ];
        }

        // Render the print sheet directly (no app chrome).
        $event     = $this->event;
        $eventHash = $eventId;
        require APP_ROOT . '/views/institution/reports/competitor-cards-print.php';
    }

    /**
     * POST /institution/events/{id}/reports/competitor-cards/settings
     * Persists the per-event Competitor Card message — shown between
     * the Registered Events table and the footer on both the printed
     * card and the card email.
     */
    public function competitorCardsSettings(string $eventId): void
    {
        $this->boot($eventId);
        try { \Models\Schema::ensureCompetitorCardSettings(); } catch (\Throwable $e) {}
        $this->verifyCsrf();
        $msg     = trim((string)($_POST['competitor_card_message'] ?? ''));
        $qrMode  = (string)($_POST['competitor_card_qr_mode'] ?? 'competitor_no');
        if (!in_array($qrMode, ['competitor_no', 'url'], true)) {
            $qrMode = 'competitor_no';
        }
        $qrUrl = trim((string)($_POST['competitor_card_qr_url'] ?? ''));
        // Light validation when URL mode is chosen — fall back to
        // competitor_no if the URL is missing or malformed so the QR
        // never renders blank.
        $fallbackNote = '';
        if ($qrMode === 'url') {
            if ($qrUrl === '' || !filter_var($qrUrl, FILTER_VALIDATE_URL)) {
                $qrMode = 'competitor_no';
                $qrUrl  = '';
                $fallbackNote = ' (URL was empty / invalid — QR is using Competitor Number.)';
            }
        }
        // QR caption — empty (or omitted) means "use the project default"
        // which is "Scan to verify". Cap length at 100 so it stays on one
        // line under the QR.
        $qrLabel = trim((string)($_POST['competitor_card_qr_label'] ?? ''));
        if (mb_strlen($qrLabel) > 100) $qrLabel = mb_substr($qrLabel, 0, 100);

        // Competitor-number label — only accept a value from the known list;
        // anything else is stored blank and resolves to the default.
        $compLabelIn = trim((string)($_POST['competitor_number_label'] ?? ''));
        $compLabel   = in_array($compLabelIn, Event::COMPETITOR_LABELS, true) ? $compLabelIn : '';

        // Registered-events table grouping on the card.
        $eventsMode = (string)($_POST['competitor_card_events_mode'] ?? 'category');
        if (!in_array($eventsMode, ['category', 'sport_event'], true)) $eventsMode = 'category';

        Event::updatePartial((int)$this->event['id'], [
            'competitor_card_message'      => $msg !== '' ? $msg : null,
            'competitor_card_qr_mode'      => $qrMode,
            'competitor_card_qr_url'       => $qrUrl   !== '' ? $qrUrl   : null,
            'competitor_card_qr_label'     => $qrLabel !== '' ? $qrLabel : null,
            'competitor_number_label'      => $compLabel !== '' ? $compLabel : null,
            'competitor_card_events_mode'  => $eventsMode,
        ]);
        $this->redirect("/institution/events/{$eventId}/reports/competitor-cards",
            'Card settings saved.' . $fallbackNote,
            $fallbackNote ? 'warning' : 'success');
    }

    /** Build context + send the competitor-card email. Returns true on success. */
    private function emailCompetitorCard(int $registrationId): bool
    {
        // withProfile() so the joined unit_name + unit_address come along —
        // the Mailer's card template needs them for the Unit row.
        $reg = EventRegistration::withProfile($registrationId);
        if (!$reg) return false;
        $event = Event::findById((int)$reg['event_id']);
        if (!$event) return false;
        $athlete = Athlete::findById((int)$reg['athlete_id']);
        if (!$athlete) return false;
        $institution = Institution::findById((int)$event['institution_id']);
        $items = EventRegistration::items($registrationId);
        $user  = User::findById((int)$athlete['user_id']);
        $email = $user['email'] ?? '';
        if (!$email) return false;
        try {
            return (new Mailer())->sendCompetitorCard($email, $athlete, $event, $institution, $reg, $items);
        } catch (\Throwable $e) {
            error_log('[reports/competitorCard/mail] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * GET /institution/events/{id}/reports/team-entry-approved
     * Pre-Event report: approved team entries with unit, event and the
     * three team members (competitor number + name). Printable.
     */
    public function teamEntryApproved(string $eventId): void
    {
        $this->boot($eventId);
        try { \Models\Schema::ensureEventStaff(); } catch (\Throwable $e) {}
        $eid = (int)$this->event['id'];

        $teams = TeamRegistration::forEvent($eid, true);
        // Hydrate members, sort by unit then team name.
        foreach ($teams as &$t) {
            $t['members'] = TeamRegistration::members((int)$t['id']);
        }
        unset($t);
        usort($teams, function ($a, $b) {
            $u = strcmp((string)($a['unit_name'] ?? ''), (string)($b['unit_name'] ?? ''));
            return $u !== 0 ? $u : strcmp((string)$a['team_name'], (string)$b['team_name']);
        });

        $this->renderWith('app', 'institution/reports/team-entry-approved', [
            'event'     => $this->event,
            'eventHash' => $eventId,
            'teams'     => $teams,
        ]);
    }

    /**
     * GET /institution/events/{id}/reports/unit-competitor-list
     * Pre-Event report: Unit-wise list of approved competitors, with one
     * row per (athlete, event category). The events the athlete is
     * registered for in that category are listed in a comma-separated
     * "Events" column (event_code + sport_event label).
     */
    public function unitCompetitorList(string $eventId): void
    {
        $this->boot($eventId);
        try { \Models\Schema::ensureUnitEmailLog(); } catch (\Throwable $e) {}
        $this->renderWith('app', 'institution/reports/unit-competitor-list', [
            'event'             => $this->event,
            'eventHash'         => $eventId,
            'units'             => $this->buildUnitCompetitorList((int)$this->event['id']),
            'email_sent_counts' => $this->unitEmailSentCounts((int)$this->event['id']),
        ]);
    }

    /** unit_id => total recipients_count across every broadcast for this event. */
    private function unitEmailSentCounts(int $eventId): array
    {
        try {
            $rows = Event::rowsRaw(
                "SELECT unit_id, COALESCE(SUM(recipients_count), 0) AS total
                   FROM unit_email_log
                  WHERE event_id = ?
                  GROUP BY unit_id", [$eventId]
            );
        } catch (\Throwable $e) { return []; }
        $out = [];
        foreach ($rows as $r) {
            if ($r['unit_id'] !== null) $out[(int)$r['unit_id']] = (int)$r['total'];
        }
        return $out;
    }

    /**
     * POST /institution/events/{id}/reports/unit-competitor-list/units/{unitId}/email
     * Send an organiser-authored broadcast email to every approved
     * athlete in this unit. The greeting + sign-off come from the
     * Mailer template; only the body text comes from the form.
     */
    public function unitEmailSend(string $eventId, string $unitId): void
    {
        $this->boot($eventId);
        try { \Models\Schema::ensureUnitEmailLog(); } catch (\Throwable $e) {}
        $this->verifyCsrf();
        $eid    = (int)$this->event['id'];
        $unitId = (int)$unitId;
        if ($unitId <= 0) $this->abort(404);

        // Confirm the unit belongs to this event.
        $unit = Event::rowsRaw(
            "SELECT id, name FROM event_units WHERE id = ? AND event_id = ?",
            [$unitId, $eid]
        )[0] ?? null;
        if (!$unit) $this->abort(404);

        $subject = trim((string)($_POST['subject'] ?? ''));
        $body    = trim((string)($_POST['body']    ?? ''));
        if ($body === '') {
            $this->redirect("/institution/events/{$eventId}/reports/unit-competitor-list",
                'Email body cannot be empty.', 'warning');
        }
        if ($subject === '') {
            $subject = ($this->event['name'] ?? 'Update') . ' – Update';
        }

        // Pull every approved athlete in this unit with their email.
        $recipients = Event::rowsRaw(
            "SELECT a.id, a.name, u.email
               FROM event_registrations er
               JOIN athletes a ON a.id = er.athlete_id
          LEFT JOIN users    u ON u.id = a.user_id
              WHERE er.event_id = ? AND er.unit_id = ?
                AND er.admin_review_status = 'approved'
              GROUP BY a.id, a.name, u.email
              ORDER BY a.name",
            [$eid, $unitId]
        );

        // The textarea ships plain text — preserve line breaks for HTML.
        $bodyHtml = nl2br(htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);

        $mailer = new Mailer();
        $sent = 0; $skipped = 0;
        $actorName = (string)(($this->institution['name'] ?? '') ?: 'Event Admin');
        foreach ($recipients as $r) {
            $email = trim((string)($r['email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                continue;
            }
            try {
                $ok = $mailer->sendUnitBroadcast(
                    $email, (string)$r['name'], $subject, $bodyHtml,
                    $this->event, $this->institution
                );
                if ($ok) $sent++; else $skipped++;
            } catch (\Throwable $e) {
                error_log('[reports/unitEmail] ' . $e->getMessage());
                $skipped++;
            }
        }

        // Log the broadcast for the unit-header badge + audit.
        try {
            Event::rowsRaw(
                "INSERT INTO unit_email_log
                    (event_id, unit_id, subject, body, recipients_count,
                     skipped_count, sent_by_name)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$eid, $unitId, $subject, $body, $sent, $skipped, $actorName]
            );
        } catch (\Throwable $e) {
            error_log('[reports/unitEmail/log] ' . $e->getMessage());
        }

        $bits = [];
        if ($sent)    $bits[] = $sent    . ' email' . ($sent    === 1 ? '' : 's') . ' sent';
        if ($skipped) $bits[] = $skipped . ' skipped (no / invalid email)';
        $msg = ($bits ? implode(' · ', $bits) : 'Nothing to send')
             . ' for ' . $unit['name'];
        $this->redirect("/institution/events/{$eventId}/reports/unit-competitor-list",
            $msg, $sent ? 'success' : 'warning');
    }

    /**
     * Shared data builder for the on-screen and print views of the
     * Unit-wise Competitor List report. Returns a unitKey => unit
     * map with grouped athlete-category rows including registered
     * events, team events, and any allotted relay/lane.
     */
    private function buildUnitCompetitorList(int $eid): array
    {
        try { \Models\Schema::ensureLaneAllocation(); } catch (\Throwable $e) {}
        try { \Models\Schema::ensureTeamEntry(); } catch (\Throwable $e) {}

        $rows = Event::rowsRaw(
            "SELECT eu.id              AS unit_id,
                    eu.name            AS unit_name,
                    eu.address         AS unit_address,
                    eu.logo            AS unit_logo,
                    er.id              AS registration_id,
                    er.competitor_number,
                    er.unit_name_other,
                    a.id               AS athlete_id,
                    a.name             AS athlete_name,
                    a.gender           AS athlete_gender,
                    a.date_of_birth    AS athlete_dob,
                    a.passport_photo   AS passport_photo,
                    a.mobile           AS athlete_mobile,
                    a.whatsapp_number  AS athlete_whatsapp,
                    u.email            AS athlete_email,
                    sc.id              AS category_id,
                    sc.name            AS category_name,
                    es.event_code      AS event_code,
                    se.name            AS sport_event_name
               FROM event_registrations er
               JOIN athletes a                   ON a.id  = er.athlete_id
          LEFT JOIN users    u                   ON u.id  = a.user_id
               JOIN event_registration_items eri ON eri.registration_id = er.id
               JOIN event_sports es              ON es.id  = eri.event_sport_id
          LEFT JOIN sport_events     se          ON se.id  = es.sport_event_id
          LEFT JOIN sport_categories sc          ON sc.id  = se.category_id
          LEFT JOIN event_units      eu          ON eu.id  = er.unit_id
              WHERE er.event_id = ?
                AND er.admin_review_status = 'approved'
              ORDER BY eu.name, a.name, sc.name, es.event_code, se.name",
            [$eid]
        );

        // Team events the athlete is part of (as a member of an approved team
        // registration on this event). athlete_id => [labels].
        $teamEvents = [];
        try {
            $teamRows = Event::rowsRaw(
                "SELECT trm.athlete_id, es.event_code, se.name AS sport_event_name
                   FROM team_registration_members trm
                   JOIN team_registrations tr ON tr.id = trm.team_registration_id
              LEFT JOIN event_sports     es ON es.id = tr.event_sport_id
              LEFT JOIN sport_events     se ON se.id = es.sport_event_id
                  WHERE tr.event_id = ?
                    AND tr.admin_review_status = 'approved'",
                [$eid]
            );
            foreach ($teamRows as $t) {
                $code = trim((string)($t['event_code'] ?? ''));
                $name = trim((string)($t['sport_event_name'] ?? ''));
                $lbl  = $code !== '' && $name !== '' ? $code . ' · ' . $name
                      : ($code !== '' ? $code : $name);
                if ($lbl === '') continue;
                $teamEvents[(int)$t['athlete_id']][] = $lbl;
            }
            foreach ($teamEvents as &$labels) {
                $labels = array_values(array_unique($labels));
            }
            unset($labels);
        } catch (\Throwable $e) {
            $teamEvents = [];
        }

        // Relay / lane allocation per (registration_id, category).
        // Each athlete-category row carries every lane allotted to them in
        // that category — relay number + date + time + lane number.
        $relayMap = []; // "regId|category" => [ {relay_number, relay_date, match_time, lane_number}, ... ]
        try {
            $laneRows = Event::rowsRaw(
                "SELECT erl.assigned_registration_id AS registration_id,
                        erl.category,
                        r.relay_number, r.relay_date, r.match_time, r.order_no,
                        l.lane_number
                   FROM event_relay_lanes erl
                   JOIN event_relays              r ON r.id = erl.relay_id
                   JOIN event_shooting_range_lanes l ON l.id = erl.lane_id
                  WHERE r.event_id = ?
                    AND erl.assigned_registration_id IS NOT NULL
                  ORDER BY COALESCE(r.order_no, 999999), r.id, l.lane_number",
                [$eid]
            );
            foreach ($laneRows as $ln) {
                $key = (int)$ln['registration_id'] . '|' . ($ln['category'] ?? '');
                $relayMap[$key][] = [
                    'relay_number' => $ln['relay_number'],
                    'relay_date'   => $ln['relay_date'],
                    'match_time'   => $ln['match_time'],
                    'lane_number'  => $ln['lane_number'],
                ];
            }
        } catch (\Throwable $e) {
            $relayMap = [];
        }

        // Group: unit => athlete_id => category_name => [athlete row + events[]]
        $eventStart = $this->event['event_date_from'] ?: date('Y-m-d');
        $units = []; // unitKey => ['unit_name','unit_address','unit_logo','rows'=>[]]
        $athleteCatBucket = []; // unitKey|athlete_id|cat => row-ref
        foreach ($rows as $r) {
            $unitName = $r['unit_name'] ?: ($r['unit_name_other'] ?: '');
            $unitKey  = $r['unit_id']
                ? 'U' . (int)$r['unit_id']
                : ($unitName !== '' ? 'O|' . $unitName : 'X');

            if (!isset($units[$unitKey])) {
                $units[$unitKey] = [
                    'unit_id'      => $r['unit_id'] ? (int)$r['unit_id'] : null,
                    'unit_name'    => $unitName ?: '— Unspecified —',
                    'unit_address' => $r['unit_id'] ? (string)($r['unit_address'] ?? '') : '',
                    'unit_logo'    => $r['unit_id'] ? (string)($r['unit_logo'] ?? '') : '',
                    'rows'         => [],
                ];
            }

            $cat = $r['category_name'] ?: '— Uncategorised —';
            $bucketKey = $unitKey . '|' . (int)$r['athlete_id'] . '|' . $cat;
            if (!isset($athleteCatBucket[$bucketKey])) {
                $age = '';
                if (!empty($r['athlete_dob'])) {
                    try {
                        $dob = new \DateTimeImmutable($r['athlete_dob']);
                        $ref = new \DateTimeImmutable($eventStart);
                        $age = (int)$dob->diff($ref)->y;
                    } catch (\Throwable $e) { $age = ''; }
                }
                $relayKey = (int)$r['registration_id'] . '|' . ($r['category_name'] ?? '');
                $units[$unitKey]['rows'][] = [
                    'photo'             => $r['passport_photo'] ?? '',
                    'competitor_number' => $r['competitor_number'],
                    'athlete_id'        => (int)$r['athlete_id'],
                    'athlete_name'      => $r['athlete_name'],
                    'age'               => $age === '' ? '—' : $age,
                    'gender'            => genderLabel($this->normGender($r['athlete_gender']), $this->event),
                    'category_name'     => $cat,
                    'events'            => [],
                    'team_events'       => $teamEvents[(int)$r['athlete_id']] ?? [],
                    'relays'            => $relayMap[$relayKey] ?? [],
                    'athlete_email'     => (string)($r['athlete_email']    ?? ''),
                    'athlete_mobile'    => (string)($r['athlete_mobile']   ?? ''),
                    'athlete_whatsapp'  => (string)($r['athlete_whatsapp'] ?? ''),
                ];
                $athleteCatBucket[$bucketKey] = count($units[$unitKey]['rows']) - 1;
            }
            $idx = $athleteCatBucket[$bucketKey];
            $eventCode = trim((string)($r['event_code'] ?? ''));
            $eventName = trim((string)($r['sport_event_name'] ?? ''));
            $label = $eventCode !== '' && $eventName !== ''
                ? $eventCode . ' · ' . $eventName
                : ($eventCode !== '' ? $eventCode : $eventName);
            if ($label !== '') $units[$unitKey]['rows'][$idx]['events'][] = $label;
        }
        // Dedupe events list per row.
        foreach ($units as &$u) {
            foreach ($u['rows'] as &$row) {
                $row['events'] = array_values(array_unique($row['events']));
            }
            unset($row);
        }
        unset($u);
        ksort($units);
        return $units;
    }

    /**
     * GET /institution/events/{id}/reports/unit-competitor-list/print
     * Same data, rendered through the print layout — A4 landscape,
     * white background, each unit on a fresh sheet.
     */
    public function unitCompetitorListPrint(string $eventId): void
    {
        $this->boot($eventId);
        $this->renderWith('print', 'institution/reports/unit-competitor-list-print', [
            'event'     => $this->event,
            'eventHash' => $eventId,
            'units'     => $this->buildUnitCompetitorList((int)$this->event['id']),
        ]);
    }

    /**
     * GET /institution/events/{id}/reports/unit-competitor-list.csv
     * CSV download: one row per (approved) registration with the
     * athlete's events listed as `#CODE - Name, …` plus contact
     * details.
     */
    /**
     * GET /institution/events/{id}/reports/category-competitor-list
     * Landing page: dropdown of event categories. When a category is
     * picked, the page renders the participants in that category with
     * Print / CSV buttons that point at the print + csv variants.
     */
    public function categoryCompetitorList(string $eventId): void
    {
        $this->boot($eventId);
        $eid = (int)$this->event['id'];
        $selected = trim((string)($_GET['category'] ?? ''));
        $athletes = $selected !== ''
            ? $this->buildCategoryCompetitorList($eid, $selected)
            : [];
        $this->renderWith('app', 'institution/reports/category-competitor-list', [
            'event'             => $this->event,
            'eventHash'         => $eventId,
            'categories'        => $this->categoriesForEvent($eid),
            'selected_category' => $selected,
            'athletes'          => $athletes,
            'comp_label'        => Event::competitorLabel($this->event),
        ]);
    }

    /**
     * GET /institution/events/{id}/reports/category-competitor-list/print
     * Same data via the print layout — A4 landscape, white background.
     */
    public function categoryCompetitorListPrint(string $eventId): void
    {
        $this->boot($eventId);
        $selected = trim((string)($_GET['category'] ?? ''));
        if ($selected === '') {
            $this->redirect("/institution/events/{$eventId}/reports/category-competitor-list",
                'Pick an Event Category to print.', 'warning');
        }
        $this->renderWith('print', 'institution/reports/category-competitor-list-print', [
            'event'             => $this->event,
            'eventHash'         => $eventId,
            'selected_category' => $selected,
            'athletes'          => $this->buildCategoryCompetitorList((int)$this->event['id'], $selected),
            'comp_label'        => Event::competitorLabel($this->event),
        ]);
    }

    /**
     * GET /institution/events/{id}/reports/category-competitor-list.csv?category=…
     * One row per athlete in the selected category — Unit Code, Unit
     * Name, athlete name, age, gender, comma-joined events list.
     */
    public function categoryCompetitorListCsv(string $eventId): void
    {
        $this->boot($eventId);
        $selected = trim((string)($_GET['category'] ?? ''));
        if ($selected === '') {
            $this->redirect("/institution/events/{$eventId}/reports/category-competitor-list",
                'Pick an Event Category before downloading.', 'warning');
        }
        $athletes = $this->buildCategoryCompetitorList((int)$this->event['id'], $selected);

        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '-',
                    strtolower($selected . '-' . (string)$this->event['name']));
        $filename = 'category-competitor-list-' . $slug . '-' . date('Ymd-Hi') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $fh = fopen('php://output', 'w');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, [
            'Sl. No', 'Unit Code', 'Unit Name', Event::competitorLabel($this->event), 'Name of Candidate',
            'Age', 'Gender', 'Events',
        ]);
        foreach ($athletes as $i => $a) {
            $events = [];
            foreach ($a['events'] as $idx => $ev) {
                $events[] = ($idx + 1) . '. ' . $ev;
            }
            fputcsv($fh, [
                $i + 1,
                $a['unit_code'],
                $a['unit_name_field'],
                $a['competitor_no'],
                $a['athlete_name'],
                $a['age'] === '' ? '' : $a['age'],
                $a['gender'],
                implode(' | ', $events),
            ]);
        }
        fclose($fh);
        exit;
    }

    // ── Event Day: Event-wise Competitor List ────────────────────────────────

    /**
     * GET /institution/events/{id}/reports/event-competitor-list
     * Landing page: pick an Event Category, then the approved athletes are
     * listed GROUPED BY SPORT EVENT (each sport-event a table of its own).
     */
    public function eventCompetitorList(string $eventId): void
    {
        $this->boot($eventId);
        $eid = (int)$this->event['id'];
        $selected = trim((string)($_GET['category'] ?? ''));
        $groups = $selected !== ''
            ? $this->buildEventCompetitorList($eid, $selected)
            : [];
        $this->renderWith('app', 'institution/reports/event-competitor-list', [
            'event'             => $this->event,
            'eventHash'         => $eventId,
            'categories'        => $this->categoriesForEvent($eid),
            'selected_category' => $selected,
            'groups'            => $groups,
            'comp_label'        => Event::competitorLabel($this->event),
        ]);
    }

    /** GET .../reports/event-competitor-list/print?category=… — A4 landscape. */
    public function eventCompetitorListPrint(string $eventId): void
    {
        $this->boot($eventId);
        $selected = trim((string)($_GET['category'] ?? ''));
        if ($selected === '') {
            $this->redirect("/institution/events/{$eventId}/reports/event-competitor-list",
                'Pick an Event Category to print.', 'warning');
        }
        $this->renderWith('print', 'institution/reports/event-competitor-list-print', [
            'event'             => $this->event,
            'eventHash'         => $eventId,
            'selected_category' => $selected,
            'groups'            => $this->buildEventCompetitorList((int)$this->event['id'], $selected),
            'comp_label'        => Event::competitorLabel($this->event),
        ]);
    }

    /**
     * GET .../reports/event-competitor-list.csv?category=… — one row per
     * (sport-event, athlete). Full data except the photo.
     */
    public function eventCompetitorListCsv(string $eventId): void
    {
        $this->boot($eventId);
        $selected = trim((string)($_GET['category'] ?? ''));
        if ($selected === '') {
            $this->redirect("/institution/events/{$eventId}/reports/event-competitor-list",
                'Pick an Event Category before downloading.', 'warning');
        }
        $groups = $this->buildEventCompetitorList((int)$this->event['id'], $selected);

        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '-',
                    strtolower($selected . '-' . (string)$this->event['name']));
        $filename = 'event-competitor-list-' . $slug . '-' . date('Ymd-Hi') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $fh = fopen('php://output', 'w');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, [
            'Sport Event', 'Sl. No', 'Unit Code', 'Unit Name', Event::competitorLabel($this->event),
            'Name of Candidate', 'Age', 'Gender', 'DOB', 'Age Category',
        ]);
        foreach ($groups as $g) {
            foreach ($g['athletes'] as $i => $a) {
                fputcsv($fh, [
                    $g['event_label'],
                    $i + 1,
                    $a['unit_code'],
                    $a['unit_name_field'],
                    $a['competitor_no'],
                    $a['athlete_name'],
                    $a['age'] === '' ? '' : $a['age'],
                    $a['gender'],
                    $a['dob'],
                    $a['age_category'],
                ]);
            }
        }
        fclose($fh);
        exit;
    }

    /**
     * Approved athletes in the given Event Category, grouped by sport-event.
     * Returns an ordered list of ['event_label' => …, 'athletes' => [ … ]].
     * Each athlete carries the institution id (Unit Code), unit name
     * (Unit Name), padded competitor number, age, gender, DOB, the
     * sport-event's age category and the passport photo.
     */
    private function buildEventCompetitorList(int $eid, string $catName): array
    {
        $rows = Event::rowsRaw(
            "SELECT er.id AS registration_id, er.competitor_number,
                    a.name AS athlete_name, a.gender, a.date_of_birth, a.passport_photo,
                    eu.name AS unit_name, eu.linked_institution_id, er.unit_name_other,
                    es.event_code, sev.name AS sport_event_name,
                    ac.name AS age_category_name
               FROM event_registrations er
               JOIN athletes a                   ON a.id  = er.athlete_id
          LEFT JOIN event_units      eu          ON eu.id = er.unit_id
               JOIN event_registration_items eri ON eri.registration_id = er.id
               JOIN event_sports es              ON es.id = eri.event_sport_id
          LEFT JOIN sport_events     sev         ON sev.id = es.sport_event_id
          LEFT JOIN sport_categories sc          ON sc.id  = sev.category_id
          LEFT JOIN age_categories   ac          ON ac.id  = sev.age_category_id
              WHERE er.event_id = ? AND er.admin_review_status = 'approved'
                AND sc.name = ?",
            [$eid, $catName]
        );

        $eventStart = $this->event['event_date_from'] ?: date('Y-m-d');
        $groups = []; $seen = [];   // seen[label][registration_id] de-dupes
        foreach ($rows as $r) {
            $code = trim((string)($r['event_code']       ?? ''));
            $name = trim((string)($r['sport_event_name'] ?? ''));
            if ($code === '' && $name === '') continue;
            $label = ($code !== '' ? $code : '') . ($code !== '' && $name !== '' ? ' - ' : '') . $name;

            $rid = (int)$r['registration_id'];
            if (isset($seen[$label][$rid])) continue;
            $seen[$label][$rid] = true;

            $age = '';
            if (!empty($r['date_of_birth'])) {
                try {
                    $dob = new \DateTimeImmutable($r['date_of_birth']);
                    $ref = new \DateTimeImmutable($eventStart);
                    $age = (int)$dob->diff($ref)->y;
                } catch (\Throwable $e) { $age = ''; }
            }
            $instId   = !empty($r['linked_institution_id']) ? (string)(int)$r['linked_institution_id'] : '';
            $unitName = (string)($r['unit_name'] ?: $r['unit_name_other'] ?: '');

            if (!isset($groups[$label])) $groups[$label] = ['event_label' => $label, 'athletes' => []];
            $groups[$label]['athletes'][] = [
                'unit_code'      => $instId,
                'unit_name_field'=> $unitName,
                'competitor_no'  => $r['competitor_number'] !== null
                                      ? str_pad((string)(int)$r['competitor_number'], 4, '0', STR_PAD_LEFT)
                                      : '',
                'athlete_name'   => (string)$r['athlete_name'],
                'age'            => $age === '' ? '' : (int)$age,
                'gender'         => genderLabel($this->normGender($r['gender']), $this->event),
                'dob'            => (string)($r['date_of_birth'] ?? ''),
                'age_category'   => (string)($r['age_category_name'] ?? ''),
                'photo'          => (string)($r['passport_photo'] ?? ''),
            ];
        }
        // Sort sport-event groups by label; athletes within by unit then name.
        ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($groups as &$g) {
            usort($g['athletes'], function ($a, $b) {
                $cu = strcasecmp((string)$a['unit_name_field'], (string)$b['unit_name_field']);
                if ($cu !== 0) return $cu;
                return strcasecmp((string)$a['athlete_name'], (string)$b['athlete_name']);
            });
        }
        unset($g);
        return array_values($groups);
    }

    // ── Event Day: Event-wise Participants Count ─────────────────────────────

    /**
     * GET /institution/events/{id}/reports/participants-count
     * Landing page: optional Event Category filter (default = all). Lists one
     * row per sport-event with its approved-participant count.
     */
    public function participantsCount(string $eventId): void
    {
        $this->boot($eventId);
        $eid = (int)$this->event['id'];
        $selected = trim((string)($_GET['category'] ?? ''));
        $this->renderWith('app', 'institution/reports/participants-count', [
            'event'             => $this->event,
            'eventHash'         => $eventId,
            'categories'        => $this->categoriesForEvent($eid),
            'selected_category' => $selected,
            'rows'              => $this->buildParticipantsCount($eid, $selected),
        ]);
    }

    /** GET .../reports/participants-count/print?category=… — A4 portrait. */
    public function participantsCountPrint(string $eventId): void
    {
        $this->boot($eventId);
        $selected = trim((string)($_GET['category'] ?? ''));
        $this->renderWith('print', 'institution/reports/participants-count-print', [
            'event'             => $this->event,
            'eventHash'         => $eventId,
            'selected_category' => $selected,
            'rows'              => $this->buildParticipantsCount((int)$this->event['id'], $selected),
        ]);
    }

    /** GET .../reports/participants-count.csv?category=… */
    public function participantsCountCsv(string $eventId): void
    {
        $this->boot($eventId);
        $selected = trim((string)($_GET['category'] ?? ''));
        $rows = $this->buildParticipantsCount((int)$this->event['id'], $selected);

        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '-',
                    strtolower(($selected !== '' ? $selected . '-' : 'all-') . (string)$this->event['name']));
        $filename = 'participants-count-' . $slug . '-' . date('Ymd-Hi') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $fh = fopen('php://output', 'w');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, ['Sl. No', 'Event Category', 'Sport Event', 'Submitted', 'Approved']);
        $totSub = 0; $totApp = 0;
        foreach ($rows as $i => $r) {
            $totSub += (int)$r['submitted'];
            $totApp += (int)$r['approved'];
            fputcsv($fh, [$i + 1, $r['category_name'], $r['sport_event'], $r['submitted'], $r['approved']]);
        }
        fputcsv($fh, ['', '', 'Total', $totSub, $totApp]);
        fclose($fh);
        exit;
    }

    /**
     * Participant counts per sport-event, optionally filtered to one Event
     * Category. Drafts are excluded entirely. Each row carries:
     *   submitted — distinct athletes whose registration is submitted for
     *               review and still active (pending OR approved)
     *   approved  — distinct athletes whose registration is approved
     * (Approved is a subset of Submitted.) Ordered by category then sport-event.
     */
    private function buildParticipantsCount(int $eid, string $catName = ''): array
    {
        $params = [$eid];
        $catSql = '';
        if ($catName !== '') { $catSql = ' AND sc.name = ?'; $params[] = $catName; }

        $rows = Event::rowsRaw(
            "SELECT sc.name AS category_name, es.event_code, sev.name AS sport_event_name,
                    COUNT(DISTINCT er.athlete_id) AS submitted,
                    COUNT(DISTINCT CASE WHEN er.admin_review_status = 'approved'
                                        THEN er.athlete_id END) AS approved
               FROM event_registrations er
               JOIN event_registration_items eri ON eri.registration_id = er.id
               JOIN event_sports es              ON es.id = eri.event_sport_id
          LEFT JOIN sport_events     sev         ON sev.id = es.sport_event_id
          LEFT JOIN sport_categories sc          ON sc.id  = sev.category_id
              WHERE er.event_id = ?
                AND er.admin_review_status IN ('pending','approved'){$catSql}
              GROUP BY es.id, sc.name, es.event_code, sev.name
              ORDER BY sc.name, sev.name, es.event_code",
            $params
        );

        $out = [];
        foreach ($rows as $r) {
            $code = trim((string)($r['event_code']       ?? ''));
            $name = trim((string)($r['sport_event_name'] ?? ''));
            if ($code === '' && $name === '') continue;
            $label = ($code !== '' ? $code : '') . ($code !== '' && $name !== '' ? ' - ' : '') . $name;
            $out[] = [
                'category_name' => (string)($r['category_name'] ?? ''),
                'sport_event'   => $label,
                'submitted'     => (int)$r['submitted'],
                'approved'      => (int)$r['approved'],
            ];
        }
        return $out;
    }

    // ── Post-Event: Qualified Athletes ───────────────────────────────────────

    /**
     * GET /institution/events/{id}/reports/qualified-athletes
     * Post-Event report: every approved athlete who met or exceeded the
     * Minimum Qualifying Score (MQS) in at least one sport-event. Each
     * athlete carries an inner list of the events they qualified in with
     * the per-event MQS and their total score.
     */
    public function qualifiedAthletes(string $eventId): void
    {
        $this->boot($eventId);
        $this->renderWith('app', 'institution/reports/qualified-athletes', [
            'event'     => $this->event,
            'eventHash' => $eventId,
            'athletes'  => $this->buildQualifiedAthletes((int)$this->event['id']),
        ]);
    }

    /** GET .../reports/qualified-athletes/print — A4 landscape print view. */
    public function qualifiedAthletesPrint(string $eventId): void
    {
        $this->boot($eventId);
        $this->renderWith('print', 'institution/reports/qualified-athletes-print', [
            'event'       => $this->event,
            'eventHash'   => $eventId,
            'institution' => $this->institution,
            'athletes'    => $this->buildQualifiedAthletes((int)$this->event['id']),
        ]);
    }

    /** GET .../reports/qualified-athletes.csv — one row per qualified event. */
    public function qualifiedAthletesCsv(string $eventId): void
    {
        $this->boot($eventId);
        $athletes = $this->buildQualifiedAthletes((int)$this->event['id']);

        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '-', strtolower((string)$this->event['name']));
        $filename = 'qualified-athletes-' . $slug . '-' . date('Ymd-Hi') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $fh = fopen('php://output', 'w');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, [
            'Sl. No', 'Competitor No.', 'Name', 'Gender', 'Age', 'Age Category', 'Unit',
            'Qualified Category', 'Qualified Event', 'MQS', 'Total Score',
        ]);
        $sl = 0;
        foreach ($athletes as $a) {
            $sl++;
            // One CSV line per qualified event so spreadsheets stay flat;
            // the athlete-level columns repeat down their event block.
            foreach ($a['qualified'] as $q) {
                fputcsv($fh, [
                    $sl,
                    $a['competitor_number'] !== '' ? $a['competitor_number'] : '',
                    $a['athlete_name'],
                    $a['gender'],
                    $a['age'] === '' ? '' : $a['age'],
                    $a['age_category'],
                    $a['unit_name'],
                    $q['category_name'],
                    $q['event_label'],
                    $q['mqs'],
                    $q['total_score'],
                ]);
            }
        }
        fclose($fh);
        exit;
    }

    /**
     * Build the Qualified-Athletes dataset: approved (athlete, sport-event)
     * pairs that carry an MQS, matched against the athlete's best score in
     * that sport-category. An athlete qualifies for a sport-event when their
     * total score ≥ that event's MQS. Returns one entry per athlete, each
     * holding the list of events they qualified in.
     */
    private function buildQualifiedAthletes(int $eid): array
    {
        // Score formatter: drop the decimal tail for whole numbers.
        $fmt = static function ($v): string {
            if ($v === null || $v === '') return '';
            $f = (float)$v;
            return ($f == floor($f))
                ? (string)(int)$f
                : rtrim(rtrim(number_format($f, 2, '.', ''), '0'), '.');
        };

        // Step 1 — approved registrations on sport-events that carry an MQS.
        $rows = Event::rowsRaw(
            "SELECT es.id                AS event_sport_id,
                    es.event_code,
                    es.mqs                AS mqs,
                    sev.name              AS sport_event_name,
                    sev.category_id       AS category_id,
                    sc.name               AS category_name,
                    ac.name               AS age_category_name,
                    er.athlete_id,
                    er.competitor_number,
                    a.name                AS athlete_name,
                    a.gender              AS athlete_gender,
                    a.date_of_birth       AS athlete_dob,
                    eu.name               AS unit_name
               FROM event_sports es
               JOIN sport_events     sev ON sev.id = es.sport_event_id
               JOIN sport_categories sc  ON sc.id  = sev.category_id
          LEFT JOIN age_categories   ac  ON ac.id  = sev.age_category_id
               JOIN event_registration_items eri ON eri.event_sport_id = es.id
               JOIN event_registrations er  ON er.id = eri.registration_id
                                          AND er.admin_review_status = 'approved'
               JOIN athletes a           ON a.id = er.athlete_id
          LEFT JOIN event_units eu        ON eu.id = er.unit_id
              WHERE es.event_id = ?
                AND es.mqs IS NOT NULL AND es.mqs > 0
              ORDER BY er.competitor_number, a.name",
            [$eid]
        );
        if (!$rows) return [];

        // Step 2 — best (highest) score per (athlete, sport-category).
        $scoreRows = Event::rowsRaw(
            "SELECT se.athlete_id, se.sport_category_id, MAX(se.grand_total) AS grand_total
               FROM score_entries se
              WHERE se.event_id = ?
                AND se.lane_status IN ('saved', 'final')
                AND se.grand_total IS NOT NULL
              GROUP BY se.athlete_id, se.sport_category_id",
            [$eid]
        );
        $scoreByKey = [];
        foreach ($scoreRows as $s) {
            $scoreByKey[(int)$s['athlete_id'] . '|' . (int)$s['sport_category_id']] = (float)$s['grand_total'];
        }

        // Step 3 — keep only the rows where the score clears the MQS, and
        // group them per athlete.
        $athletes = [];
        foreach ($rows as $r) {
            $aId   = (int)$r['athlete_id'];
            $catId = (int)$r['category_id'];
            $mqs   = (float)$r['mqs'];
            $key   = $aId . '|' . $catId;
            if (!array_key_exists($key, $scoreByKey)) continue;
            $total = $scoreByKey[$key];
            if ($total < $mqs) continue;   // not qualified for this event

            if (!isset($athletes[$aId])) {
                $athletes[$aId] = [
                    'competitor_number' => $r['competitor_number'] !== null ? (string)$r['competitor_number'] : '',
                    'athlete_name'      => (string)$r['athlete_name'],
                    'gender'            => genderLabel($this->normGender($r['athlete_gender']), $this->event),
                    'age'               => ($age = ageFromDob($r['athlete_dob'])) === null ? '' : $age,
                    'unit_name'         => (string)($r['unit_name'] ?? ''),
                    'age_cats'          => [],
                    'qualified'         => [],
                ];
            }
            $code  = trim((string)($r['event_code'] ?? ''));
            $name  = trim((string)($r['sport_event_name'] ?? ''));
            $label = $code !== '' && $name !== '' ? $code . ' · ' . $name : ($code !== '' ? $code : $name);
            $ac    = trim((string)($r['age_category_name'] ?? ''));
            if ($ac !== '') $athletes[$aId]['age_cats'][$ac] = true;
            $athletes[$aId]['qualified'][] = [
                'category_name' => (string)$r['category_name'],
                'event_label'   => $label,
                'mqs'           => $fmt($mqs),
                'total_score'   => $fmt($total),
            ];
        }

        // Flatten + finalise the age-category label.
        $out = [];
        foreach ($athletes as $a) {
            $a['age_category'] = implode(', ', array_keys($a['age_cats']));
            unset($a['age_cats']);
            $out[] = $a;
        }
        // Stable order: by competitor number (numeric), then name.
        usort($out, static function ($x, $y) {
            $cx = $x['competitor_number'] === '' ? PHP_INT_MAX : (int)$x['competitor_number'];
            $cy = $y['competitor_number'] === '' ? PHP_INT_MAX : (int)$y['competitor_number'];
            return $cx <=> $cy ?: strcmp($x['athlete_name'], $y['athlete_name']);
        });
        return $out;
    }

    /** Distinct event categories configured on this event. */
    private function categoriesForEvent(int $eid): array
    {
        return Event::rowsRaw(
            "SELECT DISTINCT sc.id, sc.name
               FROM event_sports es
               JOIN sport_events     sev ON sev.id = es.sport_event_id
               JOIN sport_categories sc  ON sc.id  = sev.category_id
              WHERE es.event_id = ?
              ORDER BY sc.name",
            [$eid]
        );
    }

    /**
     * One row per approved athlete in the given Event Category, sorted
     * by Unit Code then athlete name. Each row carries every event-sport
     * (code + name) the athlete is registered for in that category.
     */
    private function buildCategoryCompetitorList(int $eid, string $catName): array
    {
        $rows = Event::rowsRaw(
            "SELECT er.id AS registration_id, er.competitor_number,
                    a.id AS athlete_id, a.name AS athlete_name,
                    a.gender, a.date_of_birth,
                    eu.name AS unit_name, eu.linked_institution_id,
                    er.unit_name_other,
                    es.event_code, sev.name AS sport_event_name
               FROM event_registrations er
               JOIN athletes a                   ON a.id  = er.athlete_id
          LEFT JOIN event_units      eu          ON eu.id = er.unit_id
               JOIN event_registration_items eri ON eri.registration_id = er.id
               JOIN event_sports es              ON es.id = eri.event_sport_id
          LEFT JOIN sport_events     sev         ON sev.id = es.sport_event_id
          LEFT JOIN sport_categories sc          ON sc.id  = sev.category_id
              WHERE er.event_id = ? AND er.admin_review_status = 'approved'
                AND sc.name = ?",
            [$eid, $catName]
        );

        $eventStart = $this->event['event_date_from'] ?: date('Y-m-d');
        $byReg = [];
        foreach ($rows as $r) {
            $rid = (int)$r['registration_id'];
            if (!isset($byReg[$rid])) {
                $age = '';
                if (!empty($r['date_of_birth'])) {
                    try {
                        $dob = new \DateTimeImmutable($r['date_of_birth']);
                        $ref = new \DateTimeImmutable($eventStart);
                        $age = (int)$dob->diff($ref)->y;
                    } catch (\Throwable $e) { $age = ''; }
                }
                // Per spec: Unit Code column = the participating institution id
                // (event_units.linked_institution_id); Unit Name column = the
                // unit's code/name (event_units.name), falling back to the
                // typed "Other" unit name.
                $instId   = !empty($r['linked_institution_id']) ? (string)(int)$r['linked_institution_id'] : '';
                $unitName = (string)($r['unit_name'] ?: $r['unit_name_other'] ?: '');
                $byReg[$rid] = [
                    'unit_code'      => $instId,
                    'unit_name_field'=> $unitName,
                    'competitor_no'  => $r['competitor_number'] !== null
                                          ? str_pad((string)(int)$r['competitor_number'], 4, '0', STR_PAD_LEFT)
                                          : '',
                    'athlete_name'   => (string)$r['athlete_name'],
                    'age'            => $age === '' ? '' : (int)$age,
                    'gender'         => genderLabel($this->normGender($r['gender']), $this->event),
                    'events'         => [],
                ];
            }
            $code = trim((string)($r['event_code']        ?? ''));
            $name = trim((string)($r['sport_event_name']  ?? ''));
            if ($code === '' && $name === '') continue;
            $label = ($code !== '' ? $code : '') . ($code !== '' && $name !== '' ? ' - ' : '') . $name;
            $byReg[$rid]['events'][] = $label;
        }
        foreach ($byReg as &$row) {
            $row['events'] = array_values(array_unique($row['events']));
            sort($row['events']);
        }
        unset($row);
        // Sort by unit name (case-insensitive), then athlete name.
        usort($byReg, function ($a, $b) {
            $cu = strcasecmp((string)$a['unit_name_field'], (string)$b['unit_name_field']);
            if ($cu !== 0) return $cu;
            return strcasecmp((string)$a['athlete_name'], (string)$b['athlete_name']);
        });
        return $byReg;
    }

    public function unitCompetitorListCsv(string $eventId): void
    {
        $this->boot($eventId);
        $eid = (int)$this->event['id'];

        // Drive the CSV off the same builder the on-screen and print views
        // use so Event Category, Team Events and Relay/Lane stay in sync.
        $units = $this->buildUnitCompetitorList($eid);

        $filename = 'unit-competitor-list-'
                  . preg_replace('/[^A-Za-z0-9_-]+/', '-',
                        strtolower((string)$this->event['name']))
                  . '-' . date('Ymd-Hi') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $fh = fopen('php://output', 'w');
        // BOM so Excel opens the UTF-8 file with the right encoding.
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, [
            'Unit', 'Athlete Name', 'Comp. No.', 'Age', 'Gender',
            'Event Category', 'Events', 'Team Events', 'Relay & Lane',
            'Registered Email', 'Mobile', 'WhatsApp',
        ]);
        foreach ($units as $u) {
            foreach ($u['rows'] as $r) {
                $compNo = $r['competitor_number']
                    ? '#' . str_pad((string)(int)$r['competitor_number'], 4, '0', STR_PAD_LEFT)
                    : '';
                $relayBits = array_map(static function (array $ln): string {
                    $parts = [];
                    if ($ln['relay_number'] !== null && $ln['relay_number'] !== '') {
                        $parts[] = 'Relay ' . $ln['relay_number'];
                    }
                    if ($ln['lane_number'] !== null && $ln['lane_number'] !== '') {
                        $parts[] = 'Lane ' . $ln['lane_number'];
                    }
                    $when = trim(trim((string)($ln['relay_date'] ?? '')) . ' ' . trim((string)($ln['match_time'] ?? '')));
                    if ($when !== '') $parts[] = $when;
                    return implode(' · ', $parts);
                }, $r['relays']);
                fputcsv($fh, [
                    $u['unit_name'],
                    $r['athlete_name'],
                    $compNo,
                    $r['age'],
                    $r['gender'],
                    $r['category_name'],
                    implode(', ', $r['events']),
                    implode(', ', $r['team_events']),
                    implode('; ', array_filter($relayBits, static fn ($s) => $s !== '')),
                    $r['athlete_email'],
                    $r['athlete_mobile'],
                    $r['athlete_whatsapp'],
                ]);
            }
        }
        fclose($fh);
        exit;
    }

    /**
     * GET /institution/events/{id}/reports/relay-participants
     * Pre-Event report: Relay-wise list of allotted participants.
     * Heading carries the event details and logo (landscape print);
     * the body shows lane details, unit, event category, competitor
     * number, athlete name and that athlete's registered sport-events
     * in the lane's category.
     */
    public function relayParticipants(string $eventId): void
    {
        $this->boot($eventId);
        try { \Models\Schema::ensureLaneAllocation(); } catch (\Throwable $e) {}
        $eid = (int)$this->event['id'];

        // One row per (relay × lane) — using the lanes that belong to the
        // relay's range/distance so even lanes NOT configured into the
        // relay (no event_relay_lanes row) still surface in the report,
        // letting reviewers see Reserved / Not-in-use lanes alongside
        // active ones.
        $rows = Event::rowsRaw(
            "SELECT r.id              AS relay_id,
                    r.relay_number,
                    r.order_no,
                    r.relay_date,
                    r.match_time,
                    r.reporting_time,
                    d.name             AS range_name,
                    d.distance_meters,
                    sr.name            AS venue_name,
                    sr.location        AS venue_location,
                    l.id               AS lane_id,
                    l.lane_number,
                    l.lane_type,
                    erl.category       AS lane_category,
                    sc.abbreviation    AS lane_category_abbr,
                    eu.name            AS unit_name,
                    eu.address         AS unit_address,
                    eu.logo            AS unit_logo,
                    er.id              AS registration_id,
                    er.competitor_number,
                    a.id               AS athlete_id,
                    a.name             AS athlete_name,
                    a.gender           AS athlete_gender,
                    a.date_of_birth    AS athlete_dob,
                    a.passport_photo   AS athlete_photo,
                    (CASE
                       WHEN erl.relay_id IS NULL                                           THEN 'not_use'
                       WHEN erl.assigned_registration_id IS NOT NULL                       THEN 'allotted'
                       WHEN erl.assigned_unit_id IS NOT NULL                               THEN 'unit_assigned'
                       ELSE 'reserved'
                     END)              AS lane_status
               FROM event_relays r
               JOIN event_shooting_range_distances d   ON d.id  = r.shooting_range_distance_id
               JOIN event_shooting_ranges          sr  ON sr.id = d.shooting_range_id
               JOIN event_shooting_range_lanes     l   ON l.distance_id = d.id
          LEFT JOIN event_relay_lanes              erl ON erl.relay_id  = r.id
                                                       AND erl.lane_id   = l.id
          LEFT JOIN sport_categories               sc  ON sc.name = erl.category
          LEFT JOIN event_units                    eu  ON eu.id = erl.assigned_unit_id
          LEFT JOIN event_registrations            er  ON er.id = erl.assigned_registration_id
          LEFT JOIN athletes                       a   ON a.id  = er.athlete_id
              WHERE r.event_id = ?
              ORDER BY COALESCE(r.order_no, 999999), r.id, l.lane_number",
            [$eid]
        );

        // Group rows by relay; for each lane fetch the athlete's events
        // registered IN THAT lane's category.
        // Team events by athlete + category — keyed so we can show only
        // the team codes whose category matches the lane the athlete is
        // currently sitting in (mirrors the Events column filter).
        $teamCodesByAthleteCat = [];
        try {
            $tRows = Event::rowsRaw(
                "SELECT trm.athlete_id, es.event_code, sc.name AS category_name
                   FROM team_registration_members trm
                   JOIN team_registrations tr ON tr.id = trm.team_registration_id
              LEFT JOIN event_sports     es ON es.id = tr.event_sport_id
              LEFT JOIN sport_events     se ON se.id = es.sport_event_id
              LEFT JOIN sport_categories sc ON sc.id = se.category_id
                  WHERE tr.event_id = ?
                    AND tr.admin_review_status = 'approved'",
                [$eid]
            );
            foreach ($tRows as $t) {
                $code = trim((string)($t['event_code'] ?? ''));
                $cat  = trim((string)($t['category_name'] ?? ''));
                if ($code === '' || $cat === '') continue;
                $teamCodesByAthleteCat[(int)$t['athlete_id']][$cat][] = $code;
            }
            foreach ($teamCodesByAthleteCat as &$byCat) {
                foreach ($byCat as &$codes) {
                    $codes = array_values(array_unique($codes));
                }
                unset($codes);
            }
            unset($byCat);
        } catch (\Throwable $e) {
            $teamCodesByAthleteCat = [];
        }

        // For the lane → athlete_id lookup so we can attach team codes.
        $athleteByReg = [];
        try {
            $regIds = array_values(array_filter(array_map(
                fn($r) => (int)($r['registration_id'] ?? 0), $rows)));
            if ($regIds) {
                $in = implode(',', array_fill(0, count($regIds), '?'));
                foreach (Event::rowsRaw(
                    "SELECT id, athlete_id FROM event_registrations WHERE id IN ({$in})",
                    $regIds) as $ar) {
                    $athleteByReg[(int)$ar['id']] = (int)$ar['athlete_id'];
                }
            }
        } catch (\Throwable $e) {}

        $relays = [];
        foreach ($rows as $r) {
            $rid = (int)$r['relay_id'];
            if (!isset($relays[$rid])) {
                $relays[$rid] = [
                    'relay_number'    => $r['relay_number'],
                    'relay_date'      => $r['relay_date'],
                    'match_time'      => $r['match_time'],
                    'reporting_time'  => $r['reporting_time'],
                    'range_name'      => $r['range_name'],
                    'distance_meters' => $r['distance_meters'],
                    'venue_name'      => $r['venue_name'],
                    'venue_location'  => $r['venue_location'],
                    'lanes'           => [],
                ];
            }

            // Registered events on this athlete's registration in the lane's
            // category — event_code only (no sport-event label). Also
            // gather the distinct age-category names from those events
            // so the row can show them under the athlete's name.
            $eventCodes  = [];
            $ageCatNames = [];
            if (!empty($r['registration_id']) && !empty($r['lane_category'])) {
                $events = Event::rowsRaw(
                    "SELECT DISTINCT es.event_code, ac.name AS age_category_name
                       FROM event_registration_items eri
                       JOIN event_sports     es ON es.id = eri.event_sport_id
                       JOIN sport_events     se ON se.id = es.sport_event_id
                       JOIN sport_categories sc ON sc.id = se.category_id
                  LEFT JOIN age_categories   ac ON ac.id = se.age_category_id
                      WHERE eri.registration_id = ? AND sc.name = ?
                      ORDER BY es.event_code",
                    [(int)$r['registration_id'], $r['lane_category']]
                );
                $eventCodesSeen = $ageCatSeen = [];
                foreach ($events as $ev) {
                    $code = trim((string)($ev['event_code'] ?? ''));
                    if ($code !== '' && !isset($eventCodesSeen[$code])) {
                        $eventCodesSeen[$code] = true;
                        $eventCodes[] = $code;
                    }
                    $ac = trim((string)($ev['age_category_name'] ?? ''));
                    if ($ac !== '' && !isset($ageCatSeen[$ac])) {
                        $ageCatSeen[$ac] = true;
                        $ageCatNames[] = $ac;
                    }
                }
            }

            $athleteId  = $athleteByReg[(int)($r['registration_id'] ?? 0)] ?? 0;
            $laneCatKey = trim((string)($r['lane_category'] ?? ''));
            $teamCodes  = ($athleteId && $laneCatKey !== '')
                ? ($teamCodesByAthleteCat[$athleteId][$laneCatKey] ?? [])
                : [];

            // Age in years against the event start date (falls back to
            // today when the event_date_from isn't set).
            $age = null;
            if (!empty($r['athlete_dob'])) {
                try {
                    $dob = new \DateTimeImmutable((string)$r['athlete_dob']);
                    $ref = new \DateTimeImmutable($this->event['event_date_from'] ?: 'today');
                    $age = (int)$dob->diff($ref)->y;
                } catch (\Throwable $e) { $age = null; }
            }

            $relays[$rid]['lanes'][] = [
                'lane_number'        => $r['lane_number'],
                'category'           => $r['lane_category'],
                'category_abbr'      => $r['lane_category_abbr'],
                'lane_status'        => $r['lane_status'],
                'unit_name'          => $r['unit_name'],
                'unit_address'       => $r['unit_address'],
                'unit_logo'          => $r['unit_logo'],
                'competitor_number'  => $r['competitor_number'],
                'athlete_name'       => $r['athlete_name'],
                'athlete_photo'      => $r['athlete_photo'],
                'athlete_age'        => $age,
                'athlete_gender'     => $r['athlete_gender'],
                'age_category_label' => implode(' / ', $ageCatNames),
                'event_codes'        => $eventCodes,
                'team_codes'         => $teamCodes,
            ];
        }

        // Drop relays that have no allotted athlete at all — the user
        // doesn't want empty relays in the printable report.
        $relays = array_filter($relays, function ($r) {
            foreach ($r['lanes'] as $ln) {
                if (!empty($ln['athlete_name'])) return true;
            }
            return false;
        });

        $this->renderWith('print', 'institution/reports/relay-participants', [
            'event'     => $this->event,
            'eventHash' => $eventId,
            'relays'    => $relays,
        ]);
    }

    /**
     * GET /institution/events/{id}/reports/unit-others
     * Pre-Event report: lists every registration where the athlete picked
     * "Other" for Unit / Club / Institution and typed a name. Useful for
     * vetting free-text units before approval.
     */
    public function unitOthers(string $eventId): void
    {
        $this->boot($eventId);
        $eid = (int)$this->event['id'];

        $rows = Event::rowsRaw(
            "SELECT er.id, er.admin_review_status, er.submitted_at, er.registered_at,
                    er.unit_name_other, er.unit_reg_no,
                    a.name AS athlete_name, a.mobile AS athlete_mobile
               FROM event_registrations er
               JOIN athletes a ON a.id = er.athlete_id
              WHERE er.event_id = ?
                AND er.unit_id IS NULL
                AND er.unit_name_other IS NOT NULL
                AND er.unit_name_other <> ''
              ORDER BY a.name",
            [$eid]
        );

        $this->renderWith('app', 'institution/reports/unit-others', [
            'event'     => $this->event,
            'eventHash' => $eventId,
            'rows'      => $rows,
        ]);
    }
}
