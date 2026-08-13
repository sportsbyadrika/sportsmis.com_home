<?php
/**
 * "Organise an event" card for a main-account dashboard. Lets a signed-in user
 * request organiser (event-admin) access, and shows the status of an existing
 * request. Hidden for institution admins (they already organise).
 * Self-contained: reads the user's latest organiser request directly.
 */
if (\Core\Auth::role() === 'super_admin') return;

$__req = null; $__sports = [];
try {
    \Models\Schema::ensureAccessRequests();
    $__req = \Models\AccessRequest::latestForUser((int)\Core\Auth::id(), 'organiser');
    $__sports = \Models\Athlete::getEventSports();
} catch (\Throwable $e) { $__req = null; }
$__status = $__req['status'] ?? '';

// Organiser access can only be requested after the athlete profile is complete.
$__athlete = null;
try { $__athlete = \Models\Athlete::findByUserId((int)\Core\Auth::id()); } catch (\Throwable $e) {}
$__profileComplete = !empty($__athlete['profile_completed']);
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
  <?php elseif (!$__profileComplete): ?>
    <p class="small text-muted mb-3">
      <span class="badge bg-secondary-subtle text-secondary-emphasis me-1">Profile incomplete</span>
      Complete your athlete profile first, then you can request organiser access to run your own event.
    </p>
    <a href="/athlete/profile" class="btn btn-sm btn-outline-primary">
      <i class="bi bi-person-badge me-1"></i>Complete profile
    </a>
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
    <form method="POST" action="/account/request-organiser" class="row g-2">
      <?= csrf() ?>
      <div class="col-12">
        <label class="form-label small mb-1">Organisation / Event name</label>
        <input type="text" name="org_name" class="form-control form-control-sm"
               placeholder="e.g. District Athletics Meet" maxlength="255" required>
      </div>
      <div class="col-12">
        <label class="form-label small mb-1">Event sport</label>
        <select name="sport" class="form-select form-select-sm">
          <option value="">Select sport</option>
          <?php foreach ($__sports as $s): ?>
            <option value="<?= e($s['name']) ?>"><?= e($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12">
        <label class="form-label small mb-1">Message <span class="text-muted">(optional)</span></label>
        <input type="text" name="message" class="form-control form-control-sm"
               placeholder="Brief note" maxlength="2000">
      </div>
      <div class="col-12 d-grid">
        <button class="btn btn-sm btn-primary"><i class="bi bi-send me-1"></i>Request</button>
      </div>
    </form>
  <?php endif; ?>
</div>
