<?php
$pageTitle = 'Edit Event';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$eventId = (int)$event['id'];
$paymentModes = $event['payment_modes'] ?? [];
$eventSports  = $event['sports'] ?? [];
$units        = $units ?? [];
$documents    = $documents ?? [];
$nocRequired  = $event['noc_required'] ?? 'optional';
$teamEntryEnabled = !empty($event['team_entry_enabled']);
$teamEntryMethods = eventTeamEntryMethods($event);
?>

<!-- Toast -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999">
  <div id="evToast" class="toast align-items-center border-0" role="alert" aria-live="assertive">
    <div class="d-flex">
      <div class="toast-body fw-medium" id="toastMsg"></div>
      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<!-- Header -->
<?php
  $statusMap = [
    'draft'     => ['label' => 'Draft',     'class' => 'bg-secondary'],
    'active'    => ['label' => 'Active',    'class' => 'bg-success'],
    'completed' => ['label' => 'Completed', 'class' => 'bg-info text-dark'],
    'suspended' => ['label' => 'Suspended', 'class' => 'bg-danger'],
  ];
  $currentStatus = $event['status'] ?? 'draft';
  if (!isset($statusMap[$currentStatus])) $currentStatus = 'draft';
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div class="d-flex align-items-center gap-2">
    <a href="/institution/events" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <h5 class="mb-0 fw-bold"><i class="bi bi-calendar-event me-2"></i><?= e($event['name']) ?></h5>
    <span id="statusBadge" class="badge ms-1 <?= $statusMap[$currentStatus]['class'] ?>"><?= $statusMap[$currentStatus]['label'] ?></span>
  </div>
  <div class="d-flex align-items-center gap-2">
    <label for="ev_status" class="form-label small mb-0 me-1 text-muted">Status</label>
    <select id="ev_status" class="form-select form-select-sm" style="width:160px">
      <?php foreach ($statusMap as $k => $v): ?>
        <option value="<?= $k ?>" <?= $currentStatus === $k ? 'selected' : '' ?>><?= $v['label'] ?></option>
      <?php endforeach; ?>
    </select>
    <button type="button" id="statusBtn" class="btn btn-primary btn-sm fw-semibold" onclick="saveSection('status')">
      <i class="bi bi-save me-1"></i>Save Status
    </button>
    <a href="/institution/events/<?= e(hid_event((int)$event['id'])) ?>/reports"
       class="btn btn-outline-primary btn-sm fw-semibold">
      <i class="bi bi-bar-chart me-1"></i>Reports
    </a>
    <a href="/institution/events/<?= e(hid_event((int)$event['id'])) ?>/unit-users"
       class="btn btn-outline-primary btn-sm fw-semibold">
      <i class="bi bi-person-gear me-1"></i>Unit Users
    </a>
    <a href="/institution/events/<?= e(hid_event((int)$event['id'])) ?>/staff-users"
       class="btn btn-outline-primary btn-sm fw-semibold">
      <i class="bi bi-person-vcard me-1"></i>Event Staff
    </a>
    <a href="/institution/events/<?= e(hid_event((int)$event['id'])) ?>/team-registrations"
       class="btn btn-outline-primary btn-sm fw-semibold">
      <i class="bi bi-people me-1"></i>Team Entries
    </a>
    <a href="/institution/events/<?= e(hid_event((int)$event['id'])) ?>/grievances"
       class="btn btn-outline-primary btn-sm fw-semibold">
      <i class="bi bi-chat-square-dots me-1"></i>Grievances
      <?php $gOpen = (int)($event['grievance_open'] ?? 0); $gTot = (int)($event['grievance_total'] ?? 0); if ($gTot > 0): ?>
        <span class="badge rounded-pill <?= $gOpen > 0 ? 'bg-danger' : 'bg-secondary' ?> ms-1">
          <?= $gOpen > 0 ? $gOpen . ' open' : $gTot ?>
        </span>
      <?php endif; ?>
    </a>
  </div>
</div>
<div class="alert alert-info py-2 small mb-3">
  <i class="bi bi-info-circle me-1"></i>
  Set <strong>Active</strong> to publish this event to athletes; <strong>Draft</strong> while you are still editing;
  <strong>Suspended</strong> to temporarily hide it; <strong>Completed</strong> after the event has finished.
</div>

<div class="row g-4">

  <div class="col-lg-8">

    <!-- Event Details -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0">Event Details</h6>
        <button type="button" class="btn btn-sm btn-primary px-3" onclick="saveSection('details')"><i class="bi bi-save me-1"></i>Save</button>
      </div>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label fw-medium">Event Name <span class="text-danger">*</span></label>
          <input type="text" id="ev_name" value="<?= e($event['name']) ?>" class="form-control">
        </div>
        <div class="col-md-12">
          <label class="form-label fw-medium">Venue / Location <span class="text-danger">*</span></label>
          <input type="text" id="ev_location" value="<?= e($event['location']) ?>" class="form-control">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-medium">Registration From <span class="text-danger">*</span></label>
          <input type="date" id="ev_reg_from" value="<?= e($event['reg_date_from']) ?>" class="form-control">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-medium">Registration To <span class="text-danger">*</span></label>
          <input type="date" id="ev_reg_to" value="<?= e($event['reg_date_to']) ?>" class="form-control">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-medium">Event Starts <span class="text-danger">*</span></label>
          <input type="date" id="ev_event_from" value="<?= e($event['event_date_from']) ?>" class="form-control">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-medium">Event Ends <span class="text-danger">*</span></label>
          <input type="date" id="ev_event_to" value="<?= e($event['event_date_to']) ?>" class="form-control">
        </div>
      </div>
    </div>

    <!-- Map Location moved to right column under Contact SPOC -->

    <!-- Payment -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-credit-card me-2"></i>Payment Settings</h6>
        <button type="button" class="btn btn-sm btn-primary px-3" onclick="saveSection('payment')"><i class="bi bi-save me-1"></i>Save</button>
      </div>
      <label class="form-label fw-medium">Payment Mode(s) <span class="text-danger">*</span></label>
      <div class="d-flex gap-3 mb-3 flex-wrap">
        <div class="form-check form-check-inline border rounded-3 px-3 py-2">
          <input class="form-check-input" type="checkbox" name="payment_modes[]" value="manual" id="pm_manual"
                 <?= in_array('manual', $paymentModes) ? 'checked' : '' ?> onchange="toggleManualFields()">
          <label class="form-check-label fw-medium" for="pm_manual"><i class="bi bi-bank me-1"></i>Manual Submission</label>
        </div>
        <div class="form-check form-check-inline border rounded-3 px-3 py-2">
          <input class="form-check-input" type="checkbox" name="payment_modes[]" value="online" id="pm_online"
                 <?= in_array('online', $paymentModes) ? 'checked' : '' ?> onchange="toggleOnlineFields()">
          <label class="form-check-label fw-medium" for="pm_online"><i class="bi bi-credit-card me-1"></i>Online Payment</label>
        </div>
      </div>
      <div id="manualFields" style="display:none">
        <div class="mb-3">
          <label class="form-label fw-medium">Bank Details</label>
          <textarea id="bank_details" rows="3" class="form-control"
                    placeholder="Bank Name, Account Number, IFSC, Branch…"><?= e($event['bank_details'] ?? '') ?></textarea>
        </div>
        <div>
          <label class="form-label fw-medium">Bank QR Code <small class="text-muted fw-normal">(Optional)</small></label>
          <?php if ($event['bank_qr_code']): ?>
            <div class="mb-1" id="qrPreview"><img src="<?= e($event['bank_qr_code']) ?>" alt="QR" height="60" class="rounded"></div>
          <?php else: ?>
            <div class="mb-1 d-none" id="qrPreview"></div>
          <?php endif; ?>
          <input type="file" id="bank_qr_code" class="form-control" accept="image/jpeg,image/png">
        </div>
      </div>
      <div id="onlineFields" style="display:none">
        <div class="alert alert-info py-2 px-3 small mb-3">
          <i class="bi bi-info-circle me-1"></i>Bank account where Online Payment receipts will be settled. Visible only to the event administrator and Super Admin.
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-medium">Bank Name</label>
            <input type="text" id="bank_name" class="form-control" maxlength="255"
                   value="<?= e($event['bank_name'] ?? '') ?>" placeholder="e.g. State Bank of India">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-medium">Branch Name</label>
            <input type="text" id="bank_branch" class="form-control" maxlength="255"
                   value="<?= e($event['bank_branch'] ?? '') ?>" placeholder="e.g. M G Road, Bengaluru">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-medium">Account Number</label>
            <input type="text" id="bank_account_number" class="form-control" maxlength="64"
                   value="<?= e($event['bank_account_number'] ?? '') ?>" placeholder="e.g. 00012345678901"
                   inputmode="numeric" pattern="[0-9]*">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-medium">IFSC Code</label>
            <input type="text" id="bank_ifsc" class="form-control text-uppercase" maxlength="20"
                   value="<?= e($event['bank_ifsc'] ?? '') ?>" placeholder="e.g. SBIN0001234"
                   style="text-transform:uppercase">
          </div>
        </div>
      </div>
    </div>

    <!-- Sports in this Event -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3 flex-wrap gap-2">
        <h6 class="fw-semibold mb-0"><i class="bi bi-trophy me-2"></i>Sports in this Event</h6>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="downloadSportsCsv()">
          <i class="bi bi-download me-1"></i>Download CSV
        </button>
      </div>

      <!-- Picker -->
      <div class="row g-2 align-items-end mb-3">
        <div class="col-md-2">
          <label class="form-label small mb-1">Sport</label>
          <select id="picker_sport" class="form-select form-select-sm" onchange="loadCategories()">
            <option value="">— Select Sport —</option>
            <?php foreach ($sports as $sp): ?>
              <option value="<?= (int)$sp['id'] ?>"><?= e($sp['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small mb-1">Category</label>
          <select id="picker_category" class="form-select form-select-sm" disabled onchange="loadSportEvents()">
            <option value="">— Select Category —</option>
          </select>
        </div>
        <div class="col-md-1">
          <label class="form-label small mb-1">Gender</label>
          <select id="picker_gender" class="form-select form-select-sm" onchange="loadSportEvents()">
            <option value="">All</option>
            <option value="male">Men</option>
            <option value="female">Women</option>
            <option value="mixed">Mixed</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small mb-1">Sport Event</label>
          <select id="picker_sport_event" class="form-select form-select-sm" disabled>
            <option value="">— Select Event —</option>
          </select>
        </div>
        <div class="col-md-1">
          <label class="form-label small mb-1">Fee ₹</label>
          <input id="picker_fee" type="number" min="0" step="0.01" class="form-control form-control-sm" value="0">
        </div>
        <div class="col-md-1">
          <label class="form-label small mb-1" title="Team entry fee (optional)">Team ₹</label>
          <input id="picker_team_fee" type="number" min="0" step="0.01" class="form-control form-control-sm" placeholder="—">
        </div>
        <div class="col-md-2">
          <label class="form-label small mb-1">Event Code <span class="text-danger">*</span></label>
          <input id="picker_event_code" type="text" maxlength="50" class="form-control form-control-sm"
                 placeholder="e.g. AP-10M-SR-M">
        </div>
        <div class="col-md-1">
          <button type="button" class="btn btn-sm btn-primary w-100" onclick="addSportEvent()"><i class="bi bi-plus me-1"></i>Add</button>
        </div>
      </div>

      <p class="small text-muted mb-3">No matching events for the selected category/gender? Ask the super admin to add them under <em>Settings → Sports</em>.</p>

      <!-- Search & filter on the added list -->
      <div class="row g-2 align-items-center mb-2">
        <div class="col-md-5">
          <input id="rowsSearch" type="search" class="form-control form-control-sm"
                 placeholder="Search added events…" oninput="applyRowFilters()">
        </div>
        <div class="col-md-3">
          <select id="rowsSportFilter" class="form-select form-select-sm" onchange="applyRowFilters()">
            <option value="">All sports</option>
          </select>
        </div>
        <div class="col-md-3">
          <select id="rowsGenderFilter" class="form-select form-select-sm" onchange="applyRowFilters()">
            <option value="">All genders</option>
            <option value="male">Men</option>
            <option value="female">Women</option>
            <option value="mixed">Mixed</option>
          </select>
        </div>
        <div class="col-md-1 text-end">
          <span class="badge bg-secondary" id="rowsCount">0</span>
        </div>
      </div>

      <div class="table-responsive" id="sportsTableWrap">
        <table class="table table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>Sport</th>
              <th>Event Code</th>
              <th>Category / Event</th>
              <th>Age / Gender</th>
              <th class="text-end">Entry Fee</th>
              <th class="text-end" title="Team entry fee (optional)">Team Entry Fee</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="sportsRows">
            <?php if ($eventSports): foreach ($eventSports as $row): ?>
              <tr data-row-id="<?= (int)$row['id'] ?>"
                  data-sport="<?= e($row['sport_name']) ?>"
                  data-gender="<?= e($row['sport_event_gender'] ?? '') ?>"
                  data-label="<?= e($row['sport_event_name'] ?? $row['category'] ?? '') ?>">
                <td><?= e($row['sport_name']) ?></td>
                <td>
                  <input type="text" class="form-control form-control-sm font-monospace"
                         data-field="event_code" value="<?= e($row['event_code'] ?? '') ?>"
                         maxlength="50" placeholder="e.g. AP-10M-SR-M" style="min-width:130px">
                </td>
                <td><?= e($row['sport_event_category'] ?? '') ?> <span class="text-muted"><?= e($row['sport_event_name'] ?? $row['category'] ?? '') ?></span></td>
                <td><?= e($row['sport_event_age_category'] ?? '') ?> <span class="text-muted small"><?= e($row['sport_event_gender'] ?? '') ?></span></td>
                <td class="text-end">
                  <div class="input-group input-group-sm" style="min-width:110px">
                    <span class="input-group-text">₹</span>
                    <input type="number" class="form-control text-end"
                           data-field="entry_fee" min="0" step="0.01"
                           value="<?= number_format((float)$row['entry_fee'], 2, '.', '') ?>">
                  </div>
                </td>
                <td class="text-end">
                  <div class="input-group input-group-sm" style="min-width:110px">
                    <span class="input-group-text">₹</span>
                    <input type="number" class="form-control text-end"
                           data-field="team_entry_fee" min="0" step="0.01"
                           value="<?= isset($row['team_entry_fee']) && $row['team_entry_fee'] !== null && $row['team_entry_fee'] !== ''
                                        ? number_format((float)$row['team_entry_fee'], 2, '.', '')
                                        : '' ?>"
                           placeholder="—">
                  </div>
                </td>
                <td class="text-end text-nowrap">
                  <button class="btn btn-sm btn-outline-primary me-1" type="button" onclick="updateSportEvent(this)" title="Save changes">
                    <i class="bi bi-save"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-danger" type="button" onclick="removeSportEvent(this)" title="Remove">
                    <i class="bi bi-trash"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; else: ?>
              <tr id="emptyRow"><td colspan="7" class="text-muted text-center py-3">No sport events added yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Registration Settings (NOC + Team Entry) -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-shield-check me-2"></i>Registration Settings</h6>
        <button type="button" class="btn btn-sm btn-primary px-3" onclick="saveSection('noc')"><i class="bi bi-save me-1"></i>Save</button>
      </div>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-medium">NOC Letter from Unit</label>
          <select id="noc_required" class="form-select form-select-sm">
            <option value="none"      <?= $nocRequired==='none'      ? 'selected':'' ?>>Not Required</option>
            <option value="optional"  <?= $nocRequired==='optional'  ? 'selected':'' ?>>Optional</option>
            <option value="mandatory" <?= $nocRequired==='mandatory' ? 'selected':'' ?>>Mandatory</option>
          </select>
          <small class="text-muted d-block mt-1">Athletes will see this on the registration page when picking a Unit.</small>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-medium d-block">Team Entry</label>
          <div class="form-check form-switch mt-1">
            <input class="form-check-input" type="checkbox" role="switch" id="team_entry_enabled"
                   <?= $teamEntryEnabled ? 'checked' : '' ?> onchange="toggleTeamEntryMethods()">
            <label class="form-check-label" for="team_entry_enabled">
              Enable Team Entry registrations
            </label>
          </div>
          <div id="teamEntryMethods" class="mt-2 ps-1" style="<?= $teamEntryEnabled ? '' : 'display:none' ?>">
            <div class="small fw-medium text-muted mb-1">Allowed submission methods <span class="text-danger">*</span></div>
            <?php
              $methodLabels = [
                'athlete'     => 'Through Athlete Login',
                'unit_user'   => 'Through Unit User Login',
                'event_staff' => 'Through Event Staff Login',
              ];
            ?>
            <?php foreach ($methodLabels as $mKey => $mLabel): ?>
              <div class="form-check">
                <input class="form-check-input team-entry-method" type="checkbox"
                       id="tem_<?= $mKey ?>" value="<?= $mKey ?>"
                       <?= in_array($mKey, $teamEntryMethods, true) ? 'checked' : '' ?>>
                <label class="form-check-label" for="tem_<?= $mKey ?>"><?= $mLabel ?></label>
              </div>
            <?php endforeach; ?>
            <small class="text-muted d-block mt-1">Pick at least one. Only the checked portals will see the Team Entry option for this event.</small>
          </div>
          <small class="text-muted d-block mt-1">When enabled, teams of three members can be registered under this event using the per-sport-event Team Entry Fee.</small>
        </div>
      </div>
    </div>

    <!-- Units / Clubs / Institutions -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3 flex-wrap gap-2">
        <h6 class="fw-semibold mb-0"><i class="bi bi-buildings me-2"></i>Units / Clubs / Institutions</h6>
        <div class="d-flex align-items-center gap-2">
          <a href="data:text/csv;charset=utf-8,name,address%0AABC%20Sports%20Club,123%20Main%20St%0AXYZ%20Academy,"
             download="units-template.csv" class="small text-muted text-decoration-none" title="Download template">
            <i class="bi bi-download me-1"></i>Template
          </a>
          <label class="btn btn-sm btn-outline-secondary mb-0">
            <i class="bi bi-upload me-1"></i>Upload CSV
            <input type="file" id="unitCsv" accept=".csv,text/csv" class="d-none" onchange="unitsCsvUpload(this)">
          </label>
        </div>
      </div>
      <p class="small text-muted mb-3">
        Athletes pick from this list while registering. Each unit needs a name; address is optional.
        <span class="d-block mt-1">CSV format: <code>name,address</code> — header row optional, blank rows and duplicates (case-insensitive) are skipped.</span>
      </p>

      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-light"><tr><th style="width:35%">Name</th><th>Address</th><th style="width:120px"></th></tr></thead>
          <tbody id="unitRows">
            <?php foreach ($units as $u): ?>
              <tr data-id="<?= (int)$u['id'] ?>">
                <td><input class="form-control form-control-sm" data-field="name" value="<?= e($u['name']) ?>"></td>
                <td><input class="form-control form-control-sm" data-field="address" value="<?= e($u['address'] ?? '') ?>"></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary me-1" type="button" onclick="unitSave(this)"><i class="bi bi-save"></i></button>
                  <button class="btn btn-sm btn-outline-danger" type="button" onclick="unitDelete(this)"><i class="bi bi-trash"></i></button>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($units)): ?>
              <tr id="emptyUnits"><td colspan="3" class="text-muted text-center py-3">No units added yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="row g-2 align-items-end border-top pt-3">
        <div class="col-md-4"><label class="form-label small mb-1">Add New Unit</label>
          <input id="newUnitName" class="form-control form-control-sm" placeholder="Unit / Club / Institution name"></div>
        <div class="col-md-7">
          <input id="newUnitAddress" class="form-control form-control-sm" placeholder="Address (optional)">
        </div>
        <div class="col-md-1"><button type="button" class="btn btn-primary btn-sm w-100" onclick="unitAdd()"><i class="bi bi-plus me-1"></i>Add</button></div>
      </div>
    </div>

    <!-- Sports Items / Weapons (per-event allow-list) -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3 flex-wrap gap-2">
        <h6 class="fw-semibold mb-0"><i class="bi bi-tools me-2"></i>Sports Items / Weapons (allowed for athletes)</h6>
      </div>
      <p class="small text-muted">Pick a sport, then add the catalogue items / weapons that athletes can declare during registration.</p>

      <div class="row g-2 align-items-end mb-3">
        <div class="col-md-4">
          <label class="form-label small mb-1">Sport</label>
          <select id="ei_sport" class="form-select form-select-sm" onchange="onItemSportChange()">
            <option value="">Select a sport…</option>
            <?php foreach ($sports as $s): ?>
              <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label small mb-1">Item / Weapon</label>
          <select id="ei_item" class="form-select form-select-sm">
            <option value="">— pick a sport first —</option>
          </select>
        </div>
        <div class="col-md-2">
          <button type="button" class="btn btn-primary btn-sm w-100" onclick="addEventItem()">
            <i class="bi bi-plus-lg me-1"></i>Add
          </button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr><th>Sport</th><th>Item / Weapon</th><th>Description</th><th class="text-end"></th></tr>
          </thead>
          <tbody id="eventItemsTbody">
            <?php if (empty($event_items)): ?>
              <tr id="eventItemsEmpty"><td colspan="4" class="text-muted text-center py-3">No items selected yet.</td></tr>
            <?php else: foreach ($event_items as $it): ?>
              <tr data-item-id="<?= (int)$it['sport_item_id'] ?>">
                <td class="text-muted small"><?= e($it['sport_name']) ?></td>
                <td class="fw-medium"><?= e($it['item_name']) ?></td>
                <td class="text-muted small"><?= e($it['item_description'] ?? '—') ?></td>
                <td class="text-end">
                  <button type="button" class="btn btn-sm btn-outline-danger"
                          onclick="removeEventItem(<?= (int)$it['sport_item_id'] ?>)">
                    <i class="bi bi-trash"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Shooting Range Venues (venue → range → lane) -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3 flex-wrap gap-2">
        <h6 class="fw-semibold mb-0"><i class="bi bi-bullseye me-2"></i>Shooting Range Venues</h6>
        <button type="button" class="btn btn-sm btn-primary" onclick="sRangeAdd()">
          <i class="bi bi-plus-lg me-1"></i>Add Venue
        </button>
      </div>
      <p class="small text-muted">Each event can have multiple <strong>Venues</strong> (name + location). Each venue contains one or more <strong>Shooting Ranges</strong> (named — e.g. <em>Main Range</em>, <em>Pistol Bay</em> — with an optional distance). Each shooting range has numbered <strong>Lanes</strong> (Manual / Mechanical / Electronic).</p>
      <div id="sRangesTree">
        <?php if (empty($shooting_ranges)): ?>
          <div class="text-muted small fst-italic py-2" id="sRangesEmpty">
            <i class="bi bi-info-circle me-1"></i>No venues added yet. Click <strong>Add Venue</strong> to start.
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Relay Details (per-event schedule mapped onto shooting ranges) -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3 flex-wrap gap-2">
        <h6 class="fw-semibold mb-0"><i class="bi bi-stopwatch me-2"></i>Relay Details</h6>
        <button type="button" class="btn btn-sm btn-primary" onclick="openRelayModal()">
          <i class="bi bi-plus-lg me-1"></i>Add Relay
        </button>
      </div>
      <p class="small text-muted">Each relay is a scheduled slot on a specific Shooting Range. The lanes table inside the relay modal assigns an Event Category (or marks the lane Reserved / Not Using) per lane.</p>
      <div id="relaysList"></div>
    </div>

    <!-- Add / Edit Relay modal -->
    <div class="modal fade" id="relayModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="relayModalTitle"><i class="bi bi-stopwatch me-2"></i>Add Relay</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="relay_id" value="0">
            <div class="row g-3 mb-3">
              <div class="col-md-3">
                <label class="form-label small mb-1">Relay Number <span class="text-danger">*</span></label>
                <input type="text" id="relay_number" class="form-control" maxlength="64" placeholder="e.g. R1, A">
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1">Relay Date</label>
                <input type="date" id="relay_date" class="form-control">
              </div>
              <div class="col-md-2">
                <label class="form-label small mb-1">Match Time</label>
                <input type="time" id="relay_match_time" class="form-control">
              </div>
              <div class="col-md-2">
                <label class="form-label small mb-1">Reporting Time</label>
                <input type="time" id="relay_reporting_time" class="form-control">
              </div>
              <div class="col-md-2">
                <label class="form-label small mb-1">Shooting Range <span class="text-danger">*</span></label>
                <select id="relay_range_id" class="form-select" onchange="onRelayRangeChange()"></select>
              </div>
            </div>
            <h6 class="fw-semibold border-top pt-3 mt-3 mb-2"><i class="bi bi-grid-3x3-gap me-2"></i>Active Lanes</h6>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0" id="relayLanesTable">
                <thead class="table-light">
                  <tr>
                    <th style="width:7%" class="text-center">Sl. No</th>
                    <th>Lane Number</th>
                    <th>Type</th>
                    <th>Event Category</th>
                  </tr>
                </thead>
                <tbody id="relayLanesBody">
                  <tr><td colspan="4" class="text-muted text-center py-3">Pick a Shooting Range first.</td></tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="relaySaveFromModal()">
              <i class="bi bi-save me-1"></i>Save Relay
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Documents (Undertaking, Rules, etc.) -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-file-earmark-text me-2"></i>Event Documents</h6>
      </div>
      <p class="small text-muted mb-3">
        Upload event-specific forms (e.g. Undertaking Form, Rules &amp; Regulations).
        Active documents appear on the athlete registration page with a <em>View</em> button.
      </p>

      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th style="width:25%">Name</th>
              <th>Purpose</th>
              <th style="width:130px">Status</th>
              <th style="width:200px">File</th>
              <th style="width:140px"></th>
            </tr>
          </thead>
          <tbody id="docRows">
            <?php foreach ($documents as $d): ?>
              <tr data-id="<?= (int)$d['id'] ?>">
                <td><input class="form-control form-control-sm" data-field="name" value="<?= e($d['name']) ?>"></td>
                <td><input class="form-control form-control-sm" data-field="purpose" value="<?= e($d['purpose'] ?? '') ?>" placeholder="e.g. Undertaking signed by athlete"></td>
                <td>
                  <select class="form-select form-select-sm" data-field="status">
                    <option value="active"   <?= ($d['status'] ?? 'active') === 'active'   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($d['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                  </select>
                </td>
                <td>
                  <input type="file" class="form-control form-control-sm" data-field="file" accept="application/pdf,image/jpeg,image/png">
                  <?php if (!empty($d['file'])): ?>
                    <a href="<?= e($d['file']) ?>" target="_blank" class="small"><i class="bi bi-eye me-1"></i>Current file</a>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary me-1" type="button" onclick="docSave(this)"><i class="bi bi-save"></i></button>
                  <button class="btn btn-sm btn-outline-danger" type="button" onclick="docDelete(this)"><i class="bi bi-trash"></i></button>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($documents)): ?>
              <tr id="emptyDocs"><td colspan="5" class="text-muted text-center py-3">No documents added yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="row g-2 align-items-end border-top pt-3">
        <div class="col-md-3">
          <label class="form-label small mb-1">Document Name</label>
          <input id="newDocName" class="form-control form-control-sm" placeholder="e.g. Undertaking Form">
        </div>
        <div class="col-md-4">
          <label class="form-label small mb-1">Purpose</label>
          <input id="newDocPurpose" class="form-control form-control-sm" placeholder="What is this document for?">
        </div>
        <div class="col-md-2">
          <label class="form-label small mb-1">Status</label>
          <select id="newDocStatus" class="form-select form-select-sm">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small mb-1">File</label>
          <input id="newDocFile" type="file" class="form-control form-control-sm" accept="application/pdf,image/jpeg,image/png">
        </div>
        <div class="col-md-1">
          <button type="button" class="btn btn-primary btn-sm w-100" onclick="docAdd()"><i class="bi bi-plus me-1"></i>Add</button>
        </div>
      </div>
    </div>

  </div>

  <!-- Right column -->
  <div class="col-lg-4">

    <!-- Logo -->
    <div class="sms-card p-4 mb-4 text-center">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-image me-2"></i>Event Logo</h6>
      </div>
      <div id="logoPreview" class="mb-3">
        <?php if (!empty($event['logo'])): ?>
          <img src="<?= e($event['logo']) ?>?t=<?= time() ?>" alt="Logo" id="currentLogo"
               class="rounded-3" width="160" height="160" style="object-fit:contain;border:1px solid #e2e8f0;background:#fff">
        <?php else: ?>
          <div class="text-muted small" id="currentLogo">No logo uploaded yet.</div>
        <?php endif; ?>
      </div>
      <input type="file" id="logoFileInput" accept="image/jpeg,image/png,image/webp" class="d-none" onchange="onLogoSelect(this)">
      <button type="button" class="btn btn-outline-primary btn-sm w-100"
              onclick="document.getElementById('logoFileInput').click()">
        <i class="bi bi-camera me-1"></i>Change Logo
      </button>
      <small class="text-muted d-block mt-2">JPG/PNG/WEBP · Max 2 MB</small>
      <div id="logoSaving" class="mt-2 d-none text-center">
        <div class="spinner-border spinner-border-sm text-primary"></div>
        <small class="text-muted ms-1">Uploading…</small>
      </div>
    </div>

    <!-- Contact -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-person-lines-fill me-2"></i>Contact (SPOC)</h6>
        <button type="button" class="btn btn-sm btn-primary px-3" onclick="saveSection('contact')"><i class="bi bi-save me-1"></i>Save</button>
      </div>
      <div class="mb-3">
        <label class="form-label fw-medium">Name <span class="text-danger">*</span></label>
        <input type="text" id="contact_name" value="<?= e($event['contact_name']) ?>" class="form-control">
      </div>
      <div class="mb-3">
        <label class="form-label fw-medium">Designation</label>
        <input type="text" id="contact_designation" value="<?= e($event['contact_designation'] ?? '') ?>" class="form-control" placeholder="e.g. Event Coordinator">
      </div>
      <div class="mb-3">
        <label class="form-label fw-medium">Mobile <span class="text-danger">*</span></label>
        <input type="tel" id="contact_mobile" value="<?= e($event['contact_mobile']) ?>" class="form-control" placeholder="10-digit" maxlength="10">
      </div>
      <div>
        <label class="form-label fw-medium">Email <span class="text-danger">*</span></label>
        <input type="email" id="contact_email" value="<?= e($event['contact_email']) ?>" class="form-control">
      </div>
    </div>

    <!-- Map Location -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-geo-alt me-2"></i>Geographic Location</h6>
        <button type="button" class="btn btn-sm btn-primary px-3" onclick="saveSection('location')"><i class="bi bi-save me-1"></i>Save</button>
      </div>
      <div id="eventMap" style="height:240px;border-radius:12px;border:1px solid #e2e8f0"></div>
      <div class="row g-3 mt-2">
        <div class="col-md-6">
          <label class="form-label fw-medium">Latitude</label>
          <input type="text" id="latitude" value="<?= e($event['latitude'] ?? '') ?>" class="form-control" placeholder="e.g. 9.9312">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-medium">Longitude</label>
          <input type="text" id="longitude" value="<?= e($event['longitude'] ?? '') ?>" class="form-control" placeholder="e.g. 76.2673">
        </div>
      </div>
      <small class="text-muted mt-1 d-block">Click on the map to set coordinates, or enter manually.</small>
    </div>

  </div>

</div>

<!-- OpenLayers Map -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@v8.2.0/ol.css">
<script src="https://cdn.jsdelivr.net/npm/ol@v8.2.0/dist/ol.js"></script>

<script>
const CSRF = '<?= e($csrfToken) ?>';
const EV_ID = <?= $eventId ?>;
const SAVE_URL = '/institution/events/' + EV_ID + '/save';

function showToast(msg, type) {
  type = type || 'success';
  const el  = document.getElementById('evToast');
  el.className = 'toast align-items-center border-0 text-bg-' + type;
  document.getElementById('toastMsg').textContent = msg;
  if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
    bootstrap.Toast.getOrCreateInstance(el, { delay: 3500 }).show();
  } else {
    alert(msg);
  }
}

async function postSection(fd) {
  fd.append('_token', CSRF);
  const res = await fetch(SAVE_URL, { method: 'POST', body: fd });
  let data; try { data = await res.json(); } catch (_) { data = { success:false, message:'Server error.' }; }
  return data;
}

async function saveSection(section) {
  const btn = document.querySelector(`button[onclick="saveSection('${section}')"]`);
  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }

  const fd = new FormData();
  fd.append('section', section);

  if (section === 'details') {
    fd.append('name',            document.getElementById('ev_name').value);
    fd.append('location',        document.getElementById('ev_location').value);
    fd.append('reg_date_from',   document.getElementById('ev_reg_from').value);
    fd.append('reg_date_to',     document.getElementById('ev_reg_to').value);
    fd.append('event_date_from', document.getElementById('ev_event_from').value);
    fd.append('event_date_to',   document.getElementById('ev_event_to').value);
  }
  if (section === 'location') {
    fd.append('latitude',  document.getElementById('latitude').value);
    fd.append('longitude', document.getElementById('longitude').value);
  }
  if (section === 'payment') {
    document.querySelectorAll('input[name="payment_modes[]"]:checked').forEach(cb => fd.append('payment_modes[]', cb.value));
    fd.append('bank_details', document.getElementById('bank_details').value);
    const qr = document.getElementById('bank_qr_code');
    if (qr.files[0]) fd.append('bank_qr_code', qr.files[0]);
    fd.append('bank_name',           document.getElementById('bank_name').value);
    fd.append('bank_branch',         document.getElementById('bank_branch').value);
    fd.append('bank_account_number', document.getElementById('bank_account_number').value);
    fd.append('bank_ifsc',           document.getElementById('bank_ifsc').value.toUpperCase());
  }
  if (section === 'contact') {
    fd.append('contact_name',        document.getElementById('contact_name').value);
    fd.append('contact_designation', document.getElementById('contact_designation').value);
    fd.append('contact_mobile',      document.getElementById('contact_mobile').value);
    fd.append('contact_email',       document.getElementById('contact_email').value);
  }
  if (section === 'noc') {
    fd.append('noc_required', document.getElementById('noc_required').value);
    if (document.getElementById('team_entry_enabled')?.checked) {
      fd.append('team_entry_enabled', '1');
      document.querySelectorAll('.team-entry-method:checked')
        .forEach(cb => fd.append('team_entry_methods[]', cb.value));
    }
  }
  if (section === 'status') {
    fd.append('status', document.getElementById('ev_status').value);
  }

  const data = await postSection(fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success && section === 'status') {
    const map = {
      draft:     ['Draft',     'bg-secondary'],
      active:    ['Active',    'bg-success'],
      completed: ['Completed', 'bg-info text-dark'],
      suspended: ['Suspended', 'bg-danger'],
    };
    const v = document.getElementById('ev_status').value;
    const badge = document.getElementById('statusBadge');
    if (badge && map[v]) { badge.className = 'badge ms-1 ' + map[v][1]; badge.textContent = map[v][0]; }
  }
  if (data.success && section === 'payment' && data.qr_url) {
    const prev = document.getElementById('qrPreview');
    prev.classList.remove('d-none');
    prev.innerHTML = '<img src="' + data.qr_url + '?t=' + Date.now() + '" alt="QR" height="60" class="rounded">';
    document.getElementById('bank_qr_code').value = '';
  }
  if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-save me-1"></i>Save'; }
}

/* Show/hide the Team Entry method checkboxes with the master toggle. */
function toggleTeamEntryMethods() {
  const on  = document.getElementById('team_entry_enabled')?.checked;
  const box = document.getElementById('teamEntryMethods');
  if (box) box.style.display = on ? '' : 'none';
}

/* ── Logo Upload (no cropper for now — direct upload) ── */
async function onLogoSelect(input) {
  if (!input.files || !input.files[0]) return;
  if (input.files[0].size > 2 * 1024 * 1024) {
    showToast('Image is larger than 2 MB.', 'danger'); input.value = ''; return;
  }
  document.getElementById('logoSaving').classList.remove('d-none');
  const fd = new FormData();
  fd.append('section', 'logo');
  fd.append('logo', input.files[0]);
  const data = await postSection(fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success && data.logo_url) {
    const url = data.logo_url + '?t=' + Date.now();
    const wrap = document.getElementById('logoPreview');
    wrap.innerHTML = '<img src="' + url + '" alt="Logo" id="currentLogo" class="rounded-3" width="160" height="160" style="object-fit:contain;border:1px solid #e2e8f0;background:#fff">';
  }
  document.getElementById('logoSaving').classList.add('d-none');
  input.value = '';
}

/* ── Sport Picker (chained dropdowns) ── */
async function loadCategories() {
  const sportId = document.getElementById('picker_sport').value;
  const cat = document.getElementById('picker_category');
  const ev  = document.getElementById('picker_sport_event');
  cat.innerHTML = '<option value="">— Select Category —</option>';
  ev.innerHTML  = '<option value="">— Select Event —</option>';
  cat.disabled = !sportId; ev.disabled = true;
  if (!sportId) return;
  const res  = await fetch('/institution/events/sports/' + sportId + '/categories');
  const data = await res.json();
  (data.categories || []).forEach(c => {
    const label = c.abbreviation ? (c.name + ' (' + c.abbreviation + ')') : c.name;
    cat.insertAdjacentHTML('beforeend', '<option value="' + c.id + '">' + label + '</option>');
  });
}
async function loadSportEvents() {
  const catId  = document.getElementById('picker_category').value;
  const gender = document.getElementById('picker_gender').value;
  const ev = document.getElementById('picker_sport_event');
  ev.innerHTML = '<option value="">— Select Event —</option>';
  ev.disabled = !catId;
  if (!catId) return;
  const url = '/institution/events/categories/' + catId + '/events' + (gender ? ('?gender=' + encodeURIComponent(gender)) : '');
  const res  = await fetch(url);
  const data = await res.json();
  const list = data.sport_events || [];
  if (!list.length) {
    ev.insertAdjacentHTML('beforeend', '<option value="" disabled>No events for this filter</option>');
  }
  list.forEach(se =>
    ev.insertAdjacentHTML('beforeend', '<option value="' + se.id + '">' + se.name + '</option>'));
}

async function addSportEvent(force) {
  const seId    = document.getElementById('picker_sport_event').value;
  const fee     = document.getElementById('picker_fee').value || '0';
  const teamFee = document.getElementById('picker_team_fee').value.trim();
  const code    = document.getElementById('picker_event_code').value.trim();
  if (!seId) { showToast('Pick a sport event first.', 'warning'); return; }
  if (!code) { showToast('Enter an Event Code (a short label/identifier).', 'warning'); return; }
  if (teamFee !== '' && parseFloat(teamFee) < 0) {
    showToast('Team Entry Fee, when set, must be zero or more.', 'warning'); return;
  }

  const fd = new FormData();
  fd.append('section', 'sport_event_add');
  fd.append('sport_event_id', seId);
  fd.append('entry_fee', fee);
  fd.append('team_entry_fee', teamFee);
  fd.append('event_code', code);
  if (force) fd.append('force', '1');

  const data = await postSection(fd);
  if (!data.success && data.duplicate) {
    if (confirm(data.message + '\n\nUpdate the entry fee for this event to ₹' + fee + ' and code to "' + code + '"?')) {
      return addSportEvent(true);
    }
    showToast('Already in this event — kept as-is.', 'warning');
    return;
  }
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) {
    renderSportRows(data.list || []);
    document.getElementById('picker_event_code').value = '';
    document.getElementById('picker_team_fee').value   = '';
  }
}
async function updateSportEvent(btn) {
  const tr      = btn.closest('tr');
  const code    = (tr.querySelector('[data-field="event_code"]')?.value || '').trim();
  const fee     = (tr.querySelector('[data-field="entry_fee"]')?.value || '').trim();
  const teamFee = (tr.querySelector('[data-field="team_entry_fee"]')?.value || '').trim();
  if (!code) { showToast('Event Code is required.', 'warning'); return; }
  if (fee === '' || isNaN(parseFloat(fee)) || parseFloat(fee) < 0) {
    showToast('Enter a valid Entry Fee (zero or more).', 'warning'); return;
  }
  if (teamFee !== '' && (isNaN(parseFloat(teamFee)) || parseFloat(teamFee) < 0)) {
    showToast('Team Entry Fee, when set, must be zero or more.', 'warning'); return;
  }
  const orig = btn.innerHTML;
  btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';
  const fd = new FormData();
  fd.append('section', 'sport_event_update');
  fd.append('row_id', tr.dataset.rowId);
  fd.append('event_code', code);
  fd.append('entry_fee', fee);
  fd.append('team_entry_fee', teamFee);
  const data = await postSection(fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) {
    renderSportRows(data.list || []);
  } else {
    btn.disabled = false; btn.innerHTML = orig;
  }
}

async function removeSportEvent(btn) {
  const tr = btn.closest('tr');
  const code  = (tr.children[1]?.textContent || '').trim();
  const label = (tr.dataset.label || tr.children[2]?.textContent || '').trim();
  const summary = code ? (code + (label ? ' — ' + label : '')) : (label || 'this entry');
  if (!confirm('Remove "' + summary + '" from this event?\n\nThis cannot be undone — athletes who already registered for it will lose this option.')) {
    return;
  }
  btn.disabled = true;
  const fd = new FormData();
  fd.append('section', 'sport_event_remove');
  fd.append('row_id', tr.dataset.rowId);
  const data = await postSection(fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) renderSportRows(data.list || []);
  else btn.disabled = false;
}
function renderSportRows(list) {
  const body = document.getElementById('sportsRows');
  if (!list.length) {
    body.innerHTML = '<tr id="emptyRow"><td colspan="7" class="text-muted text-center py-3">No sport events added yet.</td></tr>';
    refreshSportFilterOptions();
    applyRowFilters();
    return;
  }
  const esc = s => (s == null ? '' : String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])));
  body.innerHTML = list.map(r => `
    <tr data-row-id="${r.id}"
        data-sport="${esc(r.sport_name)}"
        data-gender="${esc(r.sport_event_gender)}"
        data-label="${esc(r.sport_event_name || r.category)}">
      <td>${esc(r.sport_name)}</td>
      <td>
        <input type="text" class="form-control form-control-sm font-monospace"
               data-field="event_code" value="${esc(r.event_code)}"
               maxlength="50" placeholder="e.g. AP-10M-SR-M" style="min-width:130px">
      </td>
      <td>${esc(r.sport_event_category)} <span class="text-muted">${esc(r.sport_event_name || r.category)}</span></td>
      <td>${esc(r.sport_event_age_category)} <span class="text-muted small">${esc(r.sport_event_gender)}</span></td>
      <td class="text-end">
        <div class="input-group input-group-sm" style="min-width:110px">
          <span class="input-group-text">₹</span>
          <input type="number" class="form-control text-end"
                 data-field="entry_fee" min="0" step="0.01"
                 value="${parseFloat(r.entry_fee).toFixed(2)}">
        </div>
      </td>
      <td class="text-end">
        <div class="input-group input-group-sm" style="min-width:110px">
          <span class="input-group-text">₹</span>
          <input type="number" class="form-control text-end"
                 data-field="team_entry_fee" min="0" step="0.01"
                 value="${r.team_entry_fee === null || r.team_entry_fee === undefined || r.team_entry_fee === '' ? '' : parseFloat(r.team_entry_fee).toFixed(2)}"
                 placeholder="—">
        </div>
      </td>
      <td class="text-end text-nowrap">
        <button class="btn btn-sm btn-outline-primary me-1" type="button" onclick="updateSportEvent(this)" title="Save changes"><i class="bi bi-save"></i></button>
        <button class="btn btn-sm btn-outline-danger" type="button" onclick="removeSportEvent(this)" title="Remove"><i class="bi bi-trash"></i></button>
      </td>
    </tr>`).join('');
  refreshSportFilterOptions();
  applyRowFilters();
}

function refreshSportFilterOptions() {
  const sel = document.getElementById('rowsSportFilter');
  if (!sel) return;
  const current = sel.value;
  const sports = [...new Set([...document.querySelectorAll('#sportsRows tr[data-sport]')]
    .map(tr => tr.dataset.sport).filter(Boolean))].sort();
  sel.innerHTML = '<option value="">All sports</option>' + sports.map(s => `<option>${s}</option>`).join('');
  if (sports.includes(current)) sel.value = current;
}

function applyRowFilters() {
  const q       = (document.getElementById('rowsSearch')?.value || '').toLowerCase().trim();
  const sport   =  document.getElementById('rowsSportFilter')?.value || '';
  const gender  =  document.getElementById('rowsGenderFilter')?.value || '';
  let visible = 0;
  document.querySelectorAll('#sportsRows tr[data-row-id]').forEach(tr => {
    const text = tr.textContent.toLowerCase();
    const ok = (!q || text.includes(q))
            && (!sport || tr.dataset.sport === sport)
            && (!gender || tr.dataset.gender === gender);
    tr.style.display = ok ? '' : 'none';
    if (ok) visible++;
  });
  const badge = document.getElementById('rowsCount');
  if (badge) badge.textContent = visible;
}

function downloadSportsCsv() {
  const rows = document.querySelectorAll('#sportsRows tr[data-row-id]');
  if (!rows.length) { showToast('Nothing to export yet.', 'warning'); return; }
  const lines = [['Sport', 'Event Code', 'Category', 'Event', 'Age Category', 'Gender', 'Entry Fee']];
  rows.forEach(tr => {
    if (tr.style.display === 'none') return;
    const cells = tr.querySelectorAll('td');
    const sport = (cells[0]?.textContent || '').trim();
    const code  = (cells[1]?.textContent || '').trim();
    // Cell 3 = "Category EventName" — split on the trailing muted span text.
    const catCell = cells[2];
    const cat   = (catCell?.firstChild?.textContent || '').trim();
    const evNm  = (catCell?.querySelector('.text-muted')?.textContent || '').trim();
    const ageCell = cells[3];
    const age   = (ageCell?.firstChild?.textContent || '').trim();
    const gen   = (ageCell?.querySelector('.text-muted')?.textContent || '').trim();
    const fee   = (cells[4]?.textContent || '').replace('₹', '').trim();
    lines.push([sport, code, cat, evNm, age, gen, fee]);
  });
  if (lines.length === 1) { showToast('No visible rows to export.', 'warning'); return; }
  const csv = lines.map(r => r.map(c => `"${(c || '').replace(/"/g, '""')}"`).join(',')).join('\r\n');
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'event-' + EV_ID + '-sports.csv';
  document.body.appendChild(a); a.click(); a.remove();
  URL.revokeObjectURL(url);
}

// initial filter state on page load
document.addEventListener('DOMContentLoaded', () => { refreshSportFilterOptions(); applyRowFilters(); });

/* ── Units / Clubs / Institutions ── */
function renderUnits(list) {
  const body = document.getElementById('unitRows');
  if (!list || !list.length) {
    body.innerHTML = '<tr id="emptyUnits"><td colspan="3" class="text-muted text-center py-3">No units added yet.</td></tr>';
    return;
  }
  const esc = s => (s == null ? '' : String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])));
  body.innerHTML = list.map(u => `
    <tr data-id="${u.id}">
      <td><input class="form-control form-control-sm" data-field="name" value="${esc(u.name)}"></td>
      <td><input class="form-control form-control-sm" data-field="address" value="${esc(u.address || '')}"></td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-primary me-1" type="button" onclick="unitSave(this)"><i class="bi bi-save"></i></button>
        <button class="btn btn-sm btn-outline-danger" type="button" onclick="unitDelete(this)"><i class="bi bi-trash"></i></button>
      </td>
    </tr>`).join('');
}
async function unitSave(btn) {
  const tr = btn.closest('tr');
  const fd = new FormData();
  fd.append('section', 'unit_save');
  fd.append('unit_id', tr.dataset.id);
  fd.append('name',    tr.querySelector('[data-field=name]').value);
  fd.append('address', tr.querySelector('[data-field=address]').value);
  const data = await postSection(fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) renderUnits(data.list || []);
}
async function unitDelete(btn) {
  const tr = btn.closest('tr');
  const name = (tr.querySelector('[data-field=name]')?.value || 'this unit').trim();
  if (!confirm('Delete unit "' + name + '"?')) return;
  const fd = new FormData();
  fd.append('section', 'unit_delete');
  fd.append('unit_id', tr.dataset.id);
  const data = await postSection(fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) renderUnits(data.list || []);
}
async function unitsCsvUpload(input) {
  if (!input.files || !input.files[0]) return;
  const fd = new FormData();
  fd.append('section', 'unit_csv');
  fd.append('file', input.files[0]);
  const data = await postSection(fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) renderUnits(data.list || []);
  input.value = '';
}

async function unitAdd() {
  const name    = document.getElementById('newUnitName').value.trim();
  const address = document.getElementById('newUnitAddress').value.trim();
  if (!name) { showToast('Unit name is required.', 'warning'); return; }
  const fd = new FormData();
  fd.append('section', 'unit_save');
  fd.append('unit_id', '0');
  fd.append('name',    name);
  fd.append('address', address);
  const data = await postSection(fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) {
    renderUnits(data.list || []);
    document.getElementById('newUnitName').value = '';
    document.getElementById('newUnitAddress').value = '';
  }
}

/* ── Sports Items / Weapons (per-event allow-list) ── */
async function onItemSportChange() {
  const sportId = document.getElementById('ei_sport').value;
  const sel = document.getElementById('ei_item');
  if (!sportId) { sel.innerHTML = '<option value="">— pick a sport first —</option>'; return; }
  try {
    const res = await fetch('/institution/events/sports/' + sportId + '/items');
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Load failed');
    const opts = (data.items || []).map(it => `<option value="${it.id}">${escapeHtml(it.name)}</option>`).join('');
    sel.innerHTML = opts.length
      ? '<option value="">Select an item…</option>' + opts
      : '<option value="">No items configured for this sport</option>';
  } catch (e) {
    sel.innerHTML = '<option value="">Failed to load: ' + e.message + '</option>';
  }
}

function renderEventItems(list) {
  const body = document.getElementById('eventItemsTbody');
  if (!list || !list.length) {
    body.innerHTML = '<tr id="eventItemsEmpty"><td colspan="4" class="text-muted text-center py-3">No items selected yet.</td></tr>';
    return;
  }
  body.innerHTML = list.map(it => `
    <tr data-item-id="${it.sport_item_id}">
      <td class="text-muted small">${escapeHtml(it.sport_name || '')}</td>
      <td class="fw-medium">${escapeHtml(it.item_name || '')}</td>
      <td class="text-muted small">${escapeHtml(it.item_description || '—')}</td>
      <td class="text-end">
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeEventItem(${it.sport_item_id})">
          <i class="bi bi-trash"></i>
        </button>
      </td>
    </tr>`).join('');
}

async function addEventItem() {
  const sportItemId = document.getElementById('ei_item').value;
  if (!sportItemId) { alert('Pick an item first.'); return; }
  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('section', 'item_add');
  fd.append('sport_item_id', sportItemId);
  try {
    const res = await fetch(SAVE_URL, { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.success) { alert(data.message || 'Add failed.'); return; }
    renderEventItems(data.list || []);
    document.getElementById('ei_item').value = '';
  } catch (e) {
    alert('Network error: ' + e.message);
  }
}

async function removeEventItem(itemId) {
  if (!confirm('Remove this item from the event allow-list?')) return;
  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('section', 'item_remove');
  fd.append('sport_item_id', itemId);
  try {
    const res = await fetch(SAVE_URL, { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.success) { alert(data.message || 'Remove failed.'); return; }
    renderEventItems(data.list || []);
  } catch (e) {
    alert('Network error: ' + e.message);
  }
}

function escapeHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

/* ── Shooting Ranges (facility → distance → lane) ── */

let SHOOTING_RANGES = <?= json_encode($shooting_ranges ?? []) ?>;

function sRangeRender() {
  const wrap = document.getElementById('sRangesTree');
  if (!wrap) return;
  if (!SHOOTING_RANGES.length) {
    wrap.innerHTML = '<div class="text-muted small fst-italic py-2" id="sRangesEmpty">' +
      '<i class="bi bi-info-circle me-1"></i>No venues added yet. Click <strong>Add Venue</strong> to start.' +
      '</div>';
    return;
  }
  wrap.innerHTML = SHOOTING_RANGES.map(sRangeCardHtml).join('');
}

function sRangeCardHtml(r) {
  const distances = (r.distances || []).map(d => sDistRowHtml(r.id, d)).join('');
  return `
  <div class="border rounded-3 p-3 mb-3" data-range-id="${r.id}">
    <div class="row g-2 align-items-end mb-2">
      <div class="col-md-5">
        <label class="form-label small mb-1">Venue Name</label>
        <input class="form-control form-control-sm" data-srange-field="name" value="${escapeHtml(r.name)}">
      </div>
      <div class="col-md-5">
        <label class="form-label small mb-1">Location</label>
        <input class="form-control form-control-sm" data-srange-field="location" value="${escapeHtml(r.location || '')}" placeholder="Optional venue / building / address">
      </div>
      <div class="col-md-2 text-end d-flex gap-1 justify-content-end">
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="sRangeSave(${r.id})" title="Save"><i class="bi bi-save"></i></button>
        <button type="button" class="btn btn-sm btn-outline-danger"  onclick="sRangeDelete(${r.id})" title="Delete venue"><i class="bi bi-trash"></i></button>
      </div>
    </div>
    <div class="ms-3 ps-3 border-start">
      <div class="d-flex justify-content-between align-items-center mb-1">
        <small class="text-muted text-uppercase fw-semibold">Shooting Ranges</small>
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="sDistAdd(${r.id})">
          <i class="bi bi-plus-lg me-1"></i>Add Shooting Range
        </button>
      </div>
      <div data-distances>
        ${distances || '<div class="text-muted small fst-italic py-2">No shooting ranges configured.</div>'}
      </div>
    </div>
  </div>`;
}

function sDistRowHtml(rangeId, d) {
  const lanes = (d.lanes || []).map(l => sLaneRowHtml(d.id, l)).join('');
  const distVal = (d.distance_meters === null || d.distance_meters === undefined) ? '' : d.distance_meters;
  return `
  <div class="border rounded-3 p-2 mb-2 bg-light-subtle" data-distance-id="${d.id}">
    <div class="row g-2 align-items-end">
      <div class="col-md-5">
        <label class="form-label small mb-1">Range Name <span class="text-danger">*</span></label>
        <input class="form-control form-control-sm" data-sdist-field="name" value="${escapeHtml(d.name || '')}" placeholder="e.g. Main Range">
      </div>
      <div class="col-md-3">
        <label class="form-label small mb-1">Distance <small class="text-muted">(optional)</small></label>
        <div class="input-group input-group-sm">
          <input class="form-control" data-sdist-field="distance_meters" type="number" min="0" value="${escapeHtml(distVal)}" placeholder="10">
          <span class="input-group-text">m</span>
        </div>
      </div>
      <div class="col-md-4 text-end d-flex gap-1 justify-content-end">
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="sDistSave(${rangeId}, ${d.id})" title="Save"><i class="bi bi-save"></i></button>
        <button type="button" class="btn btn-sm btn-outline-danger"  onclick="sDistDelete(${d.id})" title="Delete shooting range"><i class="bi bi-trash"></i></button>
      </div>
    </div>
    <div class="ms-3 ps-3 border-start mt-2">
      <div class="d-flex justify-content-between align-items-center mb-1">
        <small class="text-muted text-uppercase fw-semibold">Lanes</small>
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="sLaneAdd(${d.id})">
          <i class="bi bi-plus-lg me-1"></i>Add Lane
        </button>
      </div>
      <div data-lanes>
        ${lanes || '<div class="text-muted small fst-italic py-2">No lanes configured.</div>'}
      </div>
    </div>
  </div>`;
}

function sLaneRowHtml(distId, l) {
  const types = ['manual','mechanical','electronic'];
  // New lanes default to "mechanical"; existing lanes keep their saved type.
  const effType = l.lane_type || 'mechanical';
  const opts = types.map(t => `<option value="${t}"${effType === t ? ' selected' : ''}>${t.charAt(0).toUpperCase()+t.slice(1)}</option>`).join('');
  const catCur  = l.default_category || '';
  const catOpts = '<option value="">— Any —</option>' + (EVENT_CATEGORIES || []).map(c => {
    const v = (typeof c === 'string') ? c : (c.name || '');
    return `<option value="${escapeHtml(v)}"${v === catCur ? ' selected' : ''}>${escapeHtml(v)}</option>`;
  }).join('');
  return `
  <div class="row g-2 align-items-end mb-1" data-lane-id="${l.id}">
    <div class="col-4 col-md-2">
      <label class="form-label small mb-1">Lane #</label>
      <input class="form-control form-control-sm" data-slane-field="lane_number" type="number" min="1" value="${escapeHtml(l.lane_number)}">
    </div>
    <div class="col-4 col-md-3">
      <label class="form-label small mb-1">Type</label>
      <select class="form-select form-select-sm" data-slane-field="lane_type">${opts}</select>
    </div>
    <div class="col-4 col-md-4">
      <label class="form-label small mb-1">Default Event Category</label>
      <select class="form-select form-select-sm" data-slane-field="default_category">${catOpts}</select>
    </div>
    <div class="col-12 col-md-3 text-end d-flex gap-1 justify-content-end">
      <button type="button" class="btn btn-sm btn-outline-primary" onclick="sLaneSave(${distId}, ${l.id})" title="Save"><i class="bi bi-save"></i></button>
      <button type="button" class="btn btn-sm btn-outline-danger"  onclick="sLaneDelete(${l.id})" title="Delete lane"><i class="bi bi-trash"></i></button>
    </div>
  </div>`;
}

async function sRangesPost(section, params) {
  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('section', section);
  for (const [k, v] of Object.entries(params)) fd.append(k, v);
  const res = await fetch(SAVE_URL, { method: 'POST', body: fd });
  const data = await res.json().catch(() => ({success:false, message:'Server returned invalid response.'}));
  if (!data.success) { showToast(data.message || 'Failed.', 'danger'); return null; }
  if (data.list) { SHOOTING_RANGES = data.list; sRangeRender(); }
  showToast(data.message || 'Saved.', 'success');
  return data;
}

async function sRangeAdd() {
  const name = prompt('Venue name (e.g. Main Stadium, Indoor Hall)');
  if (!name) return;
  await sRangesPost('srange_save', { id: 0, name, location: '' });
}

async function sRangeSave(id) {
  const card = document.querySelector(`[data-range-id="${id}"]`);
  if (!card) return;
  const name     = card.querySelector('[data-srange-field=name]').value.trim();
  const location = card.querySelector('[data-srange-field=location]').value.trim();
  if (!name) { showToast('Venue name is required.', 'warning'); return; }
  await sRangesPost('srange_save', { id, name, location });
}

async function sRangeDelete(id) {
  if (!confirm('Delete this venue? All its shooting ranges and lanes will be removed.')) return;
  await sRangesPost('srange_delete', { id });
}

async function sDistAdd(rangeId) {
  const name = prompt('Shooting range name (e.g. Main Range, Pistol Bay)');
  if (!name) return;
  await sRangesPost('srdist_save', { id: 0, shooting_range_id: rangeId, name, distance_meters: '' });
}

async function sDistSave(rangeId, id) {
  const row = document.querySelector(`[data-distance-id="${id}"]`);
  if (!row) return;
  const name    = row.querySelector('[data-sdist-field=name]').value.trim();
  const metersR = row.querySelector('[data-sdist-field=distance_meters]').value.trim();
  if (!name) { showToast('Shooting range name is required.', 'warning'); return; }
  if (metersR !== '' && parseInt(metersR, 10) < 0) {
    showToast('Distance, when set, must be 0 or more.', 'warning'); return;
  }
  await sRangesPost('srdist_save', { id, shooting_range_id: rangeId, name, distance_meters: metersR });
}

async function sDistDelete(id) {
  if (!confirm('Delete this shooting range and all its lanes?')) return;
  await sRangesPost('srdist_delete', { id });
}

async function sLaneAdd(distId) {
  const n = prompt('Lane number (e.g. 1)');
  if (!n) return;
  const num = parseInt(n, 10);
  if (!num || num <= 0) { showToast('Lane number must be a positive integer.', 'warning'); return; }
  await sRangesPost('srlane_save', { id: 0, distance_id: distId, lane_number: num, lane_type: 'mechanical', default_category: '' });
}

async function sLaneSave(distId, id) {
  const row = document.querySelector(`[data-lane-id="${id}"]`);
  if (!row) return;
  const num  = parseInt(row.querySelector('[data-slane-field=lane_number]').value, 10);
  const type = row.querySelector('[data-slane-field=lane_type]').value;
  const cat  = row.querySelector('[data-slane-field=default_category]').value;
  if (!num || num <= 0) { showToast('Lane number must be a positive integer.', 'warning'); return; }
  await sRangesPost('srlane_save', { id, distance_id: distId, lane_number: num, lane_type: type, default_category: cat });
}

async function sLaneDelete(id) {
  if (!confirm('Delete this lane?')) return;
  await sRangesPost('srlane_delete', { id });
}

document.addEventListener('DOMContentLoaded', sRangeRender);

/* ── Relay Details ── */

let RELAYS = <?= json_encode($relays ?? []) ?>;
const EVENT_CATEGORIES = <?= json_encode($event_categories ?? []) ?>;
const RELAY_FIXED_OPTS = [
  { value: 'any_event_category', label: 'Any Event Category' },
  { value: 'reserved',           label: 'Reserved' },
  { value: 'not_using',          label: 'Not Using' },
];
let _relayModal = null;
function getRelayModal() {
  if (!_relayModal) {
    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) return null;
    _relayModal = new bootstrap.Modal(document.getElementById('relayModal'));
  }
  return _relayModal;
}

function flatRangeOptions(selectedId) {
  const opts = [];
  (SHOOTING_RANGES || []).forEach(venue => {
    (venue.distances || []).forEach(d => {
      const distSfx = (d.distance_meters !== null && d.distance_meters !== undefined && d.distance_meters !== '')
        ? ` (${d.distance_meters}m)` : '';
      const label = `${venue.name} → ${d.name}${distSfx}`;
      const sel   = String(selectedId) === String(d.id) ? ' selected' : '';
      opts.push(`<option value="${d.id}"${sel}>${escapeHtml(label)}</option>`);
    });
  });
  return opts.length
    ? '<option value="">Select shooting range…</option>' + opts.join('')
    : '<option value="" disabled selected>No shooting ranges configured yet</option>';
}

function lanesForRange(rangeDistId) {
  for (const v of (SHOOTING_RANGES || [])) {
    for (const d of (v.distances || [])) {
      if (String(d.id) === String(rangeDistId)) return d.lanes || [];
    }
  }
  return [];
}

function categoryDropdownHtml(laneId, currentValue) {
  const cur = (currentValue == null || currentValue === '') ? 'not_using' : String(currentValue);
  const fixedOpts = RELAY_FIXED_OPTS.map(o =>
    `<option value="${escapeHtml(o.value)}"${cur === o.value ? ' selected' : ''}>${escapeHtml(o.label)}</option>`
  ).join('');
  const catOpts = EVENT_CATEGORIES.map(c =>
    `<option value="${escapeHtml(c)}"${cur === c ? ' selected' : ''}>${escapeHtml(c)}</option>`
  ).join('');
  const evGroup = catOpts ? `<optgroup label="Event Categories">${catOpts}</optgroup>` : '';
  return `<select class="form-select form-select-sm" data-lane-cat="${laneId}">${evGroup}<optgroup label="Special">${fixedOpts}</optgroup></select>`;
}

function relayRenderLanesTable(rangeDistId, assignments) {
  // assignments: array of {lane_id, category} for an existing relay, or [] for new.
  const tbody = document.getElementById('relayLanesBody');
  const lanes = lanesForRange(rangeDistId);
  if (!lanes.length) {
    tbody.innerHTML = '<tr><td colspan="4" class="text-muted text-center py-3">' +
      (rangeDistId ? 'No lanes configured under this range — add some in the Shooting Range Venues panel first.'
                   : 'Pick a Shooting Range first.') +
      '</td></tr>';
    return;
  }
  const map = new Map((assignments || []).map(a => [Number(a.lane_id), a.category]));
  tbody.innerHTML = lanes.map((l, idx) => `
    <tr data-lane-row data-lane-id="${l.id}">
      <td class="text-center">${idx + 1}</td>
      <td class="fw-medium">Lane ${escapeHtml(l.lane_number)}</td>
      <td>${escapeHtml((l.lane_type || '').replace(/^./, c => c.toUpperCase()))}</td>
      <td>${categoryDropdownHtml(l.id, map.get(Number(l.id)))}</td>
    </tr>`).join('');
}

function onRelayRangeChange() {
  const rid = document.getElementById('relay_range_id').value;
  // Editing — preserve existing assignments only if range didn't change.
  const existingId = Number(document.getElementById('relay_id').value || 0);
  let preset = [];
  if (existingId) {
    const r = RELAYS.find(x => Number(x.id) === existingId);
    if (r && String(r.shooting_range_distance_id) === String(rid)) {
      preset = r.lane_assignments || [];
    }
  }
  relayRenderLanesTable(rid, preset);
}

function openRelayModal(relay) {
  // relay = undefined → new; otherwise the RELAYS row to edit
  const isNew = !relay;
  document.getElementById('relayModalTitle').innerHTML = (isNew ? '<i class="bi bi-stopwatch me-2"></i>Add Relay' : '<i class="bi bi-stopwatch me-2"></i>Edit Relay');
  document.getElementById('relay_id').value             = relay ? relay.id : 0;
  document.getElementById('relay_number').value         = relay ? (relay.relay_number || '') : '';
  document.getElementById('relay_date').value           = relay ? (relay.relay_date || '') : '';
  document.getElementById('relay_match_time').value     = relay ? ((relay.match_time || '').slice(0,5)) : '';
  document.getElementById('relay_reporting_time').value = relay ? ((relay.reporting_time || '').slice(0,5)) : '';
  const rangeSel = document.getElementById('relay_range_id');
  rangeSel.innerHTML = flatRangeOptions(relay ? relay.shooting_range_distance_id : '');
  // Render lanes table for the chosen range (empty if none picked yet).
  relayRenderLanesTable(rangeSel.value, relay ? (relay.lane_assignments || []) : []);
  const m = getRelayModal();
  if (m) m.show();
}

function relayRender() {
  const wrap = document.getElementById('relaysList');
  if (!wrap) return;
  if (!RELAYS.length) {
    wrap.innerHTML = '<div class="text-muted small fst-italic py-2"><i class="bi bi-info-circle me-1"></i>No relays added yet. Click <strong>Add Relay</strong> to start.</div>';
    return;
  }
  // List as a compact summary table with Edit / Delete per row.
  const rows = RELAYS.map((r, i) => {
    const distSfx = (r.distance_meters !== null && r.distance_meters !== undefined && r.distance_meters !== '')
      ? ` (${r.distance_meters}m)` : '';
    const range = `${escapeHtml(r.venue_name || '')} → ${escapeHtml(r.range_name || '')}${distSfx}`;
    const used = (r.lane_assignments || []).length;
    const date = r.relay_date  ? escapeHtml(r.relay_date)  : '<span class="text-muted">—</span>';
    const mt   = r.match_time ? escapeHtml((r.match_time || '').slice(0,5)) : '<span class="text-muted">—</span>';
    const rt   = r.reporting_time ? escapeHtml((r.reporting_time || '').slice(0,5)) : '<span class="text-muted">—</span>';
    return `
    <tr data-relay-id="${r.id}">
      <td>${i + 1}</td>
      <td class="fw-medium">${escapeHtml(r.relay_number || '')}</td>
      <td>${date}</td>
      <td>${mt}</td>
      <td>${rt}</td>
      <td>${range}</td>
      <td class="text-center"><span class="badge bg-primary-subtle text-primary">${used} active</span></td>
      <td class="text-end">
        <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="relayEdit(${r.id})" title="Edit"><i class="bi bi-pencil"></i></button>
        <button type="button" class="btn btn-sm btn-outline-danger"  onclick="relayDelete(${r.id}, '${escapeHtml(r.relay_number || '').replace(/'/g, '&#39;')}')" title="Delete"><i class="bi bi-trash"></i></button>
      </td>
    </tr>`;
  }).join('');
  wrap.innerHTML = `
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:5%">#</th>
            <th>Relay #</th>
            <th>Date</th>
            <th>Match Time</th>
            <th>Reporting Time</th>
            <th>Shooting Range</th>
            <th class="text-center">Lanes</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>
    </div>`;
}

function relayEdit(id) {
  const r = RELAYS.find(x => Number(x.id) === Number(id));
  if (!r) return;
  openRelayModal(r);
}

async function relayPost(section, params, listKey = 'list') {
  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('section', section);
  for (const [k, v] of Object.entries(params)) {
    if (Array.isArray(v)) v.forEach(x => fd.append(k, x));
    else                  fd.append(k, v);
  }
  const res = await fetch(SAVE_URL, { method: 'POST', body: fd });
  const data = await res.json().catch(() => ({success:false, message:'Server returned invalid response.'}));
  if (!data.success) { showToast(data.message || 'Failed.', 'danger'); return null; }
  if (data[listKey]) { RELAYS = data[listKey]; relayRender(); }
  showToast(data.message || 'Saved.', 'success');
  return data;
}

async function relaySaveFromModal() {
  const id      = Number(document.getElementById('relay_id').value || 0);
  const number  = document.getElementById('relay_number').value.trim();
  const date    = document.getElementById('relay_date').value;
  const match   = document.getElementById('relay_match_time').value;
  const report  = document.getElementById('relay_reporting_time').value;
  const rangeId = document.getElementById('relay_range_id').value;

  if (!number)  { showToast('Relay number is required.', 'warning'); return; }
  if (!rangeId) { showToast('Pick a Shooting Range for this relay.', 'warning'); return; }

  // Collect per-lane (lane_id, category) pairs from the lanes table.
  const laneIds = [];
  const cats    = [];
  document.querySelectorAll('#relayLanesBody [data-lane-row]').forEach(tr => {
    const lid = tr.getAttribute('data-lane-id');
    const sel = tr.querySelector('[data-lane-cat]');
    if (!lid || !sel) return;
    laneIds.push(lid);
    cats.push(sel.value);
  });

  const ok = await relayPost('relay_save', {
    id,
    relay_number: number,
    relay_date: date,
    match_time: match,
    reporting_time: report,
    shooting_range_distance_id: rangeId,
    'lane_ids[]':   laneIds,
    'categories[]': cats,
  });
  if (ok) {
    const m = getRelayModal();
    if (m) m.hide();
  }
}

async function relayDelete(id, label) {
  if (!confirm('Delete relay "' + (label || '#' + id) + '"? This cannot be undone.')) return;
  await relayPost('relay_delete', { id });
}

document.addEventListener('DOMContentLoaded', relayRender);

/* ── Event Documents ── */
function renderDocs(list) {
  const body = document.getElementById('docRows');
  if (!list || !list.length) {
    body.innerHTML = '<tr id="emptyDocs"><td colspan="5" class="text-muted text-center py-3">No documents added yet.</td></tr>';
    return;
  }
  const esc = s => (s == null ? '' : String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])));
  body.innerHTML = list.map(d => `
    <tr data-id="${d.id}">
      <td><input class="form-control form-control-sm" data-field="name" value="${esc(d.name)}"></td>
      <td><input class="form-control form-control-sm" data-field="purpose" value="${esc(d.purpose || '')}" placeholder="e.g. Undertaking signed by athlete"></td>
      <td>
        <select class="form-select form-select-sm" data-field="status">
          <option value="active"   ${d.status === 'active'   ? 'selected' : ''}>Active</option>
          <option value="inactive" ${d.status === 'inactive' ? 'selected' : ''}>Inactive</option>
        </select>
      </td>
      <td>
        <input type="file" class="form-control form-control-sm" data-field="file" accept="application/pdf,image/jpeg,image/png">
        ${d.file ? `<a href="${esc(d.file)}" target="_blank" class="small"><i class="bi bi-eye me-1"></i>Current file</a>` : ''}
      </td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-primary me-1" type="button" onclick="docSave(this)"><i class="bi bi-save"></i></button>
        <button class="btn btn-sm btn-outline-danger" type="button" onclick="docDelete(this)"><i class="bi bi-trash"></i></button>
      </td>
    </tr>`).join('');
}

async function docSave(btn) {
  const tr = btn.closest('tr');
  const fd = new FormData();
  fd.append('section', 'document_save');
  fd.append('doc_id',  tr.dataset.id);
  fd.append('name',    tr.querySelector('[data-field=name]').value);
  fd.append('purpose', tr.querySelector('[data-field=purpose]').value);
  fd.append('status',  tr.querySelector('[data-field=status]').value);
  const fileInput = tr.querySelector('[data-field=file]');
  if (fileInput && fileInput.files[0]) fd.append('file', fileInput.files[0]);
  const data = await postSection(fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) renderDocs(data.list || []);
}

async function docDelete(btn) {
  const tr = btn.closest('tr');
  const name = (tr.querySelector('[data-field=name]')?.value || 'this document').trim();
  if (!confirm('Delete document "' + name + '"?')) return;
  const fd = new FormData();
  fd.append('section', 'document_delete');
  fd.append('doc_id', tr.dataset.id);
  const data = await postSection(fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) renderDocs(data.list || []);
}

async function docAdd() {
  const name    = document.getElementById('newDocName').value.trim();
  const purpose = document.getElementById('newDocPurpose').value.trim();
  const status  = document.getElementById('newDocStatus').value;
  const file    = document.getElementById('newDocFile').files[0];
  if (!name) { showToast('Document name is required.', 'warning'); return; }
  const fd = new FormData();
  fd.append('section', 'document_save');
  fd.append('doc_id',  '0');
  fd.append('name',    name);
  fd.append('purpose', purpose);
  fd.append('status',  status);
  if (file) fd.append('file', file);
  const data = await postSection(fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) {
    renderDocs(data.list || []);
    document.getElementById('newDocName').value = '';
    document.getElementById('newDocPurpose').value = '';
    document.getElementById('newDocStatus').value = 'active';
    document.getElementById('newDocFile').value = '';
  }
}

/* Submit-for-approval button removed — institutions now control event
   status directly via the Status dropdown in the page header. */

/* ── Map ── */
const lat = parseFloat(document.getElementById('latitude').value) || 20.5937;
const lon = parseFloat(document.getElementById('longitude').value) || 78.9629;
const marker = new ol.Feature({ geometry: new ol.geom.Point(ol.proj.fromLonLat([lon, lat])) });
const vectorLayer = new ol.layer.Vector({
  source: new ol.source.Vector({ features: [marker] }),
  style: new ol.style.Style({ image: new ol.style.Icon({ src: 'https://cdn.jsdelivr.net/npm/ol@v8.2.0/examples/data/icon.png', anchor: [0.5, 1] }) })
});
const map = new ol.Map({
  target: 'eventMap',
  layers: [new ol.layer.Tile({ source: new ol.source.OSM() }), vectorLayer],
  view: new ol.View({ center: ol.proj.fromLonLat([lon, lat]), zoom: 6 })
});
map.on('click', function(e) {
  const [lng, lt] = ol.proj.toLonLat(e.coordinate);
  document.getElementById('latitude').value  = lt.toFixed(6);
  document.getElementById('longitude').value = lng.toFixed(6);
  marker.getGeometry().setCoordinates(e.coordinate);
});

function toggleManualFields() {
  document.getElementById('manualFields').style.display = document.getElementById('pm_manual').checked ? 'block' : 'none';
}
function toggleOnlineFields() {
  document.getElementById('onlineFields').style.display = document.getElementById('pm_online').checked ? 'block' : 'none';
}
toggleManualFields();
toggleOnlineFields();
</script>
