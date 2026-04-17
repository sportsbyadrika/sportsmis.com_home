<?php
namespace Controllers;

use Core\{Controller, Auth, FileUpload};
use Models\{Institution, Staff, Event};

class InstitutionController extends Controller
{
    private array $institution;

    private function boot(): void
    {
        $this->requireAuth('institution_admin');
        $inst = Institution::findByUserId(Auth::id());
        if (!$inst) $this->redirect('/login', 'Institution not found.', 'error');
        $this->institution = $inst;
    }

    public function dashboard(): void
    {
        $this->boot();
        $events = Event::getByInstitution($this->institution['id']);
        $this->renderWith('app', 'dashboard/institution', [
            'institution' => $this->institution,
            'events'      => $events,
            'flash'       => $this->flash(),
        ]);
    }

    public function profileForm(): void
    {
        $this->boot();
        $this->renderWith('app', 'institution/profile', [
            'institution'       => $this->institution,
            'institution_types' => Institution::getTypes(),
            'flash'             => $this->flash(),
            'errors'            => $this->errors(),
        ]);
    }

    public function updateProfile(): void
    {
        $this->boot();
        $this->verifyCsrf();

        $errors = $this->validate([
            'name'       => 'required|max:255',
            'reg_number' => 'required|max:100',
            'address'    => 'required',
        ]);

        $data = [
            'name'       => trim($_POST['name']),
            'type_id'    => (int)($_POST['type_id'] ?? 0) ?: null,
            'reg_number' => trim($_POST['reg_number']),
            'address'    => trim($_POST['address']),
        ];

        if (!empty($_FILES['logo']['name'])) {
            try {
                $uploader = new FileUpload();
                $data['logo'] = $uploader->upload($_FILES['logo'], 'institutions', true);
            } catch (\RuntimeException $e) {
                $errors['logo'][] = $e->getMessage();
            }
        }

        if (!empty($_FILES['reg_document']['name'])) {
            try {
                $uploader = new FileUpload();
                $data['reg_document'] = $uploader->upload($_FILES['reg_document'], 'institutions');
            } catch (\RuntimeException $e) {
                $errors['reg_document'][] = $e->getMessage();
            }
        }

        if ($errors) {
            $_SESSION['errors'] = $errors;
            $this->redirect('/institution/profile');
        }

        $data['profile_completed'] = 1;
        Institution::updateProfile($this->institution['id'], $data);
        $this->redirect('/institution/dashboard', 'Profile updated successfully!');
    }

    // ── Staff Management ─────────────────────────────────────────────────────

    public function staffIndex(): void
    {
        $this->boot();
        $this->renderWith('app', 'institution/staff/index', [
            'institution' => $this->institution,
            'staff_list'  => Staff::getByInstitution($this->institution['id']),
            'flash'       => $this->flash(),
        ]);
    }

    public function staffCreateForm(): void
    {
        $this->boot();
        $this->renderWith('app', 'institution/staff/create', [
            'institution' => $this->institution,
            'roles'       => Staff::getAllRoles(),
            'flash'       => $this->flash(),
            'errors'      => $this->errors(),
        ]);
    }

    public function staffCreate(): void
    {
        $this->boot();
        $this->verifyCsrf();

        $errors = $this->validate([
            'name'   => 'required|max:255',
            'email'  => 'required|email',
            'mobile' => 'required|mobile',
        ]);

        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!$errors && \Models\User::findByEmail($email)) {
            $errors['email'][] = 'Email already registered.';
        }

        if ($errors) {
            $_SESSION['errors'] = $errors;
            $this->redirect('/institution/staff/create');
        }

        $password = \Core\Auth::generatePassword();
        $userId   = \Models\User::create($email, \Core\Auth::hashPassword($password), 'staff');

        $staffId = Staff::create([
            'institution_id' => $this->institution['id'],
            'user_id'        => $userId,
            'name'           => trim($_POST['name']),
            'mobile'         => trim($_POST['mobile']),
        ], array_map('intval', $_POST['roles'] ?? []));

        (new \Core\Mailer())->sendCredentials($email, trim($_POST['name']), $password);

        $this->redirect('/institution/staff', 'Staff member added and login credentials sent.');
    }

    public function staffEditForm(string $id): void
    {
        $this->boot();
        $staff = Staff::findById((int)$id);
        if (!$staff || $staff['institution_id'] != $this->institution['id']) $this->abort(404);

        $this->renderWith('app', 'institution/staff/edit', [
            'institution' => $this->institution,
            'staff'       => $staff,
            'roles'       => Staff::getAllRoles(),
            'assigned'    => Staff::getRoleIds((int)$id),
            'flash'       => $this->flash(),
            'errors'      => $this->errors(),
        ]);
    }

    public function staffUpdate(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $staff = Staff::findById((int)$id);
        if (!$staff || $staff['institution_id'] != $this->institution['id']) $this->abort(404);

        Staff::updateStaff((int)$id, [
            'name'   => trim($_POST['name']),
            'mobile' => trim($_POST['mobile']),
            'status' => $_POST['status'] ?? 'active',
        ], array_map('intval', $_POST['roles'] ?? []));

        $this->redirect('/institution/staff', 'Staff member updated.');
    }
}
