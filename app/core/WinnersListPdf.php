<?php
namespace Core;

/**
 * Winners / Prize list (Dompdf) for a single competition date.
 *
 * Lists the First / Second / Third place holders of every sport-event held on
 * the chosen date, grouped by event name, with the athlete (or team) name and
 * their institution / unit. Winners come from the entered final-round ranks.
 *
 * @param array $ctx [
 *   'event'  => events row (name + logo),
 *   'date'   => 'Y-m-d',
 *   'groups' => [ ['event'=>label,'sub'=>'Category · Age · Gender',
 *                  'rows'=>[ ['medal'=>'First','name'=>..,'unit'=>..], ... ] ], ... ],
 * ]
 */
class WinnersListPdf
{
    private const MEDAL = [1 => 'First', 2 => 'Second', 3 => 'Third'];

    public static function stream(array $ctx): void
    {
        $html = self::html($ctx);
        $ev   = $ctx['event'] ?? [];
        $code = trim((string)($ev['event_code'] ?? '')) ?: ('EVT' . (int)($ev['id'] ?? 0));
        $date = (string)($ctx['date'] ?? '');
        Pdf::stream($html, 'winners-' . $code . '-' . $date . '.pdf', 'A4', 'portrait', true);
    }

    private static function html(array $ctx): string
    {
        $ev     = $ctx['event'] ?? [];
        $groups = $ctx['groups'] ?? [];
        $e      = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $date   = (string)($ctx['date'] ?? '');
        $dateLabel = ($date !== '' && ($ts = strtotime($date))) ? date('l, d F Y', $ts) : $date;
        $eventName = strtoupper((string)($ev['name'] ?? ''));
        $logo = Pdf::imageDataUriThumb((string)($ev['logo'] ?? ''), 120, 120);

        $body = '';
        foreach ($groups as $g) {
            $rows = '';
            foreach (($g['rows'] ?? []) as $r) {
                $medal = (string)($r['medal'] ?? '');
                $cls   = ['First' => 'm1', 'Second' => 'm2', 'Third' => 'm3'][$medal] ?? '';
                $rows .= '<tr>'
                    . '<td class="c md ' . $cls . '">' . $e($medal) . '</td>'
                    . '<td class="nm">' . $e(mb_strtoupper((string)($r['name'] ?? ''), 'UTF-8')) . '</td>'
                    . '<td>' . $e($r['unit'] ?? '') . '</td>'
                    . '</tr>';
            }
            if ($rows === '') continue;
            $sub = trim((string)($g['sub'] ?? ''));
            $body .= '<div class="grp">'
                . '<table class="tbl">'
                . '<thead>'
                . '<tr><td class="evt" colspan="3">' . $e($g['event'] ?? '')
                .   ($sub !== '' ? ' <span class="sub">' . $e($sub) . '</span>' : '') . '</td></tr>'
                . '<tr><th class="c" style="width:90px">Medal</th><th>Name</th><th style="width:38%">Institution / Unit</th></tr>'
                . '</thead>'
                . '<tbody>' . $rows . '</tbody>'
                . '</table>'
                . '</div>';
        }
        if ($body === '') {
            $body = '<div class="empty">No final results have been entered for events held on '
                  . $e($dateLabel !== '' ? $dateLabel : 'this date') . '.</div>';
        }

        $logoImg = $logo !== '' ? '<img class="lg" src="' . $logo . '">' : '';
        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            * { font-family: "DejaVu Sans", sans-serif; }
            body { color: #111; font-size: 11px; margin: 0; }
            .head { text-align: center; padding: 6px 0 10px; border-bottom: 2px solid #222; margin-bottom: 10px; }
            .head .lg { width: 46px; height: 46px; object-fit: contain; vertical-align: middle; margin-right: 8px; }
            .head .en { font-size: 16px; font-weight: bold; vertical-align: middle; }
            .head .ttl { font-size: 12px; font-weight: bold; margin-top: 4px; text-transform: uppercase; letter-spacing: .5px; }
            .head .dt { font-size: 11px; color: #444; margin-top: 2px; }
            .grp { page-break-inside: avoid; margin-bottom: 12px; }
            table.tbl { width: 100%; border-collapse: collapse; }
            table.tbl th, table.tbl td { border: 1px solid #333; padding: 4px 7px; vertical-align: middle; }
            table.tbl th { background: #f0f0f0; font-size: 10px; text-align: left; }
            table.tbl td.c, table.tbl th.c { text-align: center; }
            td.evt { background: #222; color: #fff; font-weight: bold; font-size: 12px; }
            td.evt .sub { font-weight: normal; font-size: 10px; color: #cfe0ff; }
            td.md { font-weight: bold; }
            td.md.m1 { color: #a9791c; } td.md.m2 { color: #6b7280; } td.md.m3 { color: #9a5a2b; }
            td.nm { font-weight: bold; }
            .empty { text-align: center; color: #666; padding: 40px; border: 1px dashed #bbb; }
        </style></head><body>'
        . '<div class="head">' . $logoImg . '<span class="en">' . $e($eventName) . '</span>'
        . '<div class="ttl">Prize Winners &mdash; First / Second / Third</div>'
        . '<div class="dt">' . $e($dateLabel) . '</div></div>'
        . $body
        . '</body></html>';
    }
}
