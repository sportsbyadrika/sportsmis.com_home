<?php
namespace Models;

use Core\Model;

/**
 * Global key/value application settings, controlled by the super admin.
 * Self-heals its table so reads never fatal on a fresh install — a missing
 * table simply yields the caller's default.
 */
class AppSetting extends Model
{
    /** Raw string value for a key, or $default when unset / unavailable. */
    public static function get(string $key, ?string $default = null): ?string
    {
        try {
            Schema::ensureAppSettings();
            $r = static::row("SELECT `value` FROM app_settings WHERE `key` = ?", [$key]);
            return $r ? (string)$r['value'] : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /** Boolean flag: truthy = 1/true/yes/on. Blank / unset → $default. */
    public static function getBool(string $key, bool $default): bool
    {
        $v = self::get($key, null);
        if ($v === null || $v === '') return $default;
        return in_array(strtolower(trim($v)), ['1', 'true', 'yes', 'on'], true);
    }

    /** Upsert a value for a key. */
    public static function set(string $key, string $value): void
    {
        Schema::ensureAppSettings();
        static::query(
            "INSERT INTO app_settings (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
            [$key, $value]
        );
    }

    public static function setBool(string $key, bool $on): void
    {
        self::set($key, $on ? '1' : '0');
    }
}
