<?php
namespace Core;

/**
 * Unit-wise, date-wise athlete roster / attendance sheet (Dompdf).
 *
 * One unit per printed section, each starting on a fresh page. Every page
 * carries the event (name + logo) and the unit (name + logo) as a repeating
 * table header, then the athlete list: Sl.No, BIB, Name, Employee No,
 * Designation and a tick box for the team manager. A signature line for the
 * day closes each unit's section.
 *
 * @param array $ctx from Services\UnitDateRoster::gather()
 */
class UnitDateRosterPdf
{
    public static function stream(array $ctx): void
    {
        $html = self::html($ctx);
        $ev   = $ctx['event'] ?? [];
        $code = trim((string)($ev['event_code'] ?? '')) ?: ('EVT' . (int)($ev['id'] ?? 0));
        $date = (string)($ctx['date'] ?? '');
        Pdf::stream($html, 'unit-roster-' . $code . '-' . $date . '.pdf', 'A4', 'portrait', true);
    }

    private static function html(array $ctx): string
    {
        $ev    = $ctx['event'] ?? [];
        $units = $ctx['units'] ?? [];
        $e     = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $date  = (string)($ctx['date'] ?? '');
        $dateLabel = ($date !== '' && ($ts = strtotime($date))) ? date('l, d F Y', $ts) : $date;

        $eventName = strtoupper((string)($ev['name'] ?? ''));
        $eventLogo = Pdf::imageDataUri((string)($ev['logo'] ?? ''));

        $logoCell = function (string $uri) {
            return $uri !== ''
                ? '<img class="lg" src="' . $uri . '">'
                : '<span class="lg ph"></span>';
        };

        $pages = '';
        foreach ($units as $idx => $u) {
            $rows = '';
            $athletes = $u['athletes'] ?? [];
            $minRows  = 15;
            $count    = max(count($athletes), $minRows);
            for ($i = 0; $i < $count; $i++) {
                $a = $athletes[$i] ?? null;
                $rows .= '<tr>'
                    . '<td class="c">' . ($i + 1) . '</td>'
                    . '<td class="c">' . ($a ? $e($a['bib']) : '') . '</td>'
                    . '<td>' . ($a ? $e($a['name']) : '') . '</td>'
                    . '<td>' . ($a ? $e($a['employee']) : '') . '</td>'
                    . '<td>' . ($a ? $e($a['designation']) : '') . '</td>'
                    . '<td class="c"><span class="chk"></span></td>'
                    . '</tr>';
            }

            // Repeating header (event + unit) lives in the table's thead so
            // Dompdf re-prints it whenever a unit's list spans several pages.
            $head =
                  '<thead>'
                . '<tr><td class="hdr" colspan="6">'
                .   '<table class="htbl"><tr>'
                .     '<td class="hl">' . $logoCell($eventLogo)
                .       '<span class="htxt"><span class="hsm">Event</span>' . $e($eventName) . '</span></td>'
                .     '<td class="hr">' . $logoCell(Pdf::imageDataUri((string)($u['unit_logo'] ?? '')))
                .       '<span class="htxt"><span class="hsm">Unit</span>' . $e($u['unit_name'] ?? '') . '</span></td>'
                .   '</tr></table>'
                . '</td></tr>'
                . '<tr><td class="title" colspan="6">NOMINAL / ATTENDANCE ROLL &mdash; ' . $e($dateLabel) . '</td></tr>'
                . '<tr>'
                .   '<th class="c" style="width:34px">Sl.No</th>'
                .   '<th class="c" style="width:60px">BIB No</th>'
                .   '<th>Name of Athlete</th>'
                .   '<th style="width:110px">Employee No</th>'
                .   '<th style="width:120px">Designation</th>'
                .   '<th class="c" style="width:40px">&#10003;</th>'
                . '</tr>'
                . '</thead>';

            $pages .= '<div class="unit' . ($idx > 0 ? ' brk' : '') . '">'
                . '<table class="tbl">' . $head . '<tbody>' . $rows . '</tbody></table>'
                . '<table class="sign"><tr>'
                .   '<td class="sx">Total athletes: <strong>' . count($athletes) . '</strong></td>'
                .   '<td class="sy">Signature of Team Manager<br><span class="sd">(' . $e($dateLabel) . ')</span></td>'
                . '</tr></table>'
                . '</div>';
        }

        if ($pages === '') {
            $pages = '<div class="unit"><div class="empty">No athletes are scheduled to compete on '
                   . $e($dateLabel !== '' ? $dateLabel : 'this date') . '. '
                   . 'Check the Order of Events schedule for this day.</div></div>';
        }

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            * { font-family: "DejaVu Sans", sans-serif; }
            body { color: #111; font-size: 10.5px; margin: 0; }
            .unit { padding: 4px 2px 0; }
            .brk  { page-break-before: always; }
            table.tbl { width: 100%; border-collapse: collapse; }
            table.tbl > thead > tr > th,
            table.tbl > tbody > tr > td { border: 1px solid #333; padding: 4px 6px; vertical-align: middle; }
            table.tbl th { background: #f0f0f0; font-size: 9.5px; }
            table.tbl td.c, table.tbl th.c { text-align: center; }
            /* Header band (event + unit), repeated per page. */
            td.hdr { padding: 0 !important; border: 1px solid #333 !important; background: #fff; }
            table.htbl { width: 100%; border-collapse: collapse; }
            table.htbl td { width: 50%; padding: 6px 8px; vertical-align: middle; }
            table.htbl td.hl { border-right: 1px solid #333; }
            .lg { width: 34px; height: 34px; object-fit: contain; float: left; margin-right: 8px; }
            .ph { background: #f0f0f0; border: 1px solid #ccc; display: inline-block; }
            .htxt { display: block; overflow: hidden; font-weight: bold; font-size: 11px; line-height: 1.25; }
            .hsm  { display: block; font-weight: normal; font-size: 8px; text-transform: uppercase;
                    color: #666; letter-spacing: .3px; }
            td.title { text-align: center; font-weight: bold; text-decoration: underline;
                       font-size: 11px; padding: 5px !important; background: #fafafa; }
            .chk { display: inline-block; width: 12px; height: 12px; border: 1px solid #333; }
            table.sign { width: 100%; border-collapse: collapse; margin-top: 18px;
                         page-break-inside: avoid; }
            table.sign td { padding-top: 26px; font-size: 10.5px; vertical-align: bottom; }
            table.sign td.sx { text-align: left; }
            table.sign td.sy { text-align: right; }
            .sign .sd { font-weight: normal; color: #444; font-size: 9.5px; }
            .empty { text-align: center; color: #666; padding: 40px; border: 1px dashed #bbb; }
        </style></head><body>' . $pages . '</body></html>';
    }
}
