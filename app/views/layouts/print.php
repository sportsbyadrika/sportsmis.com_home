<?php
/**
 * Minimal layout for printable reports. Strips the app chrome and ships
 * Bootstrap + a small print stylesheet. Designed for A4 portrait with
 * a "Page N of M" running footer (Chromium / Edge honour the @page
 * counter; Firefox falls back to its own browser-supplied page numbers).
 *
 * Pages that use this layout should:
 *   - put their own <h1> headings inside the body
 *   - set $pageTitle for the <title> tag
 *   - mark each new section that should start on a fresh sheet with
 *     class="page-break"
 */
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? 'Print') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    @page {
      size: A4 portrait;
      margin: 18mm 14mm 22mm 14mm;
      @bottom-right {
        content: "Page " counter(page) " of " counter(pages);
        font-size: 9pt;
        color: #666;
      }
    }
    html, body { background: #fff; color: #111; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 11pt; }
    h1, h2, h3, h4, h5, h6 { page-break-after: avoid; }
    table { width: 100%; border-collapse: collapse; }
    table thead { display: table-header-group; }
    table tfoot { display: table-footer-group; }
    table tr, table td, table th { page-break-inside: avoid; }
    table th, table td { padding: 5px 8px; border: 1px solid #444; vertical-align: middle; }
    table thead th { background: #f1f3f5; font-weight: 600; }
    .page-break { page-break-before: always; }
    .no-break { page-break-inside: avoid; }
    .print-actions { margin-bottom: 18px; }
    .text-end   { text-align: right; }
    .text-center{ text-align: center; }
    .text-muted { color: #555; }
    .small      { font-size: 9.5pt; }
    .fw-bold    { font-weight: 700; }
    @media screen {
      body { padding: 24px; max-width: 210mm; margin: 0 auto; box-shadow: 0 0 12px rgba(0,0,0,.1); margin-top: 16px; margin-bottom: 32px; }
    }
    @media print {
      .no-print { display: none !important; }
    }
  </style>
</head>
<body>
  <div class="no-print print-actions d-flex gap-2 align-items-center">
    <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">
      <i class="bi bi-printer me-1"></i>Print
    </button>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.close()">
      <i class="bi bi-x-lg me-1"></i>Close
    </button>
    <span class="text-muted small ms-2">Use your browser's Print dialog to save as PDF.</span>
  </div>
  <?php require $content; ?>
</body>
</html>
