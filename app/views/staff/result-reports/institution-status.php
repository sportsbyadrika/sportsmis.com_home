<?php
/**
 * Athletics / Skating — Institution-wise Result Status (screen + print).
 * One row per institution (registrations > 0): athletes registered, events
 * registered (incl team), events whose final result is published (incl team),
 * athletes holding at least one medal, and athletes with no medal.
 * The count cells are clickable — they open a modal listing the athletes
 * (chest / name / gender / age group) or the published events (with total
 * participants).
 * Expects: $event, $rows, $totals.
 */
$pageTitle = 'Institution-wise Result Status — ' . $event['name'];

// Per-row drill-down payload for the modal, indexed by row number.
$detail = [];
foreach ($rows as $i => $r) {
    $detail[$i] = [
        'name'      => $r['name'],
        'athletes'  => $r['athlete_list'],
        'events'    => $r['event_list'],
    ];
}
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap sms-noprint">
  <a href="/event-staff/result-reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Reports
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-data me-2"></i>Institution-wise Result Status</h5>
  <span class="badge bg-info-subtle text-info-emphasis">Athletics / Skating</span>
  <?php if (!empty($rows)): ?>
    <button type="button" class="btn btn-sm btn-outline-dark ms-auto" onclick="window.print()">
      <i class="bi bi-printer me-1"></i>Print
    </button>
  <?php endif; ?>
</div>

<?= flashBag() ?>

<div class="sms-print-head d-none">
  <h4 class="fw-bold mb-0"><?= e($event['name']) ?></h4>
  <div class="text-muted">Institution-wise Result Status</div>
</div>

<?php if (!empty($rows)): ?>
  <p class="text-muted small sms-noprint mb-2">
    <i class="bi bi-info-circle me-1"></i>Click any highlighted count to see the athletes or events behind it.
  </p>
<?php endif; ?>

<div class="sms-card p-3">
  <?php if (empty($rows)): ?>
    <div class="text-center text-muted py-4">
      <i class="bi bi-inbox display-6 d-block mb-2"></i>
      No institutions with registrations yet.
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-bordered align-middle mb-0 sms-instab">
        <thead class="table-light">
          <tr>
            <th class="text-center" style="width:56px">Sl.No</th>
            <th>Name of Institution</th>
            <th class="text-center">Athletes<br>Registered</th>
            <th class="text-center">Events Registered<br>(incl. team)</th>
            <th class="text-center">Events (Final)<br>Published (incl. team)</th>
            <th class="text-center">Athletes with<br>&ge; 1 Medal</th>
            <th class="text-center">Athletes with<br>No Medal</th>
          </tr>
        </thead>
        <tbody>
          <?php $sl = 0; foreach ($rows as $i => $r): $sl++; ?>
            <tr<?= $sl % 2 === 0 ? ' class="mt-alt"' : '' ?>>
              <td class="text-center"><?= $sl ?></td>
              <td><?= e($r['name']) ?></td>
              <td class="text-center">
                <?php if ((int)$r['athletes'] > 0): ?>
                  <button type="button" class="btn btn-link btn-sm p-0 sms-drill fw-semibold"
                          data-row="<?= $i ?>" data-kind="athletes"><?= (int)$r['athletes'] ?></button>
                <?php else: ?>0<?php endif; ?>
              </td>
              <td class="text-center"><?= (int)$r['events'] ?></td>
              <td class="text-center">
                <?php if ((int)$r['events_pub'] > 0): ?>
                  <button type="button" class="btn btn-link btn-sm p-0 sms-drill fw-semibold"
                          data-row="<?= $i ?>" data-kind="events"><?= (int)$r['events_pub'] ?></button>
                <?php else: ?>0<?php endif; ?><?php if ((int)$r['events'] > 0): ?><span class="text-muted small"> / <?= (int)$r['events'] ?></span><?php endif; ?>
              </td>
              <td class="text-center">
                <?php if ((int)$r['with_medal'] > 0): ?>
                  <button type="button" class="btn btn-link btn-sm p-0 sms-drill fw-semibold text-success"
                          data-row="<?= $i ?>" data-kind="medal"><?= (int)$r['with_medal'] ?></button>
                <?php else: ?>0<?php endif; ?>
              </td>
              <td class="text-center">
                <?php if ((int)$r['no_medal'] > 0): ?>
                  <button type="button" class="btn btn-link btn-sm p-0 sms-drill fw-semibold text-danger"
                          data-row="<?= $i ?>" data-kind="nomedal"><?= (int)$r['no_medal'] ?></button>
                <?php else: ?>0<?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light fw-bold">
          <tr>
            <td class="text-center" colspan="2">Total (<?= count($rows) ?> institution<?= count($rows) === 1 ? '' : 's' ?>)</td>
            <td class="text-center"><?= (int)$totals['athletes'] ?></td>
            <td class="text-center"><?= (int)$totals['events'] ?></td>
            <td class="text-center"><?= (int)$totals['events_pub'] ?></td>
            <td class="text-center"><?= (int)$totals['medal'] ?></td>
            <td class="text-center"><?= (int)$totals['nomedal'] ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Drill-down modal -->
<div class="modal fade sms-noprint" id="drillModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold" id="drillTitle">Details</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="drillBody"></div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-sm btn-outline-dark" onclick="drillPrint()">
          <i class="bi bi-printer me-1"></i>Print list
        </button>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
  window.SMS_INST_DETAIL = <?= json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG) ?>;
  document.addEventListener('DOMContentLoaded', function () {
    var esc = function (s) {
      return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
      });
    };
    var genderBadge = function (g) {
      var v = (g || '').toLowerCase();
      var tone = (v.indexOf('f') === 0 || v.indexOf('w') === 0) ? 'danger'
               : (v.indexOf('m') === 0) ? 'primary' : 'secondary';
      return g ? '<span class="badge bg-' + tone + '-subtle text-' + tone + '-emphasis">' + esc(g) + '</span>' : '<span class="text-muted">—</span>';
    };
    var buildAthletes = function (list) {
      if (!list.length) return '<div class="text-muted">No athletes.</div>';
      var h = '<div class="table-responsive"><table class="table table-sm table-bordered align-middle mb-0">'
            + '<thead class="table-light"><tr>'
            + '<th class="text-center" style="width:48px">#</th>'
            + '<th class="text-center" style="width:90px">Chest No</th>'
            + '<th>Name</th><th class="text-center">Gender</th><th>Age Group</th></tr></thead><tbody>';
      list.forEach(function (a, i) {
        h += '<tr><td class="text-center">' + (i + 1) + '</td>'
           + '<td class="text-center fw-semibold">' + (a.chest > 0 ? a.chest : '<span class="text-muted">—</span>') + '</td>'
           + '<td>' + esc(a.name) + '</td>'
           + '<td class="text-center">' + genderBadge(a.gender) + '</td>'
           + '<td>' + (a.age ? esc(a.age) : '<span class="text-muted">—</span>') + '</td></tr>';
      });
      return h + '</tbody></table></div>';
    };
    var buildEvents = function (list) {
      if (!list.length) return '<div class="text-muted">No published events.</div>';
      var h = '<div class="table-responsive"><table class="table table-sm table-bordered align-middle mb-0">'
            + '<thead class="table-light"><tr>'
            + '<th class="text-center" style="width:48px">#</th>'
            + '<th>Event</th><th class="text-center" style="width:140px">Total Participants</th></tr></thead><tbody>';
      list.forEach(function (ev, i) {
        h += '<tr><td class="text-center">' + (i + 1) + '</td>'
           + '<td>' + esc(ev.name) + '</td>'
           + '<td class="text-center fw-semibold">' + ev.parts + '</td></tr>';
      });
      return h + '</tbody></table></div>';
    };

    var modalEl = document.getElementById('drillModal');
    var modal = modalEl ? new bootstrap.Modal(modalEl) : null;

    document.querySelectorAll('.sms-drill').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var row = window.SMS_INST_DETAIL[this.getAttribute('data-row')];
        if (!row) return;
        var kind = this.getAttribute('data-kind');
        var title = '', body = '';
        if (kind === 'events') {
          title = row.name + ' — Published Events';
          body = buildEvents(row.events || []);
        } else {
          var athletes = row.athletes || [];
          if (kind === 'medal')   { athletes = athletes.filter(function (a) { return a.medal; });  title = row.name + ' — Athletes with ≥ 1 Medal'; }
          else if (kind === 'nomedal') { athletes = athletes.filter(function (a) { return !a.medal; }); title = row.name + ' — Athletes with No Medal'; }
          else { title = row.name + ' — Registered Athletes'; }
          title += ' (' + athletes.length + ')';
          body = buildAthletes(athletes);
        }
        document.getElementById('drillTitle').textContent = title;
        document.getElementById('drillBody').innerHTML = body;
        if (modal) modal.show();
      });
    });
  });

  function drillPrint() {
    var title = document.getElementById('drillTitle').textContent;
    var body = document.getElementById('drillBody').innerHTML;
    var w = window.open('', '_blank');
    w.document.write('<html><head><title>' + title + '</title>'
      + '<style>body{font-family:Arial,Helvetica,sans-serif;padding:16px;}'
      + 'h3{margin:0 0 10px;} table{border-collapse:collapse;width:100%;font-size:13px;}'
      + 'th,td{border:1px solid #333;padding:5px 7px;} thead{background:#eee;}'
      + '.badge{border:1px solid #999;border-radius:4px;padding:1px 5px;font-size:11px;}'
      + '.text-muted{color:#888;} .text-center{text-align:center;}</style></head><body>'
      + '<h3>' + title + '</h3>' + body + '</body></html>');
    w.document.close();
    w.focus();
    w.print();
  }
</script>

<style>
  .sms-instab tbody tr.mt-alt > td { background: #f7f9fc; }
  .sms-drill { text-decoration: none; }
  .sms-drill:hover { text-decoration: underline; }
  @media print {
    .sms-noprint, .sidebar, .navbar, .app-sidebar, footer { display: none !important; }
    .sms-print-head { display: block !important; margin-bottom: 12px; }
    .sms-card { border: 0 !important; box-shadow: none !important; padding: 0 !important; }
    .sms-instab { font-size: 12px; }
    .sms-instab th, .sms-instab td { border: 1px solid #333 !important; }
    .sms-instab .sms-drill { color: #000 !important; text-decoration: none !important; }
    .sms-instab tbody tr.mt-alt > td { background: #f2f2f2 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    a[href]:after { content: ''; }
  }
</style>
