<?php
namespace Controllers;

use Core\{Controller, Auth, FileUpload, Mailer};
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
            'cert_show_mqs'             => !empty($_POST['cert_show_mqs'])            ? 1 : 0,
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
            // Resolve the new cert row's id, then render its PDF straight
            // away. We swallow errors so a render failure doesn't strand
            // the issued certificate — the admin can re-generate later.
            try {
                $newId = (int)(Event::rowsRaw(
                    "SELECT id FROM event_certificates
                      WHERE event_id = ? AND registration_id = ?
                      LIMIT 1",
                    [$eid, $regId]
                )[0]['id'] ?? 0);
                if ($newId > 0) $this->generatePdfForCert($newId);
            } catch (\Throwable $e) {
                error_log('[CertificateController/generateForUnit/pdf] ' . $e->getMessage());
            }
            $issued++;
        }

        // Refresh the per-event statistics JSON so downstream
        // dashboards / exports see the post-generate state.
        $this->writeStatsDataset($eid);

        $this->redirect(
            "/institution/events/{$eventHash}/certificates/units/{$unitId}/view",
            $issued ? "{$issued} certificate" . ($issued === 1 ? '' : 's')
                    . " generated" . ($existing ? " · {$existing} already existed" : '')
                    : "No new certificates — {$existing} already existed."
        );
    }

    /**
     * POST /institution/events/{eventHash}/certificates/units/{unitId}/email
     * Email each athlete in this unit their saved certificate PDF.
     * PDFs are lazily generated for any cert that doesn't yet have one
     * (older issuances pre-dating the persisted-PDF feature). Athletes
     * without a registered email are counted in the skipped tally.
     */
    public function emailForUnit(string $eventHash, string $unitId): void
    {
        $this->boot($eventHash);
        $this->verifyCsrf();
        $eid    = (int)$this->event['id'];
        $unitId = (int)$unitId;
        if ($unitId <= 0) $this->abort(404);

        $unit = Event::rowsRaw(
            "SELECT id, name FROM event_units WHERE id = ? AND event_id = ?",
            [$unitId, $eid]
        )[0] ?? null;
        if (!$unit) $this->abort(404);

        $rows = Event::rowsRaw(
            "SELECT ec.id            AS cert_id,
                    ec.certificate_no,
                    ec.pdf_path,
                    a.name           AS athlete_name,
                    u.email          AS athlete_email
               FROM event_certificates ec
               JOIN event_registrations er ON er.id = ec.registration_id
               JOIN athletes a            ON a.id = er.athlete_id
          LEFT JOIN users    u            ON u.id = a.user_id
              WHERE ec.event_id = ? AND er.unit_id = ?",
            [$eid, $unitId]
        );

        $mailer = new Mailer();
        $sent = 0; $skippedNoEmail = 0; $failed = 0;
        foreach ($rows as $r) {
            $email = trim((string)($r['athlete_email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skippedNoEmail++;
                continue;
            }
            $pdfPath = (string)($r['pdf_path'] ?? '');
            if ($pdfPath === '' || !is_file($pdfPath)) {
                $pdfPath = (string)($this->generatePdfForCert((int)$r['cert_id']) ?? '');
            }
            if ($pdfPath === '' || !is_file($pdfPath)) { $failed++; continue; }
            try {
                $ok = $mailer->sendCertificate(
                    $email, (string)$r['athlete_name'], $this->event,
                    $pdfPath, (string)$r['certificate_no']
                );
                if ($ok) {
                    $sent++;
                    Event::rowsRaw(
                        "UPDATE event_certificates
                            SET emailed_at = NOW(), email_count = email_count + 1
                          WHERE id = ?",
                        [(int)$r['cert_id']]
                    );
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                error_log('[certificates/emailForUnit] ' . $e->getMessage());
                $failed++;
            }
        }

        $bits = [];
        if ($sent)            $bits[] = $sent            . ' sent';
        if ($skippedNoEmail)  $bits[] = $skippedNoEmail  . ' skipped (no email)';
        if ($failed)          $bits[] = $failed          . ' failed';
        $msg = ($bits ? implode(' · ', $bits) : 'Nothing to send') . ' for ' . $unit['name'];
        $this->redirect("/institution/events/{$eventHash}/certificates",
            $msg, $sent ? 'success' : ($failed ? 'error' : 'warning'));
    }

    /**
     * POST /institution/events/{eventHash}/certificates/units/{unitId}/generate-chunk
     * AJAX-driven chunked variant of generateForUnit() so very large
     * units never trip PHP's max_execution_time. The browser POSTs in
     * a loop with ?offset / ?limit and shows a progress bar; each
     * response carries the running total + a `done` flag. Cert-number
     * allocation is per-row so partial runs leave the registry
     * consistent if the operator aborts mid-flight.
     */
    public function generateChunkForUnit(string $eventHash, string $unitId): void
    {
        $this->boot($eventHash);
        $this->verifyCsrf();
        $eid    = (int)$this->event['id'];
        $unitId = (int)$unitId;
        if ($unitId <= 0) { $this->json(['success' => false, 'message' => 'Bad unit.']); return; }

        if (empty($this->event['cert_bg_image'])
            || trim((string)($this->event['cert_body_template'] ?? '')) === '') {
            $this->json(['success' => false,
                'message' => 'Configure the certificate background and body template first.']);
        }

        $offset = max(0, (int)($_POST['offset'] ?? 0));
        $limit  = max(1, min(20, (int)($_POST['limit'] ?? 5)));

        $total = (int)(Event::rowsRaw(
            "SELECT COUNT(*) AS c FROM event_registrations
              WHERE event_id = ? AND unit_id = ? AND admin_review_status = 'approved'",
            [$eid, $unitId]
        )[0]['c'] ?? 0);

        if ($total === 0) {
            $this->json([
                'success'    => true,
                'done'       => true,
                'total'      => 0,
                'processed'  => 0,
                'next_offset'=> 0,
                'summary'    => ['issued' => 0, 'existing' => 0, 'failed' => 0],
            ]);
        }

        $batch = Event::rowsRaw(
            "SELECT er.id, er.competitor_number
               FROM event_registrations er
              WHERE er.event_id = ? AND er.unit_id = ?
                AND er.admin_review_status = 'approved'
              ORDER BY er.competitor_number, er.id
              LIMIT ? OFFSET ?",
            [$eid, $unitId, $limit, $offset]
        );

        $issued = 0; $existing = 0; $failed = 0;
        $userName = (string)((Auth::user() ?? [])['name'] ?? '');
        foreach ($batch as $r) {
            $regId = (int)$r['id'];
            $had = Event::rowsRaw(
                "SELECT id FROM event_certificates WHERE event_id = ? AND registration_id = ?",
                [$eid, $regId]
            );
            if ($had) { $existing++; continue; }
            try {
                $allocated = $this->allocateCertNo($eid);
                Event::rowsRaw(
                    "INSERT INTO event_certificates
                        (event_id, registration_id, certificate_no, cert_no_sequence, generated_by_name)
                     VALUES (?, ?, ?, ?, ?)",
                    [$eid, $regId, $allocated['no'], $allocated['sequence'], $userName]
                );
                $newId = (int)(Event::rowsRaw(
                    "SELECT id FROM event_certificates
                      WHERE event_id = ? AND registration_id = ?
                      LIMIT 1",
                    [$eid, $regId]
                )[0]['id'] ?? 0);
                if ($newId > 0) $this->generatePdfForCert($newId);
                $issued++;
            } catch (\Throwable $e) {
                error_log('[certificates/generateChunk] ' . $e->getMessage());
                $failed++;
            }
        }

        $nextOffset = $offset + count($batch);
        $done = $nextOffset >= $total;
        if ($done) {
            try { $this->writeStatsDataset($eid); } catch (\Throwable $e) {}
        }
        $this->json([
            'success'     => true,
            'done'        => $done,
            'total'       => $total,
            'processed'   => count($batch),
            'next_offset' => $nextOffset,
            'summary'     => ['issued' => $issued, 'existing' => $existing, 'failed' => $failed],
        ]);
    }

    /**
     * POST /institution/events/{eventHash}/certificates/units/{unitId}/email-chunk
     * AJAX-driven chunked variant of emailForUnit() — paginates through
     * the unit's issued certs, sends N per request, returns running
     * counters so the browser progress UI can summarise the work.
     */
    public function emailChunkForUnit(string $eventHash, string $unitId): void
    {
        $this->boot($eventHash);
        $this->verifyCsrf();
        $eid    = (int)$this->event['id'];
        $unitId = (int)$unitId;
        if ($unitId <= 0) { $this->json(['success' => false, 'message' => 'Bad unit.']); return; }

        $offset = max(0, (int)($_POST['offset'] ?? 0));
        $limit  = max(1, min(10, (int)($_POST['limit'] ?? 3)));

        $total = (int)(Event::rowsRaw(
            "SELECT COUNT(*) AS c
               FROM event_certificates ec
               JOIN event_registrations er ON er.id = ec.registration_id
              WHERE ec.event_id = ? AND er.unit_id = ?",
            [$eid, $unitId]
        )[0]['c'] ?? 0);

        if ($total === 0) {
            $this->json([
                'success'    => true,
                'done'       => true,
                'total'      => 0,
                'processed'  => 0,
                'next_offset'=> 0,
                'summary'    => ['sent' => 0, 'skipped_no_email' => 0, 'failed' => 0],
            ]);
        }

        $batch = Event::rowsRaw(
            "SELECT ec.id AS cert_id, ec.certificate_no, ec.pdf_path,
                    a.name AS athlete_name, u.email AS athlete_email
               FROM event_certificates ec
               JOIN event_registrations er ON er.id = ec.registration_id
               JOIN athletes a            ON a.id = er.athlete_id
          LEFT JOIN users u               ON u.id = a.user_id
              WHERE ec.event_id = ? AND er.unit_id = ?
              ORDER BY ec.id
              LIMIT ? OFFSET ?",
            [$eid, $unitId, $limit, $offset]
        );

        $mailer = new Mailer();
        $sent = 0; $skippedNoEmail = 0; $failed = 0;
        foreach ($batch as $r) {
            $email = trim((string)($r['athlete_email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skippedNoEmail++;
                continue;
            }
            $pdfPath = (string)($r['pdf_path'] ?? '');
            if ($pdfPath === '' || !is_file($pdfPath)) {
                $pdfPath = (string)($this->generatePdfForCert((int)$r['cert_id']) ?? '');
            }
            if ($pdfPath === '' || !is_file($pdfPath)) { $failed++; continue; }
            try {
                $ok = $mailer->sendCertificate(
                    $email, (string)$r['athlete_name'], $this->event,
                    $pdfPath, (string)$r['certificate_no']
                );
                if ($ok) {
                    $sent++;
                    Event::rowsRaw(
                        "UPDATE event_certificates
                            SET emailed_at = NOW(), email_count = email_count + 1
                          WHERE id = ?",
                        [(int)$r['cert_id']]
                    );
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                error_log('[certificates/emailChunk] ' . $e->getMessage());
                $failed++;
            }
        }

        $nextOffset = $offset + count($batch);
        $this->json([
            'success'     => true,
            'done'        => $nextOffset >= $total,
            'total'       => $total,
            'processed'   => count($batch),
            'next_offset' => $nextOffset,
            'summary'     => [
                'sent' => $sent,
                'skipped_no_email' => $skippedNoEmail,
                'failed' => $failed,
            ],
        ]);
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
                    generated_by_name, registration_id, pdf_path
               FROM event_certificates
              WHERE id = ? AND event_id = ?",
            [$certId, $eid]
        );
        if (!$certs) $this->abort(404);
        // Stream the saved PDF when available; otherwise build it
        // lazily and stream that. The HTML render still wins if mPDF
        // is unhappy (fallback path).
        $cert = $certs[0];
        $path = (string)($cert['pdf_path'] ?? '');
        if ($path === '' || !is_file($path)) {
            $path = (string)($this->generatePdfForCert($certId) ?? '');
        }
        if ($path !== '' && is_file($path)) {
            $this->streamPdf($path, 'Certificate-' . preg_replace('/[^A-Za-z0-9._-]+/', '-',
                (string)$cert['certificate_no']) . '.pdf');
            return;
        }
        $this->renderCertificatePage($certs);
    }

    /**
     * POST /institution/events/{eventHash}/certificates/athlete-view-toggle
     * Flips the events.cert_athlete_view_enabled flag that gates the
     * "Certificate" button on the athlete portal.
     */
    public function toggleAthleteView(string $eventHash): void
    {
        $this->boot($eventHash);
        $this->verifyCsrf();
        $enabled = !empty($_POST['enabled']) ? 1 : 0;
        Event::updatePartial((int)$this->event['id'],
            ['cert_athlete_view_enabled' => $enabled]);
        $this->redirect("/institution/events/{$eventHash}/certificates",
            $enabled
                ? 'Athletes can now view their certificate from their portal.'
                : 'Athlete view disabled — the Certificate button is hidden.');
    }

    /**
     * GET /athlete/registrations/{regHash}/certificate
     * Athlete-side view of their own Competitor Certificate. Mirrors
     * the institution-side viewOne() but does its own auth + ownership
     * check so the athlete portal can link straight to the printable
     * cert from the My Registrations page.
     */
    public function viewForAthlete(string $regHash): void
    {
        try { Schema::ensureCertificates(); } catch (\Throwable $e) {}
        if (!Auth::check() || !Auth::is('athlete')) {
            $this->redirect('/login', 'Please sign in to continue.', 'warning');
        }
        $regId = (int)\hid_reg_decode($regHash);
        $athlete = \Models\Athlete::findByUserId(Auth::id());
        if (!$athlete) $this->abort(403);

        $reg = \Models\EventRegistration::findById($regId);
        if (!$reg || (int)$reg['athlete_id'] !== (int)$athlete['id']) $this->abort(404);

        $event = Event::findById((int)$reg['event_id']);
        if (!$event) $this->abort(404);
        $event['event_code'] = $event['event_code'] ?? \ensureEventCode((int)$event['id']);

        // Per-event gate: the organiser must opt in for athlete viewing.
        if (empty($event['cert_athlete_view_enabled'])) {
            $this->redirect('/athlete/my-registrations',
                'Certificate viewing is not enabled by the organiser for this event.',
                'warning');
        }

        $certs = Event::rowsRaw(
            "SELECT id, certificate_no, cert_no_sequence, generated_at,
                    generated_by_name, registration_id, pdf_path
               FROM event_certificates
              WHERE event_id = ? AND registration_id = ?
              ORDER BY id DESC LIMIT 1",
            [(int)$event['id'], $regId]
        );
        if (!$certs) {
            $this->redirect('/athlete/my-registrations',
                'No certificate has been issued for this event yet — please check back later.',
                'warning');
        }

        // renderCertificatePage() / generatePdfForCert() lean on the
        // boot()-populated $event + $institution properties, so set them
        // up directly for this auth-bypass path.
        $this->event       = $event;
        $this->institution = Institution::findById((int)$event['institution_id']) ?: [];

        // Prefer streaming the saved PDF — it locks the cert content
        // in the moment it was issued. If the file is missing (old
        // cert from before PDFs were persisted, or a regeneration
        // failed), build it lazily now and then stream.
        $cert = $certs[0];
        $path = (string)($cert['pdf_path'] ?? '');
        if ($path === '' || !is_file($path)) {
            $path = (string)($this->generatePdfForCert((int)$cert['id']) ?? '');
        }
        if ($path !== '' && is_file($path)) {
            $this->streamPdf($path, 'Certificate-' . preg_replace('/[^A-Za-z0-9._-]+/', '-',
                (string)$cert['certificate_no']) . '.pdf');
            return;
        }
        // Final fallback — render the HTML inline so the athlete can
        // still print from the browser even if the PDF renderer is
        // misconfigured on this server.
        $this->renderCertificatePage($certs);
    }

    /**
     * GET /institution/events/{eventHash}/certificates/diagnostic
     * One-shot health check the operator can hit from the certificate
     * landing page: verifies the mPDF library is autoloadable, picks
     * the storage root, creates the subdirectories, runs a tiny render,
     * and reports everything as plain text. Lets a brand-new deploy
     * confirm the PDF pipeline before generating in bulk.
     */
    public function diagnostic(string $eventHash): void
    {
        $this->boot($eventHash);
        header('Content-Type: text/plain; charset=UTF-8');
        $lines = [];
        $lines[] = 'PHP version       : ' . PHP_VERSION;
        $lines[] = 'APP_ROOT          : ' . APP_ROOT;
        $lines[] = 'Composer autoload : ' . (file_exists(dirname(APP_ROOT) . '/vendor/autoload.php')
            ? 'OK (' . dirname(APP_ROOT) . '/vendor/autoload.php)'
            : 'MISSING — upload the vendor/ directory or run `composer install`');
        $lines[] = 'mPDF class loaded : ' . (class_exists('\\Mpdf\\Mpdf') ? 'OK' : 'NO');
        $storageRoot = $this->resolveStorageRoot();
        $lines[] = 'storage root      : ' . ($storageRoot ?: 'UNAVAILABLE — neither '
            . dirname(APP_ROOT) . '/storage nor ' . APP_ROOT . '/storage is writable');
        if ($storageRoot) {
            $lines[] = '  certificates/   : ' . (is_dir($storageRoot . '/certificates') ? 'exists' : 'will be auto-created');
            $lines[] = '  mpdf-tmp/       : ' . (is_dir($storageRoot . '/mpdf-tmp')     ? 'exists' : 'will be auto-created');
        }
        // Try a smoke-render.
        $smokeOk = false; $smokeErr = '';
        if (class_exists('\\Mpdf\\Mpdf') && $storageRoot !== '') {
            $tmp = $storageRoot . '/mpdf-tmp';
            if (!is_dir($tmp)) @mkdir($tmp, 0775, true);
            try {
                $mpdf = new \Mpdf\Mpdf(['tempDir' => $tmp]);
                $mpdf->WriteHTML('<h1>Smoke test</h1><p>If you can read this PDF in storage/, the renderer is healthy.</p>');
                $smokePath = $storageRoot . '/diagnostic-' . date('Ymd-His') . '.pdf';
                $mpdf->Output($smokePath, \Mpdf\Output\Destination::FILE);
                if (is_file($smokePath) && filesize($smokePath) > 0) {
                    $smokeOk = true;
                    $lines[] = 'smoke render      : OK — wrote ' . filesize($smokePath) . ' bytes to ' . $smokePath;
                }
            } catch (\Throwable $e) {
                $smokeErr = $e->getMessage();
            }
        }
        if (!$smokeOk && $smokeErr !== '') {
            $lines[] = 'smoke render      : FAILED — ' . $smokeErr;
        }

        // Background image diagnostics — exactly what the renderer
        // would see for THIS event.
        $lines[] = '';
        $lines[] = '── Certificate background image ─────';
        $bgUrl = (string)($this->event['cert_bg_image'] ?? '');
        $lines[] = 'cert_bg_image     : ' . ($bgUrl !== '' ? $bgUrl : '(none configured)');
        $lines[] = 'DOCUMENT_ROOT     : ' . ((string)($_SERVER['DOCUMENT_ROOT'] ?? '(empty)'));
        if ($bgUrl !== '') {
            $tried = [];
            $resolved = $this->resolveLocalImagePath($bgUrl, $tried);
            foreach ($tried as $c) {
                $status = is_file($c) ? (is_readable($c) ? '✓ readable' : '✓ exists, NOT readable')
                                      : '✗ not found';
                $lines[] = '  candidate       : ' . $c . '   [' . $status . ']';
            }
            if ($resolved === $bgUrl) {
                $lines[] = 'resolved to       : (URL fallback — mPDF will try to fetch over HTTP, which may fail)';
            } else {
                $lines[] = 'resolved to       : ' . $resolved
                    . ' (' . filesize($resolved) . ' bytes)';
            }
            // Try a tiny render with the bg as a body background to
            // confirm mPDF can actually load it.
            if ($storageRoot !== '' && class_exists('\\Mpdf\\Mpdf')) {
                $tmp = $storageRoot . '/mpdf-tmp';
                try {
                    $m2 = new \Mpdf\Mpdf([
                        'tempDir' => $tmp, 'format' => 'A4', 'orientation' => 'P',
                        'margin_left' => 0, 'margin_right' => 0, 'margin_top' => 0, 'margin_bottom' => 0,
                        'margin_header' => 0, 'margin_footer' => 0,
                        'default_font' => 'dejavusans',
                        'autoScriptToLang' => false, 'autoLangToFont' => false,
                    ]);
                    $m2->SetHTMLHeader(
                        '<img src="' . htmlspecialchars($resolved, ENT_QUOTES) . '"'
                        . ' style="position:absolute;top:0;left:0;width:210mm;height:297mm;">'
                    );
                    $bgHtml = '<html><body>'
                            . '<div style="position:absolute;top:50mm;left:30mm;font-size:14pt">'
                            . 'BG smoke test — if you see the background image behind this text, mPDF can load it.'
                            . '</div></body></html>';
                    $m2->WriteHTML($bgHtml);
                    $bgPath = $storageRoot . '/diagnostic-bg-' . date('Ymd-His') . '.pdf';
                    $m2->Output($bgPath, \Mpdf\Output\Destination::FILE);
                    $lines[] = 'bg render test    : wrote ' . filesize($bgPath) . ' bytes to ' . $bgPath;
                } catch (\Throwable $e) {
                    $lines[] = 'bg render test    : FAILED — ' . $e->getMessage();
                }
            }
        }
        echo implode("\n", $lines) . "\n";
        exit;
    }

    /**
     * Resolve an uploaded image URL to a local filesystem path so mPDF
     * can read it without an outbound HTTP fetch. Tries the standard
     * deploy layouts (repo-root, app-only, document-root) and returns
     * the original string if nothing matches — mPDF can still resolve
     * absolute URLs via stream wrappers in that case. Returns null
     * candidates separately so the diagnostic page can show what was
     * tried.
     */
    private function resolveLocalImagePath(string $url, array &$tried = []): string
    {
        $tried = [];
        if ($url === '') return '';
        // Strip a host part if present, keep the path component.
        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) $path = $url;
        if (!str_starts_with($path, '/')) return $url;
        $docRoot = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
        $candidates = array_filter([
            APP_ROOT . '/public' . $path,
            dirname(APP_ROOT) . '/public' . $path,
            $docRoot !== '' ? rtrim($docRoot, '/') . $path : null,
            APP_ROOT . $path,
            dirname(APP_ROOT) . $path,
        ]);
        foreach ($candidates as $c) {
            $tried[] = $c;
            if (is_file($c) && is_readable($c)) return $c;
        }
        error_log('[CertificateController/pdf] cert_bg_image not on disk; '
            . 'tried: ' . implode(', ', $tried) . ' — falling back to URL: ' . $url);
        return $url;
    }

    /**
     * Pick a writable storage root for generated PDFs. Tries the repo-
     * root layout first (one level above APP_ROOT — keeps files outside
     * the web root, our default). If that isn't writable on this
     * deploy (e.g. cPanel uploads where only app/ landed on the
     * server) we fall back to APP_ROOT/storage which always travels
     * with the application directory. Returns '' if neither candidate
     * is usable so the caller can log a clear error.
     */
    private function resolveStorageRoot(): string
    {
        $candidates = [
            dirname(APP_ROOT) . '/storage',
            APP_ROOT . '/storage',
        ];
        foreach ($candidates as $c) {
            if (!is_dir($c)) {
                if (!@mkdir($c, 0775, true) && !is_dir($c)) continue;
            }
            if (is_writable($c)) return $c;
        }
        return '';
    }

    /**
     * Stream a saved PDF inline with the given filename. Used by both
     * the athlete view and the institution view-one path so a single
     * PDF lifecycle backs every download channel.
     */
    private function streamPdf(string $path, string $downloadName): void
    {
        $size = filesize($path);
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/pdf');
        header('Content-Length: ' . (int)$size);
        header('Content-Disposition: inline; filename="' . $downloadName . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=0, must-revalidate');
        readfile($path);
        exit;
    }

    /**
     * GET /institution/events/{eventHash}/certificates/register —
     * Certificate Issue Register: one row per generated certificate
     * with cert no, date / timestamp, competitor no, athlete name,
     * unit, and the count of individual + team events the athlete
     * participated in.
     */
    public function issueRegister(string $eventHash): void
    {
        $this->boot($eventHash);
        $eid = (int)$this->event['id'];

        $rows = Event::rowsRaw(
            "SELECT ec.id, ec.certificate_no, ec.cert_no_sequence,
                    ec.generated_at, ec.generated_by_name,
                    ec.registration_id,
                    er.competitor_number, er.athlete_id,
                    er.unit_name_other,
                    a.name AS athlete_name,
                    eu.name AS unit_name,
                    (SELECT COUNT(*) FROM event_registration_items eri
                       WHERE eri.registration_id = er.id) AS individual_count
               FROM event_certificates ec
          LEFT JOIN event_registrations er ON er.id  = ec.registration_id
          LEFT JOIN athletes            a  ON a.id   = er.athlete_id
          LEFT JOIN event_units         eu ON eu.id  = er.unit_id
              WHERE ec.event_id = ?
              ORDER BY ec.cert_no_sequence, ec.id",
            [$eid]
        );

        // Team-event count per athlete — only approved team
        // registrations the athlete is a member of for THIS event.
        $athleteIds = array_values(array_filter(array_map(
            fn($r) => $r['athlete_id'] !== null ? (int)$r['athlete_id'] : null,
            $rows
        )));
        $teamCounts = [];
        if ($athleteIds) {
            try {
                $tc = Event::rowsRaw(
                    "SELECT trm.athlete_id, COUNT(*) AS c
                       FROM team_registration_members trm
                       JOIN team_registrations tr ON tr.id = trm.team_registration_id
                      WHERE tr.event_id = ?
                        AND tr.admin_review_status = 'approved'
                        AND trm.athlete_id IN ("
                          . implode(',', array_map('intval', $athleteIds))
                          . ")
                      GROUP BY trm.athlete_id",
                    [$eid]
                );
                foreach ($tc as $t) {
                    $teamCounts[(int)$t['athlete_id']] = (int)$t['c'];
                }
            } catch (\Throwable $e) { /* team tables absent */ }
        }

        foreach ($rows as &$r) {
            // Recompose the cert no from the current prefix / suffix
            // so the register reflects the event's live settings.
            $seq = !empty($r['certificate_no'])
                 ? $this->extractSequenceFromCertNo((string)$r['certificate_no'], $this->event)
                 : null;
            if (!$seq && !empty($r['cert_no_sequence'])) {
                $seq = (int)$r['cert_no_sequence'];
            }
            $r['certificate_no'] = $this->composeCertNo($this->event, $seq ? (int)$seq : null);
            $r['team_count']     = $teamCounts[(int)$r['athlete_id']] ?? 0;
            $r['unit_label']     = $r['unit_name'] ?: ($r['unit_name_other'] ?? '');
        }
        unset($r);

        $this->renderWith('app', 'institution/certificates/register', [
            'event'     => $this->event,
            'eventHash' => $eventHash,
            'rows'      => $rows,
        ]);
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

    // ── Event statistics dataset ────────────────────────────────────────
    //
    // Every cert generate / reset writes a JSON snapshot of the event's
    // state (athletes, registrations, scores, relays, lanes, team
    // entries, medal points by unit / athlete) to a private storage
    // path. Downstream dashboards / exports read this file instead of
    // re-running the queries.

    private function statsStoragePath(int $eventId): string
    {
        $dir = APP_ROOT . '/storage/event-stats';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        return $dir . '/event-' . $eventId . '.json';
    }

    private function writeStatsDataset(int $eventId): void
    {
        try {
            $data = $this->buildStatsDataset($eventId);
            $path = $this->statsStoragePath($eventId);
            file_put_contents(
                $path,
                json_encode($data,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                LOCK_EX
            );
        } catch (\Throwable $e) {
            // Best-effort — never block certificate generation.
            error_log('[CertificateController] stats dump failed: ' . $e->getMessage());
        }
    }

    private function buildStatsDataset(int $eventId): array
    {
        $event  = Event::findById($eventId) ?: [];
        $points = [
            'indiv' => [
                'gold'   => (int)($event['medal_pts_indiv_gold']   ?? 5),
                'silver' => (int)($event['medal_pts_indiv_silver'] ?? 3),
                'bronze' => (int)($event['medal_pts_indiv_bronze'] ?? 2),
            ],
            'team'  => [
                'gold'   => (int)($event['medal_pts_team_gold']   ?? 5),
                'silver' => (int)($event['medal_pts_team_silver'] ?? 3),
                'bronze' => (int)($event['medal_pts_team_bronze'] ?? 2),
            ],
        ];
        $pointsFor = function (string $medal, bool $isTeam) use ($points): int {
            $bucket = $isTeam ? 'team' : 'indiv';
            return match (strtolower($medal)) {
                'gold'   => $points[$bucket]['gold'],
                'silver' => $points[$bucket]['silver'],
                'bronze' => $points[$bucket]['bronze'],
                default  => 0,
            };
        };

        $units = Event::rowsRaw(
            "SELECT id, name, address, logo FROM event_units
              WHERE event_id = ? ORDER BY name", [$eventId]);

        $regs = Event::rowsRaw(
            "SELECT er.id AS registration_id, er.athlete_id, er.unit_id,
                    er.competitor_number, er.unit_name_other,
                    er.admin_review_status,
                    a.name AS athlete_name, a.gender, a.date_of_birth,
                    eu.name AS unit_name
               FROM event_registrations er
          LEFT JOIN athletes      a  ON a.id  = er.athlete_id
          LEFT JOIN event_units   eu ON eu.id = er.unit_id
              WHERE er.event_id = ?
                AND er.admin_review_status = 'approved'
              ORDER BY eu.name, er.competitor_number, er.id",
            [$eventId]
        );

        $athletes = [];
        $unitPoints = []; $athletePoints = [];
        foreach ($regs as $r) {
            $rid = (int)$r['registration_id'];
            $aid = (int)$r['athlete_id'];
            $items = EventRegistration::items($rid);
            $rows  = $this->partBRows($eventId, $aid, $items);
            $entries = []; $teamEntries = []; $athleteTotal = 0;
            foreach ($rows as $row) {
                $medal  = (string)($row['remarks'] ?? '');
                $isTeam = (($row['kind'] ?? '') === 'Team');
                $pts    = in_array(strtolower($medal), ['gold','silver','bronze'], true)
                          ? $pointsFor($medal, $isTeam) : 0;
                $athleteTotal += $pts;
                $rec = [
                    'kind'     => $row['kind']  ?? '',
                    'event'    => $row['event'] ?? '',
                    'score'    => $row['score'] !== null ? (float)$row['score'] : null,
                    'position' => $row['position'] !== null ? (int)$row['position'] : null,
                    'medal'    => $medal !== '' ? $medal : null,
                    'points'   => $pts,
                ];
                if ($isTeam) $teamEntries[] = $rec;
                else         $entries[]     = $rec;
            }
            $athletes[] = [
                'registration_id'   => $rid,
                'athlete_id'        => $aid,
                'name'              => $r['athlete_name'],
                'gender'            => $r['gender'],
                'date_of_birth'     => $r['date_of_birth'],
                'unit_id'           => $r['unit_id']   !== null ? (int)$r['unit_id'] : null,
                'unit_name'         => $r['unit_name'] ?: ($r['unit_name_other'] ?? ''),
                'competitor_number' => $r['competitor_number'] !== null ? (int)$r['competitor_number'] : null,
                'events'            => $entries,
                'team_events'       => $teamEntries,
                'total_points'      => $athleteTotal,
            ];
            $athletePoints[$aid] = ($athletePoints[$aid] ?? 0) + $athleteTotal;
            $uid = (int)$r['unit_id'];
            if ($uid > 0) $unitPoints[$uid] = ($unitPoints[$uid] ?? 0) + $athleteTotal;
        }

        $scores = Event::rowsRaw(
            "SELECT id, event_sport_id, sport_category_id, athlete_id,
                    registration_id, competitor_number, unit_id,
                    team_registration_id, relay_id, lane_id,
                    grand_total, total_penalty, remarks, lane_status
               FROM score_entries WHERE event_id = ?", [$eventId]
        );

        $teams = [];
        try {
            $teamRows = Event::rowsRaw(
                "SELECT id, athlete_id, unit_id, event_sport_id, team_name,
                        admin_review_status
                   FROM team_registrations WHERE event_id = ?", [$eventId]
            );
            $teamIds = array_map('intval', array_column($teamRows, 'id'));
            $members = $teamIds ? Event::rowsRaw(
                "SELECT team_registration_id, athlete_id, registration_id,
                        competitor_number, position
                   FROM team_registration_members
                  WHERE team_registration_id IN (" . implode(',', $teamIds) . ")", []
            ) : [];
            $membersByTeam = [];
            foreach ($members as $m) {
                $membersByTeam[(int)$m['team_registration_id']][] = [
                    'athlete_id'         => (int)$m['athlete_id'],
                    'registration_id'    => $m['registration_id'] !== null ? (int)$m['registration_id'] : null,
                    'competitor_number'  => $m['competitor_number'] !== null ? (int)$m['competitor_number'] : null,
                    'position'           => $m['position'] !== null ? (int)$m['position'] : null,
                ];
            }
            foreach ($teamRows as $t) {
                $teams[] = [
                    'team_id'             => (int)$t['id'],
                    'team_name'           => $t['team_name'],
                    'unit_id'             => $t['unit_id'] !== null ? (int)$t['unit_id'] : null,
                    'event_sport_id'      => $t['event_sport_id'] !== null ? (int)$t['event_sport_id'] : null,
                    'admin_review_status' => $t['admin_review_status'],
                    'members'             => $membersByTeam[(int)$t['id']] ?? [],
                ];
            }
        } catch (\Throwable $e) { /* team tables absent */ }

        $relays = [];
        try {
            $relayRows = Event::rowsRaw(
                "SELECT er.id, er.relay_number, er.order_no, er.relay_date,
                        er.match_time, er.reporting_time, er.result_status,
                        erd.name AS distance_name, erd.distance_meters,
                        esr.name AS range_name
                   FROM event_relays er
              LEFT JOIN event_shooting_range_distances erd ON erd.id = er.shooting_range_distance_id
              LEFT JOIN event_shooting_ranges          esr ON esr.id = erd.shooting_range_id
                  WHERE er.event_id = ?
                  ORDER BY er.order_no, er.id",
                [$eventId]
            );
            $relayIds = array_map('intval', array_column($relayRows, 'id'));
            $lanes = $relayIds ? Event::rowsRaw(
                "SELECT erl.relay_id, erl.lane_id, erl.category,
                        erl.assigned_unit_id, erl.assigned_registration_id,
                        erl.allocated_by, erl.allocated_at,
                        esrl.lane_number, esrl.lane_type,
                        eu.name AS assigned_unit_name,
                        a.name  AS assigned_athlete_name,
                        er.competitor_number AS assigned_competitor_number
                   FROM event_relay_lanes erl
              LEFT JOIN event_shooting_range_lanes esrl ON esrl.id = erl.lane_id
              LEFT JOIN event_units              eu    ON eu.id   = erl.assigned_unit_id
              LEFT JOIN event_registrations      er    ON er.id   = erl.assigned_registration_id
              LEFT JOIN athletes                 a     ON a.id    = er.athlete_id
                  WHERE erl.relay_id IN (" . implode(',', $relayIds) . ")
                  ORDER BY erl.relay_id, esrl.lane_number", []
            ) : [];
            $lanesByRelay = [];
            foreach ($lanes as $l) {
                $lanesByRelay[(int)$l['relay_id']][] = [
                    'lane_id'                    => (int)$l['lane_id'],
                    'lane_number'                => $l['lane_number'] !== null ? (int)$l['lane_number'] : null,
                    'lane_type'                  => $l['lane_type'],
                    'category'                   => $l['category'],
                    'assigned_unit_id'           => $l['assigned_unit_id'] !== null ? (int)$l['assigned_unit_id'] : null,
                    'assigned_unit_name'         => $l['assigned_unit_name'],
                    'assigned_registration_id'   => $l['assigned_registration_id'] !== null ? (int)$l['assigned_registration_id'] : null,
                    'assigned_athlete_name'      => $l['assigned_athlete_name'],
                    'assigned_competitor_number' => $l['assigned_competitor_number'] !== null ? (int)$l['assigned_competitor_number'] : null,
                    'allocated_by'               => $l['allocated_by'],
                    'allocated_at'               => $l['allocated_at'],
                ];
            }
            foreach ($relayRows as $rr) {
                $relays[] = [
                    'relay_id'        => (int)$rr['id'],
                    'relay_number'    => $rr['relay_number'],
                    'order_no'        => $rr['order_no'] !== null ? (int)$rr['order_no'] : null,
                    'relay_date'      => $rr['relay_date'],
                    'match_time'      => $rr['match_time'],
                    'reporting_time'  => $rr['reporting_time'],
                    'result_status'   => $rr['result_status'],
                    'range_name'      => $rr['range_name'],
                    'distance_name'   => $rr['distance_name'],
                    'distance_meters' => $rr['distance_meters'] !== null ? (float)$rr['distance_meters'] : null,
                    'lanes'           => $lanesByRelay[(int)$rr['id']] ?? [],
                ];
            }
        } catch (\Throwable $e) { /* relay / range tables absent */ }

        $eventSports = Event::rowsRaw(
            "SELECT es.id, es.event_code, es.entry_fee, es.team_entry_fee,
                    sev.name AS sport_event_name, sev.gender, sev.category_id,
                    sc.name AS category_name
               FROM event_sports es
          LEFT JOIN sport_events     sev ON sev.id = es.sport_event_id
          LEFT JOIN sport_categories sc  ON sc.id  = sev.category_id
              WHERE es.event_id = ?", [$eventId]
        );

        return [
            'schema_version' => 1,
            'generated_at'   => date('c'),
            'event' => [
                'id'              => isset($event['id']) ? (int)$event['id'] : $eventId,
                'name'            => $event['name']            ?? null,
                'event_code'      => $event['event_code']      ?? null,
                'location'        => $event['location']        ?? null,
                'event_date_from' => $event['event_date_from'] ?? null,
                'event_date_to'   => $event['event_date_to']   ?? null,
                'reg_date_from'   => $event['reg_date_from']   ?? null,
                'reg_date_to'     => $event['reg_date_to']     ?? null,
                'medal_points'    => $points,
            ],
            'units' => array_map(fn($u) => [
                'id'           => (int)$u['id'],
                'name'         => $u['name'],
                'address'      => $u['address'],
                'logo'         => $u['logo'],
                'total_points' => $unitPoints[(int)$u['id']] ?? 0,
            ], $units),
            'event_sports' => array_map(fn($es) => [
                'id'               => (int)$es['id'],
                'event_code'       => $es['event_code'],
                'sport_event_name' => $es['sport_event_name'],
                'category_id'      => $es['category_id'] !== null ? (int)$es['category_id'] : null,
                'category_name'    => $es['category_name'],
                'gender'           => $es['gender'],
                'entry_fee'        => $es['entry_fee']      !== null ? (float)$es['entry_fee']      : null,
                'team_entry_fee'   => $es['team_entry_fee'] !== null ? (float)$es['team_entry_fee'] : null,
            ], $eventSports),
            'athletes'     => $athletes,
            'relays'       => $relays,
            'scores'       => array_map(fn($s) => [
                'id'                   => (int)$s['id'],
                'event_sport_id'       => $s['event_sport_id']       !== null ? (int)$s['event_sport_id']       : null,
                'sport_category_id'    => $s['sport_category_id']    !== null ? (int)$s['sport_category_id']    : null,
                'athlete_id'           => $s['athlete_id']           !== null ? (int)$s['athlete_id']           : null,
                'registration_id'      => $s['registration_id']      !== null ? (int)$s['registration_id']      : null,
                'competitor_number'    => $s['competitor_number']    !== null ? (int)$s['competitor_number']    : null,
                'unit_id'              => $s['unit_id']              !== null ? (int)$s['unit_id']              : null,
                'team_registration_id' => $s['team_registration_id'] !== null ? (int)$s['team_registration_id'] : null,
                'relay_id'             => $s['relay_id']             !== null ? (int)$s['relay_id']             : null,
                'lane_id'              => $s['lane_id']              !== null ? (int)$s['lane_id']              : null,
                'grand_total'          => $s['grand_total']          !== null ? (float)$s['grand_total']        : null,
                'total_penalty'        => $s['total_penalty']        !== null ? (float)$s['total_penalty']      : null,
                'remarks'              => $s['remarks'],
                'lane_status'          => $s['lane_status'],
            ], $scores),
            'team_entries' => $teams,
            'aggregates'   => [
                'by_unit'    => (object)$unitPoints,
                'by_athlete' => (object)$athletePoints,
            ],
        ];
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
                        ['kind' => 'Individual', 'event' => 'AP-001 · 10 m Air Pistol Senior Women', 'score' => 380, 'mqs' => 365, 'position' => 1, 'remarks' => 'Gold'],
                        ['kind' => 'Individual', 'event' => 'PR-004 · 50 m Rifle Prone',              'score' => 612, 'mqs' => 590, 'position' => 4, 'remarks' => ''],
                        ['kind' => 'Team',       'event' => 'AP-TM-01 · 10 m Air Pistol Team [Team]', 'score' => 1124,'mqs' => null,'position' => 2, 'remarks' => 'Silver'],
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
        echo $this->renderCertificateHtmlFromData($data);
    }

    /**
     * Variant of renderCertificatePage() that returns the composed HTML
     * instead of echoing it. Used by the PDF renderer when it falls
     * back to the browser-print template (legacy / debugging path).
     */
    private function renderCertificateHtmlFromData(array $data): string
    {
        extract($data);
        ob_start();
        require APP_ROOT . '/views/institution/certificates/print.php';
        return (string)ob_get_clean();
    }

    /**
     * Captures the mPDF-tuned template (no .cert-page wrapper; explicit
     * <pagebreak/> between certs). The data shape is identical so we
     * just `require` the second template with the same locals.
     */
    private function renderCertificatePdfHtmlFromData(array $data): string
    {
        extract($data);
        ob_start();
        require APP_ROOT . '/views/institution/certificates/print-pdf.php';
        return (string)ob_get_clean();
    }

    /**
     * Generate the PDF for one cert id, save it under storage/, and
     * stamp pdf_path + pdf_generated_at on the event_certificates row.
     * Returns the absolute filesystem path on success, null otherwise.
     * Safe to call repeatedly — overwrites the existing file so the
     * PDF stays in sync with the latest template / score state.
     */
    public function generatePdfForCert(int $certId): ?string
    {
        $eid = (int)$this->event['id'];
        $certs = Event::rowsRaw(
            "SELECT id, certificate_no, cert_no_sequence, generated_at,
                    generated_by_name, registration_id
               FROM event_certificates
              WHERE id = ? AND event_id = ?",
            [$certId, $eid]
        );
        if (!$certs) return null;

        // ── Compose the same data shape renderCertificatePage uses ──
        $registrations = [];
        foreach ($certs as $c) {
            $seq = null;
            if (!empty($c['certificate_no'])) {
                $seq = $this->extractSequenceFromCertNo((string)$c['certificate_no'], $this->event);
            }
            if (!$seq && !empty($c['cert_no_sequence'])) $seq = (int)$c['cert_no_sequence'];
            $c['certificate_no'] = $this->composeCertNo($this->event, $seq ? (int)$seq : null);

            $rid = (int)$c['registration_id'];
            $reg = EventRegistration::withProfile($rid);
            if (!$reg) continue;
            $items = EventRegistration::items($rid);
            $athlete = Athlete::findById((int)$reg['athlete_id']);
            $registrations[] = [
                'cert'    => $c,
                'reg'     => $reg,
                'athlete' => $athlete,
                'rows'    => $this->partBRows($eid, (int)$reg['athlete_id'], $items),
            ];
        }
        if (!$registrations) return null;

        $partbTop    = max(20, (int)($this->event['cert_partb_top_mm']    ?? 200));
        $partbBottom = max($partbTop + 20, (int)($this->event['cert_partb_bottom_mm'] ?? 250));
        $contTop     = max(5,  (int)($this->event['cert_partb_cont_top_mm']    ?? 60));
        $contBottom  = max($contTop + 20, (int)($this->event['cert_partb_cont_bottom_mm'] ?? 270));

        // Resolve the cert background image to a local filesystem path
        // so mPDF reads it directly (mPDF struggles with HTTPS fetches
        // on cPanel hosts where outbound is blocked). Tries a few
        // standard layouts; if none resolve, we keep the original URL
        // so mPDF can still try its own resolver.
        $bgImageOrig = (string)($this->event['cert_bg_image'] ?? '');
        $bgImage     = $this->resolveLocalImagePath($bgImageOrig);

        // Per-page geometry shared with the per-page partial.
        $data = [
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
            'showMqs'              => !empty($this->event['cert_show_mqs']),
        ];
        $bodyTemplate = (string)($this->event['cert_body_template'] ?? '');
        $h = fn($s) => htmlspecialchars((string)($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $fmtDate = function ($s) {
            if (!$s) return '';
            try { return (new \DateTimeImmutable($s))->format('d M Y'); }
            catch (\Throwable $e) { return (string)$s; }
        };
        $fmtDates = function ($from, $to) use ($fmtDate) {
            if (!$from) return '';
            if (!$to || $from === $to) return $fmtDate($from);
            return $fmtDate($from) . ' – ' . $fmtDate($to);
        };
        $ageYears = function ($dob) {
            if (!$dob) return '';
            try {
                return (int)(new \DateTimeImmutable($dob))->diff(new \DateTimeImmutable('today'))->y;
            } catch (\Throwable $e) { return ''; }
        };
        $render = function (string $tpl, array $vars) use ($h) {
            return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($m) use ($vars, $h) {
                return $h($vars[$m[1]] ?? '');
            }, $tpl);
        };

        // ── Build mPDF: A4 portrait, zero margins so the cert
        //    background image fills the page. Temp dir lives under
        //    storage/ to keep mPDF's scratch files off /tmp.
        if (!class_exists('\\Mpdf\\Mpdf')) {
            error_log('[CertificateController/pdf] mPDF library not installed — run `composer install` and ensure vendor/ is uploaded.');
            return null;
        }
        $storageRoot = $this->resolveStorageRoot();
        if ($storageRoot === '') {
            error_log('[CertificateController/pdf] Could not locate a writable storage root. '
                    . 'Tried: ' . dirname(APP_ROOT) . '/storage, ' . APP_ROOT . '/storage. '
                    . 'Create one of those folders and grant write permission to the PHP user.');
            return null;
        }
        $tempDir = $storageRoot . '/mpdf-tmp';
        if (!is_dir($tempDir) && !@mkdir($tempDir, 0775, true) && !is_dir($tempDir)) {
            error_log('[CertificateController/pdf] mpdf-tmp not writable: ' . $tempDir);
            return null;
        }
        $eventDir = $storageRoot . '/certificates/' . $eid;
        if (!is_dir($eventDir) && !@mkdir($eventDir, 0775, true) && !is_dir($eventDir)) {
            error_log('[CertificateController/pdf] certificates dir not writable: ' . $eventDir);
            return null;
        }
        $safeNo  = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string)$certs[0]['certificate_no']);
        $outPath = $eventDir . '/' . $certId . '-' . $safeNo . '.pdf';

        try {
            // Register Inter so the generated cert matches the browser
            // print preview. Falls back to DejaVu if Inter isn't on
            // disk (e.g. partial upload).
            $fontData = (new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'];
            $fontData['inter'] = [
                'R'  => 'Inter-Regular.ttf',
                'B'  => 'Inter-Bold.ttf',
                'I'  => 'Inter-Italic.ttf',
                'BI' => 'Inter-BoldItalic.ttf',
                'useOTL' => 0x00,
            ];
            $interOnDisk = is_file(dirname(APP_ROOT) . '/vendor/mpdf/mpdf/ttfonts/Inter-Regular.ttf');
            $mpdf = new \Mpdf\Mpdf([
                'tempDir'        => $tempDir,
                'format'         => 'A4',
                'orientation'    => 'P',
                'margin_left'    => 0,
                'margin_right'   => 0,
                'margin_top'     => 0,
                'margin_bottom'  => 0,
                'margin_header'  => 0,
                'margin_footer'  => 0,
                'fontdata'       => $fontData,
                // Match the on-screen template which requests Inter
                // first. Falls back to dejavusans if the font files
                // weren't uploaded to vendor/mpdf/mpdf/ttfonts/.
                'default_font'    => $interOnDisk ? 'inter' : 'dejavusans',
                'autoScriptToLang' => false,
                'autoLangToFont'   => false,
                'useSubstitutions' => false,
                // simpleTables = true sacrifices the column-width
                // calculator for speed and ends up letting the Event
                // column eat the SCORE / POSITION / REMARKS widths.
                // Keep the proper table renderer on so the Part B
                // table matches the browser print exactly.
                'simpleTables'     => false,
            ]);

            // Drive the page loop in PHP: for each cert page we call
            // AddPage + Image (bg) + WriteHTML(body fragment). This
            // gives us exact A4 dimensions on the bg image (via mPDF's
            // direct Image() API which honours mm units, unlike CSS
            // background-size), without needing absolute-positioned
            // <img> tags that mPDF turns into 1-cm tiles or trailing
            // blank pages.
            $isFirstPageOverall = true;
            foreach ($registrations as $r) {
                $cert    = $r['cert'];
                $reg     = $r['reg'];
                $athlete = $r['athlete'] ?? [];
                $rows    = $r['rows']    ?? [];
                $vars = [
                    'certificate_no' => $cert['certificate_no'] ?? '',
                    'date'           => $fmtDate($cert['generated_at'] ?? null),
                    'competitor_no'  => $reg['competitor_number']
                                         ? str_pad((string)(int)$reg['competitor_number'], 4, '0', STR_PAD_LEFT)
                                         : '',
                    'name'           => $reg['athlete_name'] ?? '',
                    'unit_name'      => $reg['unit_name']    ?? ($reg['unit_name_other'] ?? ''),
                    'unit_address'   => $reg['unit_address'] ?? '',
                    'event_name'     => $this->event['name']      ?? '',
                    'event_dates'    => $fmtDates(
                                          $this->event['event_date_from'] ?? null,
                                          $this->event['event_date_to']   ?? null
                                       ),
                    'event_location' => $this->event['location']  ?? '',
                    'age'            => $ageYears($reg['date_of_birth'] ?? null),
                    'gender'         => ucfirst((string)($reg['gender'] ?? '')),
                ];
                $bodyHtml = $render($bodyTemplate, $vars);
                // Chunk Part B rows into per-page slices.
                $rowChunks = [];
                if ($rows) {
                    $rowChunks[] = array_slice($rows, 0, $data['rows_first']);
                    foreach (array_chunk(array_slice($rows, $data['rows_first']), $data['rows_cont']) as $c) {
                        $rowChunks[] = $c;
                    }
                } else {
                    $rowChunks = [[]];
                }
                $totalPages = count($rowChunks);
                $globalNo   = 0;
                foreach ($rowChunks as $pi => $chunk) {
                    $isFirst = ($pi === 0);
                    $pageNo  = $pi + 1;
                    if ($isFirstPageOverall) {
                        $mpdf->AddPage();
                        $isFirstPageOverall = false;
                    } else {
                        $mpdf->AddPage();
                    }
                    // Paint the bg first, then the content goes on top.
                    if ($bgImage !== '') {
                        try {
                            $mpdf->Image($bgImage, 0, 0, 210, 297, '', '', true, true);
                        } catch (\Throwable $e) {
                            error_log('[CertificateController/pdf] bg Image() failed: ' . $e->getMessage());
                        }
                    }
                    // Render this page's body fragment via the partial.
                    $pageHtml = (function () use (
                        $cert, $reg, $athlete, $rows, $chunk, $vars, $bodyHtml,
                        $isFirst, $pageNo, $totalPages, $globalNo, $h, $data
                    ): string {
                        extract($data);
                        $global_no_offset = $globalNo;
                        ob_start();
                        require APP_ROOT . '/views/institution/certificates/print-pdf-page.php';
                        return (string)ob_get_clean();
                    })();
                    $globalNo += count($chunk);
                    $mpdf->WriteHTML($pageHtml, 2); // mode 2 = body fragment
                }
            }
            $mpdf->Output($outPath, \Mpdf\Output\Destination::FILE);
        } catch (\Throwable $e) {
            error_log('[CertificateController/pdf] mPDF render failed: ' . $e->getMessage());
            return null;
        }

        // Record the path + timestamp on the cert row.
        Event::rowsRaw(
            "UPDATE event_certificates
                SET pdf_path = ?, pdf_generated_at = NOW()
              WHERE id = ? AND event_id = ?",
            [$outPath, $certId, $eid]
        );
        return $outPath;
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
            $meta   = $this->lookupEventSportInfo($esId);
            $catId  = $meta['category_id'];
            $score  = $catId ? $this->scoreFor($eventId, $athleteId, $catId) : null;
            $position = null;
            if ($score && empty($score['skip_rank'])) {
                $position = $this->positionInEventSport($eventId, $esId, $catId, $athleteId, $score);
            }
            $rows[] = [
                'kind'     => 'Individual',
                'event'    => trim(($it['event_code'] ?? '') . ' · ' . ($it['sport_event_name'] ?? '')),
                'score'    => $score ? $score['grand_total'] : null,
                'mqs'      => $meta['mqs'],
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
                    'mqs'      => null,
                    'position' => $pos,
                    'remarks'  => $medalFor($pos, ''),
                ];
            }
        } catch (\Throwable $e) { /* team tables absent */ }

        return $rows;
    }

    /**
     * Per-event-sport metadata used to build a certificate's Part B row:
     * the sport_event's category (drives the score lookup) and the
     * per-event MQS configured on event_sports.
     */
    private function lookupEventSportInfo(int $eventSportId): array
    {
        $r = Event::rowsRaw(
            "SELECT sc.id AS category_id, es.mqs AS mqs
               FROM event_sports es
          LEFT JOIN sport_events     sev ON sev.id = es.sport_event_id
          LEFT JOIN sport_categories sc  ON sc.id = sev.category_id
              WHERE es.id = ?", [$eventSportId])[0] ?? null;
        return [
            'category_id' => ($r && $r['category_id'] !== null) ? (int)$r['category_id'] : null,
            'mqs'         => ($r && $r['mqs'] !== null && $r['mqs'] !== '') ? (float)$r['mqs'] : null,
        ];
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
