<?php
namespace Controllers;

use Core\{Controller, Auth};
use Models\{AccessRequest, Athlete, Institution, User, UserCapability, Schema};

/**
 * Account-scoped actions for the unified user account — capability requests
 * ("one account, many hats"). The signed-in user asks for a capability; an
 * admin approves it elsewhere.
 */
class AccountController extends Controller
{
    /** POST /account/request-organiser — ask for organiser (event-admin) access. */
    public function requestOrganiser(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        try { Schema::ensureAccessRequests(); } catch (\Throwable $e) {}

        $user = Auth::user();
        $home = Auth::homeUrl();

        $uid = (int)Auth::id();

        // Organiser access requires a completed athlete profile first.
        $athlete = Athlete::findByUserId($uid);
        if (empty($athlete['profile_completed'])) {
            $this->redirect($home,
                'Please complete your athlete profile before requesting organiser access.', 'error');
        }

        if (AccessRequest::hasPending($uid, 'organiser')) {
            $this->redirect($home, 'You already have an organiser request awaiting review.', 'info');
        }

        $orgName = trim((string)($_POST['org_name'] ?? ''));
        $sport   = trim((string)($_POST['sport'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));
        if ($orgName === '') {
            $this->redirect($home, 'Please enter the name of the organisation / event you want to run.', 'error');
        }

        AccessRequest::create([
            'user_id'  => $uid,
            'email'    => (string)($user['email'] ?? ''),
            'type'     => 'organiser',
            'org_name' => mb_substr($orgName, 0, 255),
            'sport'    => $sport !== '' ? mb_substr($sport, 0, 100) : null,
            'message'  => $message !== '' ? mb_substr($message, 0, 2000) : null,
        ]);

        $this->redirect($home,
            'Request submitted. Our team will review it and get back to you by email.');
    }

    /**
     * POST /account/create-institution — self-serve institution profile.
     * Once the athlete profile is complete, this auto-provisions an institution
     * for the account and auto-approves organiser access (no admin step). The
     * user can then flesh out the institution details in the organiser workspace.
     */
    public function createInstitution(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        try { Schema::ensureUserCapabilities(); } catch (\Throwable $e) {}

        $user = Auth::user();
        $uid  = (int)Auth::id();
        $home = Auth::homeUrl();

        // Institution profile requires a completed athlete profile first.
        $athlete = Athlete::findByUserId($uid);
        if (empty($athlete['profile_completed'])) {
            $this->redirect($home,
                'Please complete your profile before creating an institution profile.', 'error');
        }

        // Already provisioned → make sure organiser access is live and open it.
        if (Institution::findByUserId($uid)) {
            try { UserCapability::grant($uid, 'organiser'); } catch (\Throwable $e) {}
            Auth::refreshCapabilities();
            $this->redirect('/institution/dashboard', 'Your institution profile is ready.');
        }

        // Institution details from the form; SPOC details from the athlete profile.
        $email   = strtolower((string)($user['email'] ?? ''));
        $orgName = trim((string)($_POST['org_name'] ?? ''));
        if ($orgName === '') {
            $this->redirect($home, 'Please enter your institution / club name.', 'error');
        }
        $orgName = mb_substr($orgName, 0, 255);
        $typeId  = (int)($_POST['type_id'] ?? 0) ?: null;
        $address = mb_substr(trim((string)($_POST['address'] ?? '')), 0, 500);
        $spocName   = (string)($athlete['name'] ?? $email);
        $spocMobile = (string)($athlete['mobile'] ?? '');

        try {
            Institution::ensureSchema();
            // Reuse an existing verified registration for this email if present
            // (email is UNIQUE on institution_registrations).
            $reg   = Institution::findRegistrationByEmail($email);
            $regId = $reg['id'] ?? Institution::createRegistration([
                'institution_name' => $orgName,
                'spoc_name'        => $spocName,
                'spoc_mobile'      => $spocMobile,
                'email'            => $email,
                'address'          => $address,
                'status'           => 'verified',
                'verified_at'      => date('Y-m-d H:i:s'),
            ]);
            Institution::createInstitution([
                'user_id'         => $uid,
                'registration_id' => (int)$regId,
                'name'            => $orgName,
                'type_id'         => $typeId,
                'address'         => $address,
                'spoc_name'       => $spocName,
                'spoc_mobile'     => $spocMobile,
                'spoc_email'      => $email,
            ]);
        } catch (\Throwable $e) {
            error_log('[account/createInstitution] ' . $e->getMessage());
            $this->redirect($home,
                'We could not create your institution profile just now. Please try again.', 'error');
        }

        // Auto-approve: grant organiser capability immediately.
        try { UserCapability::grant($uid, 'organiser'); } catch (\Throwable $e) {}

        // Best-effort audit trail: log a self-approved organiser access request.
        try {
            Schema::ensureAccessRequests();
            if (!AccessRequest::hasPending($uid, 'organiser')) {
                $reqId = AccessRequest::create([
                    'user_id'  => $uid,
                    'email'    => $email,
                    'type'     => 'organiser',
                    'org_name' => mb_substr($orgName, 0, 255),
                ]);
                AccessRequest::decide((int)$reqId, 'approved', null,
                    'Auto-approved (self-serve institution profile).');
            }
        } catch (\Throwable $e) { /* audit only */ }

        Auth::refreshCapabilities();
        $this->redirect('/institution/dashboard',
            'Institution profile created! Complete your institution details to start organising events.');
    }
}
