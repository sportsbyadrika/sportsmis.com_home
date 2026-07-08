<?php
namespace Models;

use Core\Model;

/**
 * Login attempt log used to rate-limit brute-force / credential-stuffing.
 * Every attempt (any portal) records one row with a success flag; the
 * controller counts recent FAILED attempts by IP and by account to decide
 * when to require a CAPTCHA or temporarily lock out.
 */
class LoginThrottle extends Model
{
    public static function record(string $ip, string $email, bool $success): void
    {
        static::insert('login_attempts', [
            'ip'      => substr($ip, 0, 45),
            'email'   => substr(strtolower(trim($email)), 0, 255),
            'success' => $success ? 1 : 0,
        ]);
    }

    /** Failed attempts from this IP within the last $seconds. */
    public static function failuresByIp(string $ip, int $seconds): int
    {
        $seconds = max(1, (int)$seconds);
        $r = static::row(
            "SELECT COUNT(*) AS c FROM login_attempts
              WHERE ip = ? AND success = 0
                AND created_at >= (NOW() - INTERVAL {$seconds} SECOND)",
            [substr($ip, 0, 45)]
        );
        return (int)($r['c'] ?? 0);
    }

    /** Failed attempts against this account within the last $seconds. */
    public static function failuresByEmail(string $email, int $seconds): int
    {
        $seconds = max(1, (int)$seconds);
        $r = static::row(
            "SELECT COUNT(*) AS c FROM login_attempts
              WHERE email = ? AND success = 0
                AND created_at >= (NOW() - INTERVAL {$seconds} SECOND)",
            [substr(strtolower(trim($email)), 0, 255)]
        );
        return (int)($r['c'] ?? 0);
    }

    /** On a successful login, clear the recent failure streak for the pair. */
    public static function clearFailures(string $ip, string $email): void
    {
        static::query(
            "DELETE FROM login_attempts
              WHERE success = 0 AND (email = ? OR ip = ?)",
            [substr(strtolower(trim($email)), 0, 255), substr($ip, 0, 45)]
        );
    }
}
