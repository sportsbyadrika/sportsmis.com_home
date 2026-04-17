<?php
namespace Controllers;

use Core\{Controller, Auth, Mailer};
use Models\{User, Institution, Athlete};

class AuthController extends Controller
{
    public function loginForm(): void
    {
        $this->requireGuest();
        $this->renderWith('auth', 'auth/login', ['flash' => $this->flash(), 'errors' => $this->errors()]);
    }

    public function login(): void
    {
        $this->requireGuest();
        $this->verifyCsrf();

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (Auth::attempt($email, $password)) {
            $this->redirect(Auth::homeUrl());
        }
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid email or password.'];
        $_SESSION['old']   = ['email' => $email];
        $this->redirect('/login');
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
            'flash'  => $this->flash(),
            'errors' => $this->errors(),
        ]);
    }

    public function registerInstitution(): void
    {
        $this->requireGuest();
        $this->verifyCsrf();

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
            $this->redirect('/register/institution');
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

        (new Mailer())->sendCredentials($email, $name, $password);

        $this->redirect('/login', 'Account created! Check your email for your login credentials.');
    }

    // ── Athlete Registration ─────────────────────────────────────────────────

    public function registerAthleteForm(): void
    {
        $this->requireGuest();
        $this->renderWith('auth', 'auth/register-athlete', [
            'flash'  => $this->flash(),
            'errors' => $this->errors(),
        ]);
    }

    public function registerAthlete(): void
    {
        $this->requireGuest();
        $this->verifyCsrf();

        $errors = $this->validate([
            'name'   => 'required|max:255',
            'mobile' => 'required|mobile',
            'email'  => 'required|email',
            'gender' => 'required',
        ]);

        if (!$errors) {
            $email = strtolower(trim($_POST['email']));
            if (Athlete::findRegistrationByEmail($email) || User::findByEmail($email)) {
                $errors['email'][] = 'This email is already registered.';
            }
        }

        if ($errors) {
            $_SESSION['errors'] = $errors;
            $this->redirect('/register/athlete');
        }

        $email    = strtolower(trim($_POST['email']));
        $name     = trim($_POST['name']);
        $password = Auth::generatePassword();

        $regId = Athlete::createRegistration([
            'name'        => $name,
            'mobile'      => trim($_POST['mobile']),
            'email'       => $email,
            'gender'      => $_POST['gender'],
            'status'      => 'verified',
            'verified_at' => date('Y-m-d H:i:s'),
        ]);

        $userId = User::create($email, Auth::hashPassword($password), 'athlete');

        Athlete::create([
            'user_id'         => $userId,
            'registration_id' => $regId,
            'name'            => $name,
            'mobile'          => trim($_POST['mobile']),
            'gender'          => $_POST['gender'],
        ]);

        (new Mailer())->sendCredentials($email, $name, $password);

        $this->redirect('/login', 'Account created! Check your email for your login credentials.');
    }

    public function pendingVerification(): void
    {
        $this->requireGuest();
        $this->renderWith('auth', 'auth/verify', ['flash' => $this->flash()]);
    }

    // ── Google OAuth ─────────────────────────────────────────────────────────

    public function googleRedirect(): void
    {
        $cfg = (require CONFIG_ROOT . '/app.php')['google'];
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

        $user = User::findByEmail($email);
        if ($user && $user['status'] === 'active') {
            Auth::login($user);
            $this->redirect(Auth::homeUrl());
        }

        // New Google user — create account and log in directly
        $googleName = $info['name'] ?? $email;

        $regId = Athlete::createRegistration([
            'name'          => $googleName,
            'mobile'        => '',
            'email'         => $email,
            'gender'        => 'other',
            'auth_provider' => 'google',
            'google_id'     => $info['sub'] ?? '',
            'status'        => 'verified',
            'verified_at'   => date('Y-m-d H:i:s'),
        ]);

        $userId = User::create($email, Auth::hashPassword(bin2hex(random_bytes(16))), 'athlete');

        Athlete::create([
            'user_id'         => $userId,
            'registration_id' => $regId,
            'name'            => $googleName,
            'gender'          => 'other',
        ]);

        $newUser = User::findById($userId);
        Auth::login($newUser);
        $this->redirect('/athlete/dashboard');
    }

    // ── Password Reset ───────────────────────────────────────────────────────

    public function forgotForm(): void
    {
        $this->requireGuest();
        $this->renderWith('auth', 'auth/forgot-password', ['flash' => $this->flash()]);
    }

    public function forgotPassword(): void
    {
        $this->requireGuest();
        $this->verifyCsrf();
        $email = strtolower(trim($_POST['email'] ?? ''));
        $user  = User::findByEmail($email);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            User::storeResetToken($email, $token);
            $resetUrl = (require CONFIG_ROOT . '/app.php')['url'] . '/password/reset/' . $token;
            (new Mailer())->sendPasswordReset($email, $user['email'], $resetUrl);
        }
        $this->redirect('/password/forgot', 'If that email exists, a reset link has been sent.');
    }

    public function resetForm(string $token): void
    {
        $this->requireGuest();
        $rec = User::findResetToken($token);
        if (!$rec) { $this->redirect('/login', 'Invalid or expired reset link.', 'error'); }
        $this->renderWith('auth', 'auth/reset-password', ['token' => $token, 'flash' => $this->flash()]);
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
