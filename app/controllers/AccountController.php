<?php
namespace Controllers;

use Core\{Controller, Auth};
use Models\{AccessRequest, Athlete, Schema};

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
}
