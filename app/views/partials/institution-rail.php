<?php
/**
 * Shared left rail for the institution (organiser) workspace — mirrors the
 * athlete rail so both workspaces feel the same. One institution profile card
 * (logo, name, type, completeness, event stats) plus a constant vertical menu.
 *
 * Expects: $institution
 * Optional: $events (for the stat strip),
 *           $show_athlete_ws (bool), $active_menu
 *           ('dashboard'|'profile'|'events'|'registrations')
 *
 * Missing values are self-loaded from the signed-in account.
 */
if (!isset($institution) || !is_array($institution) || empty($institution)) {
    try { $institution = \Models\Institution::findByUserId((int)\Core\Auth::id()) ?: []; }
    catch (\Throwable $e) { $institution = $institution ?? []; }
}
if (!isset($events)) {
    try { $events = \Models\Event::getByInstitution((int)($institution['id'] ?? 0)); }
    catch (\Throwable $e) { $events = []; }
}

$railComplete = !empty($institution['profile_completed']);
$railEvents   = $events ?? [];
$railTotal    = count($railEvents);
$railApproved = count(array_filter($railEvents, fn($e) => in_array(($e['status'] ?? ''), ['active', 'approved'], true)));
$railPending  = count(array_filter($railEvents, fn($e) => in_array(($e['status'] ?? ''), ['draft', 'pending_approval'], true)));
$railActive   = $active_menu ?? '';
$railIs       = fn(string $k) => $railActive === $k ? 'active' : '';
$railCanCreate = !empty($institution['event_creation_enabled']);
$railShowAthlete = !empty($show_athlete_ws);
?>
<div class="sms-rail-sticky">

  <!-- Institution profile card -->
  <div class="sms-card sms-profile-card mb-3">
    <div class="sms-profile-cover"></div>
    <div class="sms-profile-top">
      <div class="sms-profile-avatar">
        <?php if (!empty($institution['logo'])): ?>
          <img src="<?= e($institution['logo']) ?>" alt=""
               onerror="this.replaceWith(Object.assign(document.createElement('span'),{textContent:<?= json_encode(avatarInitials($institution['name'] ?? '')) ?>}))">
        <?php else: ?>
          <span><?= avatarInitials($institution['name'] ?? '') ?></span>
        <?php endif; ?>
      </div>
      <h5 class="sms-profile-name text-break"><?= e($institution['name'] ?? '') ?></h5>
      <div class="sms-profile-meta"><?= e($institution['type_name'] ?? 'Institution') ?></div>
      <div class="mt-2">
        <?php if ($railComplete): ?>
          <span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-check-circle me-1"></i>Profile complete</span>
        <?php else: ?>
          <span class="badge bg-warning-subtle text-warning-emphasis"><i class="bi bi-exclamation-circle me-1"></i>Profile incomplete</span>
        <?php endif; ?>
      </div>
      <a href="/institution/profile" class="btn btn-sm btn-outline-primary w-100 mt-3">
        <i class="bi bi-building me-1"></i>View / Edit profile
      </a>
    </div>

    <!-- Event stats -->
    <div class="sms-profile-stats">
      <div><div class="v"><?= $railTotal ?></div><div class="l">Events</div></div>
      <div><div class="v"><?= $railApproved ?></div><div class="l">Approved</div></div>
      <div><div class="v"><?= $railPending ?></div><div class="l">Pending</div></div>
    </div>

    <!-- Actions -->
    <div class="sms-profile-section">
      <?php if ($railCanCreate): ?>
        <a href="/institution/events/create" class="btn btn-sm btn-primary w-100">
          <i class="bi bi-plus-circle me-1"></i>New Event
        </a>
      <?php else: ?>
        <button type="button" class="btn btn-sm btn-primary w-100" data-bs-toggle="modal" data-bs-target="#createEventDisabledModal">
          <i class="bi bi-plus-circle me-1"></i>New Event
        </button>
      <?php endif; ?>
      <?php if ($railShowAthlete): ?>
        <a href="/athlete/dashboard" class="btn btn-sm btn-outline-secondary w-100 mt-2">
          <i class="bi bi-person-arms-up me-1"></i>Switch to athlete workspace
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Quick menu -->
  <div class="sms-card mb-3">
    <div class="sms-rail-menu">
      <a href="/institution/dashboard" class="<?= $railIs('dashboard') ?>"><i class="bi bi-grid"></i>Dashboard</a>
      <a href="/institution/profile" class="<?= $railIs('profile') ?>"><i class="bi bi-building"></i>Institution Profile</a>
      <a href="/institution/events" class="<?= $railIs('events') ?>"><i class="bi bi-calendar-event"></i>My Events
        <?php if ($railTotal): ?><span class="badge bg-primary-subtle text-primary-emphasis"><?= $railTotal ?></span><?php endif; ?></a>
      <a href="/institution/registrations" class="<?= $railIs('registrations') ?>"><i class="bi bi-list-check"></i>Registrations</a>
    </div>
  </div>

</div><!-- /sticky rail -->
