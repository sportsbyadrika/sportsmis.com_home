<?php
namespace Controllers;

use Core\{Controller, Auth, Mailer};
use Models\{Institution, Athlete, Event, User, AdminDelete};

class AdminController extends Controller
{
    private function boot(): void
    {
        $this->requireAuth('super_admin');
    }

    public function dashboard(): void
    {
        $this->boot();
        $this->renderWith('app', 'dashboard/admin', [
            'pending_institutions' => Institution::getPendingRegistrations(),
            'pending_athletes'     => Athlete::getPendingRegistrations(),
            'pending_events'       => array_filter(Event::getAllForAdmin(), fn($e) => $e['status'] === 'pending_approval'),
            'flash'                => $this->flash(),
        ]);
    }

    // ── Institutions ─────────────────────────────────────────────────────────

    public function institutions(): void
    {
        $this->boot();
        $tab = $_GET['tab'] ?? 'pending';
        $this->renderWith('app', 'admin/institutions', [
            'tab'                  => $tab,
            'pending_registrations'=> Institution::getPendingRegistrations(),
            'institutions'         => Institution::getAll(),
            'flash'                => $this->flash(),
        ]);
    }

    public function institutionDetail(string $id): void
    {
        $this->boot();
        $reg = Institution::getRegistrationById((int)$id);
        if (!$reg) $this->abort(404);
        $institution = $reg['user_id'] ? Institution::findByUserId($reg['user_id']) : null;
        $this->renderWith('app', 'admin/institution-detail', [
            'reg'         => $reg,
            'institution' => $institution,
            'flash'       => $this->flash(),
        ]);
    }

    public function verifyInstitution(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $reg = Institution::getRegistrationById((int)$id);
        if (!$reg || $reg['status'] !== 'pending') $this->abort(404);

        $password = Auth::generatePassword();
        $userId   = User::create($reg['email'], Auth::hashPassword($password), 'institution_admin');
        Institution::updateRegistrationStatus((int)$id, 'verified', Auth::id(), $userId);

        Institution::createInstitution([
            'user_id'        => $userId,
            'registration_id'=> (int)$id,
            'name'           => $reg['institution_name'],
            'address'        => $reg['address'],
        ]);

        (new Mailer())->sendCredentials($reg['email'], $reg['spoc_name'], $password);
        $this->redirect('/admin/institutions', "Institution verified. Credentials sent to {$reg['email']}.");
    }

    public function approveInstitution(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $institution = Institution::findById((int)$id);
        if (!$institution) $this->abort(404);

        $from = $_POST['validity_from'] ?? date('Y-m-d');
        $to   = $_POST['validity_to']   ?? date('Y-m-d', strtotime('+1 year'));

        Institution::approveInstitution((int)$id, Auth::id(), $from, $to);
        $this->redirect('/admin/institutions', 'Institution approved with validity dates.');
    }

    public function rejectInstitution(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $reg = Institution::getRegistrationById((int)$id);
        if (!$reg) $this->abort(404);
        Institution::updateRegistrationStatus((int)$id, 'rejected', Auth::id());
        $this->redirect('/admin/institutions', 'Institution registration rejected.');
    }

    // ── Athletes ─────────────────────────────────────────────────────────────

    public function athletes(): void
    {
        $this->boot();
        $this->renderWith('app', 'admin/athletes', [
            'pending_registrations' => Athlete::getPendingRegistrations(),
            'athletes'              => Athlete::getAll(),
            'flash'                 => $this->flash(),
        ]);
    }

    public function athleteDetail(string $id): void
    {
        $this->boot();
        $reg = Athlete::getRegistrationById((int)$id);
        if (!$reg) $this->abort(404);
        $this->renderWith('app', 'admin/athlete-detail', ['reg' => $reg, 'flash' => $this->flash()]);
    }

    public function verifyAthlete(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $reg = Athlete::getRegistrationById((int)$id);
        if (!$reg || $reg['status'] !== 'pending') $this->abort(404);

        $password = Auth::generatePassword();
        $userId   = User::create($reg['email'], Auth::hashPassword($password), 'athlete');
        Athlete::updateRegistrationStatus((int)$id, 'verified', Auth::id(), $userId);

        Athlete::create([
            'user_id'        => $userId,
            'registration_id'=> (int)$id,
            'name'           => $reg['name'],
            'mobile'         => $reg['mobile'],
            'gender'         => $reg['gender'],
        ]);

        (new Mailer())->sendCredentials($reg['email'], $reg['name'], $password);
        $this->redirect('/admin/athletes', "Athlete verified. Credentials sent to {$reg['email']}.");
    }

    public function rejectAthlete(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $reg = Athlete::getRegistrationById((int)$id);
        if (!$reg) $this->abort(404);
        Athlete::updateRegistrationStatus((int)$id, 'rejected', Auth::id());
        $this->redirect('/admin/athletes', 'Athlete registration rejected.');
    }

    // ── Events ───────────────────────────────────────────────────────────────

    public function events(): void
    {
        $this->boot();
        $this->renderWith('app', 'admin/events', [
            'events' => Event::getAllForAdmin(),
            'flash'  => $this->flash(),
        ]);
    }

    public function approveEvent(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        Event::setStatus((int)$id, 'active', Auth::id());
        $this->redirect('/admin/events', 'Event marked as Active.');
    }

    public function rejectEvent(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $reason = trim($_POST['reason'] ?? '');
        Event::updateStatus((int)$id, 'suspended', Auth::id(), $reason);
        $this->redirect('/admin/events', 'Event suspended.');
    }

    /** POST /admin/events/{id}/status — super admin sets any status. */
    public function setEventStatus(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['draft', 'active', 'completed', 'suspended'], true)) {
            $this->redirect('/admin/events', 'Invalid status.', 'error');
        }
        Event::setStatus((int)$id, $status, Auth::id());
        $this->redirect('/admin/events', 'Event status updated to ' . ucfirst($status) . '.');
    }

    // ── Cascade Deletes (super admin only) ───────────────────────────────────

    public function deleteEvent(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $event = Event::findById((int)$id);
        $log   = AdminDelete::event((int)$id);
        $this->renderWith('app', 'admin/delete-result', [
            'kind'   => 'Event',
            'target' => $event['name'] ?? ('#' . (int)$id),
            'log'    => $log,
            'back'   => '/admin/events',
        ]);
    }

    public function deleteRegistration(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $reg = \Models\EventRegistration::findById((int)$id);
        $log = AdminDelete::registration((int)$id);
        $back = $reg ? ('/admin/events') : '/admin/events';
        $this->renderWith('app', 'admin/delete-result', [
            'kind'   => 'Registration',
            'target' => $reg ? ('#' . (int)$id . ' on event ' . (int)$reg['event_id']) : ('#' . (int)$id),
            'log'    => $log,
            'back'   => $back,
        ]);
    }

    public function deleteAthlete(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $athlete = Athlete::findById((int)$id);
        $log = AdminDelete::athlete((int)$id);
        $this->renderWith('app', 'admin/delete-result', [
            'kind'   => 'Athlete',
            'target' => $athlete ? ($athlete['name'] . ' (#' . (int)$id . ')') : ('#' . (int)$id),
            'log'    => $log,
            'back'   => '/admin/athletes',
        ]);
    }

    /**
     * GET /admin/registrations — searchable list of every registration in
     * the system so the super admin can pick one to delete one-by-one.
     */
    public function registrations(): void
    {
        $this->boot();
        $q          = trim($_GET['q']              ?? '');
        $eventId    = (int)($_GET['event_id']      ?? 0);
        $instId     = (int)($_GET['institution_id']?? 0);
        $from       = trim($_GET['from']           ?? '');
        $to         = trim($_GET['to']             ?? '');

        $where = []; $params = [];
        if ($q !== '') {
            $where[] = '(a.name LIKE ? OR e.name LIKE ? OR i.name LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        if ($eventId) {
            $where[]  = 'er.event_id = ?';
            $params[] = $eventId;
        }
        if ($instId) {
            $where[]  = 'e.institution_id = ?';
            $params[] = $instId;
        }
        if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $where[]  = 'DATE(er.registered_at) >= ?';
            $params[] = $from;
        }
        if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $where[]  = 'DATE(er.registered_at) <= ?';
            $params[] = $to;
        }

        $sql = "SELECT er.id, er.event_id, er.athlete_id, er.admin_review_status,
                       er.payment_status, er.total_amount, er.registered_at, er.submitted_at,
                       a.name AS athlete_name, e.name AS event_name, i.name AS institution_name
                  FROM event_registrations er
                  JOIN athletes     a ON a.id = er.athlete_id
                  JOIN events       e ON e.id = er.event_id
                  JOIN institutions i ON i.id = e.institution_id"
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . ' ORDER BY er.registered_at DESC LIMIT 500';
        $rows = Event::rowsRaw($sql, $params);

        $eventsList = Event::rowsRaw(
            "SELECT e.id, e.name, i.name AS institution_name
               FROM events e
               JOIN institutions i ON i.id = e.institution_id
              ORDER BY i.name, e.name", []);
        $institutionsList = Event::rowsRaw(
            "SELECT id, name FROM institutions ORDER BY name", []);

        $this->renderWith('app', 'admin/registrations', [
            'rows'            => $rows,
            'q'               => $q,
            'event_id'        => $eventId,
            'institution_id'  => $instId,
            'from'            => $from,
            'to'              => $to,
            'events_list'     => $eventsList,
            'institutions'    => $institutionsList,
        ]);
    }
}
