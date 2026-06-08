<?php
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrfToken = $_SESSION['csrf_token'];
?>

<style>
  .pc-hero { background:linear-gradient(120deg,#fff3eb 0%,#f5f7fb 100%); padding:64px 0 48px; }
  .pc-pill { display:inline-flex; align-items:center; gap:6px; background:#fff;
             border-radius:999px; padding:6px 14px; font-size:.85rem;
             color:#475569; box-shadow:0 1px 2px rgba(15,23,42,.06); margin-bottom:18px; }
  .pc-pill i { color:#f97316; }
  .pc-h1 { font-size:2.6rem; font-weight:800; letter-spacing:-.02em; line-height:1.1; color:#0f172a; }
  .pc-sub { color:#475569; max-width:540px; }
  .pc-chip { display:inline-flex; align-items:center; gap:8px; background:#fff;
             border-radius:999px; padding:10px 18px; font-size:.92rem; color:#0f172a;
             box-shadow:0 1px 2px rgba(15,23,42,.06); }
  .pc-chip i { color:#f97316; }
  .pc-chip + .pc-chip { margin-left:10px; }
  .pc-card { background:#fff; border-radius:14px; padding:22px 26px;
             box-shadow:0 1px 2px rgba(15,23,42,.06); }
  .pc-card .row + .row { margin-top:14px; }
  .pc-card .icon-tile { width:36px; height:36px; border-radius:10px;
                        background:#fff3eb; color:#f97316; display:flex;
                        align-items:center; justify-content:center; flex-shrink:0; font-size:1.1rem; }
  .pc-card .lbl { font-size:.75rem; color:#94a3b8; }
  .pc-card .val { font-weight:600; color:#0f172a; }
  .pc-form-section { padding:56px 0; }
  .pc-form-h2 { font-size:1.9rem; font-weight:800; color:#0f172a; }
  .pc-bullet { display:flex; gap:8px; color:#475569; font-size:.95rem; padding:6px 0; }
  .pc-bullet i { color:#22c55e; }
  .pc-form-card { background:#fff; border:1px solid #e5e7eb; border-radius:14px;
                  padding:26px 28px; box-shadow:0 1px 2px rgba(15,23,42,.04); }
  .pc-form-card label { font-size:.85rem; color:#475569; margin-bottom:4px; }
  .pc-form-card .form-control { border-radius:8px; }
  .pc-form-card .form-control::placeholder { color:#cbd5e1; }
  .pc-form-card .form-check-label { font-size:.85rem; color:#475569; }
  .pc-form-card .form-check-label a { color:#f97316; text-decoration:none; font-weight:600; }
  .pc-form-card .btn-send { background:#f97316; border:0; color:#fff; font-weight:600;
                            border-radius:999px; padding:11px 30px; font-size:.95rem; }
  .pc-form-card .btn-send:hover { background:#ea580c; }
</style>

<!-- ── Hero ─────────────────────────────────────────────────────────── -->
<section class="pc-hero">
  <div class="container">
    <div class="row g-4 align-items-start">
      <div class="col-lg-7">
        <div class="pc-pill"><i class="bi bi-chat-dots-fill"></i> Talk to the team</div>
        <h1 class="pc-h1 mb-3">Let&rsquo;s build better sports<br>experiences together</h1>
        <p class="pc-sub mb-4">
          Share what you&rsquo;re looking for &mdash; performance analytics, event tech or community
          platforms. We&rsquo;ll respond quickly with the right next step.
        </p>
        <div class="d-flex flex-wrap gap-2">
          <a href="mailto:info@sportsbya.com" class="pc-chip text-decoration-none">
            <i class="bi bi-envelope-fill"></i> info@sportsbya.com
          </a>
          <a href="tel:+917994999121" class="pc-chip text-decoration-none">
            <i class="bi bi-telephone-fill"></i>
            +91-7994999121 &nbsp;&middot;&nbsp; +91-9388778885
          </a>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="pc-card">
          <div class="row align-items-start g-2">
            <div class="col-auto"><div class="icon-tile"><i class="bi bi-lightning-fill"></i></div></div>
            <div class="col">
              <div class="lbl">Average reply time</div>
              <div class="val">Under 24 hours</div>
            </div>
          </div>
          <div class="row align-items-start g-2">
            <div class="col-auto"><div class="icon-tile"><i class="bi bi-geo-alt-fill"></i></div></div>
            <div class="col"><div class="val">Thiruvananthapuram, Kerala, India</div></div>
          </div>
          <div class="row align-items-start g-2">
            <div class="col-auto"><div class="icon-tile"><i class="bi bi-calendar-event-fill"></i></div></div>
            <div class="col"><div class="val">Mon&ndash;Sat &middot; 10:00 AM &ndash; 5:00 PM IST</div></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── Form section ─────────────────────────────────────────────────── -->
<section class="pc-form-section">
  <div class="container">
    <div class="row g-4">
      <div class="col-lg-5">
        <h2 class="pc-form-h2">Tell us about your needs</h2>
        <p class="text-muted">
          Whether you&rsquo;re a federation, club or coach, we tailor solutions for your sport.
          Share a few details and we&rsquo;ll schedule a demo or follow-up call.
        </p>
        <div class="pc-bullet"><i class="bi bi-check-circle-fill"></i> We don&rsquo;t share your details with third parties.</div>
        <div class="pc-bullet"><i class="bi bi-check-circle-fill"></i> A specialist &mdash; not a bot &mdash; reads every message.</div>
      </div>
      <div class="col-lg-7">
        <form method="POST" action="/contact" class="pc-form-card">
          <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Name</label>
              <input type="text" name="name" maxlength="120" required
                     class="form-control" placeholder="Your full name">
            </div>
            <div class="col-md-6">
              <label class="form-label">Mobile number</label>
              <input type="tel" name="mobile" maxlength="20"
                     class="form-control" placeholder="+91 98765 43210">
            </div>
            <div class="col-12">
              <label class="form-label">Email</label>
              <input type="email" name="email" maxlength="180" required
                     class="form-control" placeholder="you@example.com">
            </div>
            <div class="col-12">
              <label class="form-label">How can we help?</label>
              <textarea name="message" rows="4" required class="form-control"
                        placeholder="Tell us about your sport, goals or the problem you&rsquo;re solving."></textarea>
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="cbNotify" name="notify_consent" value="1">
                <label class="form-check-label" for="cbNotify">
                  I hereby authorize to send notifications on SMS/Messages/Promotional/Informational messages
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="cbTerms" name="tnc_consent" value="1" required>
                <label class="form-check-label" for="cbTerms">
                  By submitting the form, you&rsquo;ve read and accepted our
                  <a href="/terms"   target="_blank" rel="noopener">terms and conditions</a> and our
                  <a href="/privacy" target="_blank" rel="noopener">privacy policy</a>.
                </label>
              </div>
            </div>
            <div class="col-12">
              <button type="submit" class="btn-send">
                <i class="bi bi-send-fill me-1"></i> Send message
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</section>
