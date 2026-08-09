<?php
namespace Models;

use Core\Model;

/**
 * Multi-role capabilities for the unified account. One user_id may hold several
 * capabilities: 'admin', 'organiser', 'athlete', 'staff'. The user's primary
 * users.role still drives their default landing page.
 */
class UserCapability extends Model
{
    /** Capability strings held by a user. */
    public static function forUser(int $userId): array
    {
        if ($userId <= 0) return [];
        $rows = static::rows("SELECT capability FROM user_capabilities WHERE user_id = ?", [$userId]);
        return array_map(fn($r) => (string)$r['capability'], $rows);
    }

    public static function grant(int $userId, string $capability): void
    {
        if ($userId <= 0 || $capability === '') return;
        static::query(
            "INSERT IGNORE INTO user_capabilities (user_id, capability) VALUES (?, ?)",
            [$userId, $capability]
        );
    }

    public static function has(int $userId, string $capability): bool
    {
        return (bool)static::row(
            "SELECT 1 AS x FROM user_capabilities WHERE user_id = ? AND capability = ? LIMIT 1",
            [$userId, $capability]
        );
    }

    public static function revoke(int $userId, string $capability): void
    {
        static::query(
            "DELETE FROM user_capabilities WHERE user_id = ? AND capability = ?",
            [$userId, $capability]
        );
    }
}
