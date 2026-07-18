<?php
namespace Core;

use Models\{Schema, UnitReceipt};

/**
 * Consolidated per-unit payment receipt (Dompdf). Issued by the event
 * organiser; also printable by the unit user. Covers every APPROVED bulk
 * transaction the unit made on the event.
 *
 *  Receipt No.  {event_code}/{serial}/{year}
 *  Receipt Date latest transaction-approval date
 *  No GST · event logo · event name + institution name subheading
 *  "Signed/- Authorized Signature" footer
 *
 * Callers validate access, then hand over the already-loaded event,
 * institution and unit rows.
 */
class UnitReceiptPdf
{
    /** True when the unit has at least one approved transaction to receipt. */
    public static function hasApproved(int $eventId, int $unitId): bool
    {
        return !empty(UnitReceipt::approvedTxns($eventId, $unitId));
    }

    /**
     * Build + stream the receipt PDF inline, then exit. $event / $institution
     * / $unit are pre-loaded, access-checked rows.
     */
    public static function stream(array $event, array $institution, array $unit): void
    {
        try { Schema::ensureUnitReceipts(); } catch (\Throwable $e) {}

        $eid  = (int)$event['id'];
        $uid  = (int)$unit['id'];
        $txns = UnitReceipt::approvedTxns($eid, $uid);

        $total = 0.0;
        foreach ($txns as $t) $total += (float)$t['amount'];

        $serial   = UnitReceipt::serialFor($eid, $uid);
        $code     = trim((string)($event['event_code'] ?? '')) ?: ('EVT' . $eid);
        $year     = self::yearOf($event);
        $receiptNo = $code . '/' . $serial . '/' . $year;

        // Receipt date = latest approval date across the transactions.
        $receiptDate = '';
        foreach ($txns as $t) {
            $r = (string)($t['reviewed_at'] ?? '');
            if ($r !== '' && $r > $receiptDate) $receiptDate = $r;
        }
        $receiptDateFmt = $receiptDate !== '' ? date('d M Y', strtotime($receiptDate)) : date('d M Y');

        $html = self::html([
            'event'        => $event,
            'institution'  => $institution,
            'unit'         => $unit,
            'txns'         => $txns,
            'total'        => $total,
            'receipt_no'   => $receiptNo,
            'receipt_date' => $receiptDateFmt,
        ]);

        $fname = 'receipt-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $code . '-' . $serial) . '.pdf';
        Pdf::stream($html, $fname, 'A4', 'portrait');
    }

    private static function yearOf(array $event): string
    {
        $from = (string)($event['event_date_from'] ?? '');
        if ($from !== '' && ($ts = strtotime($from))) return date('Y', $ts);
        return date('Y');
    }

    private static function html(array $c): string
    {
        $ev   = $c['event'];
        $inst = $c['institution'];
        $unit = $c['unit'];
        $txns = $c['txns'];

        $e     = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $money = fn($v) => 'Rs. ' . number_format((float)$v, 2);

        $eventLogo = Pdf::imageDataUri($ev['logo'] ?? '');
        $instLogo  = Pdf::imageDataUri($inst['logo'] ?? '');
        $logo      = $eventLogo !== '' ? $eventLogo : $instLogo;

        $rows = '';
        $i = 0;
        foreach ($txns as $t) {
            $i++;
            $txDate = !empty($t['transaction_date']) ? date('d M Y', strtotime((string)$t['transaction_date'])) : '—';
            $apDate = !empty($t['reviewed_at'])       ? date('d M Y', strtotime((string)$t['reviewed_at']))       : '—';
            $rows .= '<tr>'
                . '<td class="c">' . $i . '</td>'
                . '<td>' . $e($txDate) . '</td>'
                . '<td>' . $e($t['reference_number'] ?? '') . '</td>'
                . '<td>' . $e($apDate) . '</td>'
                . '<td class="r">' . $e($money($t['amount'] ?? 0)) . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="5" class="c muted">No approved transactions.</td></tr>';
        }

        $words   = Pdf::amountInWords((float)$c['total']);
        $logoImg = $logo !== '' ? '<img src="' . $logo . '" class="logo" alt="">' : '';
        $addr    = trim((string)($unit['address'] ?? ''));

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            * { font-family: "DejaVu Sans", sans-serif; }
            body { color: #1a1a1a; font-size: 12px; margin: 0; }
            .wrap { padding: 28px 34px; }
            .head { border-bottom: 2px solid #222; padding-bottom: 12px; }
            .head table { width: 100%; border-collapse: collapse; }
            .logo { max-height: 74px; max-width: 74px; }
            .ev-name { font-size: 20px; font-weight: bold; }
            .ev-sub { font-size: 12px; color: #555; margin-top: 2px; }
            .doc-title { text-align: center; font-size: 15px; font-weight: bold;
                         letter-spacing: 2px; margin: 16px 0 4px; text-transform: uppercase; }
            .meta { width: 100%; border-collapse: collapse; margin: 10px 0 4px; }
            .meta td { padding: 3px 0; vertical-align: top; }
            .meta .lbl { color: #666; width: 92px; }
            .party { margin: 12px 0 6px; }
            .party .lbl { color: #666; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; }
            .party .val { font-size: 13px; font-weight: bold; }
            .party .addr { color: #444; font-size: 11px; }
            table.txn { width: 100%; border-collapse: collapse; margin-top: 10px; }
            table.txn th, table.txn td { border: 1px solid #bbb; padding: 6px 8px; }
            table.txn th { background: #f0f0f0; text-align: left; font-size: 11px; text-transform: uppercase; }
            table.txn td.c, table.txn th.c { text-align: center; }
            table.txn td.r, table.txn th.r { text-align: right; }
            .total-row td { border: 1px solid #bbb; padding: 7px 8px; font-weight: bold; background: #fafafa; }
            .words { margin-top: 6px; font-size: 11px; }
            .words .lbl { color: #666; }
            .note { margin-top: 14px; font-size: 10.5px; color: #666; }
            .sign { margin-top: 46px; }
            .sign td { vertical-align: bottom; }
            .sign .line { border-top: 1px solid #333; padding-top: 4px; width: 220px; text-align: center; font-size: 11px; }
            .signed { font-size: 13px; font-style: italic; margin-bottom: 6px; }
            .muted { color: #999; }
        </style></head><body><div class="wrap">

            <div class="head"><table><tr>
                <td style="width:80px;">' . $logoImg . '</td>
                <td>
                    <div class="ev-name">' . $e($ev['name'] ?? '') . '</div>
                    <div class="ev-sub">' . $e($inst['name'] ?? '') . '</div>
                </td>
            </tr></table></div>

            <div class="doc-title">Payment Receipt</div>

            <table class="meta"><tr>
                <td><table class="meta">
                    <tr><td class="lbl">Receipt No.</td><td><strong>' . $e($c['receipt_no']) . '</strong></td></tr>
                    <tr><td class="lbl">Receipt Date</td><td>' . $e($c['receipt_date']) . '</td></tr>
                </table></td>
            </tr></table>

            <div class="party">
                <div class="lbl">Received with thanks from</div>
                <div class="val">' . $e($unit['name'] ?? '') . '</div>'
                . ($addr !== '' ? '<div class="addr">' . $e($addr) . '</div>' : '') . '
            </div>

            <table class="txn">
                <thead><tr>
                    <th class="c" style="width:34px;">#</th>
                    <th>Transaction Date</th>
                    <th>Reference No.</th>
                    <th>Approved On</th>
                    <th class="r">Amount</th>
                </tr></thead>
                <tbody>' . $rows . '</tbody>
                <tfoot><tr class="total-row">
                    <td colspan="4" class="r">Total Received</td>
                    <td class="r">' . $e($money($c['total'])) . '</td>
                </tr></tfoot>
            </table>

            <div class="words"><span class="lbl">Amount in words:</span> <strong>' . $e($words) . '</strong></div>

            <div class="note">
                This is a payment receipt issued against bank transfer(s) received towards event participation fees.
                No GST is applicable on this receipt.
            </div>

            <table class="sign" style="width:100%;"><tr>
                <td>&nbsp;</td>
                <td style="text-align:right;">
                    <div class="signed">Signed/-</div>
                    <div style="display:inline-block;" class="line">Authorized Signature<br>'
                    . $e($inst['name'] ?? '') . '</div>
                </td>
            </tr></table>

        </div></body></html>';
    }
}
