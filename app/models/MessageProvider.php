<?php
namespace Models;

use Core\Model;

/**
 * Reusable messaging API providers — a name plus the credentials (API URL &
 * key) for a channel (WhatsApp / SMS). Configured once by the super admin and
 * selected per message type, so switching provider or rotating a key is a
 * single edit.
 */
class MessageProvider extends Model
{
    public const CHANNELS = [
        'whatsapp' => 'WhatsApp',
        'sms'      => 'SMS',
    ];

    public static function all(): array
    {
        try {
            return static::rows("SELECT * FROM message_providers ORDER BY channel, name");
        } catch (\Throwable $e) { return []; }
    }

    /** Providers for one channel (e.g. 'whatsapp'). */
    public static function forChannel(string $channel): array
    {
        try {
            return static::rows(
                "SELECT * FROM message_providers WHERE channel = ? ORDER BY name",
                [$channel]
            );
        } catch (\Throwable $e) { return []; }
    }

    public static function find(int $id): ?array
    {
        if ($id <= 0) return null;
        try {
            return static::row("SELECT * FROM message_providers WHERE id = ?", [$id]);
        } catch (\Throwable $e) { return null; }
    }

    public static function create(array $data): int
    {
        return static::insert('message_providers', [
            'name'    => (string)($data['name'] ?? ''),
            'channel' => isset(self::CHANNELS[$data['channel'] ?? '']) ? $data['channel'] : 'whatsapp',
            'api_url' => (string)($data['api_url'] ?? '') ?: null,
            'api_key' => (string)($data['api_key'] ?? '') ?: null,
        ]);
    }

    public static function updateRow(int $id, array $data): void
    {
        static::update('message_providers', [
            'name'    => (string)($data['name'] ?? ''),
            'channel' => isset(self::CHANNELS[$data['channel'] ?? '']) ? $data['channel'] : 'whatsapp',
            'api_url' => (string)($data['api_url'] ?? '') ?: null,
            'api_key' => (string)($data['api_key'] ?? '') ?: null,
        ], ['id' => $id]);
    }

    public static function deleteRow(int $id): void
    {
        static::query("DELETE FROM message_providers WHERE id = ?", [$id]);
    }
}
