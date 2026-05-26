<?php
namespace Controllers;

use Core\{Controller, Auth, FileUpload};
use Models\{Institution, Event, EventRegistration, Athlete, User, Schema, TeamRegistration};

/**
 * Per-event Certificate generation for the event administrator.
 *
 *  GET  /institution/events/{eventHash}/certificates                 — landing (units list)
 *  GET  /institution/events/{eventHash}/certificates/settings        — configure background + body
 *  POST /institution/events/{eventHash}/certificates/settings        — save settings
 *  POST /institution/events/{eventHash}/certificates/units/{unitId}  — generate certs for a unit
 *  GET  /institution/events/{eventHash}/certificates/units/{unitId}/view — view all generated certs (one A4 page each)
 *  GET  /institution/events/{eventHash}/certificates/{certId}/view  — view a single certificate
 */
class CertificateController extends Controller
{
    private array $institution;
    private array $event;

    private function boot(string $eventHash): void
    {
        try { Schema::ensureCertificates(); } catch (\Throwable $e) {}
        $this->requireAuth('institution_admin');
        $inst = Institution::findByUserId(Auth::id());
        if (!$inst) $this->redirect('/login', 'Institution not found.', 'error');
        $this->institution = $inst;

        $eid = \hid_event_decode($eventHash);
        $event = Event::findById((int)$eid);
        if (!$event || (int)$event['institution_id'] !== (int)$inst['id']) $this->abort(404);
        $this->event = $event;
    }

    /** GET /institution/events/{eventHash}/certificates */
    public function index(string $eventHash): void
    {
        $this->boot($eventHash);
        $eid = (int)$this->event['id'];

        // Units on this event with approved-registration counts and
        // the count of already-generated certificates so the operator
        // sees what's pending.
        $units = Event::rowsRaw(
            "SELECT eu.id, eu.name, eu.address, eu.logo,
                    (SELECT COUNT(*) FROM event_registrations er
                       WHERE er.event_id = eu.event_id
                         AND er.unit_id = eu.id
                         AND er.admin_review_status = 'approved') AS approved_count,
                    (SELECT COUNT(*) FROM event_certificates ec
                       JOIN event_registrations er ON er.id = ec.registration_id
                      WHERE ec.event_id = eu.event_id
                        AND er.unit_id = eu.id) AS issued_count
               FROM event_units eu
              WHERE eu.event_id = ?
              ORDER BY eu.name",
            [$eid]
        );

        $configured = !empty($this->event['cert_bg_image'])
                    && !empty(trim((string)($this->event['cert_body_template'] ?? '')));

        $this->renderWith('app', 'institution/certificates/index', [
            'event'      => $this->event,
            'eventHash'  => $eventHash,
            'units'      => $units,
            'configured' => $configured,
            'flash'      => $this->flash(),
        ]);
    }

    /** GET /institution/events/{eventHash}/certificates/settings */
    public function settingsForm(string $eventHash): void
    {
        $this->boot($eventHash);
        $this->renderWith('app', 'institution/certificates/settings', [
            'event'     => $this->event,
            'eventHash' => $eventHash,
            'flash'     => $this->flash(),
        ]);
    }

    /** POST /institution/events/{eventHash}/certificates/settings */
    public function settingsSave(string $eventHash): void
    {
        $this->boot($eventHash);
        $this->verifyCsrf();
        $eid = (int)$this->event['id'];

        $data = [
            'cert_body_template' => (string)($_POST['cert_body_template'] ?? ''),
            'cert_no_prefix'     => trim((string)($_POST['cert_no_prefix'] ?? '')) ?: null,
        ];
        if (!empty($_FILES['cert_bg_image']['name'])) {
            try {
                $url = (new FileUpload())->upload($_FILES['cert_bg_image'], 'events/certificates', true);
                $data['cert_bg_image'] = $url;
            } catch (\Throwable $e) {
                $this->redirect("/institution/events/{$eventHash}/certificates/settings",
                    'Background upload failed: ' . $e->getMessage(), 'error');
            }
        }
        Event::updatePartial($eid, $data);
        $this->redirect("/institution/events/{$eventHash}/certificates/settings",
            'Certificate settings saved.');
    }

    /** POST /institution/events/{eventHash}/certificates/units/{unitId} — generate. */
    public function generateForUnit(string $eventHash, string $unitId): void
    {
        $this->boot($eventHash);
        $this->verifyCsrf();
        $eid    = (int)$this->event['id'];
        $unitId = (int)$unitId;
        if ($unitId <= 0) $this->abort(404);

        if (empty($this->event['cert_bg_image'])
            || trim((string)($this->event['cert_body_template'] ?? '')) === '') {
            $this->redirect("/institution/events/{$eventHash}/certificates",
                'Configure the certificate background and body template first.', 'warning');
        }

        // Approved registrations on this unit.
        $regs = Event::rowsRaw(
            "SELECT er.id, er.competitor_number
               FROM event_registrations er
              WHERE er.event_id = ? AND er.unit_id = ?
                AND er.admin_review_status = 'approved'
              ORDER BY er.competitor_number, er.id",
            [$eid, $unitId]
        );
        if (!$regs) {
            $this->redirect("/institution/events/{$eventHash}/certificates",
                'This unit has no approved registrations to certify.', 'warning');
        }

        $issued = 0; $existing = 0;
        foreach ($regs as $r) {
            $regId = (int)$r['id'];
            // Skip if already generated — keep stable cert numbers.
            $had = Event::rowsRaw(
                "SELECT id FROM event_certificates WHERE event_id = ? AND registration_id = ?",
                [$eid, $regId]
            );
            if ($had) { $existing++; continue; }

            $certNo = $this->allocateCertNo($eid);
            Event::rowsRaw(
                "INSERT INTO event_certificates
                    (event_id, registration_id, certificate_no, generated_by_name)
                 VALUES (?, ?, ?, ?)",
                [$eid, $regId, $certNo, (string)Auth::user()['name'] ?? '']
            );
            $issued++;
        }

        $this->redirect(
            "/institution/events/{$eventHash}/certificates/units/{$unitId}/view",
            $issued ? "{$issued} certificate" . ($issued === 1 ? '' : 's')
                    . " generated" . ($existing ? " · {$existing} already existed" : '')
                    : "No new certificates — {$existing} already existed."
        );
    }

    /** GET /institution/events/{eventHash}/certificates/units/{unitId}/view */
    public function viewUnit(string $eventHash, string $unitId): void
    {
        $this->boot($eventHash);
        $eid    = (int)$this->event['id'];
        $unitId = (int)$unitId;
        $certs = Event::rowsRaw(
            "SELECT ec.id, ec.certificate_no, ec.generated_at, ec.generated_by_name,
                    ec.registration_id
               FROM event_certificates ec
               JOIN event_registrations er ON er.id = ec.registration_id
              WHERE ec.event_id = ? AND er.unit_id = ?
              ORDER BY er.competitor_number, ec.id",
            [$eid, $unitId]
        );
        if (!$certs) $this->abort(404);
        $this->renderCertificatePage($certs);
    }

    /** GET /institution/events/{eventHash}/certificates/{certId}/view */
    public function viewOne(string $eventHash, string $certId): void
    {
        $this->boot($eventHash);
        $eid    = (int)$this->event['id'];
        $certId = (int)$certId;
        $certs = Event::rowsRaw(
            "SELECT id, certificate_no, generated_at, generated_by_name, registration_id
               FROM event_certificates
              WHERE id = ? AND event_id = ?",
            [$certId, $eid]
        );
        if (!$certs) $this->abort(404);
        $this->renderCertificatePage($certs);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function allocateCertNo(int $eventId): string
    {
        $event = Event::findById($eventId);
        $prefix = trim((string)($event['cert_no_prefix']
                    ?? ($event['event_code'] ?? '')));
        if ($prefix === '') $prefix = 'CERT';
        $next = (int)($event['cert_no_next'] ?? 1);
        Event::updatePartial($eventId, ['cert_no_next' => $next + 1]);
        return $prefix . '/' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Render one or more certificates back-to-back as A4 portrait
     * pages. Each cert renders the background image full-bleed and
     * overlays the composed body text + a Part B participation
     * table.
     */
    private function renderCertificatePage(array $certs): void
    {
        $eid = (int)$this->event['id'];
        $registrations = [];
        foreach ($certs as $c) {
            $rid = (int)$c['registration_id'];
            $reg = EventRegistration::withProfile($rid);
            if (!$reg) continue;
            $items = EventRegistration::items($rid);
            $athlete = Athlete::findById((int)$reg['athlete_id']);
            $rows = $this->partBRows($eid, (int)$reg['athlete_id'], $items);
            $registrations[] = [
                'cert'    => $c,
                'reg'     => $reg,
                'athlete' => $athlete,
                'rows'    => $rows,
            ];
        }
        $body = $this->event['cert_body_template'] ?? '';
        $bg   = $this->event['cert_bg_image'] ?? '';
        $data = [
            'event'         => $this->event,
            'institution'   => $this->institution,
            'registrations' => $registrations,
            'body_template' => (string)$body,
            'bg_image'      => (string)$bg,
        ];
        extract($data);
        require APP_ROOT . '/views/institution/certificates/print.php';
    }

    /**
     * Build the Part B table for an athlete — every event-sport they
     * registered for plus every approved team-entry they're a member
     * of, each with Score / Position / Remarks.
     */
    private function partBRows(int $eventId, int $athleteId, array $items): array
    {
        try { Schema::ensureTeamEntry(); } catch (\Throwable $e) {}
        $rows = [];

        // ── Individual events the athlete is registered for ───────
        foreach ($items as $it) {
            $esId   = (int)$it['event_sport_id'];
            $catId  = $this->lookupCategoryFor($esId);
            $score  = $catId ? $this->scoreFor($eventId, $athleteId, $catId) : null;
            $position = null;
            if ($score && empty($score['skip_rank'])) {
                $position = $this->positionInEventSport($eventId, $esId, $catId, $athleteId, $score);
            }
            $rows[] = [
                'kind'     => 'Individual',
                'event'    => trim(($it['event_code'] ?? '') . ' · ' . ($it['sport_event_name'] ?? '')),
                'score'    => $score ? $score['grand_total'] : null,
                'position' => $position,
                'remarks'  => $score['remarks'] ?? '',
            ];
        }

        // ── Team entries the athlete is a member of ───────────────
        try {
            $teams = Event::rowsRaw(
                "SELECT tr.id AS team_id, tr.team_name, tr.event_sport_id,
                        es.event_code, sev.name AS sport_event_name,
                        sc.id AS category_id
                   FROM team_registration_members trm
                   JOIN team_registrations tr ON tr.id = trm.team_registration_id
              LEFT JOIN event_sports es      ON es.id = tr.event_sport_id
              LEFT JOIN sport_events sev     ON sev.id = es.sport_event_id
              LEFT JOIN sport_categories sc  ON sc.id = sev.category_id
                  WHERE trm.athlete_id = ?
                    AND tr.event_id = ?
                    AND tr.admin_review_status = 'approved'",
                [$athleteId, $eventId]
            );
            foreach ($teams as $t) {
                $tot = $this->teamTotal($eventId, (int)$t['team_id'], (int)($t['category_id'] ?? 0));
                $pos = $this->teamPositionInEventSport($eventId, (int)$t['event_sport_id'], (int)$t['team_id']);
                $rows[] = [
                    'kind'     => 'Team',
                    'event'    => trim(($t['event_code'] ?? '') . ' · ' . ($t['sport_event_name'] ?? '') . ' [Team]'),
                    'score'    => $tot,
                    'position' => $pos,
                    'remarks'  => $t['team_name'] ?? '',
                ];
            }
        } catch (\Throwable $e) { /* team tables absent */ }

        return $rows;
    }

    private function lookupCategoryFor(int $eventSportId): ?int
    {
        $r = Event::rowsRaw(
            "SELECT sc.id
               FROM event_sports es
          LEFT JOIN sport_events     sev ON sev.id = es.sport_event_id
          LEFT JOIN sport_categories sc  ON sc.id = sev.category_id
              WHERE es.id = ?", [$eventSportId])[0] ?? null;
        return $r && $r['id'] !== null ? (int)$r['id'] : null;
    }

    private function scoreFor(int $eventId, int $athleteId, int $catId): ?array
    {
        $r = Event::rowsRaw(
            "SELECT se.grand_total, se.remarks
               FROM score_entries se
              WHERE se.event_id = ?
                AND se.athlete_id = ?
                AND se.sport_category_id = ?
                AND se.lane_status IN ('saved','final')
              ORDER BY se.grand_total DESC, se.id DESC LIMIT 1",
            [$eventId, $athleteId, $catId])[0] ?? null;
        if (!$r) return null;
        $r['skip_rank'] = in_array((string)($r['remarks'] ?? ''), ['dns','dnf','disqualified'], true);
        return $r;
    }

    private function positionInEventSport(int $eventId, int $eventSportId, int $catId, int $myAthleteId, array $myScore): ?int
    {
        // Athletes registered for this event-sport with their best
        // score on the category — rank by grand_total desc, then by
        // ascending athlete_id as a stable tiebreak.
        $rows = Event::rowsRaw(
            "SELECT er.athlete_id, se.grand_total, se.remarks
               FROM event_registration_items eri
               JOIN event_registrations er ON er.id = eri.registration_id
                                          AND er.admin_review_status = 'approved'
          LEFT JOIN score_entries se        ON se.event_id = ?
                                          AND se.athlete_id = er.athlete_id
                                          AND se.sport_category_id = ?
                                          AND se.lane_status IN ('saved','final')
              WHERE eri.event_sport_id = ?",
            [$eventId, $catId, $eventSportId]
        );
        // Filter out DNS / DNF / DQ rows and entries without a score.
        $valid = array_values(array_filter($rows, fn($r) =>
            $r['grand_total'] !== null
            && !in_array((string)($r['remarks'] ?? ''), ['dns','dnf','disqualified'], true)));
        usort($valid, fn($a, $b) => (float)$b['grand_total'] <=> (float)$a['grand_total']);
        foreach ($valid as $i => $row) {
            if ((int)$row['athlete_id'] === $myAthleteId) return $i + 1;
        }
        return null;
    }

    private function teamTotal(int $eventId, int $teamId, int $catId): ?float
    {
        if (!$catId) return null;
        $members = Event::rowsRaw(
            "SELECT athlete_id FROM team_registration_members
              WHERE team_registration_id = ?", [$teamId]);
        if (!$members) return null;
        $tot = 0.0; $any = false;
        foreach ($members as $m) {
            $s = $this->scoreFor($eventId, (int)$m['athlete_id'], $catId);
            if (!$s || !empty($s['skip_rank'])) continue;
            $tot += (float)$s['grand_total']; $any = true;
        }
        return $any ? $tot : null;
    }

    private function teamPositionInEventSport(int $eventId, int $eventSportId, int $myTeamId): ?int
    {
        $teams = Event::rowsRaw(
            "SELECT tr.id, sev.category_id
               FROM team_registrations tr
          LEFT JOIN event_sports es  ON es.id = tr.event_sport_id
          LEFT JOIN sport_events sev ON sev.id = es.sport_event_id
              WHERE tr.event_id = ?
                AND tr.event_sport_id = ?
                AND tr.admin_review_status = 'approved'",
            [$eventId, $eventSportId]);
        $ranked = [];
        foreach ($teams as $t) {
            $tot = $this->teamTotal($eventId, (int)$t['id'], (int)($t['category_id'] ?? 0));
            if ($tot === null) continue;
            $ranked[] = ['id' => (int)$t['id'], 'total' => $tot];
        }
        usort($ranked, fn($a, $b) => $b['total'] <=> $a['total']);
        foreach ($ranked as $i => $row) {
            if ($row['id'] === $myTeamId) return $i + 1;
        }
        return null;
    }
}
