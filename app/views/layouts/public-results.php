<?php
  // Google Analytics (GA4) measurement id — set PUBLIC_GA_ID in .env to enable.
  $gaId = trim((string)(getenv('PUBLIC_GA_ID') ?: ''));
  if (!preg_match('/^(G|UA|GT)-[A-Za-z0-9-]+$/', $gaId)) $gaId = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="referrer" content="strict-origin-when-cross-origin">
  <title><?= e($pageTitle ?? ($site['title'] ?? 'Results')) ?></title>
  <link rel="icon" href="/assets/img/favicon.ico">
  <?php if ($gaId !== ''): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($gaId) ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', '<?= e($gaId) ?>');
    </script>
  <?php endif; ?>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body { font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background:#f1f5f9; color:#0f172a; }
    .pr-nav { background:#0f172a; color:#fff; padding:14px 0; }
    .pr-nav .title { font-weight:700; font-size:1.15rem; color:#fff; text-decoration:none; }
    .pr-nav img { height:40px; width:auto; object-fit:contain; background:#fff; border-radius:6px; padding:2px; }
    .pr-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; }
    .ev-card { transition:transform .12s ease, box-shadow .12s ease; text-decoration:none; color:inherit; display:block; }
    .ev-card:hover { transform:translateY(-3px); box-shadow:0 8px 24px rgba(2,6,23,.12); }
    .ev-logo { width:72px; height:72px; object-fit:contain; }
    .pr-footer { color:#64748b; padding:24px 0; margin-top:40px; font-size:.85rem; }
    /* Mobile: turn wide tables into stacked cards (label from data-label). */
    @media (max-width: 640px) {
      .pr-cardify .table-responsive { overflow-x: visible; }
      .pr-cardify table.table thead { display:none; }
      .pr-cardify table.table, .pr-cardify table.table tbody { display:block; width:100%; }
      .pr-cardify table.table tr { display:block; border:1px solid #e2e8f0; border-radius:10px;
        margin-bottom:10px; padding:6px 10px; background:#fff; }
      .pr-cardify table.table td { display:flex; justify-content:space-between; align-items:flex-start;
        gap:12px; border:none; padding:5px 0; text-align:right; }
      .pr-cardify table.table td::before { content: attr(data-label); font-weight:600; color:#64748b;
        text-align:left; flex:0 0 auto; }
      .pr-cardify table.table td:not([data-label]) { justify-content:flex-start; text-align:left; }
    }
  </style>
</head>
<body>

<?php $base = $base ?? ''; ?>
<nav class="pr-nav">
  <div class="container d-flex align-items-center gap-3">
    <a href="<?= e($base) ?: '/' ?>" class="d-flex align-items-center gap-2 title">
      <?php if (!empty($site['logo'])): ?><img src="<?= e($site['logo']) ?>" alt=""><?php endif; ?>
      <span><?= e($site['title'] ?? 'Results') ?></span>
    </a>
    <div class="ms-auto d-flex align-items-center gap-2">
      <a class="btn btn-sm btn-light" href="<?= e($base) ?: '/' ?>"><i class="bi bi-grid me-1"></i>Events</a>
      <a class="btn btn-sm btn-warning" href="<?= e($base) ?>/search"><i class="bi bi-search me-1"></i>Search by Athlete</a>
    </div>
  </div>
</nav>

<?= flashBag() ?>
<main class="py-4"><?php require $content; ?></main>

<footer class="pr-footer">
  <div class="container text-center">
    Published results &middot; Powered by <strong>SportsMIS<sup style="font-size:.7em">&reg;</sup></strong>
    <div class="mt-1">
      <a href="https://sportsbya.com" target="_blank" rel="noopener" class="text-decoration-none">
        SportsByA Tech (OPC) Private Limited
      </a>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
