<?php
namespace Controllers;

use Core\{Controller, Auth};
use Models\{AccessRequest, Athlete, Institution, User, UserCapability, Schema, Event};

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

    /**
     * POST /account/events/{eventHash}/join-as-unit
     * Unified "Register to Join" for the dashboard eligible-events card. Works
     * for any signed-in user: if the account has no institution profile yet,
     * one is auto-provisioned from the modal's basic details (name, type,
     * address) and organiser access is granted; then the participation request
     * for the event is created / refreshed. If the account already owns an
     * institution, its request is submitted directly (no modal needed).
     */
    public function joinAsUnit(string $eventHash): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        try { Schema::ensureUserCapabilities(); } catch (\Throwable $e) {}
        try { Schema::ensureInstitutionAsUnit(); } catch (\Throwable $e) {}

        $uid  = (int)Auth::id();
        $home = Auth::homeUrl();
        $eid  = (int)\hid_event_decode($eventHash);
        $event = Event::findById($eid);
        if (!$event || empty($event['allow_institution_join_request'])) {
            $this->redirect($home, 'That event is not currently accepting participation requests.', 'warning');
        }

        // Ensure the account has an institution profile; create one from the
        // modal's basic details when it doesn't.
        $institution = Institution::findByUserId($uid);
        if (!$institution) {
            $orgName = trim((string)($_POST['org_name'] ?? ''));
            if ($orgName === '') {
                $this->redirect($home, 'Please provide your institution / unit name to join.', 'error');
            }
            $orgName = mb_substr($orgName, 0, 255);
            $user    = Auth::user();
            $email   = strtolower((string)($user['email'] ?? ''));
            $athlete = Athlete::findByUserId($uid);
            $spocName   = (string)($athlete['name'] ?? ($user['name'] ?? $email));
            $spocMobile = (string)($athlete['mobile'] ?? '');
            $typeId  = (int)($_POST['type_id'] ?? 0) ?: null;
            $address = mb_substr(trim((string)($_POST['org_address'] ?? '')), 0, 500);
            try {
                Institution::ensureSchema();
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
                $newId = Institution::createInstitution([
                    'user_id'         => $uid,
                    'registration_id' => (int)$regId,
                    'name'            => $orgName,
                    'type_id'         => $typeId,
                    'address'         => $address,
                    'spoc_name'       => $spocName,
                    'spoc_mobile'     => $spocMobile,
                    'spoc_email'      => $email,
                ]);
                UserCapability::grant($uid, 'organiser');
                try {
                    Schema::ensureAccessRequests();
                    if (!AccessRequest::hasPending($uid, 'organiser')) {
                        $reqId = AccessRequest::create([
                            'user_id'  => $uid,
                            'email'    => $email,
                            'type'     => 'organiser',
                            'org_name' => $orgName,
                        ]);
                        AccessRequest::decide((int)$reqId, 'approved', null,
                            'Auto-approved (self-serve join as unit).');
                    }
                } catch (\Throwable $e) { /* audit only */ }
                Auth::refreshCapabilities();
                $institution = Institution::findById((int)$newId);
            } catch (\Throwable $e) {
                error_log('[account/joinAsUnit:createInstitution] ' . $e->getMessage());
                $this->redirect($home,
                    'We could not create your institution profile just now. Please try again.', 'error');
            }
        }

        if (!$institution) {
            $this->redirect($home, 'We could not find your institution profile. Please try again.', 'error');
        }
        if ((int)$event['institution_id'] === (int)$institution['id']) {
            $this->redirect($home, 'You own this event — no need to request participation.', 'warning');
        }

        // Create or refresh this institution's participation request.
        $instId   = (int)$institution['id'];
        $unitName  = trim((string)($_POST['proposed_unit_name'] ?? '')) ?: (string)($institution['name'] ?? 'Institution');
        $unitAddr  = trim((string)($_POST['org_address'] ?? '')) ?: (string)($institution['address'] ?? '');
        $existing = Event::rowsRaw(
            "SELECT id, status FROM event_participation_requests
              WHERE event_id = ? AND institution_id = ? LIMIT 1",
            [$eid, $instId]
        )[0] ?? null;

        if ($existing && $existing['status'] === 'approved') {
            $this->redirect($home, 'You already have an approved participation on this event.', 'warning');
        }
        if ($existing) {
            Event::rowsRaw(
                "UPDATE event_participation_requests
                    SET proposed_unit_name = ?, proposed_unit_address = ?,
                        status = 'pending', reviewed_at = NULL,
                        reviewed_by_user_id = NULL, reviewer_notes = NULL
                  WHERE id = ?",
                [mb_substr($unitName, 0, 255), $unitAddr ?: null, (int)$existing['id']]
            );
        } else {
            Event::rowsRaw(
                "INSERT INTO event_participation_requests
                    (event_id, institution_id, proposed_unit_name,
                     proposed_unit_address, request_notes, status)
                 VALUES (?, ?, ?, ?, NULL, 'pending')",
                [$eid, $instId, mb_substr($unitName, 0, 255), $unitAddr ?: null]
            );
        }

        // Auto-approve when the event allows joining WITHOUT approval: create
        // the unit immediately so the institution can log straight in.
        if (!empty($event['institution_join_auto_approve'])) {
            $req = Event::rowsRaw(
                "SELECT id, institution_id, proposed_unit_name, proposed_unit_address, linked_unit_id
                   FROM event_participation_requests
                  WHERE event_id = ? AND institution_id = ? LIMIT 1",
                [$eid, $instId]
            )[0] ?? null;
            if ($req && empty($req['linked_unit_id'])) {
                try {
                    \Models\EventUnit::approveFromRequest($eid, $req, null,
                        'Auto-approved (event allows joining without approval).');
                } catch (\Throwable $e) { error_log('[account/joinAsUnit autoApprove] ' . $e->getMessage()); }
            }
            $this->redirect($home, 'You\'ve joined this event as a unit — open it from your dashboard to continue.');
        }

        // Notify the event owner (best effort).
        try {
            $owner = Institution::contact((int)$event['institution_id']);
            \Services\Messaging::dispatch('participation_request_received', [
                'name_of_user'  => (string)($institution['name'] ?? $unitName),
                'request_type'  => 'Participation',
                'name_of_event' => (string)($event['name'] ?? ''),
            ], $owner);
        } catch (\Throwable $e) { error_log('[account/joinAsUnit notify] ' . $e->getMessage()); }

        $this->redirect($home, 'Participation request sent. The organiser will review it shortly.');
    }
}
