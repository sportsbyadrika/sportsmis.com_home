<?php
/**
 * "Organise an event" card for a main-account dashboard. Lets a signed-in user
 * request organiser (event-admin) access, and shows the status of an existing
 * request. Hidden for institution admins (they already organise).
 * Self-contained: reads the user's latest organiser request directly.
 */
$__role = \Core\Auth::role();
if ($__role === 'institution_admin' || $__role === 'super_admin') return;

$__req = null;
try {
    \Models\Schema::ensureAccessRequests();
    $__req = \Models\AccessRequest::latestForUser((int)\Core\Auth::id(), 'organiser');
} catch (\Throwable $e) { $__req = null; }
$__status = $__req['status'] ?? '';
?>
<div class="sms-card p-3 mb-4">
  <div class="d-flex align-items-center border-bottom pb-2 mb-3">
    <h6 class="mb-0 fw-semibold"><i class="bi bi-calendar2-plus me-2"></i>Organise an event</h6>
  </div>

  <?php if ($__status === 'pending'): ?>
    <p class="small mb-0">
      <span class="badge bg-warning-subtle text-warning-emphasis me-1">Pending review</span>
      Your organiser request for <strong><?= e($__req['org_name'] ?? '') ?></strong> is awaiting review.
      We&rsquo;ll email you once it&rsquo;s decided.
    </p>
  <?php elseif ($__status === 'approved'): ?>
    <p class="small mb-1">
      <span class="badge bg-success-subtle text-success-emphasis me-1">Approved</span>
      Your organiser workspace is ready.
    </p>
    <a href="/institution/dashboard" class="btn btn-sm btn-primary">
      <i class="bi bi-building me-1"></i>Open Organiser workspace
    </a>
    <span class="small text-muted ms-2">or use <strong>Switch workspace</strong> in the account menu (top-right).</span>
  <?php else: ?>
    <?php if ($__status === 'rejected'): ?>
      <p class="small text-muted mb-2">
        <span class="badge bg-secondary-subtle text-secondary-emphasis me-1">Previous request declined</span>
        You can submit a new request below.
      </p>
    <?php endif; ?>
    <p class="small text-muted mb-3">
      Want to run your own event on SportsMIS? Request organiser access and our team will review it.
    </p>
    <form method="POST" action="/account/request-organiser" class="row g-2 align-items-end">
      <?= csrf() ?>
      <div class="col-sm-5">
        <label class="form-label small mb-1">Organisation / Event name</label>
        <input type="text" name="org_name" class="form-control form-control-sm"
               placeholder="e.g. District Athletics Meet" maxlength="255" required>
      </div>
      <div class="col-sm-5">
        <label class="form-label small mb-1">Message <span class="text-muted">(optional)</span></label>
        <input type="text" name="message" class="form-control form-control-sm"
               placeholder="Tell us briefly about your event" maxlength="2000">
      </div>
      <div class="col-sm-2 d-grid">
        <button class="btn btn-sm btn-primary"><i class="bi bi-send me-1"></i>Request</button>
      </div>
    </form>
  <?php endif; ?>
</div>
