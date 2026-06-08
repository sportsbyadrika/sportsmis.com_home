<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? 'SportsMIS®') ?></title>
  <link rel="icon" href="/assets/img/favicon.ico">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body { font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background:#fff; color:#0f172a; }
    .pub-nav { background:#fff; border-bottom:1px solid #e5e7eb; padding:14px 0; }
    .pub-nav .brand { font-weight:700; font-size:1.15rem; color:#0f172a; text-decoration:none; }
    .pub-nav .brand sup { font-size:.55em; vertical-align:super; color:#f97316; }
    .pub-nav .nav-link { color:#475569; font-weight:500; font-size:.95rem; padding:6px 14px; }
    .pub-nav .nav-link:hover { color:#0f172a; }
    .pub-nav .btn-signin { background:#f97316; color:#fff; border:0; padding:8px 18px; border-radius:999px; font-weight:600; font-size:.9rem; }
    .pub-nav .btn-signin:hover { background:#ea580c; color:#fff; }
    .pub-footer { background:#0f172a; color:#cbd5e1; padding:32px 0; margin-top:48px; }
    .pub-footer a { color:#cbd5e1; text-decoration:none; }
    .pub-footer a:hover { color:#fff; }
  </style>
</head>
<body>

<nav class="pub-nav">
  <div class="container d-flex align-items-center">
    <a href="/" class="brand d-flex align-items-center gap-2">
      <img src="/assets/img/sba-logo.png" alt="" height="32">
      SportsMIS<sup>&reg;</sup>
    </a>
    <div class="ms-auto d-flex align-items-center gap-1">
      <a class="nav-link" href="/privacy">Privacy</a>
      <a class="nav-link" href="/terms">Terms</a>
      <a class="nav-link" href="/contact">Contact</a>
      <a class="btn-signin ms-2" href="/login">Sign in</a>
    </div>
  </div>
</nav>

<?= flashBag() ?>
<?php require $content; ?>

<footer class="pub-footer">
  <div class="container d-flex flex-wrap gap-3 align-items-center justify-content-between small">
    <div>
      &copy; <?= date('Y') ?>
      <a href="https://sportsbya.com" target="_blank" rel="noopener">SportsByA Tech (OPC) Private Limited</a>
      &middot; Powered by <strong style="color:#fff">SportsMIS<sup style="font-size:.7em">&reg;</sup></strong>
    </div>
    <div>
      <a href="/privacy" class="me-3">Privacy</a>
      <a href="/terms"   class="me-3">Terms</a>
      <a href="/contact">Contact</a>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
