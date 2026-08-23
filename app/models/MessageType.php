<?php
namespace Models;

use Core\Model;

/**
 * Registry + per-type settings for outbound notifications.
 *
 * A "message type" is a code point in the software that notifies someone
 * (e.g. a participation request arriving). Email is the default channel and is
 * always available; WhatsApp and SMS are premium channels the super admin
 * enables and configures per type (their "approval"). Settings live in the
 * message_settings table, keyed by the type code.
 *
 * WhatsApp settings match the chatico.in payload: apiKey, campaignName and up
 * to 7 templateParams, each mapped to a software field (values resolved at
 * send time) or a constant string.
 */
class MessageType extends Model
{
    /**
     * All software field keys a template param can be mapped to, with a human
     * label. Extend this as new message types need more values.
     */
    public const FIELDS = [
        'name_of_user'      => 'Name of user',
        'request_type'      => 'Request type',
        'name_of_event'     => 'Name of event',
        'status_of_request' => 'Status of request',
    ];

    /**
     * The catalogue of message types. Each entry:
     *   label      – shown to the super admin
     *   desc       – what triggers it / who receives it
     *   recipient  – human description of the recipient
     *   fields     – field keys available in this type's context
     *   email      – ['subject' => ..., 'body' => ...] default email template
     *                 ({placeholders} are replaced from the context)
     *   wa_params  – suggested default param mapping (field keys / '' for blank)
     */
    public const TYPES = [
        'participation_request_received' => [
            'label'     => 'Participation Request — Received',
            'desc'      => 'Sent to the event admin when an institution requests to participate in their event.',
            'recipient' => 'Event admin (event owner)',
            'fields'    => ['name_of_user', 'request_type', 'name_of_event'],
            'email'     => [
                'subject' => 'New participation request for {name_of_event}',
                'body'    => 'Hello,<br><br><strong>{name_of_user}</strong> has requested to participate '
                           . 'in <strong>{name_of_event}</strong>.<br><br>'
                           . 'Please review it under Participation Requests in your event.',
            ],
            'wa_params' => ['name_of_user', 'name_of_event', 'name_of_user'],
        ],
        'participation_request_decided' => [
            'label'     => 'Participation Request — Approved / Rejected',
            'desc'      => 'Automatic reply to the requesting user after the event admin approves or rejects their participation request.',
            'recipient' => 'Requesting institution',
            'fields'    => ['name_of_user', 'name_of_event', 'status_of_request'],
            'email'     => [
                'subject' => 'Your participation request for {name_of_event} was {status_of_request}',
                'body'    => 'Hello {name_of_user},<br><br>Your request to participate in '
                           . '<strong>{name_of_event}</strong> has been <strong>{status_of_request}</strong>.',
            ],
            'wa_params' => ['name_of_user', 'name_of_event', 'name_of_user', 'status_of_request'],
        ],
    ];

    public static function exists(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    /** The definition for a type (label/desc/fields/email/…), or null. */
    public static function def(string $type): ?array
    {
        return self::TYPES[$type] ?? null;
    }

    /**
     * Merged settings for a type: the stored row layered over channel
     * defaults (email on, whatsapp/sms off) plus a decoded wa_params array.
     */
    public static function settingsFor(string $type): array
    {
        $row = [];
        try {
            $row = static::row("SELECT * FROM message_settings WHERE message_type = ?", [$type]) ?? [];
        } catch (\Throwable $e) { $row = []; }

        $def       = self::def($type) ?? [];
        $waParams  = [];
        if (!empty($row['wa_params'])) {
            $decoded = json_decode((string)$row['wa_params'], true);
            if (is_array($decoded)) $waParams = $decoded;
        } elseif (!empty($def['wa_params'])) {
            // Seed the suggested mapping so the admin starts from a sensible default.
            foreach ($def['wa_params'] as $f) {
                $waParams[] = ['src' => 'field', 'val' => (string)$f];
            }
        }

        return [
            'message_type'     => $type,
            'email_enabled'    => array_key_exists('email_enabled', $row) ? (int)$row['email_enabled'] : 1,
            'whatsapp_enabled' => (int)($row['whatsapp_enabled'] ?? 0),
            'sms_enabled'      => (int)($row['sms_enabled'] ?? 0),
            'wa_provider_id'   => (int)($row['wa_provider_id'] ?? 0),
            'wa_campaign_name' => (string)($row['wa_campaign_name'] ?? ''),
            'wa_params'        => $waParams,
        ];
    }

    /** Every registered type with its merged settings (for the admin screen). */
    public static function allWithSettings(): array
    {
        $out = [];
        foreach (self::TYPES as $code => $def) {
            $out[$code] = ['code' => $code, 'def' => $def, 'settings' => self::settingsFor($code)];
        }
        return $out;
    }

    /** Upsert one type's settings. $waParams is a list of ['src'=>..,'val'=>..]. */
    public static function saveSettings(string $type, array $data): void
    {
        if (!self::exists($type)) return;
        $waParams = json_encode(array_values($data['wa_params'] ?? []));
        static::query(
            "INSERT INTO message_settings
                (message_type, email_enabled, whatsapp_enabled, sms_enabled,
                 wa_provider_id, wa_campaign_name, wa_params)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                email_enabled=VALUES(email_enabled),
                whatsapp_enabled=VALUES(whatsapp_enabled),
                sms_enabled=VALUES(sms_enabled),
                wa_provider_id=VALUES(wa_provider_id),
                wa_campaign_name=VALUES(wa_campaign_name),
                wa_params=VALUES(wa_params)",
            [
                $type,
                !empty($data['email_enabled'])    ? 1 : 0,
                !empty($data['whatsapp_enabled']) ? 1 : 0,
                !empty($data['sms_enabled'])      ? 1 : 0,
                (int)($data['wa_provider_id'] ?? 0) ?: null,
                (string)($data['wa_campaign_name'] ?? '') ?: null,
                $waParams,
            ]
        );
    }
}
