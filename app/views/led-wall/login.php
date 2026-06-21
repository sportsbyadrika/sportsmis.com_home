<?php
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SportsMIS LED Wall &mdash; Sign in</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    :root { --ink:#0b1f3a; --accent:#FFD23F; }
    html,body { height:100%; margin:0; background: radial-gradient(circle at 30% 20%, #1a3470, #04122a);
                color:#fff; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
    .wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; padding: 24px; }
    .card { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.14);
            border-radius: 16px; padding: 32px 28px; width: 100%; max-width: 420px;
            box-shadow: 0 18px 48px rgba(0,0,0,.45); backdrop-filter: blur(6px); }
    .brand { text-align:center; margin-bottom: 18px; }
    .brand .badge { display:inline-block; padding: 4px 10px; border-radius: 999px;
                    background: var(--accent); color: var(--ink); font-weight: 800;
                    letter-spacing: .04em; font-size: 12px; }
    .brand h1 { font-size: 22px; margin: 10px 0 4px; font-weight: 800; }
    .brand p { color: #cbd5e1; margin: 0; font-size: 14px; }
    label { font-size: 13px; color: #cbd5e1; font-weight: 600; margin-bottom: 6px; display:block; }
    .field { margin-bottom: 14px; }
    input[type="text"], input[type="password"] {
      width: 100%; padding: 12px 14px; border-radius: 10px;
      border: 1.5px solid rgba(255,255,255,.18);
      background: rgba(0,0,0,.25); color: #fff; font-size: 16px;
      font-family: ui-monospace, "SFMono-Regular", Menlo, monospace;
      letter-spacing: .04em;
    }
    input:focus { outline: none; border-color: var(--accent); }
    .btn { width:100%; padding: 12px; border-radius: 10px; border: 0;
           background: var(--accent); color: var(--ink); font-weight: 800;
           font-size: 16px; cursor: pointer; letter-spacing: .03em; }
    .btn:hover { filter: brightness(.95); }
    .err { background: rgba(220,53,69,.22); border: 1px solid rgba(220,53,69,.45);
           color: #fecaca; border-radius: 10px; padding: 10px 12px; font-size: 13px;
           margin-bottom: 14px; }
    .hint { color:#94a3b8; font-size: 12px; text-align: center; margin-top: 14px; }
  </style>
</head>
<body>
<div class="wrap">
  <form class="card" method="POST" action="/led-wall/login" autocomplete="off">
    <div class="brand">
      <span class="badge"><i class="bi bi-broadcast"></i> LED WALL</span>
      <h1>Sign in to display results</h1>
      <p>Use the event code and the numeric PIN your organiser shared.</p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="err"><i class="bi bi-exclamation-triangle me-1"></i><?= $h($error) ?></div>
    <?php endif; ?>

    <div class="field">
      <label for="ev_code">Event Code</label>
      <input id="ev_code" name="event_code" type="text" maxlength="20"
             autocapitalize="characters" required
             value="<?= $h($event_code ?? '') ?>" placeholder="EVxxxxxx">
    </div>
    <div class="field">
      <label for="ev_pwd">Numeric Password</label>
      <input id="ev_pwd" name="password" type="password" inputmode="numeric"
             pattern="\d{4,10}" minlength="4" maxlength="10" required
             placeholder="••••">
    </div>
    <button class="btn" type="submit">
      <i class="bi bi-play-circle me-1"></i>Open Slideshow
    </button>

    <div class="hint">
      The link generated after sign-in can be bookmarked &mdash; it stays valid
      until the organiser changes the password.
    </div>
  </form>
</div>
</body>
</html>
