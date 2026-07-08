<?php
namespace Models;

use Core\Model;

/**
 * Rate-limit log for public self-registration. Every attempt on the
 * athlete / institution register endpoints is recorded so we can cap the
 * number of sign-ups from a single IP in a rolling window and keep a
 * forensic trail of automated abuse.
 */
class SignupThrottle extends Model
{
    public static function record(string $ip, string $action, ?string $email = null, ?string $outcome = null): void
    {
        static::insert('signup_attempts', [
            'ip'      => substr($ip, 0, 45),
            'action'  => substr($action, 0, 30),
            'email'   => $email !== null ? substr($email, 0, 255) : null,
            'outcome' => $outcome !== null ? substr($outcome, 0, 20) : null,
        ]);
    }

    /** Attempts from this IP within the last $seconds (any action). */
    public static function countByIp(string $ip, int $seconds): int
    {
        // $seconds is an internal constant — inline it so the INTERVAL is
        // a literal (portable across PDO prepare emulation modes).
        $seconds = max(1, (int)$seconds);
        $r = static::row(
            "SELECT COUNT(*) AS c FROM signup_attempts
              WHERE ip = ? AND created_at >= (NOW() - INTERVAL {$seconds} SECOND)",
            [substr($ip, 0, 45)]
        );
        return (int)($r['c'] ?? 0);
    }
}
