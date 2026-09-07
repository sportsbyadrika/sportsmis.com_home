<?php
namespace Controllers;

use Core\{Controller, Auth, OrderOfEventsPdf, UnitDateRosterPdf};
use Models\{Schema, Event, EventStaff, OrderOfEvents};
use Services\UnitDateRoster;

/**
 * Order of Events (competition programme) portal for Event Staff holding the
 * 'order_of_events' privilege.
 *
 * Routes (under /event-staff/order-of-events...):
 *   GET  /                schedule editor — every sport-event with slot/status
 *   POST /save            AJAX save one row's serial no / date / time
 *   POST /status          AJAX save one row's call-room status
 *   GET  /print.pdf       printable programme (date-wise or all)
 */
class OrderOfEventsController extends Controller
{
    private array $staff;
    private array $event;

    private function boot(): void
    {
        // Order-of-Events fields live on event_sports; ensureSportHierarchy
        // self-heals them.
        try { Schema::ensureSportHierarchy(); } catch (\Throwable $e) {}
        try { Schema::ensureMeetRecords(); } catch (\Throwable $e) {}
        if (!Auth::eventStaffCheck()) {
            $this->redirect('/event-staff/login', 'Please sign in to continue.', 'warning');
        }
        $session = Auth::eventStaff();
        $s = EventStaff::findById((int)$session['id']);
        if (!$s || $s['status'] !== 'active') {
            Auth::eventStaffLogout();
            $this->redirect('/event-staff/login', 'Your staff account is not active.', 'error');
        }
        $s['privileges'] = EventStaff::privilegesFor((int)$s['id']);
        if (!in_array('order_of_events', $s['privileges'], true)) {
            $this->abort(403);
        }
        $event = Event::findById((int)$s['event_id']);
        if (!$event) $this->abort(404);
        $event['event_code'] = $event['event_code'] ?? \ensureEventCode((int)$event['id']);
        $this->staff = $s;
        $this->event = $event;
    }

    // ── Schedule editor ──────────────────────────────────────────────────────

    public function index(): void
    {
        $this->boot();
        $filter  = trim((string)($_GET['date'] ?? ''));
        $fCat    = trim((string)($_GET['category'] ?? ''));
        $fAge    = trim((string)($_GET['age'] ?? ''));
        $fGender = trim((string)($_GET['gender'] ?? ''));
        $rows    = OrderOfEvents::listForEvent(
            (int)$this->event['id'],
            $filter !== '' ? $filter : null,
            ['category' => $fCat, 'age' => $fAge, 'gender' => $fGender]
        );
        $this->renderWith('staff', 'staff/order-of-events/index', [
            'staff'       => $this->staff,
            'event'       => $this->event,
            'rows'        => $rows,
            'statuses'    => OrderOfEvents::STATUSES,
            'dates'       => OrderOfEvents::distinctDates((int)$this->event['id']),
            'unscheduled' => OrderOfEvents::hasUnscheduled((int)$this->event['id']),
            'facets'      => OrderOfEvents::filterFacets((int)$this->event['id']),
            'records'     => \Models\MeetRecord::mapForEvent((int)$this->event['id']),
            'filter'      => $filter,
            'f_category'  => $fCat,
            'f_age'       => $fAge,
            'f_gender'    => $fGender,
            'flash'       => $this->flash(),
        ]);
    }

    // ── Meet Records (existing records per sport-event) ──────────────────────

    /** GET /event-staff/meet-records — manage the existing meet records. */
    public function meetRecords(): void
    {
        $this->boot();
        $eid = (int)$this->event['id'];
        $rows = OrderOfEvents::listForEvent($eid);
        $events = array_map(fn($r) => [
            'esid'     => (int)$r['id'],
            'label'    => (trim((string)($r['sport_event_name'] ?? '')) ?: (string)($r['event_code'] ?? ''))
                        . ' · ' . trim((string)($r['sport_event_age_category'] ?? ''))
                        . ' · ' . genderLabel((string)($r['sport_event_gender'] ?? ''), $this->event),
            'category' => trim((string)($r['sport_event_category'] ?? '')),
        ], $rows);
        $cats = [];
        foreach ($events as $ev) { if ($ev['category'] !== '') $cats[$ev['category']] = true; }
        ksort($cats);

        $this->renderWith('staff', 'staff/order-of-events/meet-records', [
            'staff'       => $this->staff,
            'event'       => $this->event,
            'events_json' => $events,
            'categories'  => array_keys($cats),
            'records'     => \Models\MeetRecord::listForEvent($eid),
            'flash'       => $this->flash(),
        ]);
    }

    /** POST /event-staff/meet-records/save (AJAX) — upsert one record. */
    public function meetRecordSave(): void
    {
        $this->boot();
        $this->verifyCsrf();
        $eid  = (int)$this->event['id'];
        $esid = (int)($_POST['event_sport_id'] ?? 0);
        $r = Event::rowsRaw("SELECT id FROM event_sports WHERE id = ? AND event_id = ?", [$esid, $eid]);
        if (!$r) $this->json(['success' => false, 'message' => 'Pick a valid event.']);
        $value = mb_substr(trim((string)($_POST['record_value'] ?? '')), 0, 60);
        if ($value === '') $this->json(['success' => false, 'message' => 'Record value is required.']);
        $meet    = mb_substr(trim((string)($_POST['meet_name'] ?? '')), 0, 160) ?: null;
        $year    = mb_substr(trim((string)($_POST['record_year'] ?? '')), 0, 10) ?: null;
        $athlete = mb_substr(trim((string)($_POST['athlete_name'] ?? '')), 0, 160) ?: null;
        \Models\MeetRecord::save($eid, $esid, $value, $meet, $year, $athlete);
        $this->json(['success' => true, 'message' => 'Meet record saved.',
                     'records' => \Models\MeetRecord::listForEvent($eid)]);
    }

    /** POST /event-staff/meet-records/delete (AJAX). */
    public function meetRecordDelete(): void
    {
        $this->boot();
        $this->verifyCsrf();
        $eid = (int)$this->event['id'];
        \Models\MeetRecord::deleteRow((int)($_POST['id'] ?? 0), $eid);
        $this->json(['success' => true, 'message' => 'Record removed.',
                     'records' => \Models\MeetRecord::listForEvent($eid)]);
    }

    // ── AJAX: save serial no / date / time for one row ───────────────────────

    public function save(): void
    {
        $this->boot();
        $this->verifyCsrf();

        $rowId = (int)($_POST['row_id'] ?? 0);
        if ($rowId <= 0) $this->json(['success' => false, 'message' => 'Invalid row.']);

        // Serial number — blank clears it, otherwise a positive integer.
        $slRaw = trim((string)($_POST['sl_no'] ?? ''));
        if ($slRaw !== '' && (!ctype_digit($slRaw) || (int)$slRaw < 1)) {
            $this->json(['success' => false, 'message' => 'Serial number must be a whole number (1 or more).']);
        }
        $slNo = $slRaw === '' ? null : (int)$slRaw;

        // Date — blank clears; otherwise YYYY-MM-DD.
        $dateRaw = trim((string)($_POST['date'] ?? ''));
        $date = null;
        if ($dateRaw !== '') {
            $d = \DateTime::createFromFormat('Y-m-d', $dateRaw);
            if (!$d || $d->format('Y-m-d') !== $dateRaw) {
                $this->json(['success' => false, 'message' => 'Enter a valid date.']);
            }
            $date = $dateRaw;
        }

        // Time — blank clears; otherwise HH:MM (24h). Normalise to HH:MM:00.
        $timeRaw = trim((string)($_POST['time'] ?? ''));
        $time = null;
        if ($timeRaw !== '') {
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $timeRaw)) {
                $this->json(['success' => false, 'message' => 'Enter a valid time (HH:MM, 24-hour).']);
            }
            $time = $timeRaw . ':00';
        }

        OrderOfEvents::updateSchedule((int)$this->event['id'], $rowId, [
            'order_sl_no' => $slNo,
            'order_date'  => $date,
            'order_time'  => $time,
        ]);

        $this->json(['success' => true, 'message' => 'Saved.']);
    }

    // ── AJAX: change call-room status for one row ────────────────────────────

    public function status(): void
    {
        $this->boot();
        $this->verifyCsrf();

        $rowId  = (int)($_POST['row_id'] ?? 0);
        $status = trim((string)($_POST['status'] ?? ''));
        if ($rowId <= 0) $this->json(['success' => false, 'message' => 'Invalid row.']);
        if (!OrderOfEvents::isValidStatus($status)) {
            $this->json(['success' => false, 'message' => 'Unknown status.']);
        }
        OrderOfEvents::updateStatus((int)$this->event['id'], $rowId, $status);
        $this->json([
            'success' => true,
            'message' => 'Status set to ' . OrderOfEvents::statusLabel($status) . '.',
            'label'   => OrderOfEvents::statusLabel($status),
            'status'  => $status,
        ]);
    }

    // ── Printable programme (PDF) ────────────────────────────────────────────

    public function printPdf(): void
    {
        $this->boot();
        $filter  = trim((string)($_GET['date'] ?? ''));
        $fCat    = trim((string)($_GET['category'] ?? ''));
        $fAge    = trim((string)($_GET['age'] ?? ''));
        $fGender = trim((string)($_GET['gender'] ?? ''));
        $rows    = OrderOfEvents::listForEvent(
            (int)$this->event['id'],
            $filter !== '' ? $filter : null,
            ['category' => $fCat, 'age' => $fAge, 'gender' => $fGender]
        );
        OrderOfEventsPdf::stream([
            'event'   => $this->event,
            'rows'    => $rows,
            'filter'  => $filter,
            'counts'  => OrderOfEvents::athleteCounts((int)$this->event['id']),
            'filters' => ['category' => $fCat, 'age' => $fAge, 'gender' => $fGender],
        ]);
    }

    // ── Unit-wise / date-wise athlete roster (PDF) ───────────────────────────

    /**
     * GET /event-staff/order-of-events/unit-roster.pdf?date=YYYY-MM-DD
     * Attendance / reporting sheet for one competition day: every unit's
     * athletes scheduled that day, one unit per page, for the team manager to
     * tick and sign. A valid date is required.
     */
    public function unitRosterPdf(): void
    {
        $this->boot();
        $date = trim((string)($_GET['date'] ?? ''));
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            $this->redirect('/event-staff/order-of-events',
                'Pick a valid date for the unit-wise roster.', 'warning');
        }
        // Embedded photos make Dompdf memory-hungry; raise the ceiling where the
        // host allows it (photos are also downscaled before embedding).
        @ini_set('memory_limit', '512M');
        UnitDateRosterPdf::stream(
            UnitDateRoster::gather((int)$this->event['id'], $date)
        );
    }
}
