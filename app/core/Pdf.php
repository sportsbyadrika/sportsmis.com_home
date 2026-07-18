<?php
namespace Core;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Thin Dompdf wrapper for the unit reports (payment receipt, approved
 * participants list). Certificates keep using mPDF — this is deliberately
 * separate so the two renderers never interfere.
 *
 * Images must be embedded as data: URIs (isRemoteEnabled stays off) — use
 * Pdf::imageDataUri() to turn an uploaded logo URL into an inline image the
 * renderer can read without any outbound fetch.
 */
class Pdf
{
    /** Render an HTML string to raw PDF bytes. */
    public static function render(string $html, string $paper = 'A4', string $orientation = 'portrait'): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $tmp = self::tempDir();
        if ($tmp !== '') {
            $options->set('tempDir', $tmp);
            $options->set('fontCache', $tmp);
        }
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($paper, $orientation);
        $dompdf->render();
        return (string)$dompdf->output();
    }

    /** Render + stream inline with no-store headers, then exit. */
    public static function stream(string $html, string $downloadName,
                                  string $paper = 'A4', string $orientation = 'portrait'): void
    {
        $pdf = self::render($html, $paper, $orientation);
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . str_replace('"', '', $downloadName) . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        echo $pdf;
        exit;
    }

    /**
     * Resolve an uploaded image URL to an inline data: URI. Reads the file
     * from the local filesystem across the standard deploy layouts; returns
     * '' when the image can't be found or read so the caller can omit it.
     */
    public static function imageDataUri(?string $url): string
    {
        $url = trim((string)$url);
        if ($url === '') return '';

        // Already a data URI.
        if (str_starts_with($url, 'data:')) return $url;

        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $local = '';
        if (str_starts_with($path, '/')) {
            $docRoot = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
            $candidates = array_filter([
                APP_ROOT . '/public' . $path,
                dirname(APP_ROOT) . '/public' . $path,
                $docRoot !== '' ? rtrim($docRoot, '/') . $path : null,
                APP_ROOT . $path,
                dirname(APP_ROOT) . $path,
            ]);
            foreach ($candidates as $c) {
                if (is_file($c) && is_readable($c)) { $local = $c; break; }
            }
        } elseif (is_file($url) && is_readable($url)) {
            $local = $url;
        }
        if ($local === '') return '';

        $data = @file_get_contents($local);
        if ($data === false || $data === '') return '';

        $mime = 'image/png';
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) { $mime = finfo_file($fi, $local) ?: $mime; finfo_close($fi); }
        } else {
            $ext = strtolower(pathinfo($local, PATHINFO_EXTENSION));
            $mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                     'webp' => 'image/webp', 'gif' => 'image/gif'][$ext] ?? 'image/png';
        }
        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }

    /** A writable scratch directory for Dompdf's font cache / temp files. */
    private static function tempDir(): string
    {
        $candidates = [
            dirname(APP_ROOT) . '/storage/dompdf',
            APP_ROOT . '/storage/dompdf',
            sys_get_temp_dir() . '/dompdf',
        ];
        foreach ($candidates as $c) {
            if (!is_dir($c)) { @mkdir($c, 0775, true); }
            if (is_dir($c) && is_writable($c)) return $c;
        }
        return sys_get_temp_dir();
    }

    /**
     * Indian-format amount in words (rupees). Handles the lakh/crore grouping
     * used on receipts. Paise are rounded into the rupee for simplicity.
     */
    public static function amountInWords(float $amount): string
    {
        $rupees = (int)round($amount);
        if ($rupees === 0) return 'Zero Rupees Only';

        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
                 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
                 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        $two = function (int $n) use ($ones, $tens): string {
            if ($n < 20) return $ones[$n];
            return trim($tens[intdiv($n, 10)] . ' ' . $ones[$n % 10]);
        };
        $three = function (int $n) use ($ones, $two): string {
            $h = intdiv($n, 100); $r = $n % 100;
            $s = '';
            if ($h) $s .= $ones[$h] . ' Hundred';
            if ($r) $s .= ($h ? ' ' : '') . $two($r);
            return $s;
        };

        $crore = intdiv($rupees, 10000000); $rupees %= 10000000;
        $lakh  = intdiv($rupees, 100000);   $rupees %= 100000;
        $thou  = intdiv($rupees, 1000);     $rupees %= 1000;
        $hund  = $rupees;

        $parts = [];
        if ($crore) $parts[] = $three($crore) . ' Crore';
        if ($lakh)  $parts[] = $two($lakh) . ' Lakh';
        if ($thou)  $parts[] = $two($thou) . ' Thousand';
        if ($hund)  $parts[] = $three($hund);
        return trim(implode(' ', $parts)) . ' Rupees Only';
    }
}
