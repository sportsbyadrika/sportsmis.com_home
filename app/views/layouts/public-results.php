<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? ($site['title'] ?? 'Results')) ?></title>
  <link rel="icon" href="/assets/img/favicon.ico">
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
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
