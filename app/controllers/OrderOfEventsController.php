<?php
namespace Controllers;

use Core\{Controller, Auth, OrderOfEventsPdf};
use Models\{Schema, Event, EventStaff, OrderOfEvents};

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
        $filter = trim((string)($_GET['date'] ?? ''));
        $rows   = OrderOfEvents::listForEvent((int)$this->event['id'], $filter !== '' ? $filter : null);
        $this->renderWith('staff', 'staff/order-of-events/index', [
            'staff'       => $this->staff,
            'event'       => $this->event,
            'rows'        => $rows,
            'statuses'    => OrderOfEvents::STATUSES,
            'dates'       => OrderOfEvents::distinctDates((int)$this->event['id']),
            'unscheduled' => OrderOfEvents::hasUnscheduled((int)$this->event['id']),
            'filter'      => $filter,
            'flash'       => $this->flash(),
        ]);
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
        $filter = trim((string)($_GET['date'] ?? ''));
        $rows   = OrderOfEvents::listForEvent((int)$this->event['id'], $filter !== '' ? $filter : null);
        OrderOfEventsPdf::stream([
            'event'  => $this->event,
            'rows'   => $rows,
            'filter' => $filter,
        ]);
    }
}
