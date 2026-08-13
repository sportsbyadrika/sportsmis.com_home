<?php
/**
 * "Athlete workspace" card for the organiser (institution) dashboard. Mirrors
 * the "Organise an event" card shown on the athlete dashboard: a shortcut that
 * lets this one account hop into its athlete workspace. Only shown when the
 * account actually holds the athlete capability (i.e. has an athlete profile).
 */
if (\Core\Auth::role() === 'super_admin') return;
$__hasAthlete = in_array('athlete', \Core\Auth::capabilities(), true);
if (!$__hasAthlete) return;
?>
<div class="sms-card p-3 mb-4">
  <div class="d-flex align-items-center border-bottom pb-2 mb-3">
    <h6 class="mb-0 fw-semibold"><i class="bi bi-person-arms-up me-2"></i>User workspace</h6>
  </div>
  <p class="small text-muted mb-3">
    Take part in events as an athlete with this same account &mdash; register,
    view your competitor cards and results.
  </p>
  <a href="/athlete/dashboard" class="btn btn-sm btn-primary">
    <i class="bi bi-person-arms-up me-1"></i>Open User workspace
  </a>
  <p class="small text-muted mt-2 mb-0">
    or use <strong>Switch workspace</strong> in the account menu (top-right).
  </p>
</div>
