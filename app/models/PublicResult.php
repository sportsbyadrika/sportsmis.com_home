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

    /** Active sites (for the public Results directory), each with its event count. */
    public static function activeSites(): array
    {
        return static::rows(
            "SELECT s.*,
                    (SELECT COUNT(*) FROM public_result_site_events e WHERE e.site_id = s.id) AS event_count
               FROM public_result_sites s
              WHERE s.status = 'active'
              ORDER BY s.title, s.slug"
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

    /**
     * Map of event_id => slug for events published on any ACTIVE site (first
     * site wins if an event is in several). Powers "Result" links elsewhere.
     */
    public static function eventSlugMap(): array
    {
        $map = [];
        foreach (static::rows(
            "SELECT se.event_id, s.slug
               FROM public_result_site_events se
               JOIN public_result_sites s ON s.id = se.site_id AND s.status = 'active'
              ORDER BY se.site_id, se.sort_order"
        ) as $r) {
            $eid = (int)$r['event_id'];
            if (!isset($map[$eid])) $map[$eid] = (string)$r['slug'];
        }
        return $map;
    }

    /** Record one athlete-search hit from an IP (and prune old rows). */
    public static function recordSearch(string $ip): void
    {
        try {
            static::insert('public_search_throttle', ['ip' => substr($ip, 0, 45)]);
            // Cheap prune: drop rows older than a day (~1% of the time).
            if (function_exists('random_int') && random_int(1, 100) === 1) {
                static::query("DELETE FROM public_search_throttle WHERE created_at < (NOW() - INTERVAL 1 DAY)");
            }
        } catch (\Throwable $e) { /* throttle is best-effort */ }
    }

    /** Number of searches from an IP within the last $seconds. */
    public static function recentSearches(string $ip, int $seconds): int
    {
        try {
            $r = static::row(
                "SELECT COUNT(*) AS c FROM public_search_throttle
                  WHERE ip = ? AND created_at >= (NOW() - INTERVAL ? SECOND)",
                [substr($ip, 0, 45), $seconds]
            );
            return (int)($r['c'] ?? 0);
        } catch (\Throwable $e) { return 0; }
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
