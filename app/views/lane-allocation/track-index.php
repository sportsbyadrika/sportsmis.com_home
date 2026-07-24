<?php
$pageTitle = 'Lane Allocation — ' . ($event['name'] ?? '');
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken   = $_SESSION['csrf_token'];
$isAdmin     = (($actor['mode'] ?? '') === 'admin');
$trackEvents = $track_events ?? [];
$maxRounds   = (int)($max_rounds ?? 0);
$roundNames  = $round_names ?? ['Preliminary heats', 'Semifinal heats', 'Final'];
$totalApproved = 0;
foreach ($trackEvents as $te) { $totalApproved += (int)$te['approved']; }
$typeBadge = function (string $t): string {
    if ($t === 'track') return '<span class="badge bg-primary-subtle text-primary-emphasis">Track</span>';
    if ($t === 'field') return '<span class="badge bg-success-subtle text-success-emphasis">Field</span>';
    return '<span class="text-muted">—</span>';
};
?>

<!-- ── Top Bar ───────────────────────────────────────────────── -->
<div class="sms-card p-3 mb-3">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <h5 class="mb-0 fw-bold"><i class="bi bi-flag me-2"></i>Lane Allocation
        <span class="badge bg-info-subtle text-info-emphasis ms-1"><?= e($sport ?? 'Athletics') ?></span>
      </h5>
      <div class="text-muted small mt-1">
        <span class="badge bg-primary-subtle text-primary-emphasis"><i class="bi bi-hash"></i> <?= e($event['event_code'] ?? '') ?></span>
        <span class="ms-1"><?= e($event['name'] ?? '') ?></span>
      </div>
    </div>
  </div>
</div>

<?= flashBag() ?>

<!-- ── Tabs ──────────────────────────────────────────────────── -->
<ul class="nav nav-tabs mb-3" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-events" type="button" role="tab">
      <i class="bi bi-list-ol me-1"></i>Events
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link disabled" type="button" role="tab" tabindex="-1" aria-disabled="true" title="Coming soon">
      <i class="bi bi-diagram-3 me-1"></i>Heats &amp; Lane Draw
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link disabled" type="button" role="tab" tabindex="-1" aria-disabled="true" title="Coming soon">
      <i class="bi bi-trophy me-1"></i>Results
    </button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="tab-events" role="tabpanel">
    <div class="sms-card p-3">
      <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-2 flex-wrap">
        <strong><i class="bi bi-list-ol me-1"></i>Events with Approved Athletes</strong>
        <span class="badge bg-secondary-subtle text-secondary-emphasis">
          <?= count($trackEvents) ?> event<?= count($trackEvents) === 1 ? '' : 's' ?> · <?= $totalApproved ?> approved
        </span>
        <?php if ($isAdmin): ?>
          <button type="button" id="updTypeBtn" class="btn btn-sm btn-primary ms-auto" disabled
                  onclick="openEventTypeModal()">
            <i class="bi bi-tag me-1"></i>Update Event Type <span id="selCount" class="badge bg-light text-dark ms-1">0</span>
          </button>
        <?php endif; ?>
      </div>

      <?php if (empty($trackEvents)): ?>
        <p class="text-muted small text-center py-3 mb-0">No sport events have approved athletes yet.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <?php if ($isAdmin): ?>
                <th style="width:34px"><input type="checkbox" class="form-check-input" id="selAll" onchange="toggleAll(this)"></th>
              <?php endif; ?>
              <th style="width:56px">Sl. No</th>
              <th>Name of Sport Event</th>
              <th class="text-end" style="width:110px">Approved</th>
              <th class="text-center" style="width:80px">Type</th>
              <th class="text-center" style="width:110px">Primary Rounds</th>
              <?php for ($c = 1; $c <= $maxRounds; $c++): ?>
                <th class="text-center" style="width:120px">Round <?= $c ?></th>
              <?php endfor; ?>
              <?php if ($isAdmin): ?><th class="text-center" style="width:70px">Action</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($trackEvents as $i => $te):
              $esid   = (int)$te['event_sport_id'];
              $rounds = $te['rounds'];
            ?>
              <tr>
                <?php if ($isAdmin): ?>
                  <td><input type="checkbox" class="form-check-input row-check" value="<?= $esid ?>" onchange="updSel()"></td>
                <?php endif; ?>
                <td class="text-center"><?= $i + 1 ?></td>
                <td>
                  <div class="fw-medium"><?= e($te['sport_event']) ?></div>
                  <?php if ($te['category'] !== '' || $te['event_code'] !== ''): ?>
                    <div class="small text-muted">
                      <?= e($te['category']) ?><?php if ($te['category'] !== '' && $te['event_code'] !== ''): ?> · <?php endif; ?>
                      <?php if ($te['event_code'] !== ''): ?><code><?= e($te['event_code']) ?></code><?php endif; ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td class="text-end fw-bold"><?= (int)$te['approved'] ?></td>
                <td class="text-center">
                  <?= $typeBadge((string)$te['type']) ?>
                  <?php if ($te['type'] === 'track' && (int)$te['num_tracks'] > 0): ?>
                    <div class="small text-muted"><?= (int)$te['num_tracks'] ?> tracks</div>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <?= $te['primary_rounds'] !== null ? '<span class="fw-semibold">' . (int)$te['primary_rounds'] . '</span>' : '<span class="text-muted">—</span>' ?>
                </td>
                <?php for ($c = 0; $c < $maxRounds; $c++):
                  $rd = $rounds[$c] ?? null; ?>
                  <td class="text-center">
                    <?php if ($rd): ?>
                      <span class="badge bg-light text-dark border"><?= e($rd['round_name']) ?></span>
                      <div class="small text-muted"><?= (int)$rd['num_heats'] ?> heat<?= (int)$rd['num_heats'] === 1 ? '' : 's' ?></div>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                <?php endfor; ?>
                <?php if ($isAdmin): ?>
                  <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            data-bs-toggle="modal" data-bs-target="#manageModal-<?= $esid ?>" title="Manage rounds">
                      <i class="bi bi-diagram-3"></i>
                    </button>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($isAdmin): ?>
<!-- ── Update Event Type modal ── -->
<div class="modal fade" id="eventTypeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="/lane-allocation/track/event-type">
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
        <div id="etIds"></div>
        <div class="modal-header">
          <h6 class="modal-title fw-semibold"><i class="bi bi-tag me-2"></i>Update Event Type</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="small text-muted">Applying to <strong id="etCount">0</strong> selected event(s).</p>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="track_event_type" id="etTrack" value="track"
                   onchange="document.getElementById('etTracksWrap').style.display='block'">
            <label class="form-check-label" for="etTrack">Track <small class="text-muted">(lane races — needs number of tracks)</small></label>
          </div>
          <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="track_event_type" id="etField" value="field"
                   onchange="document.getElementById('etTracksWrap').style.display='none'">
            <label class="form-check-label" for="etField">Field <small class="text-muted">(single round)</small></label>
          </div>
          <div id="etTracksWrap" style="display:none">
            <label class="form-label small fw-medium">Number of Tracks / Lanes</label>
            <input type="number" name="track_num_tracks" min="1" step="1" class="form-control form-control-sm" value="8">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Update</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Per-event Manage Rounds modals ── -->
<?php foreach ($trackEvents as $te):
  $esid    = (int)$te['event_sport_id'];
  $rounds  = $te['rounds'];
  $prelim  = $te['primary_rounds'];
?>
<div class="modal fade" id="manageModal-<?= $esid ?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-semibold"><i class="bi bi-diagram-3 me-2"></i>Manage Rounds — <?= e($te['sport_event']) ?></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2 mb-3">
          <div class="col-4">
            <div class="border rounded p-2 text-center">
              <div class="small text-muted">Total Athletes</div>
              <div class="fs-5 fw-bold"><?= (int)$te['approved'] ?></div>
            </div>
          </div>
          <div class="col-4">
            <div class="border rounded p-2 text-center">
              <div class="small text-muted">Tracks</div>
              <div class="fs-5 fw-bold"><?= $te['type'] === 'track' && (int)$te['num_tracks'] > 0 ? (int)$te['num_tracks'] : '—' ?></div>
            </div>
          </div>
          <div class="col-4">
            <div class="border rounded p-2 text-center">
              <div class="small text-muted">Prelim Heats</div>
              <div class="fs-5 fw-bold"><?= $prelim !== null ? (int)$prelim : '—' ?></div>
            </div>
          </div>
        </div>

        <?php if ($te['type'] === ''): ?>
          <div class="alert alert-warning py-2 small mb-3">
            <i class="bi bi-exclamation-triangle me-1"></i>Set this event's type (Track / Field) first to compute preliminary heats.
          </div>
        <?php endif; ?>

        <div class="fw-semibold small mb-1">Rounds</div>
        <?php if (empty($rounds)): ?>
          <p class="text-muted small">No rounds added yet.</p>
        <?php else: ?>
          <ol class="ps-3 mb-3">
            <?php foreach ($rounds as $rd): ?>
              <li class="mb-1 d-flex align-items-center gap-2">
                <span><?= e($rd['round_name']) ?> · <strong><?= (int)$rd['num_heats'] ?></strong> heat<?= (int)$rd['num_heats'] === 1 ? '' : 's' ?></span>
                <form method="POST" action="/lane-allocation/track/round-delete" class="ms-auto"
                      onsubmit="return confirm('Remove this round?');">
                  <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                  <input type="hidden" name="round_id" value="<?= (int)$rd['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger py-0 px-1" title="Remove"><i class="bi bi-x-lg"></i></button>
                </form>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php endif; ?>

        <form method="POST" action="/lane-allocation/track/round-add" class="border-top pt-3">
          <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
          <input type="hidden" name="event_sport_id" value="<?= $esid ?>">
          <div class="fw-semibold small mb-2">Add Round</div>
          <div class="row g-2 align-items-end">
            <div class="col-7">
              <label class="form-label small mb-1">Round name</label>
              <select name="round_name" class="form-select form-select-sm" required>
                <?php foreach ($roundNames as $rn): ?>
                  <option value="<?= e($rn) ?>"><?= e($rn) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-3">
              <label class="form-label small mb-1">Heats</label>
              <input type="number" name="num_heats" min="1" step="1" class="form-control form-control-sm"
                     value="<?= $prelim !== null ? (int)$prelim : 1 ?>" required>
            </div>
            <div class="col-2 d-grid">
              <button class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i></button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>

<script>
(function () {
  function checked() { return Array.from(document.querySelectorAll('.row-check:checked')); }
  window.updSel = function () {
    const n = checked().length;
    const btn = document.getElementById('updTypeBtn');
    document.getElementById('selCount').textContent = n;
    btn.disabled = n === 0;
  };
  window.toggleAll = function (cb) {
    document.querySelectorAll('.row-check').forEach(c => { c.checked = cb.checked; });
    updSel();
  };
  window.openEventTypeModal = function () {
    const ids = checked().map(c => c.value);
    if (!ids.length) return;
    const box = document.getElementById('etIds');
    box.innerHTML = ids.map(v => '<input type="hidden" name="event_sport_ids[]" value="' + v + '">').join('');
    document.getElementById('etCount').textContent = ids.length;
    new bootstrap.Modal(document.getElementById('eventTypeModal')).show();
  };
})();
</script>
<?php endif; ?>
