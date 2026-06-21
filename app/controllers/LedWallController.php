<?php
declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Hash;
use Models\Event;
use Models\Schema;

/**
 * Public LED-wall slideshow. Anyone who has the event code and the
 * numeric password the event admin set can bring up the auto-rotating
 * result deck on a TV / projector. The slideshow URL itself carries a
 * signed token derived from the password so it can be bookmarked and
 * survive browser restarts; changing the password invalidates every
 * previously-issued URL.
 */
class LedWallController extends Controller
{
    private const TOKEN_CTX_PREFIX = 'led-wall:';

    public function loginForm(): void
    {
        try { Schema::ensureLedWall(); } catch (\Throwable $e) {}
        $errMap = [
            'missing'  => 'Enter the event code and the numeric password.',
            'invalid'  => 'Event code or password is incorrect, or the LED wall feature is not enabled for this event.',
            'expired'  => 'This slideshow link is no longer valid — the password has been changed. Re-enter it below.',
            'disabled' => 'The LED wall feature has been turned off for this event by the organiser.',
        ];
        $this->render('led-wall/login', [
            'flash'      => $this->flash(),
            'error'      => $errMap[(string)($_GET['error'] ?? '')] ?? '',
            'event_code' => (string)($_GET['event_code'] ?? ''),
        ]);
    }

    public function login(): void
    {
        try { Schema::ensureLedWall(); } catch (\Throwable $e) {}
        $code = strtoupper(trim((string)($_POST['event_code'] ?? '')));
        $pass = trim((string)($_POST['password']   ?? ''));
        if ($code === '' || $pass === '') {
            $this->redirect('/led-wall?error=missing&event_code=' . urlencode($code));
        }
        $event = Event::rowsRaw(
            "SELECT id, event_code, led_wall_enabled, led_wall_password
               FROM events WHERE event_code = ? LIMIT 1",
            [$code]
        )[0] ?? null;
        if (!$event
            || empty($event['led_wall_enabled'])
            || (string)$event['led_wall_password'] === ''
            || !hash_equals((string)$event['led_wall_password'], $pass)) {
            $this->redirect('/led-wall?error=invalid&event_code=' . urlencode($code));
        }
        $eventId = (int)$event['id'];
        $hash    = Hash::encode($eventId, 'event');
        $token   = Hash::encode($eventId, self::TOKEN_CTX_PREFIX . (string)$event['led_wall_password']);
        $this->redirect('/led-wall/' . $hash . '?t=' . urlencode($token));
    }

    public function show(string $hash): void
    {
        try { Schema::ensureLedWall(); } catch (\Throwable $e) {}
        $eventId = Hash::decode($hash, 'event') ?? 0;
        if ($eventId <= 0) $this->redirect('/led-wall?error=invalid');
        $event = Event::rowsRaw(
            "SELECT id, event_code, name, logo, led_wall_enabled, led_wall_password
               FROM events WHERE id = ? LIMIT 1",
            [$eventId]
        )[0] ?? null;
        if (!$event || empty($event['led_wall_enabled'])) {
            $this->redirect('/led-wall?error=disabled&event_code=' . urlencode((string)($event['event_code'] ?? '')));
        }
        $expected = Hash::encode($eventId, self::TOKEN_CTX_PREFIX . (string)$event['led_wall_password']);
        $given    = trim((string)($_GET['t'] ?? ''));
        if ($given === '' || !hash_equals($expected, $given)) {
            $this->redirect('/led-wall?error=expired&event_code=' . urlencode((string)$event['event_code']));
        }
        $slides = $this->buildSlides($eventId);
        $this->render('led-wall/show', [
            'event'  => $event,
            'slides' => $slides,
        ]);
    }

    /**
     * Build the slide deck: per-(category, sport-event) individual
     * results first, then per-(category, sport-event) team results in
     * the same order. Each slide is a self-contained array the view
     * iterates over.
     */
    private function buildSlides(int $eventId): array
    {
        // Per-(athlete, category) best grand_total picked up once.
        $scoreRows = Event::rowsRaw(
            "SELECT se.athlete_id, se.sport_category_id, se.grand_total,
                    se.remarks, se.competitor_number
               FROM score_entries se
              WHERE se.event_id = ?
                AND se.lane_status IN ('saved', 'final')",
            [$eventId]
        );
        $scoreByAthCat = [];
        foreach ($scoreRows as $s) {
            $key = (int)$s['athlete_id'] . '|' . (int)$s['sport_category_id'];
            if (isset($scoreByAthCat[$key])
                && (float)$scoreByAthCat[$key]['grand_total'] >= (float)$s['grand_total']) {
                continue;
            }
            $scoreByAthCat[$key] = $s;
        }
        $unranked = ['dns', 'dnf', 'disqualified'];

        // ── Individuals ─────────────────────────────────────────
        $rows = Event::rowsRaw(
            "SELECT es.id AS event_sport_id, es.event_code, es.mqs,
                    sev.name AS sport_event_name,
                    sc.id    AS category_id,
                    sc.name  AS category_name,
                    sc.abbreviation AS category_abbr,
                    er.competitor_number AS reg_competitor_number,
                    er.athlete_id,
                    a.name   AS athlete_name,
                    eu.name  AS unit_name
               FROM event_sports es
               JOIN sport_events sev      ON sev.id = es.sport_event_id
               JOIN sport_categories sc   ON sc.id = sev.category_id
               JOIN event_registration_items eri ON eri.event_sport_id = es.id
               JOIN event_registrations er  ON er.id = eri.registration_id
                                          AND er.admin_review_status = 'approved'
               JOIN athletes a            ON a.id = er.athlete_id
          LEFT JOIN event_units eu        ON eu.id = er.unit_id
              WHERE es.event_id = ?
              ORDER BY sc.name, es.event_code, a.name",
            [$eventId]
        );
        $individuals = [];
        foreach ($rows as $r) {
            $key = (int)$r['event_sport_id'];
            if (!isset($individuals[$key])) {
                $individuals[$key] = [
                    'type'          => 'individual',
                    'category'      => (string)$r['category_name'],
                    'category_abbr' => (string)($r['category_abbr'] ?? ''),
                    'event_code'    => (string)($r['event_code']    ?? ''),
                    'sport_event'   => (string)$r['sport_event_name'],
                    'mqs'           => ($r['mqs'] !== null && $r['mqs'] !== '')
                                        ? (float)$r['mqs'] : null,
                    'entries'       => [],
                ];
            }
            $sKey   = (int)$r['athlete_id'] . '|' . (int)$r['category_id'];
            $score  = $scoreByAthCat[$sKey] ?? null;
            $hasScore = $score !== null;
            $rankable = $hasScore && !in_array((string)($score['remarks'] ?? ''), $unranked, true);
            $individuals[$key]['entries'][] = [
                'athlete_name' => (string)$r['athlete_name'],
                'unit_name'    => (string)($r['unit_name'] ?? ''),
                'grand_total'  => $rankable ? (float)$score['grand_total'] : null,
                'rankable'     => $rankable,
            ];
        }
        foreach ($individuals as &$slide) {
            usort($slide['entries'], function ($a, $b) {
                if ($a['rankable'] !== $b['rankable']) return $b['rankable'] <=> $a['rankable'];
                return ($b['grand_total'] ?? 0) <=> ($a['grand_total'] ?? 0);
            });
            $r = 0;
            foreach ($slide['entries'] as $i => $_) {
                $slide['entries'][$i]['rank'] = $slide['entries'][$i]['rankable']
                    ? ++$r : null;
                $tot = $slide['entries'][$i]['grand_total'];
                $slide['entries'][$i]['qualified'] = $slide['mqs'] !== null
                    && $tot !== null
                    && $tot >= $slide['mqs'];
            }
        }
        unset($slide);

        // ── Teams ────────────────────────────────────────────────
        try { Schema::ensureTeamEntry(); } catch (\Throwable $e) {}
        $teams = Event::rowsRaw(
            "SELECT tr.id AS team_id, tr.team_name, tr.event_sport_id,
                    eu.name AS unit_name,
                    es.event_code,
                    sev.name AS sport_event_name,
                    sc.id   AS category_id,
                    sc.name AS category_name,
                    sc.abbreviation AS category_abbr
               FROM team_registrations tr
          LEFT JOIN event_units eu       ON eu.id = tr.unit_id
          LEFT JOIN event_sports es      ON es.id = tr.event_sport_id
          LEFT JOIN sport_events sev     ON sev.id = es.sport_event_id
          LEFT JOIN sport_categories sc  ON sc.id = sev.category_id
              WHERE tr.event_id = ?
                AND tr.admin_review_status = 'approved'
              ORDER BY sc.name, es.event_code, tr.team_name",
            [$eventId]
        );
        $membersByTeam = [];
        $teamIds = array_map(fn($t) => (int)$t['team_id'], $teams);
        if ($teamIds) {
            $in = implode(',', array_fill(0, count($teamIds), '?'));
            $rowsM = Event::rowsRaw(
                "SELECT trm.team_registration_id, trm.athlete_id, trm.position,
                        a.name AS athlete_name
                   FROM team_registration_members trm
              LEFT JOIN athletes a ON a.id = trm.athlete_id
                  WHERE trm.team_registration_id IN ({$in})
                  ORDER BY trm.team_registration_id, trm.position, trm.id",
                $teamIds
            );
            foreach ($rowsM as $m) {
                $membersByTeam[(int)$m['team_registration_id']][] = $m;
            }
        }
        $teamSlides = [];
        foreach ($teams as $t) {
            $key = (int)$t['event_sport_id'];
            if (!isset($teamSlides[$key])) {
                $teamSlides[$key] = [
                    'type'          => 'team',
                    'category'      => (string)$t['category_name'],
                    'category_abbr' => (string)($t['category_abbr'] ?? ''),
                    'event_code'    => (string)($t['event_code']    ?? ''),
                    'sport_event'   => (string)$t['sport_event_name'],
                    'entries'       => [],
                ];
            }
            $members  = $membersByTeam[(int)$t['team_id']] ?? [];
            $names    = [];
            $teamTotal= 0.0;
            $scored   = 0;
            $need     = max(1, count($members));
            foreach ($members as $m) {
                if ($m['athlete_name']) $names[] = (string)$m['athlete_name'];
                $sKey = (int)$m['athlete_id'] . '|' . (int)$t['category_id'];
                $sc   = $scoreByAthCat[$sKey] ?? null;
                if ($sc && !in_array((string)($sc['remarks'] ?? ''), $unranked, true)) {
                    $teamTotal += (float)$sc['grand_total'];
                    $scored++;
                }
            }
            $teamSlides[$key]['entries'][] = [
                'team_name'   => (string)($t['team_name'] ?: '—'),
                'unit_name'   => (string)($t['unit_name'] ?? ''),
                'members'     => implode(', ', $names),
                'grand_total' => $scored >= $need ? $teamTotal : null,
                'rankable'    => $scored >= $need,
            ];
        }
        foreach ($teamSlides as &$slide) {
            usort($slide['entries'], function ($a, $b) {
                if ($a['rankable'] !== $b['rankable']) return $b['rankable'] <=> $a['rankable'];
                return ($b['grand_total'] ?? 0) <=> ($a['grand_total'] ?? 0);
            });
            $r = 0;
            foreach ($slide['entries'] as $i => $_) {
                $slide['entries'][$i]['rank'] = $slide['entries'][$i]['rankable']
                    ? ++$r : null;
            }
        }
        unset($slide);

        return array_merge(array_values($individuals), array_values($teamSlides));
    }
}
