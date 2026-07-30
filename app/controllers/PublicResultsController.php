<?php
namespace Controllers;

use Core\Controller;
use Models\{Schema, Event, PublicResult};

/**
 * Public, no-login results site served under a path slug
 * (e.g. sportsmis.com/sahodaya). Shows the events in the site's group; each
 * event card opens that event's PUBLISHED results (medal tally). A "Search by
 * Athlete" flow looks an athlete up by BIB/Chest number + date of birth and
 * shows their round-wise results.
 */
class PublicResultsController extends Controller
{
    private array $site;
    private string $base = '';

    private function boot(string $slug): void
    {
        try { Schema::ensurePublicResults(); } catch (\Throwable $e) {}
        $slug = strtolower(trim($slug));
        $site = $slug !== '' ? PublicResult::findActiveSite($slug) : null;
        if (!$site) { $this->abort(404); }
        $this->site = $site;
        $this->base = '/' . rawurlencode((string)$site['slug']);
    }

    /** GET /{slug} — event cards for the site. */
    public function index(string $slug): void
    {
        $this->boot($slug);
        $events = PublicResult::siteEvents((int)$this->site['id']);
        $this->renderWith('public-results', 'public-results/index', [
            'site'   => $this->site,
            'base'   => $this->base,
            'events' => $events,
        ]);
    }

    /** GET /{slug}/event/{id} — published results (medal tally) for one event. */
    public function event(string $slug, string $id): void
    {
        $this->boot($slug);
        $eventId = (int)$id;
        if (!PublicResult::siteHasEvent((int)$this->site['id'], $eventId)) $this->abort(404);
        $event = Event::findById($eventId);
        if (!$event) $this->abort(404);

        // Published-only medal tally (unit points + event-wise winners).
        $tally = \Services\TrackMedal::build($event, 0, 0, true);
        $this->renderWith('public-results', 'public-results/event', [
            'site'         => $this->site,
            'base'         => $this->base,
            'event'        => $event,
            'unit_tally'   => $tally['unit_tally'],
            'events'       => $tally['events'],
            'unit_medals'  => $tally['unit_medals'],
            'completion'   => $tally['completion'] ?? null,
            'last_updated' => $tally['last_updated'] ?? null,
            'age_top'      => $tally['age_top'] ?? [],
        ]);
    }

    /** GET /{slug}/search — athlete lookup form. */
    public function search(string $slug): void
    {
        $this->boot($slug);
        $this->renderWith('public-results', 'public-results/search', [
            'site'  => $this->site,
            'base'  => $this->base,
            'error' => $_SESSION['pr_search_error'] ?? '',
            'old'   => $_SESSION['pr_search_old'] ?? [],
        ]);
        unset($_SESSION['pr_search_error'], $_SESSION['pr_search_old']);
    }

    /** POST /{slug}/search — resolve the athlete and show their results. */
    public function searchResult(string $slug): void
    {
        $this->boot($slug);
        $chest = trim((string)($_POST['chest'] ?? ''));
        $dobIn = trim((string)($_POST['dob'] ?? ''));
        $back  = $this->base . '/search';

        // Accept the date-picker's yyyy-mm-dd, and dd/mm/yyyy as a fallback.
        $dob = null;
        if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})$#', $dobIn, $m)) {
            $y = (int)$m[1]; $mo = (int)$m[2]; $d = (int)$m[3];
            if (checkdate($mo, $d, $y)) $dob = sprintf('%04d-%02d-%02d', $y, $mo, $d);
        } elseif (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#', $dobIn, $m)) {
            $d = (int)$m[1]; $mo = (int)$m[2]; $y = (int)$m[3];
            if (checkdate($mo, $d, $y)) $dob = sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }
        if ($chest === '' || $dob === null) {
            $_SESSION['pr_search_error'] = 'Enter a valid Chest/BIB number and pick the date of birth.';
            $_SESSION['pr_search_old']   = ['chest' => $chest, 'dob' => ($dob ?? '')];
            $this->redirect($back);
        }

        $eventIds = PublicResult::eventIds((int)$this->site['id']);
        if (!$eventIds) {
            $_SESSION['pr_search_error'] = 'No events are configured for this site yet.';
            $this->redirect($back);
        }
        $in = implode(',', array_fill(0, count($eventIds), '?'));
        $rows = Event::rowsRaw(
            "SELECT er.id AS reg_id, er.event_id, er.athlete_id, er.competitor_number,
                    a.name AS athlete_name, a.date_of_birth, a.gender
               FROM event_registrations er
               JOIN athletes a ON a.id = er.athlete_id
              WHERE er.competitor_number = ? AND a.date_of_birth = ?
                AND er.admin_review_status = 'approved'
                AND er.event_id IN ($in)
              ORDER BY er.event_id",
            array_merge([$chest, $dob], $eventIds)
        );
        if (!$rows) {
            $_SESSION['pr_search_error'] = 'No athlete found for that Chest/BIB number and date of birth.';
            $_SESSION['pr_search_old']   = ['chest' => $chest, 'dob' => $dob];
            $this->redirect($back);
        }

        // One athlete (first match); collect their registrations across the site.
        $athleteId = (int)$rows[0]['athlete_id'];
        $athlete   = [
            'name'          => (string)$rows[0]['athlete_name'],
            'date_of_birth' => (string)$rows[0]['date_of_birth'],
            'gender'        => (string)$rows[0]['gender'],
            'chest'         => $chest,
        ];
        $ageGroups = \Models\Athlete::baseAgeCategories($rows[0]['date_of_birth'] ?? null);

        $blocks = [];
        foreach ($rows as $r) {
            if ((int)$r['athlete_id'] !== $athleteId) continue;
            $ev = Event::findById((int)$r['event_id']);
            $blocks[] = [
                'event_name'    => (string)($ev['name'] ?? ''),
                'event_logo'    => (string)($ev['logo'] ?? ''),
                'chest'         => (string)$r['competitor_number'],
                'track_results' => \Services\AthleteTrackResults::forRegistration((int)$r['reg_id']),
            ];
        }

        $this->renderWith('public-results', 'public-results/athlete', [
            'site'       => $this->site,
            'base'       => $this->base,
            'athlete'    => $athlete,
            'age_groups' => $ageGroups,
            'blocks'     => $blocks,
        ]);
    }
}
