<?php
/**
 * Shared left rail for the Unit console — same LinkedIn-style shell as the
 * athlete/institution workspaces. A unit "profile" card (logo, unit name,
 * event, key stats) plus a vertical menu mirroring the top nav (with the same
 * conditional items).
 *
 * Expects: $unit_user, $event, $units, $active_unit, $stats
 * Optional: $active_menu ('dashboard'|'registrations'|'team-entry'|'transactions'
 *                        |'noc'|'lane'|'medal')
 */
$__ev = $event ?? [];
// Menu guards — mirror app/views/layouts/unit.php exactly.
$railTeamEntry = false;
try { $railTeamEntry = in_array('unit_user', \eventTeamEntryMethods($__ev), true); } catch (\Throwable $e) {}
$railNoc = false;
if (!empty($__ev['id']) && !empty($__ev['noc_enabled'])) {
    $__nocIds = array_values(array_map(fn($u) => (int)$u['id'], $units ?? []));
    if ($__nocIds) { try { $railNoc = \Models\Noc::approvedCount((int)$__ev['id'], $__nocIds) > 0; } catch (\Throwable $e) {} }
}
$railLane  = !empty($__ev['unit_lane_allocation_enabled']);
$railMedal = false;
try { $__sp = \Models\Event::sport($__ev); $railMedal = (stripos($__sp, 'athlet') !== false || stripos($__sp, 'skat') !== false); } catch (\Throwable $e) {}
$railApxB  = !empty($active_unit) && \Controllers\UnitController::appendixBAvailable($__ev);
$railBalance = (float)($stats['demand'] ?? 0) - (float)($stats['claimed'] ?? 0);
$railActive = $active_menu ?? 'dashboard';
$railIs = fn(string $k) => $railActive === $k ? 'active' : '';
?>
<div class="sms-rail-sticky">

  <!-- Unit profile card -->
  <div class="sms-card sms-profile-card mb-3">
    <div class="sms-profile-cover"></div>
    <div class="sms-profile-top">
      <div id="unitLogoBox" class="mx-auto mb-2" style="width:84px;height:84px">
        <?php if (!empty($active_unit['logo'])): ?>
          <img src="<?= e($active_unit['logo']) ?>?t=<?= time() ?>" id="unitLogoImg" alt="Unit Logo"
               width="84" height="84" class="rounded" style="object-fit:cover;border:1px solid #e2e8f0;background:#fff">
        <?php else: ?>
          <div id="unitLogoImg" class="rounded d-flex align-items-center justify-content-center bg-light text-muted mx-auto"
               style="width:84px;height:84px;border:1px dashed #cbd5e1"><i class="bi bi-image fs-4"></i></div>
        <?php endif; ?>
      </div>
      <input type="file" id="unitLogoFile" accept="image/jpeg,image/png,image/webp"
             class="d-none" onchange="initUnitLogoCrop(this)">
      <div>
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('unitLogoFile').click()">
          <i class="bi bi-upload me-1"></i>Change Logo
        </button>
      </div>
      <div id="unitLogoSaving" class="small text-primary mt-1 d-none"><span class="spinner-border spinner-border-sm"></span></div>

      <h5 class="sms-profile-name mt-2 text-break"><?= e($active_unit['name'] ?? 'Unit') ?></h5>
      <div class="sms-profile-meta text-break"><i class="bi bi-calendar-event me-1"></i><?= e($event['name'] ?? '') ?></div>
      <div class="small text-muted mt-1">Unit #<?= (int)($active_unit['id'] ?? 0) ?> &middot; Code <?= e($event['event_code'] ?? '') ?></div>

      <?php if (!empty($event['allow_unit_registration'])): ?>
        <a href="/unit/athletes/new" class="btn btn-sm btn-primary w-100 mt-3">
          <i class="bi bi-person-plus me-1"></i>Add Athlete
        </a>
      <?php endif; ?>
      <?php if ($railApxB): ?>
        <a href="/unit/appendix-b/<?= (int)$active_unit['id'] ?>" target="_blank" rel="noopener"
           class="btn btn-sm btn-outline-success w-100 mt-2"
           title="Athletics entry forms — Track / Field, Men / Women (Appendix B)">
          <i class="bi bi-file-earmark-pdf me-1"></i>Appendix B
        </a>
      <?php endif; ?>
    </div>

    <!-- Key stats -->
    <div class="sms-profile-stats">
      <div><div class="v"><?= (int)($stats['total'] ?? 0) ?></div><div class="l">Athletes</div></div>
      <div><div class="v"><?= (int)($stats['approved'] ?? 0) ?></div><div class="l">Approved</div></div>
      <div><div class="v" style="font-size:.9rem;<?= $railBalance > 0.005 ? 'color:#dc3545' : ($railBalance < -0.005 ? 'color:#997404' : 'color:#198754') ?>">₹<?= number_format($railBalance, 0) ?></div><div class="l">Balance</div></div>
    </div>

    <?php if (!empty($units) && count($units) > 1): ?>
    <!-- Unit switcher -->
    <div class="sms-profile-section">
      <div class="sms-sec-label"><i class="bi bi-arrow-left-right me-1"></i>Switch Unit</div>
      <form method="GET" action="/unit/dashboard">
        <select name="unit_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <?php foreach ($units as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= $active_unit && (int)$active_unit['id'] === (int)$u['id'] ? 'selected' : '' ?>>
              <?= e($u['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
    <?php endif; ?>
  </div>

  <!-- Quick menu -->
  <div class="sms-card mb-3">
    <div class="sms-rail-menu">
      <a href="/unit/dashboard" class="<?= $railIs('dashboard') ?>"><i class="bi bi-grid"></i>Dashboard</a>
      <a href="/unit/registrations" class="<?= $railIs('registrations') ?>"><i class="bi bi-clipboard-data"></i>Registrations</a>
      <?php if ($railTeamEntry): ?>
        <a href="/team-entry" class="<?= $railIs('team-entry') ?>"><i class="bi bi-people"></i>Team Entry</a>
      <?php endif; ?>
      <a href="/unit/transactions" class="<?= $railIs('transactions') ?>"><i class="bi bi-cash-stack"></i>Transactions</a>
      <a href="#" onclick="openSubmitAll(); return false;"><i class="bi bi-send-check"></i>Submit Applications</a>
      <?php if ($railNoc): ?>
        <a href="/unit/noc" class="<?= $railIs('noc') ?>"><i class="bi bi-file-earmark-check"></i>NOC</a>
      <?php endif; ?>
      <?php if ($railLane): ?>
        <a href="/lane-allocation" class="<?= $railIs('lane') ?>"><i class="bi bi-bullseye"></i>Lane Allocation</a>
      <?php endif; ?>
      <?php if ($railMedal): ?>
        <a href="/unit/medal-tally" class="<?= $railIs('medal') ?>"><i class="bi bi-award"></i>Medal Tally</a>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /sticky rail -->
