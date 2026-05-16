<?php
namespace Core;

class Auth
{
    public static function check(): bool
    {
        return !empty($_SESSION['user']);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        return $_SESSION['user']['id'] ?? null;
    }

    public static function role(): ?string
    {
        return $_SESSION['user']['role'] ?? null;
    }

    public static function is(string ...$roles): bool
    {
        return in_array(static::role(), $roles, true);
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        \Models\User::updateLastLogin($user['id']);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function homeUrl(): string
    {
        return match (static::role()) {
            'super_admin'       => '/admin/dashboard',
            'institution_admin' => '/institution/dashboard',
            'athlete'           => '/athlete/dashboard',
            'staff'             => '/staff/dashboard',
            default             => '/login',
        };
    }

    public static function attempt(string $email, string $password): bool
    {
        $user = \Models\User::findByEmail($email);
        if (!$user || !password_verify($password, $user['password'])) return false;
        if ($user['status'] !== 'active') return false;

        static::login($user);
        return true;
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function generatePassword(int $length = 10): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789@#!';
        return substr(str_shuffle(str_repeat($chars, 3)), 0, $length);
    }

    // ── Unit / Institution / Club user session (separate from $_SESSION['user']) ──

    public static function unitUserCheck(): bool
    {
        return !empty($_SESSION['unit_user']);
    }

    public static function unitUser(): ?array
    {
        return $_SESSION['unit_user'] ?? null;
    }

    public static function unitUserLogin(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['unit_user'] = [
            'id'       => (int)$user['id'],
            'name'     => $user['name'],
            'email'    => $user['email'],
            'event_id' => (int)$user['event_id'],
        ];
    }

    public static function unitUserLogout(): void
    {
        unset($_SESSION['unit_user'], $_SESSION['unit_active_unit_id']);
    }

    // ── Event Staff session (separate from $_SESSION['user'] and unit_user) ──

    public static function eventStaffCheck(): bool
    {
        return !empty($_SESSION['event_staff']);
    }

    public static function eventStaff(): ?array
    {
        return $_SESSION['event_staff'] ?? null;
    }

    public static function eventStaffLogin(array $staff, array $privileges): void
    {
        session_regenerate_id(true);
        $_SESSION['event_staff'] = [
            'id'         => (int)$staff['id'],
            'name'       => $staff['name'],
            'email'      => $staff['email'],
            'event_id'   => (int)$staff['event_id'],
            'privileges' => array_values($privileges),
        ];
    }

    public static function eventStaffLogout(): void
    {
        unset($_SESSION['event_staff']);
    }

    /** Does the logged-in event staff hold the given privilege? */
    public static function eventStaffCan(string $privilege): bool
    {
        $s = static::eventStaff();
        return $s && in_array($privilege, $s['privileges'] ?? [], true);
    }
}
