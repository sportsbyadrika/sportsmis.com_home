<?php
namespace Core;

/**
 * Appendix-B athletics entry forms (Dompdf) for one unit:
 *   B-I Track (Men) · B-II Track (Women) · B-III Field (Men) · B-IV Field (Women)
 *
 * Each configured Track/Field sport-event is listed with numbered competitor
 * slots and a Reserve column, filled from the data gathered by
 * Services\AppendixB. Empty sections (no events of that type/gender) are
 * skipped. The last page carries the submission-status line.
 */
class AppendixBPdf
{
    private const APPX = [
        'track|male'   => "B-I",
        'track|female' => "B-II",
        'field|male'   => "B-III",
        'field|female' => "B-IV",
    ];

    /** @param array $ctx from Services\AppendixB::gather() */
    public static function stream(array $ctx): void
    {
        $html = self::html($ctx);
        $ev   = $ctx['event'] ?? [];
        $code = trim((string)($ev['event_code'] ?? '')) ?: ('EVT' . (int)($ev['id'] ?? 0));
        $uname = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)($ctx['unit']['name'] ?? 'unit'));
        Pdf::stream($html, 'appendix-b-' . $code . '-' . $uname . '.pdf', 'A4', 'portrait', true);
    }

    private static function html(array $ctx): string
    {
        $ev   = $ctx['event'] ?? [];
        $unit = $ctx['unit'] ?? [];
        $e    = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $fmt  = function ($d) {
            $d = trim((string)$d);
            return ($d !== '' && ($ts = strtotime($d))) ? date('jS F Y', $ts) : '';
        };

        // Only sections that actually have events.
        $sections = array_values(array_filter($ctx['sections'] ?? [], fn($s) => !empty($s['events'])));
        $meetName = strtoupper((string)($ev['name'] ?? ''));
        $venue    = trim((string)($ev['location'] ?? ''));
        $from     = $fmt($ev['event_date_from'] ?? '');
        $to       = $fmt($ev['event_date_to'] ?? '');
        $when     = $from !== '' ? ('W.E.F. ' . $e($from) . ($to !== '' && $to !== $from ? ' TO ' . $e($to) : '')) : '';

        // Submission status line for the last page.
        if (!empty($ctx['submitted']) && !empty($ctx['submitted_at']) && ($ts = strtotime((string)$ctx['submitted_at']))) {
            $status = 'Submitted on ' . $e(date('d M Y, h:i A', $ts));
            $statusCls = 'ok';
        } else {
            $status = 'These details are not yet submitted.';
            $statusCls = 'pending';
        }

        $pages = '';
        $n = count($sections);
        foreach ($sections as $idx => $sec) {
            $key   = $sec['type'] . '|' . $sec['gender'];
            $appx  = self::APPX[$key] ?? 'B';

            // One <tr> per event, with the numbered competitor slots as a
            // nested table inside the "Name of the Competitor" cell. This keeps
            // each event atomic so page-break-inside:avoid can stop it being
            // split across pages (a rowspan group would otherwise orphan rows).
            $body = '';
            $sl = 0;
            foreach ($sec['events'] as $evt) {
                $sl++;
                $comp = $evt['competitors'] ?? [];
                if (!$comp) $comp = [''];
                $reserve = implode('<br>', array_map($e, array_filter($evt['reserves'] ?? [], fn($x) => trim((string)$x) !== '')));
                $inner = '';
                foreach ($comp as $i => $name) {
                    $last = ($i === count($comp) - 1) ? ' last' : '';
                    $inner .= '<tr><td class="num' . $last . '">' . ($i + 1) . '</td>'
                            . '<td class="nm' . $last . '">' . $e($name) . '</td></tr>';
                }
                $body .= '<tr class="evt">'
                    . '<td class="c">' . $sl . '</td>'
                    . '<td class="evn">' . $e($evt['name']) . '</td>'
                    . '<td class="comp"><table class="inner">' . $inner . '</table></td>'
                    . '<td class="reserve">' . $reserve . '</td>'
                    . '</tr>';
            }

            $formTitle = 'ENTRY FORM FOR ATHLETICS ' . strtoupper($sec['type'])
                       . ' EVENTS (' . ($sec['gender'] === 'male' ? 'MEN' : 'WOMEN') . ')';

            $pages .= '<div class="page' . ($idx > 0 ? ' brk' : '') . '">'
                . '<div class="appx">Appendix-&lsquo;' . $e($appx) . '&rsquo;</div>'
                . '<div class="meet">' . $e($meetName) . '</div>'
                . ($venue !== '' ? '<div class="meet-sub">HELD AT ' . $e(strtoupper($venue)) . '</div>' : '')
                . ($when !== '' ? '<div class="meet-sub">' . $when . '</div>' : '')
                . '<div class="ftitle">' . $e($formTitle) . '</div>'
                . '<table class="hdr">'
                . '<tr><td class="k">NAME OF THE UNIT</td><td class="v">: ' . $e($unit['name'] ?? '') . '</td></tr>'
                . '<tr><td class="k">NAME OF THE TEAM MANAGER</td><td class="v">:</td></tr>'
                . '<tr><td class="k">NAME OF THE TEAM CAPTAIN</td><td class="v">:</td></tr>'
                . '<tr><td class="k">MOBILE NO. THE TEAM MANAGER</td><td class="v">:</td></tr>'
                . '<tr><td class="k">MOBILE NO. THE TEAM CAPTAIN</td><td class="v">:</td></tr>'
                . '</table>'
                . '<table class="tbl"><thead><tr>'
                . '<th class="c" style="width:38px">Sl.No</th>'
                . '<th style="width:150px">Event</th>'
                . '<th>Name of the Competitor</th>'
                . '<th style="width:150px">Reserve</th>'
                . '</tr></thead><tbody>' . $body . '</tbody></table>'
                . '<div class="cert"><u>Certified that :-</u>'
                . '<ol><li>All competitors listed above have put in a minimum of 3 months service in this state police department.</li>'
                . '<li>All competitors listed above have completed 18 years (in age).</li></ol></div>'
                . '<div class="sign">Signature with Stamp of Competent Authority</div>'
                . '</div>';
        }

        if ($pages === '') {
            $pages = '<div class="page"><div class="empty">No Track / Field events have been classified for this event yet. '
                   . 'Ask the event administrator to set the Track / Field type under Sports in this Event &rarr; Bulk Edit. '
                   . 'The Nominal Roll (B-V) below still lists everyone the unit has registered.</div></div>';
        }

        // ── Appendix B-V — Nominal Roll ─────────────────────────────────────
        $roll = $ctx['roll'] ?? [];
        $rows = '';
        $minRows = 22;                          // keep the printed form full
        $count   = max(count($roll), $minRows);
        for ($i = 0; $i < $count; $i++) {
            $r = $roll[$i] ?? null;
            $rows .= '<tr>'
                . '<td class="c">' . ($i + 1) . '</td>'
                . '<td>' . ($r ? $e($r['gl_no'])  : '') . '</td>'
                . '<td>' . ($r ? $e($r['rank'])   : '') . '</td>'
                . '<td class="nm">' . ($r ? $e($r['name']) : '') . '</td>'
                . '<td>' . ($r ? $e($r['mobile']) : '') . '</td>'
                . '<td></td>'
                . '</tr>';
        }

        $sub      = $ctx['submitter'] ?? [];
        $officeNm = trim((string)($sub['office_name'] ?? ($unit['name'] ?? '')));
        $logoUri  = \Core\Pdf::imageDataUri((string)($sub['office_logo'] ?? ''));
        $photoUri = \Core\Pdf::imageDataUri((string)($sub['photo'] ?? ''));
        $person   = trim((string)($sub['person_name'] ?? ''));
        $mobile   = trim((string)($sub['mobile'] ?? ''));
        $email    = trim((string)($sub['email'] ?? ''));

        $submitBlock =
              '<table class="submit"><tr>'
            . '<td class="office">'
            .   ($logoUri !== '' ? '<img class="logo" src="' . $logoUri . '">' : '<div class="logo ph"></div>')
            .   '<div class="ometa"><div class="olabel">Representing Office / Unit</div>'
            .     '<div class="oname">' . $e($officeNm !== '' ? $officeNm : '—') . '</div></div>'
            . '</td>'
            . '<td class="person">'
            .   ($photoUri !== '' ? '<img class="photo" src="' . $photoUri . '">' : '<div class="photo ph"></div>')
            .   '<div class="pmeta"><div class="olabel">Submitted by</div>'
            .     '<div class="pname">' . $e($person !== '' ? $person : '—') . '</div>'
            .     ($mobile !== '' ? '<div class="pline"><b>Mob:</b> ' . $e($mobile) . '</div>' : '')
            .     ($email  !== '' ? '<div class="pline"><b>Email:</b> ' . $e($email) . '</div>' : '')
            .   '</div>'
            . '</td>'
            . '</tr></table>'
            . '<div class="status ' . $statusCls . '">' . $status . '</div>';

        $pages .= '<div class="page brk">'
            . '<div class="appx">Appendix-&lsquo;B-V&rsquo;</div>'
            . '<div class="meet">' . $e($meetName) . '</div>'
            . ($venue !== '' ? '<div class="meet-sub">HELD AT ' . $e(strtoupper($venue)) . '</div>' : '')
            . ($when !== '' ? '<div class="meet-sub">' . $when . '</div>' : '')
            . '<div class="ftitle">NOMINAL ROLL</div>'
            . '<table class="tbl roll"><thead><tr>'
            . '<th class="c" style="width:34px">Sl.No</th>'
            . '<th style="width:90px">GL. No</th>'
            . '<th style="width:120px">Rank</th>'
            . '<th>Name</th>'
            . '<th style="width:110px">Mob. No</th>'
            . '<th style="width:90px">Remarks</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>'
            . '<div class="ctitle">COMPOSITION OF TEAM</div>'
            . '<table class="tbl comp-team"><thead><tr>'
            . '<th>Unit</th><th>GOs</th><th>INSPs</th><th>SIs</th>'
            . '<th>ASIs</th><th>HCs</th><th>CTs</th><th>Total</th>'
            . '</tr></thead><tbody><tr>'
            . '<td class="nm">' . $e($officeNm) . '</td>'
            . '<td></td><td></td><td></td><td></td><td></td><td></td><td></td>'
            . '</tr></tbody></table>'
            . $submitBlock
            . '<div class="sign">Signature with Stamp of Competent Authority</div>'
            . '</div>';

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            * { font-family: "DejaVu Sans", sans-serif; }
            body { color: #111; font-size: 10.5px; margin: 0; }
            .page { padding: 6px 4px 0; }
            .brk  { page-break-before: always; }
            .appx { text-align: right; font-weight: bold; font-style: italic; font-size: 11px; margin-bottom: 4px; }
            .meet { text-align: center; font-weight: bold; font-size: 13px; text-transform: uppercase; line-height: 1.3; }
            .meet-sub { text-align: center; font-weight: bold; font-size: 11px; }
            .ftitle { text-align: center; font-weight: bold; text-decoration: underline; font-size: 12px; margin: 12px 0 8px; }
            table.hdr { width: 100%; margin: 0 0 10px 4px; border-collapse: collapse; }
            table.hdr td { padding: 2px 0; font-size: 11px; }
            table.hdr td.k { width: 42%; }
            table.tbl { width: 100%; border-collapse: collapse; }
            table.tbl > thead > tr > th,
            table.tbl > tbody > tr > td { border: 1px solid #333; padding: 3px 5px; vertical-align: middle; }
            table.tbl th { background: #f0f0f0; font-size: 10px; }
            table.tbl td.c { text-align: center; }
            table.tbl td.evn { vertical-align: middle; }
            table.tbl td.reserve { vertical-align: top; }
            table.tbl td.comp { padding: 0; }               /* nested table fills it */
            table.tbl tr.evt { page-break-inside: avoid; }
            /* Numbered competitor slots inside the competitor cell. */
            table.inner { width: 100%; border-collapse: collapse; }
            table.inner td { padding: 3px 5px; border-bottom: 1px solid #333; }
            table.inner td.last { border-bottom: 0; }
            table.inner td.num { width: 26px; text-align: center; color: #444; border-right: 1px solid #333; }
            .cert { margin-top: 10px; font-size: 10.5px; }
            .cert ol { margin: 2px 0 0 0; padding-left: 18px; }
            .cert li { margin-bottom: 2px; }
            .sign { margin-top: 34px; text-align: right; font-size: 11px; }
            .status { margin-top: 14px; font-size: 10.5px; font-weight: bold; padding: 5px 8px; border: 1px solid #bbb; display: inline-block; }
            .status.ok { color: #157347; border-color: #157347; }
            .status.pending { color: #b02a37; border-color: #b02a37; }
            .empty { text-align: center; color: #666; padding: 40px; border: 1px dashed #bbb; }
            /* B-V Nominal Roll */
            table.roll td { height: 19px; }
            table.roll td.nm { text-align: left; }
            .ctitle { text-align: center; font-weight: bold; text-decoration: underline;
                      font-size: 12px; margin: 16px 0 6px; }
            table.comp-team th, table.comp-team td { text-align: center; }
            table.comp-team td.nm { text-align: left; }
            /* Standard submission block */
            table.submit { width: 100%; border-collapse: collapse; margin-top: 16px;
                           page-break-inside: avoid; }
            table.submit td { border: 1px solid #333; padding: 8px 10px;
                              vertical-align: middle; width: 50%; }
            .submit img.logo  { width: 46px; height: 46px; object-fit: contain; float: left;
                                margin-right: 10px; }
            .submit img.photo { width: 46px; height: 56px; object-fit: cover; float: left;
                                margin-right: 10px; border: 1px solid #bbb; }
            .submit .ph { background: #f0f0f0; border: 1px solid #ccc; }
            .submit div.logo.ph  { width: 46px; height: 46px; float: left; margin-right: 10px; }
            .submit div.photo.ph { width: 46px; height: 56px; float: left; margin-right: 10px; }
            .submit .olabel { font-size: 8.5px; text-transform: uppercase; color: #666;
                              letter-spacing: .3px; }
            .submit .oname  { font-weight: bold; font-size: 12px; }
            .submit .pname  { font-weight: bold; font-size: 11px; }
            .submit .pline  { font-size: 9.5px; color: #333; }
            .submit .ometa, .submit .pmeta { overflow: hidden; }
        </style></head><body>' . $pages . '</body></html>';
    }
}
