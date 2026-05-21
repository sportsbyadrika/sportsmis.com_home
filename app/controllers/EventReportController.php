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

        $unitFilter = (int)($_GET['unit_id']    ?? 0);
        $compFilter = (string)($_GET['comp']    ?? '');   // '', 'yes', 'no'
        $nocFilter  = (string)($_GET['noc']     ?? '');   // '', 'pending', 'accepted', 'rejected'
        $cardFilter = (string)($_GET['card']    ?? '');   // '', 'issued', 'allocated', 'pending'

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
                $units[$unitKey]['rows'][] = [
                    'competitor_number' => $r['competitor_number'],
                    'athlete_name'      => $r['athlete_name'],
                    'age'               => $age === '' ? '—' : $age,
                    'gender'            => ucfirst($this->normGender($r['athlete_gender'])),
                    'category_name'     => $cat,
                    'events'            => [],
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
                    erl.lane_id,
                    erl.category,
                    l.lane_number,
                    l.lane_type,
                    eu.name            AS unit_name,
                    eu.address         AS unit_address,
                    eu.logo            AS unit_logo,
                    er.id              AS registration_id,
                    er.competitor_number,
                    a.name             AS athlete_name
               FROM event_relays r
               JOIN event_shooting_range_distances d   ON d.id  = r.shooting_range_distance_id
               JOIN event_shooting_ranges          sr  ON sr.id = d.shooting_range_id
               JOIN event_relay_lanes              erl ON erl.relay_id = r.id
               JOIN event_shooting_range_lanes     l   ON l.id  = erl.lane_id
          LEFT JOIN event_units                    eu  ON eu.id = erl.assigned_unit_id
          LEFT JOIN event_registrations            er  ON er.id = erl.assigned_registration_id
          LEFT JOIN athletes                       a   ON a.id  = er.athlete_id
              WHERE r.event_id = ?
              ORDER BY COALESCE(r.order_no, 999999), r.id, l.lane_number",
            [$eid]
        );

        // Group rows by relay; for each lane fetch the athlete's events
        // registered IN THAT lane's category.
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

            $events = [];
            if (!empty($r['registration_id']) && !empty($r['category'])) {
                $events = Event::rowsRaw(
                    "SELECT DISTINCT es.event_code, se.name AS sport_event_name
                       FROM event_registration_items eri
                       JOIN event_sports     es ON es.id = eri.event_sport_id
                       JOIN sport_events     se ON se.id = es.sport_event_id
                       JOIN sport_categories sc ON sc.id = se.category_id
                      WHERE eri.registration_id = ? AND sc.name = ?
                      ORDER BY es.event_code, se.name",
                    [(int)$r['registration_id'], $r['category']]
                );
            }
            $eventLabels = [];
            foreach ($events as $ev) {
                $code = trim((string)($ev['event_code'] ?? ''));
                $name = trim((string)($ev['sport_event_name'] ?? ''));
                $lbl  = $code !== '' && $name !== '' ? $code . ' · ' . $name
                      : ($code !== '' ? $code : $name);
                if ($lbl !== '') $eventLabels[] = $lbl;
            }

            $relays[$rid]['lanes'][] = [
                'lane_number'       => $r['lane_number'],
                'lane_type'         => $r['lane_type'],
                'category'          => $r['category'],
                'unit_name'         => $r['unit_name'],
                'unit_address'      => $r['unit_address'],
                'unit_logo'         => $r['unit_logo'],
                'competitor_number' => $r['competitor_number'],
                'athlete_name'      => $r['athlete_name'],
                'events'            => $eventLabels,
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
