<?php $pageTitle = 'Event Staff Login'; ?>

<div class="border rounded-3 overflow-hidden shadow-sm">
  <div class="p-3 px-4" style="background:#dcfce7;border-bottom:1px solid #bbf7d0">
    <div class="d-flex align-items-center gap-2">
      <div style="width:36px;height:36px;border-radius:.5rem;background:#0b1f3a;display:flex;align-items:center;justify-content:center">
        <i class="bi bi-person-vcard text-success"></i>
      </div>
      <div>
        <div class="fw-bold" style="font-size:1rem;line-height:1.2;color:#0b1f3a">Event Staff Login</div>
        <div class="text-muted" style="font-size:.8rem">For staff assigned to an event by the administrator</div>
      </div>
    </div>
  </div>

  <div class="p-4 bg-white">
    <form method="POST" action="/event-staff/login" novalidate>
      <?= csrf() ?>

      <div class="mb-3">
        <label class="form-label fw-medium">Event Code</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-hash"></i></span>
          <input type="text" name="event_code" class="form-control text-uppercase"
                 placeholder="e.g. EVABC123" maxlength="32" required autofocus
                 style="text-transform:uppercase">
        </div>
        <small class="text-muted">Provided by the event administrator.</small>
      </div>

      <div class="mb-3">
        <label class="form-label fw-medium">Email Address</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-envelope"></i></span>
          <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label fw-medium">Password</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>
      </div>

      <button type="submit" class="btn btn-primary w-100 py-2 fw-medium">
        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
      </button>
    </form>

    <div class="mt-3 small text-muted text-center">
      Unit / Club / Institution user? <a href="/unit/login">Unit Login</a>
    </div>
  </div>
</div>
