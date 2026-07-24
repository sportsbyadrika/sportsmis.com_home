<?php
/**
 * Multi-card print sheet — one Competitor Card per page. Rendered outside the
 * app layout (required directly by EventReportController::competitorCardsPrint)
 * so it prints clean. Expects: $cards — a list of per-card contexts, each with
 * athlete, event, institution, registration, category_rows, age_category_label.
 */
$cards = $cards ?? [];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Competitor Cards — <?= e($event['name'] ?? '') ?> (<?= count($cards) ?>)</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
  body { background:#eef2f7; font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; padding:24px 0; }
  .cc-actions { max-width:840px; margin:0 auto 16px; padding:0 12px; display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap; }
  .cc-page { margin:0 auto 22px; }
  .cc-card { max-width:840px; margin:0 12px; background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 8px 24px rgba(2,8,23,.08); border:1px solid #e2e8f0; }
  @media (min-width:864px){ .cc-card { margin:0 auto; } }
  .cc-header { background:linear-gradient(135deg,#0b1f3a,#1f3b7a); color:#fff; padding:20px 28px; display:flex; align-items:center; gap:18px; flex-wrap:wrap; }
  .cc-header .inst-logo { width:56px; height:56px; object-fit:contain; background:#fff; border-radius:10px; padding:4px; flex-shrink:0; }
  .cc-header .inst-logo-fallback { width:56px; height:56px; border-radius:10px; background:rgba(255,255,255,.12); display:grid; place-items:center; color:#fff; font-weight:700; font-size:22px; flex-shrink:0; }
  .cc-header h1 { font-size:18px; margin:0 0 4px; font-weight:700; word-wrap:break-word; }
  .cc-header h2 { font-size:14px; margin:0; opacity:.85; font-weight:500; word-wrap:break-word; }
  .cc-body { display:grid; grid-template-columns:1fr 240px; gap:24px; padding:24px 28px; }
  .cc-section-title { font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:#64748b; margin-bottom:6px; }
  .cc-row { display:flex; gap:8px; padding:6px 0; border-bottom:1px dashed #e2e8f0; }
  .cc-row:last-child { border-bottom:none; }
  .cc-row .lbl { width:140px; color:#64748b; font-size:13px; flex-shrink:0; }
  .cc-row .val { flex:1; font-weight:600; font-size:14px; color:#0f172a; word-wrap:break-word; min-width:0; }
  .cc-photo-pane { text-align:center; }
  .cc-photo { width:160px; height:160px; object-fit:cover; border-radius:12px; border:3px solid #0b1f3a; max-width:100%; }
  .cc-photo-fallback { width:160px; height:160px; border-radius:12px; background:#e2e8f0; display:grid; place-items:center; font-size:48px; font-weight:700; color:#475569; margin:0 auto; }
  .cc-num { margin-top:12px; }
  .cc-num .lbl { font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:#64748b; }
  .cc-num .val { font-size:36px; font-weight:800; color:#0b1f3a; line-height:1; letter-spacing:1px; }
  .cc-qr { margin-top:14px; padding-top:12px; border-top:1px dashed #e2e8f0; }
  .cc-qr img { display:block; margin:0 auto; max-width:100%; height:auto; }
  .cc-qr-cap { font-size:10px; letter-spacing:.06em; text-transform:uppercase; color:#94a3b8; margin-top:4px; }
  .cc-events { padding:0 28px 24px; }
  .cc-events-scroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }
  .cc-events table { width:100%; border-collapse:collapse; font-size:13px; }
  .cc-events th, .cc-events td { padding:8px 10px; border-bottom:1px solid #e2e8f0; text-align:left; vertical-align:top; }
  .cc-events th { background:#f8fafc; color:#475569; text-transform:uppercase; font-size:11px; letter-spacing:.05em; }
  .cc-events td.text-end, .cc-events th.text-end { text-align:right; }
  .cc-cell-label { display:none; font-size:10px; letter-spacing:.05em; text-transform:uppercase; color:#94a3b8; margin-bottom:2px; }
  .cc-message { margin:14px 28px 0; padding:8px 14px 12px; background:#fff7ed; border:1px solid #fed7aa; border-radius:8px; color:#7c2d12; font-size:12.5px; line-height:1.45; white-space:pre-line; font-weight:700; }
  .cc-message-title { font-size:10.5px; letter-spacing:.06em; text-transform:uppercase; color:#9a3412; margin-bottom:1px; font-weight:700; line-height:1.2; }
  .cc-footer { background:#f8fafc; padding:14px 28px; font-size:11px; color:#64748b; display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; }
  .cc-empty { max-width:840px; margin:0 auto; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:24px; text-align:center; color:#64748b; }

  @media print {
    body { background:#fff; padding:0; }
    .cc-actions { display:none; }
    .cc-page { margin:0; page-break-after:always; }
    .cc-page:last-child { page-break-after:auto; }
    .cc-card { box-shadow:none; border:1px solid #cbd5e1; margin:0; border-radius:0; max-width:none; }
  }
</style>
</head>
<body>

<div class="cc-actions">
  <a href="<?= e($back_url ?? ('/institution/events/' . ($eventHash ?? '') . '/reports/competitor-cards')) ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
  <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
    <i class="bi bi-printer me-1"></i>Print / Save as PDF (<?= count($cards) ?>)
  </button>
</div>

<?php if (empty($cards)): ?>
  <div class="cc-empty">No printable competitor cards in the selection — a card is available only after a registration is approved and a competitor number is allocated.</div>
<?php else: ?>
  <?php foreach ($cards as $card): ?>
    <?php
      $athlete            = $card['athlete'];
      $event              = $card['event'];
      $institution        = $card['institution'];
      $registration       = $card['registration'];
      $category_rows      = $card['category_rows'];
      $event_rows         = $card['event_rows'] ?? [];
      $age_category_label = $card['age_category_label'];
    ?>
    <div class="cc-page">
      <?php include APP_ROOT . '/views/athlete/events/_competitor-card-body.php'; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
