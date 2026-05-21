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
        $byUnitEvent = []; // unit_name => [ event_sport_id => [...counts] ]
        $byUnitCat   = []; // unit_name => [ category_name => unique-athlete count ]
        $byUnitCatSeen = []; // "unit|cat|athlete" dedupe set for the pivot
        $pivotCats   = []; // set of category names appearing in the pivot

        foreach ($rows as $r) {
            $cat = $r['category_name'] ?: '— Uncategorised —';
            $unitName = $r['unit_name'] ?: $r['unit_name_other'] ?: '';
            $unitAddr = $r['unit_name'] ? trim((string)($r['unit_address'] ?? '')) : '';
            if ($unitName === '') {
                $unit = '— Unspecified —';
            } else {
                $unit = $unitAddr !== '' ? ($unitName . ' - ' . $unitAddr) : $unitName;
            }

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

            $byUnit[$unit][$g]         = ($byUnit[$unit][$g] ?? 0) + 1;
            $byUnit[$unit]['total']    = ($byUnit[$unit]['total'] ?? 0) + 1;

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
            'sports'          => $sports,
            'categories'      => $categories,
            'sport_filter'    => $sportFilter,
            'category_filter' => $categoryFilter,
        ]);
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

        // Individual (athlete) payments.
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

        // Team entry payments.
        try { \Models\Schema::ensureTeamEntry(); } catch (\Throwable $e) {}

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

        $team = [];
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

        // Merge and sort by transaction date (newest first) then id.
        $rows = array_merge($individual, $team);
        usort($rows, function ($a, $b) {
            $d = strcmp((string)($b['transaction_date'] ?? ''), (string)($a['transaction_date'] ?? ''));
            if ($d !== 0) return $d;
            return ((int)($b['payment_id'] ?? 0)) <=> ((int)($a['payment_id'] ?? 0));
        });

        $grand = 0.0;
        $approved = 0.0; $pending = 0.0; $rejected = 0.0;
        foreach ($rows as $r) {
            $grand += (float)$r['amount'];
            if ($r['status'] === 'approved') $approved += (float)$r['amount'];
            elseif ($r['status'] === 'rejected') $rejected += (float)$r['amount'];
            else $pending += (float)$r['amount'];
        }

        $this->renderWith('app', 'institution/reports/fee-collection', [
            'event'         => $this->event,
            'eventHash'     => $eventId,
            'rows'          => $rows,
            'grand_total'   => $grand,
            'approved_total'=> $approved,
            'pending_total' => $pending,
            'rejected_total'=> $rejected,
            'from'          => $from,
            'to'            => $to,
            'status'        => $status,
            'mode'          => $mode,
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
                'gender'            => ucfirst($this->normGender($r['athlete_gender'])),
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
     * POST /institution/events/{id}/reports/competitor-cards/generate
     * Accepts ids[] of approved registrations. For each: allocate competitor
     * number (if not already allocated), email the Competitor Card, mark
     * card_issued_at. Returns a short summary via flash.
     */
    public function competitorCardsGenerate(string $eventId): void
    {
        $this->boot($eventId);
        $this->verifyCsrf();
        $eid = (int)$this->event['id'];

        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids) || !$ids) {
            $this->redirect("/institution/events/{$eventId}/reports/competitor-cards",
                'Select at least one registration to generate.', 'warning');
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));

        $generated = 0; $emailed = 0; $skipped = 0; $errors = 0;
        foreach ($ids as $regId) {
            $reg = EventRegistration::findById($regId);
            if (!$reg || (int)$reg['event_id'] !== $eid
                || ($reg['admin_review_status'] ?? '') !== 'approved') {
                $skipped++;
                continue;
            }
            // Allocate competitor number (idempotent).
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
        if ($generated) $parts[] = $generated . ' card' . ($generated === 1 ? '' : 's') . ' newly issued';
        if ($emailed)   $parts[] = $emailed . ' email' . ($emailed === 1 ? '' : 's') . ' sent';
        if ($skipped)   $parts[] = $skipped . ' skipped';
        if ($errors)    $parts[] = $errors . ' error' . ($errors === 1 ? '' : 's');
        $msg = $parts ? implode(' · ', $parts) : 'Nothing to generate.';
        $this->redirect("/institution/events/{$eventId}/reports/competitor-cards", $msg,
            $errors ? 'warning' : 'success');
    }

    /** Build context + send the competitor-card email. Returns true on success. */
    private function emailCompetitorCard(int $registrationId): bool
    {
        $reg = EventRegistration::findById($registrationId);
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
        try { \Models\Schema::ensureLaneAllocation(); } catch (\Throwable $e) {}
        try { \Models\Schema::ensureTeamEntry(); } catch (\Throwable $e) {}
        $eid = (int)$this->event['id'];

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
                    sc.id              AS category_id,
                    sc.name            AS category_name,
                    es.event_code      AS event_code,
                    se.name            AS sport_event_name
               FROM event_registrations er
               JOIN athletes a                   ON a.id  = er.athlete_id
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
                    'athlete_name'      => $r['athlete_name'],
                    'age'               => $age === '' ? '—' : $age,
                    'gender'            => ucfirst($this->normGender($r['athlete_gender'])),
                    'category_name'     => $cat,
                    'events'            => [],
                    'team_events'       => $teamEvents[(int)$r['athlete_id']] ?? [],
                    'relays'            => $relayMap[$relayKey] ?? [],
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

        $this->renderWith('app', 'institution/reports/unit-competitor-list', [
            'event'     => $this->event,
            'eventHash' => $eventId,
            'units'     => $units,
        ]);
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
        // Team events by athlete — registered athlete_id => [event_code, ...].
        // Computed once and looked up per lane.
        $teamCodesByAthlete = [];
        try {
            $tRows = Event::rowsRaw(
                "SELECT trm.athlete_id, es.event_code
                   FROM team_registration_members trm
                   JOIN team_registrations tr ON tr.id = trm.team_registration_id
              LEFT JOIN event_sports     es ON es.id = tr.event_sport_id
                  WHERE tr.event_id = ?
                    AND tr.admin_review_status = 'approved'",
                [$eid]
            );
            foreach ($tRows as $t) {
                $code = trim((string)($t['event_code'] ?? ''));
                if ($code === '') continue;
                $teamCodesByAthlete[(int)$t['athlete_id']][] = $code;
            }
            foreach ($teamCodesByAthlete as &$codes) {
                $codes = array_values(array_unique($codes));
            }
            unset($codes);
        } catch (\Throwable $e) {
            $teamCodesByAthlete = [];
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
            // category — event_code only (no sport-event label).
            $eventCodes = [];
            if (!empty($r['registration_id']) && !empty($r['lane_category'])) {
                $events = Event::rowsRaw(
                    "SELECT DISTINCT es.event_code
                       FROM event_registration_items eri
                       JOIN event_sports     es ON es.id = eri.event_sport_id
                       JOIN sport_events     se ON se.id = es.sport_event_id
                       JOIN sport_categories sc ON sc.id = se.category_id
                      WHERE eri.registration_id = ? AND sc.name = ?
                      ORDER BY es.event_code",
                    [(int)$r['registration_id'], $r['lane_category']]
                );
                foreach ($events as $ev) {
                    $code = trim((string)($ev['event_code'] ?? ''));
                    if ($code !== '') $eventCodes[] = $code;
                }
            }

            $athleteId  = $athleteByReg[(int)($r['registration_id'] ?? 0)] ?? 0;
            $teamCodes  = $athleteId ? ($teamCodesByAthlete[$athleteId] ?? []) : [];

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
                'event_codes'        => $eventCodes,
                'team_codes'         => $teamCodes,
            ];
        }

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
