<?php
/**
 * Shared left rail for the athlete workspace (dashboard, profile, registrations,
 * results, events). One unified profile card (athlete identity + merged
 * institution) plus a constant vertical menu, so every athlete page has the
 * same LinkedIn-style shell with page content on the right.
 *
 * Expects: $athlete
 * Optional: $institution, $institution_types,
 *           $rail_reg_count, $rail_confirmed, $rail_pay_due,
 *           $rail_unit_count, $rail_staff_count,
 *           $active_menu ('dashboard'|'profile'|'registrations'|'results'|'events')
 */
$railProfileComplete = (bool)($athlete['profile_completed'] ?? false);
$railInst      = $institution ?? null;
$railHasInst   = !empty($railInst);
$railTypes     = $institution_types ?? [];
$railReg       = (int)($rail_reg_count ?? 0);
$railConfirmed = (int)($rail_confirmed ?? 0);
$railPayDue    = (int)($rail_pay_due ?? 0);
$railUnit      = (int)($rail_unit_count ?? 0);
$railStaff     = (int)($rail_staff_count ?? 0);
$railActive    = $active_menu ?? '';
$railIs = fn(string $k) => $railActive === $k ? 'active' : '';
?>
<div class="sms-rail-sticky">

  <!-- Unified profile card -->
  <div class="sms-card sms-profile-card mb-3">
    <div class="sms-profile-cover"></div>
    <div class="sms-profile-top">
      <div class="sms-profile-avatar">
        <?php if (!empty($athlete['passport_photo'])): ?>
          <img src="<?= e($athlete['passport_photo']) ?>" alt=""
               onerror="this.replaceWith(Object.assign(document.createElement('span'),{textContent:<?= json_encode(avatarInitials($athlete['name'] ?? '')) ?>}))">
        <?php else: ?>
          <span><?= avatarInitials($athlete['name'] ?? '') ?></span>
        <?php endif; ?>
      </div>
      <h5 class="sms-profile-name text-break"><?= e($athlete['name'] ?? '') ?></h5>
      <div class="sms-profile-meta">
        <?php
          $bits = [];
          if (!empty($athlete['gender']))        $bits[] = ucfirst($athlete['gender']);
          if (!empty($athlete['date_of_birth'])) $bits[] = ageFromDob($athlete['date_of_birth']) . ' yrs';
          echo e(implode(' · ', $bits) ?: 'Athlete');
        ?>
      </div>
      <div class="mt-2">
        <?php if ($railProfileComplete): ?>
          <span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-check-circle me-1"></i>Profile complete</span>
        <?php else: ?>
          <span class="badge bg-warning-subtle text-warning-emphasis"><i class="bi bi-exclamation-circle me-1"></i>Profile incomplete</span>
        <?php endif; ?>
      </div>
      <a href="/athlete/profile" class="btn btn-sm btn-outline-primary w-100 mt-3">
        <i class="bi bi-person-badge me-1"></i>View / Edit profile
      </a>
    </div>

    <!-- Stat strip -->
    <div class="sms-profile-stats">
      <div><div class="v"><?= $railReg ?></div><div class="l">Registered</div></div>
      <div><div class="v"><?= $railConfirmed ?></div><div class="l">Confirmed</div></div>
      <div><div class="v"><?= $railPayDue ?></div><div class="l">Pay due</div></div>
    </div>

    <!-- Merged institution / unit section -->
    <div class="sms-profile-section">
      <div class="sms-sec-label"><i class="bi bi-building me-1"></i>Institution / Unit</div>
      <?php if ($railHasInst): ?>
        <div class="sms-inst-row">
          <?php if (!empty($railInst['logo'])): ?>
            <img src="<?= e($railInst['logo']) ?>" alt="" class="sms-inst-logo">
          <?php else: ?>
            <span class="sms-inst-logo"><i class="bi bi-building"></i></span>
          <?php endif; ?>
          <div class="min-w-0">
            <div class="fw-semibold text-truncate" title="<?= e($railInst['name'] ?? '') ?>"><?= e($railInst['name'] ?? 'Your institution') ?></div>
            <?php if (!empty($railInst['type_name'])): ?>
              <div class="small text-muted text-truncate"><?= e($railInst['type_name']) ?></div>
            <?php endif; ?>
          </div>
        </div>
      <?php elseif ($railProfileComplete): ?>
        <p class="small text-muted mb-2">
          Add your institution / unit to organise events or to join an event as a unit.
        </p>
        <button type="button" class="btn btn-sm btn-primary w-100" data-bs-toggle="modal" data-bs-target="#addInstitutionModal">
          <i class="bi bi-building-add me-1"></i>Add institution / unit
        </button>
      <?php else: ?>
        <p class="small text-muted mb-0">
          <i class="bi bi-info-circle me-1"></i>Complete your profile first, then you can add an institution / unit.
        </p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Quick menu -->
  <div class="sms-card mb-3">
    <div class="sms-rail-menu">
      <a href="/athlete/dashboard" class="<?= $railIs('dashboard') ?>"><i class="bi bi-grid"></i>Dashboard</a>
      <a href="/athlete/profile" class="<?= $railIs('profile') ?>"><i class="bi bi-person-badge"></i>My Profile</a>
      <a href="/athlete/my-registrations" class="<?= $railIs('registrations') ?>"><i class="bi bi-list-check"></i>My Registrations
        <?php if ($railReg): ?><span class="badge bg-primary-subtle text-primary-emphasis"><?= $railReg ?></span><?php endif; ?></a>
      <a href="/athlete/my-results" class="<?= $railIs('results') ?>"><i class="bi bi-trophy"></i>My Results</a>
      <a href="/athlete/events" class="<?= $railIs('events') ?>"><i class="bi bi-search"></i>Find Events</a>
      <?php if ($railUnit): ?>
        <a href="/athlete/dashboard#unitAccess"><i class="bi bi-buildings"></i>Unit / Club Access
          <span class="badge bg-success-subtle text-success-emphasis"><?= $railUnit ?></span></a>
      <?php endif; ?>
      <?php if ($railStaff): ?>
        <a href="/athlete/dashboard#staffAccess"><i class="bi bi-clipboard-check"></i>Event Staff Access
          <span class="badge bg-info-subtle text-info-emphasis"><?= $railStaff ?></span></a>
      <?php endif; ?>

      <?php if ($railHasInst): ?>
        <!-- Institution / organiser activities — same account, grouped -->
        <div class="sms-rail-group"><i class="bi bi-building me-1"></i>Institution &mdash; <?= e($railInst['name'] ?? '') ?></div>
        <a href="/institution/profile" class="<?= $railIs('inst_profile') ?>"><i class="bi bi-building"></i>Institution Profile</a>
        <a href="/institution/events" class="<?= $railIs('inst_events') ?>"><i class="bi bi-calendar-event"></i>Manage Events</a>
        <a href="/institution/registrations" class="<?= $railIs('inst_registrations') ?>"><i class="bi bi-clipboard-check"></i>Event Registrations</a>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /sticky rail -->

<?php if (!$railHasInst && $railProfileComplete): ?>
<!-- Add institution / unit — basic profile, merged into this account -->
<div class="modal fade" id="addInstitutionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="POST" action="/account/create-institution">
      <?= csrf() ?>
      <div class="modal-header">
        <h6 class="modal-title fw-semibold"><i class="bi bi-building-add me-2"></i>Add institution / unit</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-3">
          This becomes part of your account — your name &amp; contact (SPOC) are taken from your
          profile. You can complete the full details later in the organiser workspace.
        </p>
        <div class="mb-3">
          <label class="form-label small mb-1">Institution / Unit name <span class="text-danger">*</span></label>
          <input type="text" name="org_name" class="form-control form-control-sm"
                 placeholder="e.g. City Sports Club" maxlength="255" required>
        </div>
        <div class="mb-3">
          <label class="form-label small mb-1">Institution type</label>
          <select name="type_id" class="form-select form-select-sm">
            <option value="">— Select type —</option>
            <?php foreach ($railTypes as $t): ?>
              <option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label small mb-1">Address</label>
          <textarea name="address" class="form-control form-control-sm" rows="2"
                    placeholder="Institution / unit address" maxlength="500"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-building-add me-1"></i>Create institution profile</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
