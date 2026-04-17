<?php
namespace Controllers;

use Core\{Controller, Auth, Mailer};
use Models\{Institution, Athlete, Event, User};

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
        Event::updateStatus((int)$id, 'approved', Auth::id());
        $this->redirect('/admin/events', 'Event approved.');
    }

    public function rejectEvent(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $reason = trim($_POST['reason'] ?? '');
        Event::updateStatus((int)$id, 'rejected', Auth::id(), $reason);
        $this->redirect('/admin/events', 'Event rejected.');
    }
}
