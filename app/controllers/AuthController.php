<?php
namespace Controllers;

use Core\{Controller, Auth, Mailer};
use Models\{User, Institution, Athlete, Event, Schema, SignupThrottle};

class AuthController extends Controller
{
    // Role → allowed tabs mapping (staff can log in from either side)
    private static array $TAB_ROLES = [
        'athlete'     => ['athlete', 'staff'],
        'institution' => ['institution_admin', 'staff'],
    ];

    private function roleMatchesTab(string $role, string $tab): bool
    {
        if ($role === 'super_admin') return true;
        return in_array($role, self::$TAB_ROLES[$tab] ?? [], true);
    }

    private function tabMismatchMessage(string $role, string $badTab): string
    {
        return match(true) {
            $role === 'athlete'           => 'This account is an Athlete account. Please use the <strong>Athlete</strong> tab.',
            $role === 'institution_admin' => 'This account is an Institution account. Please use the <strong>Institution / Club</strong> tab.',
            $role === 'staff'             => 'Staff account detected. You may use either tab.',
            default                       => 'Your account type does not match the selected login option.',
        };
    }

    public function loginForm(): void
    {
        $this->requireGuest();
        // Don't call $this->flash() here — flashBag() in the auth layout
        // is responsible for surfacing the flash. Reading it twice would
        // consume it before the layout had a chance to render it.
        $activeEvents = [];
        try { $activeEvents = Event::activeForPublic(); } catch (\Throwable $e) {}
        $this->renderWith('auth', 'auth/login', [
            'errors'        => $this->errors(),
            'active_events' => $activeEvents,
        ]);
    }

    public function institutionLoginForm(): void
    {
        $this->requireGuest();
        $this->renderWith('auth', 'auth/login-institution', ['errors' => $this->errors()]);
    }

    public function login(): void
    {
        $this->requireGuest();
        $this->verifyCsrf();

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $tab      = $_POST['role_hint'] ?? 'athlete';

        // The login page is the new 2-card chooser at /login — failures
        // re-open the matching panel via ?panel=… so the user lands
        // back on the form they just submitted.
        $loginPage = $tab === 'institution'
            ? '/login?panel=institution-login'
            : '/login?panel=athlete-login';

        if ($email === '' || $password === '') {
            $_SESSION['flash'] = ['type' => 'error',
                'message' => 'Please enter both email and password to sign in.'];
            $_SESSION['old']   = ['email' => $email];
            $this->redirect($loginPage);
        }

        if (Auth::attempt($email, $password)) {
            $user = Auth::user();
            if ($this->roleMatchesTab($user['role'], $tab)) {
                $this->redirect(Auth::homeUrl());
            }
            Auth::logout();
            $_SESSION['flash'] = ['type' => 'error', 'message' => $this->tabMismatchMessage($user['role'], $tab)];
            $_SESSION['old']   = ['email' => $email];
            $this->redirect($loginPage);
        }

        // Differentiate "user doesn't exist" from "wrong password" only loosely
        // for usability; full disclosure would help account-enumeration attacks.
        $existing = User::findByEmail(strtolower($email));
        $msg = !$existing
            ? "We couldn't find an account for that email. Please check the address or register."
            : 'The password you entered is incorrect. Please try again or use Forgot password to reset it.';
        $_SESSION['flash'] = ['type' => 'error', 'message' => $msg];
        $_SESSION['old']   = ['email' => $email];
        $this->redirect($loginPage);
    }

    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/login', 'You have been logged out.');
    }

    // ── Institution Registration ─────────────────────────────────────────────

    public function registerInstitutionForm(): void
    {
        $this->requireGuest();
        $this->renderWith('auth', 'auth/register-institution', [
            'errors' => $this->errors(),
        ]);
    }

    public function registerInstitution(): void
    {
        $this->requireGuest();
        $this->verifyCsrf();
        $this->guardRegistration('institution', '/login?panel=institution-register');

        $errors = $this->validate([
            'institution_name' => 'required|max:255',
            'spoc_name'        => 'required|max:255',
            'spoc_mobile'      => 'required|mobile',
            'email'            => 'required|email',
            'address'          => 'required',
        ]);

        if (!$errors) {
            $email = strtolower(trim($_POST['email']));
            if (Institution::findRegistrationByEmail($email) || User::findByEmail($email)) {
                $errors['email'][] = 'This email is already registered.';
            }
        }

        if ($errors) {
            $_SESSION['errors'] = $errors;
            // Land back in the chooser with the institution-register panel open.
            $this->redirect('/login?panel=institution-register');
        }

        $email    = strtolower(trim($_POST['email']));
        $name     = trim($_POST['spoc_name']);
        $instName = trim($_POST['institution_name']);
        $password = Auth::generatePassword();

        $regId = Institution::createRegistration([
            'institution_name' => $instName,
            'spoc_name'        => $name,
            'spoc_mobile'      => trim($_POST['spoc_mobile']),
            'email'            => $email,
            'address'          => trim($_POST['address']),
            'status'           => 'verified',
            'verified_at'      => date('Y-m-d H:i:s'),
        ]);

        $userId = User::create($email, Auth::hashPassword($password), 'institution_admin');

        Institution::createInstitution([
            'user_id'         => $userId,
            'registration_id' => $regId,
            'name'            => $instName,
            'address'         => trim($_POST['address']),
        ]);

        (new Mailer())->sendInstitutionCredentials($email, $name, $password);

        $this->redirect('/login', 'Account created! Check your email for your login credentials.');
    }

    // ── Athlete Registration ─────────────────────────────────────────────────

    public function registerAthleteForm(): void
    {
        $this->requireGuest();
        $this->renderWith('auth', 'auth/register-athlete', [
            'errors'      => $this->errors(),
            'google_data' => $_SESSION['google_reg'] ?? null,
        ]);
    }

    public function registerAthlete(): void
    {
        $this->requireGuest();
        $this->verifyCsrf();

        $googleReg = $_SESSION['google_reg'] ?? null;
        // Google sign-ups are already identity-verified via OAuth, so only
        // gate the plain email self-registration path against bots.
        if (!$googleReg) {
            $this->guardRegistration('athlete', '/login?panel=athlete-register');
        }

        $errors = $this->validate([
            'name'   => 'required|max:255',
            'mobile' => 'required|mobile',
            'email'  => 'required|email',
            'gender' => 'required',
        ]);
        $email     = $googleReg ? $googleReg['email'] : strtolower(trim($_POST['email']));

        if (!$errors && !$googleReg) {
            if (Athlete::findRegistrationByEmail($email) || User::findByEmail($email)) {
                $errors['email'][] = 'This email is already registered.';
            }
        }

        if ($errors) {
            $_SESSION['errors'] = $errors;
            // The Google-prefill flow has its own /register/athlete page;
            // everyone else gets bounced back into the chooser with the
            // athlete-register panel open.
            $target = !empty($_SESSION['google_data']) ? '/register/athlete' : '/login?panel=athlete-register';
            $this->redirect($target);
        }
        $name      = trim($_POST['name']);

        $regId = Athlete::createRegistration([
            'name'          => $name,
            'mobile'        => trim($_POST['mobile']),
            'email'         => $email,
            'gender'        => $_POST['gender'],
            'auth_provider' => $googleReg ? 'google' : 'email',
            'google_id'     => $googleReg['google_id'] ?? null,
            'status'        => 'verified',
            'verified_at'   => date('Y-m-d H:i:s'),
        ]);

        if ($googleReg) {
            // Google-verified — no password email, log in directly
            unset($_SESSION['google_reg']);
            $userId = User::create($email, Auth::hashPassword(bin2hex(random_bytes(16))), 'athlete');
            Athlete::create(['user_id' => $userId, 'registration_id' => $regId, 'name' => $name,
                             'mobile' => trim($_POST['mobile']), 'gender' => $_POST['gender']]);
            $newUser = User::findById($userId);
            Auth::login($newUser);
            $this->redirect('/athlete/dashboard', 'Welcome! Please complete your profile.');
        }

        $password = Auth::generatePassword();
        $userId   = User::create($email, Auth::hashPassword($password), 'athlete');
        Athlete::create(['user_id' => $userId, 'registration_id' => $regId, 'name' => $name,
                         'mobile' => trim($_POST['mobile']), 'gender' => $_POST['gender']]);
        (new Mailer())->sendCredentials($email, $name, $password);

        $this->redirect('/login', 'Account created! Check your email for your login credentials.');
    }

    public function pendingVerification(): void
    {
        $this->requireGuest();
        $this->renderWith('auth', 'auth/verify', []);
    }

    // ── Anti-abuse for public self-registration ──────────────────────────────

    /** Max public sign-ups allowed from a single IP in a rolling window. */
    private const SIGNUP_MAX_PER_HOUR = 8;
    private const SIGNUP_MAX_PER_DAY  = 30;

    /** Best-effort client IP. REMOTE_ADDR only — never trust a spoofable
     *  X-Forwarded-For for a security control. */
    private function clientIp(): string
    {
        return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }

    /**
     * Gate a public self-registration request against automated abuse.
     * Layers: (1) hard per-IP rate limit, (2) honeypot field, (3) signed
     * timing trap. Records every attempt for velocity + forensics. On a
     * bot signal we "tarpit" — pretend success without creating anything —
     * so the attacker's tool can't tell it was blocked. On a rate-limit
     * hit or a stale/absent token we bounce back with a visible message.
     * Returns only when the request looks like a genuine human.
     */
    private function guardRegistration(string $action, string $backPanel): void
    {
        try { Schema::ensureSignupThrottle(); } catch (\Throwable $e) {}
        $ip    = $this->clientIp();
        $email = strtolower(trim((string)($_POST['email'] ?? '')));

        // Record the attempt first so velocity counts include this one.
        try { SignupThrottle::record($ip, $action, $email); } catch (\Throwable $e) {}

        // 1) Hard per-IP rate limit — the real stop against mass insertion.
        try {
            $perHour = SignupThrottle::countByIp($ip, 3600);
            $perDay  = SignupThrottle::countByIp($ip, 86400);
        } catch (\Throwable $e) { $perHour = 0; $perDay = 0; }
        if ($perHour > self::SIGNUP_MAX_PER_HOUR || $perDay > self::SIGNUP_MAX_PER_DAY) {
            error_log("[antibot] rate limit hit ip={$ip} action={$action} hour={$perHour} day={$perDay}");
            $_SESSION['errors'] = ['email' => [
                'Too many registration attempts from your network. Please try again later.']];
            $this->redirect($backPanel);
        }

        // 2) + 3) Honeypot / signed timing trap.
        $reason = antibot_reason();
        if ($reason === 'bot') {
            // Definite bot — silently drop and fake success.
            error_log("[antibot] bot signal (honeypot/timing) ip={$ip} action={$action}");
            $this->redirect('/login', 'Account created! Check your email for your login credentials.');
        }
        if ($reason === 'stale' || $reason === 'missing') {
            // Legit but the form was cached / posted without the guard fields.
            $_SESSION['errors'] = ['email' => [
                'Your session expired. Please reload the page and try again.']];
            $this->redirect($backPanel);
        }

        // 4) CAPTCHA (only when configured). Fails closed.
        if (captcha_enabled() && !captcha_verify($ip)) {
            error_log("[antibot] captcha failed ip={$ip} action={$action}");
            $_SESSION['errors'] = ['email' => [
                'CAPTCHA verification failed. Please tick the box and try again.']];
            $this->redirect($backPanel);
        }
    }

    // ── Google OAuth ─────────────────────────────────────────────────────────

    public function googleRedirect(): void
    {
        $cfg = (require CONFIG_ROOT . '/app.php')['google'];
        $_SESSION['google_tab'] = $_GET['tab'] ?? 'athlete'; // remember which tab triggered this
        $params = http_build_query([
            'client_id'     => $cfg['client_id'],
            'redirect_uri'  => $cfg['redirect_uri'],
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => bin2hex(random_bytes(16)),
        ]);
        $_SESSION['oauth_state'] = $params;
        header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
        exit;
    }

    public function googleCallback(): void
    {
        $cfg  = (require CONFIG_ROOT . '/app.php')['google'];
        $code = $_GET['code'] ?? '';
        if (!$code) { $this->redirect('/login', 'Google login failed.', 'error'); }

        // Exchange code for token
        $tokenRes = $this->httpPost('https://oauth2.googleapis.com/token', [
            'code'          => $code,
            'client_id'     => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'redirect_uri'  => $cfg['redirect_uri'],
            'grant_type'    => 'authorization_code',
        ]);
        $token = json_decode($tokenRes, true);
        if (empty($token['access_token'])) { $this->redirect('/login', 'Google auth failed.', 'error'); }

        // Get user info
        $infoRes  = file_get_contents('https://www.googleapis.com/oauth2/v3/userinfo', false,
            stream_context_create(['http' => ['header' => "Authorization: Bearer {$token['access_token']}\r\n"]])
        );
        $info = json_decode($infoRes, true);
        $email = strtolower($info['email'] ?? '');

        $tab = $_SESSION['google_tab'] ?? 'athlete';
        unset($_SESSION['google_tab']);

        $user = User::findByEmail($email);
        if ($user && $user['status'] === 'active') {
            if (!$this->roleMatchesTab($user['role'], $tab)) {
                $loginPage = $tab === 'institution' ? '/institution/login' : '/login';
                $_SESSION['flash'] = ['type' => 'error',
                    'message' => $this->tabMismatchMessage($user['role'], $tab)];
                $this->redirect($loginPage);
            }
            Auth::login($user);
            $this->redirect(Auth::homeUrl());
        }

        // New Google user — send to athlete registration form to fill in missing details
        $_SESSION['google_reg'] = [
            'name'      => $info['name'] ?? '',
            'email'     => $email,
            'google_id' => $info['sub'] ?? '',
        ];
        $_SESSION['flash'] = ['type' => 'info',
            'message' => 'Google account verified! Please complete your registration below.'];
        header('Location: /register/athlete');
        exit;
    }

    // ── Password Reset ───────────────────────────────────────────────────────

    public function forgotForm(): void
    {
        $this->requireGuest();
        // Same as loginForm: flashBag() in the layout reads + clears the
        // session flash; doing it here too would consume it first.
        $this->renderWith('auth', 'auth/forgot-password', []);
    }

    public function forgotPassword(): void
    {
        $this->requireGuest();
        $this->verifyCsrf();
        $email = strtolower(trim($_POST['email'] ?? ''));
        if ($email === '') {
            $this->redirect('/password/forgot', 'Please enter the email address linked to your account.', 'error');
        }
        $user = User::findByEmail($email);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            User::storeResetToken($email, $token);
            $resetUrl = (require CONFIG_ROOT . '/app.php')['url'] . '/password/reset/' . $token;

            // Pull the real name from athletes / institutions so the email
            // doesn't open with "Hello user@example.com,".
            $name = $email;
            if ($user['role'] === 'athlete') {
                $a = \Models\Athlete::findByUserId((int)$user['id']);
                if (!empty($a['name'])) $name = $a['name'];
            } elseif ($user['role'] === 'institution_admin') {
                $i = Institution::findByUserId((int)$user['id']);
                if (!empty($i['name'])) $name = $i['name'];
            }

            try {
                $sent = (new Mailer())->sendPasswordReset($email, $name, $resetUrl);
                if (!$sent) error_log('[forgotPassword] mail() returned false for ' . $email);
            } catch (\Throwable $e) {
                error_log('[forgotPassword] ' . $e->getMessage());
            }
        }
        // Same response whether or not the email exists — do not leak account existence.
        $this->redirect('/password/forgot',
            'If an account exists for that email, a reset link has been sent. Please check your inbox (and spam folder).');
    }

    public function resetForm(string $token): void
    {
        $this->requireGuest();
        $rec = User::findResetToken($token);
        if (!$rec) { $this->redirect('/login', 'Invalid or expired reset link.', 'error'); }
        $this->renderWith('auth', 'auth/reset-password', ['token' => $token]);
    }

    public function resetPassword(): void
    {
        $this->requireGuest();
        $this->verifyCsrf();
        $token    = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirmation'] ?? '';

        $rec = User::findResetToken($token);
        if (!$rec) { $this->redirect('/login', 'Invalid or expired reset link.', 'error'); }
        if (strlen($password) < 8) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Password must be at least 8 characters.'];
            $this->redirect("/password/reset/{$token}");
        }
        if ($password !== $confirm) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Passwords do not match.'];
            $this->redirect("/password/reset/{$token}");
        }

        $user = User::findByEmail($rec['email']);
        User::updatePassword($user['id'], Auth::hashPassword($password));
        User::deleteResetToken($rec['email']);
        $this->redirect('/login', 'Password reset successful. Please log in.');
    }

    /**
     * POST /account/password — logged-in user updates their own password
     * from the layout modal. Verifies the current password first so a
     * stolen session can't silently rotate credentials.
     */
    public function changePassword(): void
    {
        if (!Auth::check()) {
            $this->redirect('/login', 'Please sign in.', 'error');
        }
        $this->verifyCsrf();

        $current = (string)($_POST['current_password']      ?? '');
        $new     = (string)($_POST['password']              ?? '');
        $confirm = (string)($_POST['password_confirmation'] ?? '');
        $home    = Auth::homeUrl();

        if ($current === '' || $new === '' || $confirm === '') {
            $this->redirect($home, 'All three password fields are required.', 'error');
        }
        if (strlen($new) < 8) {
            $this->redirect($home, 'New password must be at least 8 characters.', 'error');
        }
        if ($new !== $confirm) {
            $this->redirect($home, 'New password and confirmation do not match.', 'error');
        }

        $user = User::findById((int)Auth::id());
        if (!$user || !password_verify($current, $user['password'])) {
            $this->redirect($home, 'Current password is incorrect.', 'error');
        }
        if (password_verify($new, $user['password'])) {
            $this->redirect($home, 'New password must be different from the current password.', 'error');
        }

        User::updatePassword((int)$user['id'], Auth::hashPassword($new));
        $this->redirect($home, 'Password updated successfully.');
    }

    private function httpPost(string $url, array $data): string
    {
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($data),
        ]]);
        return file_get_contents($url, false, $ctx) ?: '';
    }
}
