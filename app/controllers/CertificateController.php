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

        $clamp = fn($v, $lo, $hi, $def) =>
            max($lo, min($hi, (int)($v === '' ? $def : $v)));
        $data = [
            'cert_body_template'  => (string)($_POST['cert_body_template'] ?? ''),
            'cert_no_prefix'      => trim((string)($_POST['cert_no_prefix'] ?? '')) ?: null,
            'cert_no_suffix'      => trim((string)($_POST['cert_no_suffix'] ?? '')) ?: null,
            'cert_meta_top_mm'          => $clamp($_POST['cert_meta_top_mm']          ?? null,   5, 200,  60),
            'cert_body_top_mm'          => $clamp($_POST['cert_body_top_mm']          ?? null,  20, 250, 100),
            'cert_partb_top_mm'         => $clamp($_POST['cert_partb_top_mm']         ?? null,  20, 280, 200),
            'cert_partb_bottom_mm'      => $clamp($_POST['cert_partb_bottom_mm']      ?? null,  40, 290, 250),
            'cert_partb_cont_top_mm'    => $clamp($_POST['cert_partb_cont_top_mm']    ?? null,   5, 280,  60),
            'cert_partb_cont_bottom_mm' => $clamp($_POST['cert_partb_cont_bottom_mm'] ?? null,  40, 290, 270),
            'cert_partb_rows_first'     => $clamp($_POST['cert_partb_rows_first']    ?? null,   1, 50,    7),
            'cert_partb_rows_cont'      => $clamp($_POST['cert_partb_rows_cont']     ?? null,   1, 80,   25),
            'cert_cont_name_size_pt'    => $clamp($_POST['cert_cont_name_size_pt']   ?? null,   6, 60,  13),
            'cert_cont_name_bold'       => !empty($_POST['cert_cont_name_bold'])      ? 1 : 0,
            'cert_cont_name_uppercase'  => !empty($_POST['cert_cont_name_uppercase']) ? 1 : 0,
        ];
        // Keep the legacy max-height field in lock-step (bottom - top) so
        // any older callers still see a sensible value.
        $data['cert_partb_max_height_mm'] = max(20, $data['cert_partb_bottom_mm'] - $data['cert_partb_top_mm']);

        // Initial sequence — only honoured before any certificate has
        // been issued on this event, so existing cert numbers stay
        // stable.
        if (isset($_POST['cert_no_next'])) {
            $startVal = (int)$_POST['cert_no_next'];
            if ($startVal > 0) {
                $issued = Event::rowsRaw(
                    "SELECT COUNT(*) AS c FROM event_certificates WHERE event_id = ?", [$eid]
                )[0]['c'] ?? 0;
                if ((int)$issued === 0) {
                    $data['cert_no_next'] = $startVal;
                }
            }
        }

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

        // Recompose existing stored certificate_no values so the DB
        // stays in lockstep with the saved prefix / suffix. Render-
        // time also recomposes, but updating the stored field keeps
        // any external integrations (email, export, etc.) honest.
        try {
            $latestEvent = Event::findById($eid);
            $rows = Event::rowsRaw(
                "SELECT id, certificate_no, cert_no_sequence
                   FROM event_certificates WHERE event_id = ?",
                [$eid]
            );
            foreach ($rows as $row) {
                // Re-derive the sequence from the stored cert_no string
                // using the prefix/suffix-aware extractor, so previous
                // bad backfills (which stored the wrong digit run) get
                // corrected on the next save. Fall back to the stored
                // sequence column only if extraction fails.
                $seq = $this->extractSequenceFromCertNo(
                    (string)$row['certificate_no'], (array)$latestEvent
                );
                if (!$seq && !empty($row['cert_no_sequence'])) {
                    $seq = (int)$row['cert_no_sequence'];
                }
                if ($seq) {
                    $newNo = $this->composeCertNo($latestEvent, (int)$seq);
                    if ($newNo !== (string)$row['certificate_no']
                        || (int)($row['cert_no_sequence'] ?? 0) !== (int)$seq) {
                        Event::rowsRaw(
                            "UPDATE event_certificates
                                SET certificate_no = ?, cert_no_sequence = ?
                              WHERE id = ?",
                            [$newNo, (int)$seq, (int)$row['id']]
                        );
                    }
                }
            }
        } catch (\Throwable $e) { /* best-effort; render-time always works */ }

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
        $userName = (string)((Auth::user() ?? [])['name'] ?? '');
        foreach ($regs as $r) {
            $regId = (int)$r['id'];
            // Skip if already generated — keep stable cert numbers.
            $had = Event::rowsRaw(
                "SELECT id FROM event_certificates WHERE event_id = ? AND registration_id = ?",
                [$eid, $regId]
            );
            if ($had) { $existing++; continue; }

            $allocated = $this->allocateCertNo($eid);
            Event::rowsRaw(
                "INSERT INTO event_certificates
                    (event_id, registration_id, certificate_no, cert_no_sequence, generated_by_name)
                 VALUES (?, ?, ?, ?, ?)",
                [$eid, $regId, $allocated['no'], $allocated['sequence'], $userName]
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

    /**
     * POST /institution/events/{eventHash}/certificates/units/{unitId}/reset
     * Delete existing certificates for the unit and re-issue fresh
     * ones — used to recover from corrupted cert numbers without
     * having to touch the DB manually.
     */
    public function resetForUnit(string $eventHash, string $unitId): void
    {
        $this->boot($eventHash);
        $this->verifyCsrf();
        $eid    = (int)$this->event['id'];
        $unitId = (int)$unitId;
        if ($unitId <= 0) $this->abort(404);

        Event::rowsRaw(
            "DELETE ec FROM event_certificates ec
               JOIN event_registrations er ON er.id = ec.registration_id
              WHERE ec.event_id = ? AND er.unit_id = ?",
            [$eid, $unitId]
        );
        // Fall through to the normal generate flow so a fresh sequence
        // is allocated from the event's current cert_no_next.
        $this->generateForUnit($eventHash, (string)$unitId);
    }

    /** GET /institution/events/{eventHash}/certificates/units/{unitId}/view */
    public function viewUnit(string $eventHash, string $unitId): void
    {
        $this->boot($eventHash);
        $eid    = (int)$this->event['id'];
        $unitId = (int)$unitId;
        $certs = Event::rowsRaw(
            "SELECT ec.id, ec.certificate_no, ec.cert_no_sequence, ec.generated_at,
                    ec.generated_by_name, ec.registration_id
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
            "SELECT id, certificate_no, cert_no_sequence, generated_at,
                    generated_by_name, registration_id
               FROM event_certificates
              WHERE id = ? AND event_id = ?",
            [$certId, $eid]
        );
        if (!$certs) $this->abort(404);
        $this->renderCertificatePage($certs);
    }

    /**
     * GET /institution/events/{eventHash}/certificates/preview —
     * a faux certificate built from a synthetic registration so the
     * admin can see the template + layout settings in action without
     * actually generating anything.
     */
    public function previewSample(string $eventHash): void
    {
        $this->boot($eventHash);
        $synthetic = [[
            'id'                => 0,
            'certificate_no'    => $this->composeCertNo($this->event,
                                      (int)($this->event['cert_no_next'] ?? 1)),
            'cert_no_sequence'  => (int)($this->event['cert_no_next'] ?? 1),
            'generated_at'      => date('Y-m-d H:i:s'),
            'generated_by_name' => 'Preview',
            'registration_id'   => 0,
            '__preview'         => true,
        ]];
        $this->renderCertificatePage($synthetic);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function allocateCertNo(int $eventId): array
    {
        $event  = Event::findById($eventId);
        $next   = (int)($event['cert_no_next'] ?? 1);
        Event::updatePartial($eventId, ['cert_no_next' => $next + 1]);
        return ['no' => $this->composeCertNo($event, $next), 'sequence' => $next];
    }

    /**
     * Always assemble the cert number from the event's current
     * prefix / sequence / suffix. The stored certificate_no row is
     * just a snapshot — the render path recomposes from
     * cert_no_sequence so changes to prefix / suffix in settings
     * propagate to old certificates automatically.
     */
    private function composeCertNo(array $event, ?int $sequence): string
    {
        $prefix = trim((string)($event['cert_no_prefix']
                    ?? ($event['event_code'] ?? '')));
        $suffix = trim((string)($event['cert_no_suffix'] ?? ''));
        $seq    = $sequence !== null
            ? str_pad((string)(int)$sequence, 4, '0', STR_PAD_LEFT) : '';
        $parts = array_values(array_filter([$prefix, $seq, $suffix],
            fn($p) => $p !== '' && $p !== null));
        return implode('/', $parts);
    }

    /**
     * Best-effort recovery of the sequence integer from a stored
     * cert_no when the dedicated cert_no_sequence column is missing.
     *
     * IMPORTANT: a naive /(\d+)/ would grab any digit run — including
     * a digit run that lives INSIDE the prefix (e.g. "IC2026") or
     * the suffix (e.g. "2026"). We strip the known prefix + suffix
     * first, then parse what's between them so the sequence number
     * is unambiguous.
     */
    private function extractSequenceFromCertNo(string $certNo, array $event): ?int
    {
        $prefix = trim((string)($event['cert_no_prefix']
                    ?? ($event['event_code'] ?? '')));
        $suffix = trim((string)($event['cert_no_suffix'] ?? ''));
        $str    = $certNo;
        if ($prefix !== '' && str_starts_with($str, $prefix)) {
            $str = substr($str, strlen($prefix));
            $str = ltrim($str, '/');
        }
        if ($suffix !== '' && str_ends_with($str, $suffix)) {
            $str = substr($str, 0, -strlen($suffix));
            $str = rtrim($str, '/');
        }
        if ($str !== '' && ctype_digit($str)) return (int)$str;
        // Fall back to position-based — the second '/' segment of a
        // {prefix/seq[/suffix]} layout is the sequence.
        $parts = explode('/', $certNo);
        if (isset($parts[1]) && ctype_digit($parts[1])) return (int)$parts[1];
        foreach ($parts as $p) {
            if (ctype_digit($p)) return (int)$p;
        }
        return null;
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
            // ALWAYS recompose the cert number from the event's current
            // prefix / sequence / suffix so settings changes propagate
            // to certificates that were generated before suffix was
            // added (or before the prefix changed).
            // Prefer the prefix/suffix-aware extractor over the stored
            // cert_no_sequence column — legacy installs' backfill may
            // have stored the wrong digit run (e.g. digits from inside
            // the prefix "IC2026" or the suffix "2026" instead of the
            // real sequence "0001").
            $seq = null;
            if (!empty($c['certificate_no'])) {
                $seq = $this->extractSequenceFromCertNo(
                    (string)$c['certificate_no'], $this->event
                );
            }
            if (!$seq && !empty($c['cert_no_sequence'])) {
                $seq = (int)$c['cert_no_sequence'];
            }
            $c['certificate_no'] = $this->composeCertNo($this->event, $seq ? (int)$seq : null);

            if (!empty($c['__preview'])) {
                // Build a synthetic registration so the preview always
                // renders something, even before any registrations exist.
                $registrations[] = [
                    'cert'    => $c,
                    'reg'     => [
                        'athlete_name'      => 'ASHA MENON',
                        'competitor_number' => 1234,
                        'unit_name'         => 'Sample Rifle Club',
                        'unit_address'      => 'Sample City, State',
                        'date_of_birth'     => '1995-06-15',
                        'gender'            => 'female',
                        'passport_photo'    => '',
                    ],
                    'athlete' => [],
                    'rows'    => [
                        ['kind' => 'Individual', 'event' => 'AP-001 · 10 m Air Pistol Senior Women', 'score' => 380, 'position' => 1, 'remarks' => 'Gold'],
                        ['kind' => 'Individual', 'event' => 'PR-004 · 50 m Rifle Prone',              'score' => 612, 'position' => 4, 'remarks' => ''],
                        ['kind' => 'Team',       'event' => 'AP-TM-01 · 10 m Air Pistol Team [Team]', 'score' => 1124,'position' => 2, 'remarks' => 'Silver'],
                    ],
                ];
                continue;
            }
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
        $body  = $this->event['cert_body_template'] ?? '';
        $bg    = $this->event['cert_bg_image'] ?? '';
        $partbTop     = max(20, (int)($this->event['cert_partb_top_mm']    ?? 200));
        $partbBottom  = max($partbTop + 20, (int)($this->event['cert_partb_bottom_mm'] ?? 250));
        $contTop      = max(5,  (int)($this->event['cert_partb_cont_top_mm']    ?? 60));
        $contBottom   = max($contTop + 20, (int)($this->event['cert_partb_cont_bottom_mm'] ?? 270));
        $data  = [
            'event'                => $this->event,
            'institution'          => $this->institution,
            'registrations'        => $registrations,
            'body_template'        => (string)$body,
            'bg_image'             => (string)$bg,
            'meta_top_mm'          => max(5,  (int)($this->event['cert_meta_top_mm'] ?? 60)),
            'body_top_mm'          => max(20, (int)($this->event['cert_body_top_mm'] ?? 100)),
            'partb_top_mm'         => $partbTop,
            'partb_bottom_mm'      => $partbBottom,
            'partb_cont_top_mm'    => $contTop,
            'partb_cont_bottom_mm' => $contBottom,
            'partb_max_mm'         => $partbBottom - $partbTop,
            'partb_cont_max_mm'    => $contBottom  - $contTop,
            'rows_first'           => max(1, (int)($this->event['cert_partb_rows_first'] ?? 7)),
            'rows_cont'            => max(1, (int)($this->event['cert_partb_rows_cont']  ?? 25)),
            'cont_name_size_pt'    => max(6, (int)($this->event['cert_cont_name_size_pt']  ?? 13)),
            'cont_name_bold'       => (int)($this->event['cert_cont_name_bold']      ?? 1) ? 1 : 0,
            'cont_name_uppercase'  => (int)($this->event['cert_cont_name_uppercase'] ?? 1) ? 1 : 0,
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

        $medalFor = function (?int $pos, string $rawRemarks): string {
            $r = strtolower(trim($rawRemarks));
            if (in_array($r, ['dns','dnf','disqualified','other'], true)) {
                return strtoupper($r === 'disqualified' ? 'DQ' : $r);
            }
            if ($pos === 1) return 'Gold';
            if ($pos === 2) return 'Silver';
            if ($pos === 3) return 'Bronze';
            return '';
        };

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
                'remarks'  => $medalFor($position, (string)($score['remarks'] ?? '')),
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
                    'remarks'  => $medalFor($pos, ''),
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
