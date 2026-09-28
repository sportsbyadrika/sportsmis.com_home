<?php
$pageTitle = 'Dashboard';
$profileComplete = (bool)($athlete['profile_completed'] ?? false);

$regCount       = count($registrations);
$confirmedCount = count(array_filter($registrations, fn($r) => ($r['status'] ?? '') === 'confirmed'));
$pendingPay     = count(array_filter($registrations, fn($r) => ($r['payment_status'] ?? '') === 'pending'));

$inst        = $institution ?? null;
$hasInst     = !empty($inst);
$unitCount   = count($unit_access_cards ?? []);
$staffCount  = count($event_staff_cards ?? []);
$instTypes   = $institution_types ?? [];
?>

<?php if (!$profileComplete): ?>
<div class="alert alert-warning d-flex align-items-center gap-3 mb-4" role="alert">
  <i class="bi bi-person-exclamation fs-4 flex-shrink-0"></i>
  <div>
    <strong>Profile Incomplete.</strong> Complete your profile to register for events.
    <a href="/athlete/profile" class="alert-link ms-2">Complete Now →</a>
  </div>
</div>
<?php endif; ?>

<div class="row g-3">

  <!-- ── LEFT RAIL: one unified profile (athlete + institution) + menu ── -->
  <div class="col-12 col-lg-4 col-xl-3">
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
            <?php if ($profileComplete): ?>
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
          <div><div class="v"><?= $regCount ?></div><div class="l">Registered</div></div>
          <div><div class="v"><?= $confirmedCount ?></div><div class="l">Confirmed</div></div>
          <div><div class="v"><?= $pendingPay ?></div><div class="l">Pay due</div></div>
        </div>

        <!-- Merged institution / unit section -->
        <div class="sms-profile-section">
          <div class="sms-sec-label"><i class="bi bi-building me-1"></i>Institution / Unit</div>
          <?php if ($hasInst): ?>
            <div class="sms-inst-row">
              <?php if (!empty($inst['logo'])): ?>
                <img src="<?= e($inst['logo']) ?>" alt="" class="sms-inst-logo">
              <?php else: ?>
                <span class="sms-inst-logo"><i class="bi bi-building"></i></span>
              <?php endif; ?>
              <div class="min-w-0">
                <div class="fw-semibold text-truncate" title="<?= e($inst['name'] ?? '') ?>"><?= e($inst['name'] ?? 'Your institution') ?></div>
                <?php if (!empty($inst['type_name'])): ?>
                  <div class="small text-muted text-truncate"><?= e($inst['type_name']) ?></div>
                <?php endif; ?>
              </div>
            </div>
            <a href="/institution/dashboard" class="btn btn-sm btn-outline-secondary w-100 mt-2">
              <i class="bi bi-box-arrow-up-right me-1"></i>Open institution workspace
            </a>
          <?php elseif ($profileComplete): ?>
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
          <a href="/athlete/dashboard" class="active"><i class="bi bi-grid"></i>Dashboard</a>
          <a href="/athlete/profile"><i class="bi bi-person-badge"></i>My Profile</a>
          <a href="/athlete/my-registrations"><i class="bi bi-list-check"></i>My Registrations
            <?php if ($regCount): ?><span class="badge bg-primary-subtle text-primary-emphasis"><?= $regCount ?></span><?php endif; ?></a>
          <a href="/athlete/my-results"><i class="bi bi-trophy"></i>My Results</a>
          <a href="#eligibleEvents"><i class="bi bi-search"></i>Browse Events</a>
          <?php if ($unitCount): ?>
            <a href="#unitAccess"><i class="bi bi-buildings"></i>Unit / Club Access
              <span class="badge bg-success-subtle text-success-emphasis"><?= $unitCount ?></span></a>
          <?php endif; ?>
          <?php if ($staffCount): ?>
            <a href="#staffAccess"><i class="bi bi-clipboard-check"></i>Event Staff Access
              <span class="badge bg-info-subtle text-info-emphasis"><?= $staffCount ?></span></a>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /sticky -->
  </div><!-- /left rail -->

  <!-- ── MAIN: events + participations + access ── -->
  <div class="col-12 col-lg-8 col-xl-9">

    <!-- Events open for participation (registration-type aware) -->
    <?php require APP_ROOT . '/views/partials/eligible-events.php'; ?>

    <!-- Events I'm Participating In (unit side) — only when this account owns an institution -->
    <?php if (!empty($has_institution)):
      $myParticipations = array_values(array_filter($participation_events ?? [], function ($pe) {
        $st = (string)($pe['request_status'] ?? '');
        return $st === 'pending' || $st === 'approved' || !empty($pe['linked_unit_id']);
      }));
      if (!empty($myParticipations)):
        $participation_mine_only = true; $participation_open = true;
        require APP_ROOT . '/views/partials/participation-events.php';
      endif;
    endif; ?>

    <!-- Unit / Club access -->
    <?php if ($unitCount): require APP_ROOT . '/views/partials/unit-access-cards.php'; endif; ?>

    <!-- Event staff access -->
    <?php if ($staffCount): require APP_ROOT . '/views/partials/event-staff-cards.php'; endif; ?>

  </div><!-- /main -->
</div><!-- /row -->

<?php if (!$hasInst && $profileComplete): ?>
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
            <?php foreach ($instTypes as $t): ?>
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
