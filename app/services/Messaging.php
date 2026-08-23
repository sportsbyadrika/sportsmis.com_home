<?php
namespace Services;

use Core\Mailer;
use Models\MessageType;
use Models\Schema;

/**
 * Central outbound-notification dispatcher.
 *
 * Callers fire a message TYPE with a context (software values) and a recipient
 * (name / email / mobile). Each enabled channel is delivered independently and
 * every failure is swallowed + logged — notifications must never break the
 * flow that triggered them.
 *
 *   Messaging::dispatch('participation_request_received',
 *       ['name_of_user' => 'ABC School', 'name_of_event' => 'State Meet', ...],
 *       ['name' => 'Organiser', 'email' => 'a@b.com', 'mobile' => '9876543210']);
 *
 * Email is the default channel; WhatsApp / SMS fire only when the super admin
 * has enabled + configured them for the type (Settings → Messaging). WhatsApp
 * uses the chatico.in payload.
 */
class Messaging
{
    public static function dispatch(string $type, array $context, array $recipient): void
    {
        try { Schema::ensureMessaging(); } catch (\Throwable $e) {}
        if (!MessageType::exists($type)) return;

        $s   = MessageType::settingsFor($type);
        $def = MessageType::def($type) ?? [];

        if (!empty($s['email_enabled']) && !empty($recipient['email'])) {
            try { self::sendEmail($def, $context, (string)$recipient['email']); }
            catch (\Throwable $e) { error_log('[Messaging:email] ' . $e->getMessage()); }
        }
        if (!empty($s['whatsapp_enabled']) && !empty($recipient['mobile'])) {
            try { self::sendWhatsapp($s, $context, (string)$recipient['mobile']); }
            catch (\Throwable $e) { error_log('[Messaging:whatsapp] ' . $e->getMessage()); }
        }
        if (!empty($s['sms_enabled']) && !empty($recipient['mobile'])) {
            try { self::sendSms($s, $context, (string)$recipient['mobile']); }
            catch (\Throwable $e) { error_log('[Messaging:sms] ' . $e->getMessage()); }
        }
    }

    /** Replace {placeholders} in a template from the context. */
    private static function fill(string $tpl, array $ctx): string
    {
        return preg_replace_callback('/\{(\w+)\}/', function ($m) use ($ctx) {
            return (string)($ctx[$m[1]] ?? '');
        }, $tpl);
    }

    private static function sendEmail(array $def, array $ctx, string $to): void
    {
        $tpl = $def['email'] ?? null;
        if (!$tpl) return;
        $subject = self::fill((string)($tpl['subject'] ?? 'Notification'), $ctx);
        $body    = self::fill((string)($tpl['body'] ?? ''), $ctx);
        (new Mailer())->send($to, $subject, $body);
    }

    /**
     * Resolve the configured param mapping to the actual templateParams array,
     * preserving positions up to the highest configured slot.
     */
    private static function buildWaParams(array $mapping, array $ctx): array
    {
        $maxIdx = 0;
        foreach ($mapping as $i => $m) {
            if ((string)($m['src'] ?? '') !== '') $maxIdx = max($maxIdx, (int)$i + 1);
        }
        $vals = [];
        for ($i = 0; $i < $maxIdx; $i++) {
            $m   = $mapping[$i] ?? [];
            $src = (string)($m['src'] ?? '');
            $val = (string)($m['val'] ?? '');
            if ($src === 'field')      $vals[] = (string)($ctx[$val] ?? '');
            elseif ($src === 'const')  $vals[] = $val;
            else                       $vals[] = '';
        }
        return $vals;
    }

    private static function sendWhatsapp(array $s, array $ctx, string $mobile): void
    {
        // Credentials come from the selected provider, not the message type.
        $provider = \Models\MessageProvider::find((int)($s['wa_provider_id'] ?? 0));
        $url      = trim((string)($provider['api_url'] ?? ''));
        $key      = trim((string)($provider['api_key'] ?? ''));
        $campaign = trim((string)($s['wa_campaign_name'] ?? ''));
        if ($url === '' || $key === '' || $campaign === '') return;

        $params  = self::buildWaParams($s['wa_params'] ?? [], $ctx);
        $payload = [
            'apiKey'         => $key,
            'campaignName'   => $campaign,
            'destination'    => self::normalizeMobile($mobile),
            // The chatico.in payload derives userName from the 3rd template param.
            'userName'       => $params[2] ?? '',
            'templateParams' => $params,
        ];
        self::postJson($url, $payload);
    }

    private static function sendSms(array $s, array $ctx, string $mobile): void
    {
        // SMS provider details are not yet available. When they are, mirror
        // sendWhatsapp() here (build the provider payload + POST). Left as a
        // logged no-op so enabling the channel never throws.
        error_log('[Messaging] SMS channel enabled but no provider is configured yet.');
    }

    private static function normalizeMobile(string $m): string
    {
        return (string)preg_replace('/[^0-9+]/', '', $m);
    }

    private static function postJson(string $url, array $payload): void
    {
        $json = json_encode($payload);
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $json,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
            ]);
            $resp = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);
            if ($err !== '' || $code >= 400) {
                error_log('[Messaging] WhatsApp POST failed (' . $code . '): ' . ($err !== '' ? $err : (string)$resp));
            }
            return;
        }
        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\n",
            'content'       => $json,
            'timeout'       => 30,
            'ignore_errors' => true,
        ]]);
        if (@file_get_contents($url, false, $ctx) === false) {
            error_log('[Messaging] WhatsApp POST failed (stream transport).');
        }
    }
}
