<?php
namespace Controllers;

use Core\{Controller, Auth};
use Models\{Institution, Event, EventRegistration};

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
                    eu.name            AS unit_name,
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
        $byEvent     = []; // category_name => [ ['sl'=>i,'event_name'=>..,...], ... ]
        $eventTotals = []; // event_sport_id => [...]
        $byUnit      = []; // unit_name => [...gender counts]
        $byUnitEvent = []; // unit_name => [ event_sport_id => [...counts] ]

        foreach ($rows as $r) {
            $cat = $r['category_name'] ?: '— Uncategorised —';
            $unit = $r['unit_name'] ?: ($r['unit_name_other'] ?: '— Unspecified —');

            $byCategory[$cat] = $byCategory[$cat] ?? ['male'=>0,'female'=>0,'mixed'=>0,'other'=>0,'total'=>0];
            $byUnit[$unit]    = $byUnit[$unit]    ?? ['male'=>0,'female'=>0,'mixed'=>0,'other'=>0,'total'=>0];

            $g = $this->normGender($r['athlete_gender']);
            $byCategory[$cat][$g]      = ($byCategory[$cat][$g] ?? 0) + 1;
            $byCategory[$cat]['total'] = ($byCategory[$cat]['total'] ?? 0) + 1;
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

        $where  = ['er.event_id = ?'];
        $params = [$eid];
        if ($from !== '') { $where[] = 'p.transaction_date >= ?'; $params[] = $from; }
        if ($to   !== '') { $where[] = 'p.transaction_date <= ?'; $params[] = $to;   }
        if (in_array($status, ['pending','approved','rejected'], true)) {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }
        if (in_array($mode, ['manual','epayment'], true)) {
            $where[] = 'p.payment_method = ?';
            $params[] = $mode;
        }

        $sql = "SELECT a.name           AS athlete_name,
                       a.mobile         AS athlete_mobile,
                       eu.name          AS unit_name,
                       er.unit_name_other,
                       p.payment_method,
                       p.transaction_date,
                       p.transaction_number,
                       p.amount,
                       p.status,
                       p.razorpay_payment_id,
                       p.razorpay_order_id
                  FROM event_registration_payments p
                  JOIN event_registrations er ON er.id = p.registration_id
                  JOIN athletes      a       ON a.id  = er.athlete_id
             LEFT JOIN event_units   eu      ON eu.id = er.unit_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY p.transaction_date DESC, p.id DESC";

        $rows = Event::rowsRaw($sql, $params);

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
}
