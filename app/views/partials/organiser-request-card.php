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
$__hasInstitution = false;
try { $__hasInstitution = (bool)\Models\Institution::findByUserId((int)\Core\Auth::id()); } catch (\Throwable $e) {}

// An institution profile (organiser workspace) is ready once it exists or the
// organiser request was approved.
$__ready = $__hasInstitution || $__status === 'approved';

// Prompt to create the institution profile: profile complete, none yet.
$__createMode = $__profileComplete && !$__ready && $__status !== 'pending';
$__title = $__createMode ? 'Create your institution profile' : 'Organise an event';
$__icon  = $__createMode ? 'bi-building-add' : 'bi-calendar2-plus';
?>
<div class="sms-card p-3 mb-4">
  <div class="d-flex align-items-center border-bottom pb-2 mb-3">
    <h6 class="mb-0 fw-semibold"><i class="bi <?= $__icon ?> me-2"></i><?= $__title ?></h6>
  </div>

  <?php if ($__ready): ?>
    <p class="small mb-1">
      <span class="badge bg-success-subtle text-success-emphasis me-1">Ready</span>
      Your institution profile is set up.
    </p>
    <a href="/institution/dashboard" class="btn btn-sm btn-primary">
      <i class="bi bi-building me-1"></i>Open Organiser workspace
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
    <form method="POST" action="/account/create-institution" class="d-grid">
      <?= csrf() ?>
      <button class="btn btn-sm btn-primary">
        <i class="bi bi-building-add me-1"></i>Create institution profile
      </button>
    </form>
  <?php endif; ?>
</div>
