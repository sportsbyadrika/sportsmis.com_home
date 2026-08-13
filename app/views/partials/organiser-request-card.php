<?php
/**
 * Dashboard card for a main (athlete) account. Gated in order:
 *   1. Athlete profile must be complete first.
 *   2. If the account has no institution profile yet, it offers one-click
 *      creation — the institution is auto-provisioned and organiser access is
 *      auto-approved (see AccountController::createInstitution). The user then
 *      completes the institution details in the organiser workspace.
 * Accounts that already have an institution get an "Open workspace" shortcut.
 * Hidden for super admins. Self-contained (reads its own state).
 */
if (\Core\Auth::role() === 'super_admin') return;

$__req = null;
try {
    \Models\Schema::ensureAccessRequests();
    $__req = \Models\AccessRequest::latestForUser((int)\Core\Auth::id(), 'organiser');
} catch (\Throwable $e) { $__req = null; }
$__status = $__req['status'] ?? '';

// Athlete profile must be complete before an institution profile is created.
$__athlete = null;
try { $__athlete = \Models\Athlete::findByUserId((int)\Core\Auth::id()); } catch (\Throwable $e) {}
$__profileComplete = !empty($__athlete['profile_completed']);

// Does this account already own an institution profile?
$__institution = null;
try { $__institution = \Models\Institution::findByUserId((int)\Core\Auth::id()); } catch (\Throwable $e) {}
$__hasInstitution = (bool)$__institution;

// An institution profile (organiser workspace) is ready once it exists or the
// organiser request was approved.
$__ready = $__hasInstitution || $__status === 'approved';

// Prompt to create the institution profile: profile complete, none yet.
$__createMode = $__profileComplete && !$__ready && $__status !== 'pending';
if ($__ready)          { $__title = 'Institution profile';             $__icon = 'bi-building'; }
elseif ($__createMode) { $__title = 'Create your institution profile';  $__icon = 'bi-building-add'; }
else                   { $__title = 'Organise an event';                $__icon = 'bi-calendar2-plus'; }

// Institution-type options for the create form.
$__types = [];
if ($__createMode) {
    try { $__types = \Models\Institution::getTypes(); } catch (\Throwable $e) {}
}
?>
<div class="sms-card p-3 mb-4">
  <div class="d-flex align-items-center border-bottom pb-2 mb-3">
    <h6 class="mb-0 fw-semibold"><i class="bi <?= $__icon ?> me-2"></i><?= $__title ?></h6>
  </div>

  <?php if ($__ready): ?>
    <?php if ($__institution): ?>
      <div class="d-flex align-items-center gap-2 mb-3">
        <?php if (!empty($__institution['logo'])): ?>
          <img src="<?= e($__institution['logo']) ?>" alt="" width="44" height="44"
               class="rounded flex-shrink-0" style="object-fit:cover;border:1px solid #e2e8f0;background:#fff">
        <?php else: ?>
          <span class="rounded d-inline-flex align-items-center justify-content-center flex-shrink-0"
                style="width:44px;height:44px;background:#eef2f7;color:#94a3b8"><i class="bi bi-building fs-5"></i></span>
        <?php endif; ?>
        <div class="min-w-0">
          <div class="fw-semibold text-truncate" title="<?= e($__institution['name'] ?? '') ?>">
            <?= e($__institution['name'] ?? 'Your institution') ?>
          </div>
          <?php if (!empty($__institution['type_name'])): ?>
            <div class="small text-muted text-truncate"><?= e($__institution['type_name']) ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php else: ?>
      <p class="small mb-3">
        <span class="badge bg-success-subtle text-success-emphasis me-1">Ready</span>
        Your institution profile is set up.
      </p>
    <?php endif; ?>
    <a href="/institution/dashboard" class="btn btn-sm btn-primary">
      <i class="bi bi-building me-1"></i>Open Institution workspace
    </a>
    <span class="small text-muted ms-2">or use <strong>Switch workspace</strong> in the account menu (top-right).</span>
  <?php elseif ($__status === 'pending'): ?>
    <p class="small mb-0">
      <span class="badge bg-warning-subtle text-warning-emphasis me-1">Pending review</span>
      Your organiser request for <strong><?= e($__req['org_name'] ?? '') ?></strong> is awaiting review.
      We&rsquo;ll email you once it&rsquo;s decided.
    </p>
  <?php elseif (!$__profileComplete): ?>
    <p class="small text-muted mb-3">
      <span class="badge bg-secondary-subtle text-secondary-emphasis me-1">Profile incomplete</span>
      Complete your athlete profile first, then you can create your institution profile.
    </p>
    <a href="/athlete/profile" class="btn btn-sm btn-outline-primary">
      <i class="bi bi-person-badge me-1"></i>Complete profile
    </a>
  <?php else: ?>
    <p class="small text-muted mb-3">
      An institution profile is required for participating in an event or to host an event.
    </p>
    <form method="POST" action="/account/create-institution" class="row g-2">
      <?= csrf() ?>
      <div class="col-12">
        <label class="form-label small mb-1">Institution name</label>
        <input type="text" name="org_name" class="form-control form-control-sm"
               placeholder="e.g. City Sports Club" maxlength="255" required>
      </div>
      <div class="col-12">
        <label class="form-label small mb-1">Institution type</label>
        <select name="type_id" class="form-select form-select-sm">
          <option value="">-- Select type --</option>
          <?php foreach ($__types as $t): ?>
            <option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12">
        <label class="form-label small mb-1">Address</label>
        <textarea name="address" class="form-control form-control-sm" rows="2"
                  placeholder="Institution address" maxlength="500"></textarea>
      </div>
      <div class="col-12 d-grid">
        <button class="btn btn-sm btn-primary">
          <i class="bi bi-building-add me-1"></i>Create institution profile
        </button>
      </div>
      <div class="col-12">
        <p class="small text-muted mb-0">
          <i class="bi bi-info-circle me-1"></i>SPOC name, contact &amp; email are taken from your profile.
        </p>
      </div>
    </form>
  <?php endif; ?>
</div>
