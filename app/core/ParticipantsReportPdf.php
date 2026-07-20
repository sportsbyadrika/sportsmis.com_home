<?php
namespace Core;

/**
 * Approved Participants List (Dompdf) for a unit — printed and signed by the
 * head of the institution. Contains, for one unit on one event:
 *   1. Approved athletes (photo, name, DOB, age, mobile, email, document, events)
 *   2. Approved team entries (when team entry is enabled)
 *   3. Payment transactions with their approval status
 *   4. A declaration + signature space, and the unit SPOC details.
 *
 * The controller gathers the data (access-checked) and hands it over.
 */
class ParticipantsReportPdf
{
    /**
     * @param array $ctx keys: event, institution, unit, athletes, teams,
     *                   txns, team_enabled
     */
    public static function stream(array $ctx): void
    {
        $html  = self::html($ctx);
        $code  = trim((string)($ctx['event']['event_code'] ?? '')) ?: ('EVT' . (int)($ctx['event']['id'] ?? 0));
        $uname = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)($ctx['unit']['name'] ?? 'unit'));
        Pdf::stream($html, 'participants-' . $code . '-' . $uname . '.pdf', 'A4', 'landscape');
    }

    private static function html(array $ctx): string
    {
        $ev    = $ctx['event'] ?? [];
        $inst  = $ctx['institution'] ?? [];
        $unit  = $ctx['unit'] ?? [];
        $athletes = $ctx['athletes'] ?? [];
        $teams    = $ctx['teams'] ?? [];
        $txns     = $ctx['txns'] ?? [];
        $teamEnabled = !empty($ctx['team_enabled']);

        $e     = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $money = fn($v) => 'Rs. ' . number_format((float)$v, 2);
        $compLabel = \Models\Event::competitorLabel($ev);   // e.g. "Chest Number"
        $fmtDate = function ($d) {
            $d = trim((string)$d);
            return ($d !== '' && ($ts = strtotime($d))) ? date('d M Y', $ts) : '—';
        };

        $unitLogo = Pdf::imageDataUri($unit['logo'] ?? '');
        $eventLogo = Pdf::imageDataUri($ev['logo'] ?? '');
        $logo = $unitLogo !== '' ? $unitLogo : $eventLogo;

        // ── Athletes rows ──
        $aRows = '';
        $i = 0;
        foreach ($athletes as $a) {
            $i++;
            $photo = Pdf::imageDataUri($a['photo'] ?? '');
            $photoCell = $photo !== ''
                ? '<img src="' . $photo . '" class="photo" alt="">'
                : '<div class="photo nophoto">&nbsp;</div>';
            $events = '';
            foreach (($a['events'] ?? []) as $evn) {
                $events .= '<div>' . $e($evn) . '</div>';
            }
            $ageCat = trim((string)($a['age_category'] ?? ''));
            $dobCell = $e($fmtDate($a['dob'] ?? ''))
                . ($ageCat !== '' ? '<div class="muted">' . $e($ageCat) . '</div>' : '');
            $compNo = trim((string)($a['competitor_no'] ?? ''));
            $addr2  = trim((string)($a['address'] ?? ''));
            $mob2   = trim((string)($a['mobile'] ?? ''));
            $aRows .= '<tr>'
                . '<td class="c">' . $i . '</td>'
                . '<td class="c">' . ($compNo !== '' ? '<strong>' . $e($compNo) . '</strong>' : '—') . '</td>'
                . '<td class="c">' . $photoCell . '</td>'
                . '<td>' . $e($a['name'] ?? '') . '</td>'
                . '<td class="c">' . $dobCell . '</td>'
                . '<td class="c">' . ($a['age'] !== null ? (int)$a['age'] : '—') . '</td>'
                . '<td class="c">' . $e($a['gender'] ?? '') . '</td>'
                . '<td>' . ($addr2 !== '' ? $e($addr2) : '<span class="muted">—</span>')
                    . ($mob2 !== '' ? '<div class="muted">Mob: ' . $e($mob2) . '</div>' : '') . '</td>'
                . '<td>' . $e($a['doc'] ?? '') . ($a['doc_no'] ? '<div class="muted">' . $e($a['doc_no']) . '</div>' : '') . '</td>'
                . '<td>' . ($events !== '' ? $events : '—') . '</td>'
                . '</tr>';
        }
        if ($aRows === '') {
            $aRows = '<tr><td colspan="10" class="c muted">No approved athletes.</td></tr>';
        }

        // ── Team rows ──
        $tRows = '';
        $ti = 0;
        foreach ($teams as $t) {
            $ti++;
            $members = '';
            foreach (($t['members'] ?? []) as $m) $members .= '<div>' . $e($m) . '</div>';
            $tRows .= '<tr>'
                . '<td class="c">' . $ti . '</td>'
                . '<td>' . $e($t['team_name'] ?? '') . '</td>'
                . '<td>' . $e($t['event'] ?? '') . '</td>'
                . '<td class="c">' . (int)($t['member_count'] ?? 0) . '</td>'
                . '<td>' . ($members !== '' ? $members : '—') . '</td>'
                . '</tr>';
        }
        if ($tRows === '') {
            $tRows = '<tr><td colspan="5" class="c muted">No approved team entries.</td></tr>';
        }

        // ── Transaction rows ──
        $xRows = ''; $xTotal = 0.0;
        foreach ($txns as $x) {
            $xTotal += (float)($x['amount'] ?? 0);
            $stCls = ['approved'=>'ok','submitted'=>'warn','pending'=>'warn','draft'=>'muted','rejected'=>'bad'][$x['status'] ?? ''] ?? '';
            $xRows .= '<tr>'
                . '<td class="c">' . $e($fmtDate($x['date'] ?? '')) . '</td>'
                . '<td>' . $e($x['channel'] ?? '') . '</td>'
                . '<td>' . $e($x['reference'] ?? '') . '</td>'
                . '<td class="r">' . $e($money($x['amount'] ?? 0)) . '</td>'
                . '<td class="c ' . $stCls . '">' . $e(ucfirst((string)($x['status'] ?? ''))) . '</td>'
                . '</tr>';
        }
        if ($xRows === '') {
            $xRows = '<tr><td colspan="5" class="c muted">No transactions.</td></tr>';
        }

        $addr = trim((string)($unit['address'] ?? ''));
        $spocBits = array_filter([
            trim((string)($unit['spoc_name'] ?? '')),
            trim((string)($unit['spoc_mobile'] ?? '')),
            trim((string)($unit['spoc_email'] ?? '')),
        ]);
        $spoc = implode(' · ', array_map($e, $spocBits));

        $teamSection = $teamEnabled ? (
            '<div class="sec-title">Team Entries</div>'
            . '<table class="tbl"><thead><tr>'
            . '<th class="c" style="width:34px">#</th><th>Team</th><th>Event</th>'
            . '<th class="c" style="width:70px">Members</th><th>Member Names</th>'
            . '</tr></thead><tbody>' . $tRows . '</tbody></table>'
        ) : '';

        $logoImg = $logo !== '' ? '<img src="' . $logo . '" class="ulogo" alt="">' : '';

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            * { font-family: "DejaVu Sans", sans-serif; }
            body { color: #1a1a1a; font-size: 10px; margin: 0; }
            .wrap { padding: 20px 24px; }
            .head { border-bottom: 2px solid #222; padding-bottom: 8px; }
            .head table { width: 100%; border-collapse: collapse; }
            .ulogo { max-height: 60px; max-width: 60px; }
            .u-name { font-size: 18px; font-weight: bold; }
            .u-school { font-size: 14px; font-weight: bold; color: #333; margin-top: 2px; }
            .u-sub { font-size: 10px; color: #555; }
            .doc-title { text-align: center; font-size: 14px; font-weight: bold;
                         letter-spacing: 1px; margin: 12px 0 6px; text-transform: uppercase; }
            .sec-title { font-size: 11px; font-weight: bold; margin: 14px 0 4px;
                         border-left: 3px solid #333; padding-left: 6px; }
            table.tbl { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
            table.tbl th, table.tbl td { border: 1px solid #bbb; padding: 3px 5px; vertical-align: top; }
            table.tbl th { background: #f0f0f0; text-align: left; font-size: 9px; text-transform: uppercase; }
            table.tbl td.c, table.tbl th.c { text-align: center; }
            table.tbl td.r, table.tbl th.r { text-align: right; }
            .photo { width: 34px; height: 42px; object-fit: cover; border: 1px solid #ccc; }
            .nophoto { background: #f3f3f3; display: inline-block; }
            .muted { color: #999; }
            .ok { color: #157347; font-weight: bold; }
            .warn { color: #b8860b; font-weight: bold; }
            .bad { color: #b02a37; font-weight: bold; }
            .total-row td { font-weight: bold; background: #fafafa; }
            .declare { margin-top: 18px; font-size: 10px; border: 1px solid #ccc; padding: 10px 12px; }
            .sign { margin-top: 34px; }
            .sign td { vertical-align: bottom; width: 50%; }
            .sign .line { border-top: 1px solid #333; padding-top: 4px; width: 240px; font-size: 10px; }
            .foot { margin-top: 14px; font-size: 9px; color: #555; border-top: 1px dashed #bbb; padding-top: 6px; }
        </style></head><body><div class="wrap">

            <div class="head"><table><tr>
                <td style="width:70px;">' . $logoImg . '</td>
                <td>
                    <div class="u-name">' . $e($ev['name'] ?? '') . '</div>'
                    . '<div class="u-school">' . $e($unit['name'] ?? '') . '</div>'
                    . ($addr !== '' ? '<div class="u-sub">' . $e($addr) . '</div>' : '')
                . '</td>
            </tr></table></div>

            <div class="doc-title">Approved Participants List</div>

            <div class="sec-title">Athletes</div>
            <table class="tbl"><thead><tr>
                <th class="c" style="width:26px">#</th>
                <th class="c" style="width:52px">' . $e($compLabel) . '</th>
                <th class="c" style="width:44px">Photo</th>
                <th>Name</th>
                <th class="c" style="width:78px">DOB<div class="muted" style="font-weight:normal;text-transform:none">Age Category</div></th>
                <th class="c" style="width:34px">Age</th>
                <th class="c" style="width:56px">Gender</th>
                <th style="width:150px">Address</th>
                <th>Document</th>
                <th>Events</th>
            </tr></thead><tbody>' . $aRows . '</tbody></table>

            ' . $teamSection . '

            <div class="sec-title">Payment Transactions</div>
            <table class="tbl"><thead><tr>
                <th class="c" style="width:80px">Date</th>
                <th style="width:110px">Channel</th>
                <th>Reference No.</th>
                <th class="r" style="width:110px">Amount</th>
                <th class="c" style="width:90px">Status</th>
            </tr></thead><tbody>' . $xRows . '</tbody>
            <tfoot><tr class="total-row">
                <td colspan="3" class="r">Total</td>
                <td class="r">' . $e($money($xTotal)) . '</td><td></td>
            </tr></tfoot></table>

            <div class="declare">
                I hereby declare that the above list of approved participants, team entries and payment
                transactions of <strong>' . $e($unit['name'] ?? '') . '</strong> for
                <strong>' . $e($ev['name'] ?? '') . '</strong> is true and correct to the best of my knowledge.
            </div>

            <table class="sign" style="width:100%;"><tr>
                <td>&nbsp;</td>
                <td style="text-align:right;">
                    <div style="display:inline-block;" class="line">
                        Signature &amp; Seal — Head of the Institution / Unit
                    </div>
                </td>
            </tr></table>'
            . ($spoc !== '' ? '<div class="foot"><strong>Unit SPOC:</strong> ' . $spoc . '</div>' : '')
            . '</div></body></html>';
    }
}
