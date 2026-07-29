<?php
namespace Models;

use Core\Model;

/**
 * Public result "sites" — each maps a subdomain slug (e.g. "sahodaya" for
 * sahodaya.sportsmis.com) to a group of events whose PUBLISHED results are
 * shown on a public, no-login page. Configured by super admin.
 */
class PublicResult extends Model
{
    /**
     * Extract a candidate subdomain slug from an HTTP host, or null when the
     * host is the bare/`www` domain (no usable subdomain). The base domain is
     * taken from PUBLIC_RESULTS_BASE_DOMAIN when set, else inferred as the last
     * two labels of the host.
     */
    public static function slugFromHost(string $host): ?string
    {
        $host = strtolower(trim($host));
        if ($host === '') return null;
        // Strip a port if present, and any trailing dot.
        $host = rtrim(preg_replace('/:\d+$/', '', $host), '.');
        // Ignore bare IPs / localhost.
        if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) return null;

        $base = strtolower((string)(getenv('PUBLIC_RESULTS_BASE_DOMAIN') ?: ''));
        if ($base !== '' && str_ends_with($host, '.' . $base)) {
            $sub = substr($host, 0, -strlen('.' . $base));
        } else {
            $labels = explode('.', $host);
            if (count($labels) < 3) return null;            // bare domain, no subdomain
            $sub = implode('.', array_slice($labels, 0, count($labels) - 2));
        }
        $sub = trim($sub);
        if ($sub === '' || $sub === 'www' || $sub === 'app') return null;
        // Only accept the first label as the slug (ignore any deeper nesting).
        $sub = explode('.', $sub)[0];
        return preg_match('/^[a-z0-9][a-z0-9-]{0,62}$/', $sub) ? $sub : null;
    }

    /** The active public site for the current request host, or null. */
    public static function currentSite(): ?array
    {
        $slug = self::slugFromHost((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($slug === null) return null;
        return self::findActiveSite($slug);
    }

    public static function findActiveSite(string $slug): ?array
    {
        return static::row(
            "SELECT * FROM public_result_sites WHERE slug = ? AND status = 'active'",
            [$slug]
        );
    }

    public static function findSite(int $id): ?array
    {
        return static::row("SELECT * FROM public_result_sites WHERE id = ?", [$id]);
    }

    public static function allSites(): array
    {
        return static::rows(
            "SELECT s.*,
                    (SELECT COUNT(*) FROM public_result_site_events e WHERE e.site_id = s.id) AS event_count
               FROM public_result_sites s ORDER BY s.title, s.slug"
        );
    }

    public static function createSite(array $data): int
    {
        return static::insert('public_result_sites', $data);
    }

    public static function updateSite(int $id, array $data): void
    {
        static::update('public_result_sites', $data, ['id' => $id]);
    }

    public static function deleteSite(int $id): void
    {
        static::query("DELETE FROM public_result_sites WHERE id = ?", [$id]);
    }

    /** Event ids linked to a site. */
    public static function eventIds(int $siteId): array
    {
        return array_map(
            fn($r) => (int)$r['event_id'],
            static::rows("SELECT event_id FROM public_result_site_events WHERE site_id = ? ORDER BY sort_order, id", [$siteId])
        );
    }

    /** Events (with logo + name) linked to a site, in configured order. */
    public static function siteEvents(int $siteId): array
    {
        return static::rows(
            "SELECT e.id, e.name, e.logo, e.event_date_from, e.event_date_to
               FROM public_result_site_events se
               JOIN events e ON e.id = se.event_id
              WHERE se.site_id = ?
              ORDER BY se.sort_order, e.name",
            [$siteId]
        );
    }

    /** True when an event belongs to a site. */
    public static function siteHasEvent(int $siteId, int $eventId): bool
    {
        return (bool)static::row(
            "SELECT id FROM public_result_site_events WHERE site_id = ? AND event_id = ?",
            [$siteId, $eventId]
        );
    }

    /** Replace a site's event list with the given ids (order = array order). */
    public static function setSiteEvents(int $siteId, array $eventIds): void
    {
        static::query("DELETE FROM public_result_site_events WHERE site_id = ?", [$siteId]);
        $i = 0;
        foreach (array_values(array_unique(array_map('intval', $eventIds))) as $eid) {
            if ($eid <= 0) continue;
            static::insert('public_result_site_events', [
                'site_id'    => $siteId,
                'event_id'   => $eid,
                'sort_order' => $i++,
            ]);
        }
    }
}
