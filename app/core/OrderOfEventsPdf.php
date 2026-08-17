<?php
namespace Core;

use Models\OrderOfEvents;

/**
 * Order of Events (competition programme) — Dompdf report.
 *
 * Rows are grouped by scheduled date (a heading per day), each day ordered by
 * time then serial number. Rows with no date are grouped under "Unscheduled".
 * The controller gathers the (access-checked) rows and hands them over.
 */
class OrderOfEventsPdf
{
    /** @param array $ctx keys: event, rows, filter */
    public static function stream(array $ctx): void
    {
        $html = self::html($ctx);
        $ev   = $ctx['event'] ?? [];
        $code = trim((string)($ev['event_code'] ?? '')) ?: ('EVT' . (int)($ev['id'] ?? 0));
        $tag  = trim((string)($ctx['filter'] ?? '')) !== '' ? ('-' . $ctx['filter']) : '-all';
        Pdf::stream($html, 'order-of-events-' . $code . $tag . '.pdf', 'A4', 'portrait', true);
    }

    private static function html(array $ctx): string
    {
        $ev     = $ctx['event'] ?? [];
        $rows   = $ctx['rows'] ?? [];
        $filter  = trim((string)($ctx['filter'] ?? ''));
        $counts  = $ctx['counts'] ?? [];   // event_sports.id => athlete count
        $filters = $ctx['filters'] ?? [];  // category / age / gender selections

        $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $fmtDate = function ($d) {
            $d = trim((string)$d);
            return ($d !== '' && ($ts = strtotime($d))) ? date('l, d M Y', $ts) : '';
        };
        $fmtTime = function ($t) {
            $t = trim((string)$t);
            return ($t !== '' && ($ts = strtotime($t))) ? date('h:i A', $ts) : '';
        };
        // Group rows by date key ('' = unscheduled), preserving the model's
        // ordering (time, then serial number) within each group.
        $groups = [];
        foreach ($rows as $r) {
            $key = (string)($r['order_date'] ?? '');
            $groups[$key][] = $r;
        }
        // Dated groups ascending first, unscheduled last.
        uksort($groups, function ($a, $b) {
            if ($a === '') return 1;
            if ($b === '') return -1;
            return strcmp($a, $b);
        });

        $logo    = Pdf::imageDataUri($ev['logo'] ?? '');
        $logoImg = $logo !== '' ? '<img class="elogo" src="' . $logo . '" alt="">' : '';

        $sections = '';
        if (!$rows) {
            $sections = '<div class="empty">No sport-events in the programme'
                      . ($filter !== '' ? ' for the selected date' : '') . '.</div>';
        }
        foreach ($groups as $dateKey => $grp) {
            $heading = $dateKey === '' ? 'Unscheduled' : $e($fmtDate($dateKey));
            $body = '';
            $i = 0;
            foreach ($grp as $r) {
                $i++;
                $sl   = ($r['order_sl_no'] !== null && $r['order_sl_no'] !== '')
                          ? (int)$r['order_sl_no'] : '';
                $time = $fmtTime($r['order_time'] ?? '');
                $sport = trim((string)($r['sport_name'] ?? ''));
                $cat  = trim((string)($r['sport_event_category'] ?? ''));
                $sev  = trim((string)($r['sport_event_name'] ?? ''));
                $age  = trim((string)($r['sport_event_age_category'] ?? ''));
                $athletes = (int)($counts[(int)$r['id']] ?? 0);

                // Bold event label (the specific sport-event, with age category
                // when present), and the sport as a small sub-heading.
                $label = $sev !== '' ? $sev : ($cat !== '' ? $cat : $sport);
                $eventCell = '<strong>' . $e($label) . '</strong>';
                if ($age !== '') $eventCell .= ' <span class="age">· ' . $e($age) . '</span>';
                if ($sport !== '') $eventCell .= '<div class="sub">' . $e($sport) . '</div>';

                $body .= '<tr>'
                    . '<td class="c">' . ($sl !== '' ? $sl : '—') . '</td>'
                    . '<td class="c">' . ($time !== '' ? $e($time) : '—') . '</td>'
                    . '<td>' . $eventCell . '</td>'
                    . '<td class="c">' . $athletes . '</td>'
                    . '<td class="c">' . $e(OrderOfEvents::statusLabel($r['order_call_status'] ?? '')) . '</td>'
                    . '</tr>';
            }
            $sections .= '<div class="day">'
                . '<div class="day-title">' . $heading . ' <span class="count">('
                . count($grp) . ' event' . (count($grp) === 1 ? '' : 's') . ')</span></div>'
                . '<table class="tbl"><thead><tr>'
                . '<th class="c" style="width:44px">Sl.</th>'
                . '<th class="c" style="width:80px">Time</th>'
                . '<th>Event</th>'
                . '<th class="c" style="width:64px">Athletes</th>'
                . '<th class="c" style="width:110px">Call Status</th>'
                . '</tr></thead><tbody>' . $body . '</tbody></table>'
                . '</div>';
        }

        // Base scope reflects the Day filter; extra filters are appended.
        if ($filter === 'unscheduled') {
            $scopeLabel = 'Unscheduled Events';
        } elseif ($filter !== '') {
            $scopeLabel = 'Programme for ' . $e($fmtDate($filter));
        } else {
            $scopeLabel = 'Full Programme — All Days';
        }
        $genLabel = function ($g) use ($ev) {
            $g = strtolower(trim((string)$g));
            if ($g === '') return '';
            if (\function_exists('genderLabel')) return \genderLabel($g, $ev);
            return match ($g) { 'male' => 'Men', 'female' => 'Women', 'mixed' => 'Mixed', default => ucfirst($g) };
        };
        $chips = array_filter([
            trim((string)($filters['category'] ?? '')),
            trim((string)($filters['age'] ?? '')),
            $genLabel($filters['gender'] ?? ''),
        ], fn($v) => $v !== '');
        if ($chips) {
            $scopeLabel .= ' &middot; ' . $e(implode(' · ', $chips));
        }

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            * { font-family: "DejaVu Sans", sans-serif; }
            body { color: #1a1a1a; font-size: 10.5px; margin: 0; }
            .wrap { padding: 18px 22px; }
            .head { border-bottom: 2px solid #222; padding-bottom: 8px; }
            .head table { width: 100%; border-collapse: collapse; }
            .elogo { max-height: 58px; max-width: 58px; }
            .e-name { font-size: 17px; font-weight: bold; }
            .e-sub { font-size: 10px; color: #555; margin-top: 2px; }
            .doc-title { text-align: center; font-size: 14px; font-weight: bold;
                         letter-spacing: 1px; margin: 12px 0 2px; text-transform: uppercase; }
            .scope { text-align: center; font-size: 10px; color: #555; margin-bottom: 8px; }
            .day { margin-top: 12px; }
            .day-title { font-size: 12px; font-weight: bold; background: #eef1f4;
                         border-left: 3px solid #333; padding: 4px 8px; }
            .day-title .count { font-weight: normal; color: #666; font-size: 10px; }
            table.tbl { width: 100%; border-collapse: collapse; margin-top: 3px; }
            table.tbl th, table.tbl td { border: 1px solid #bbb; padding: 4px 6px; vertical-align: top; }
            table.tbl th { background: #f5f5f5; text-align: left; font-size: 9px; text-transform: uppercase; }
            table.tbl td.c, table.tbl th.c { text-align: center; }
            .sub  { font-size: 9px; color: #666; margin-top: 1px; }
            .age  { font-weight: normal; color: #555; font-size: 9.5px; }
            .empty { text-align: center; color: #888; padding: 30px; border: 1px dashed #ccc; margin-top: 16px; }
            .foot { margin-top: 16px; font-size: 8.5px; color: #666; border-top: 1px dashed #bbb; padding-top: 6px; }
        </style></head><body><div class="wrap">
            <div class="head"><table><tr>
                <td style="width:66px;">' . $logoImg . '</td>
                <td>
                    <div class="e-name">' . $e($ev['name'] ?? '') . '</div>'
                    . (trim((string)($ev['location'] ?? '')) !== ''
                        ? '<div class="e-sub">' . $e($ev['location']) . '</div>' : '')
                    . '<div class="e-sub">Event Code: ' . $e($ev['event_code'] ?? '') . '</div>'
                . '</td>
            </tr></table></div>
            <div class="doc-title">Order of Events</div>
            <div class="scope">' . $scopeLabel . '</div>'
            . $sections
            . '<div class="foot">Generated on ' . $e(date('d M Y, h:i A'))
            . ' · SportsMIS&reg;</div>'
            . '</div></body></html>';
    }
}
