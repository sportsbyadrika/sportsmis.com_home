<?php
/**
 * Public results — event home. A panel of cards (each opening a results section
 * or the athlete search) followed by the optional event video.
 * Expects: $event, $base, $counts (units/events/agetop/qual).
 */
$pageTitle = ($event['name'] ?? 'Results');
$base = $base ?? '';
$eid  = (int)($event['id'] ?? 0);
$c    = $counts ?? [];

// Result-section cards → /{slug}/event/{id}/{slug2}
$cards = [
  ['standings',    'Unit-wise Points',          'Points table & medal standings',   'bi-buildings',     'primary', (int)($c['units']  ?? 0), 'units'],
  ['winners',      'Event-wise Winners',        '1st, 2nd & 3rd for each event',     'bi-trophy',        'warning', (int)($c['events'] ?? 0), 'events'],
  ['top-athletes',     'Age-category Top Athletes',    'Best athletes by age & gender',        'bi-people',         'info',    (int)($c['agetop']   ?? 0), 'age groups'],
  ['top-institutions', 'Age-category Top Institutions', 'Top institutions by age category',     'bi-buildings-fill', 'danger',  (int)($c['ageunits'] ?? 0), 'age groups'],
  ['qualified',        'Qualified List',               'Round-wise qualified athletes',        'bi-check2-square',  'success', (int)($c['qual']     ?? 0), 'events'],
];

// YouTube (optional, set by super admin) — up to 10 links, one per line. Accept
// the usual URL forms or a bare 11-char id; collect the valid video ids.
$ytIds = [];
foreach (preg_split('/\r\n|\r|\n/', (string)($event['result_video_url'] ?? '')) as $line) {
    $line = trim($line);
    if ($line === '') continue;
    if (preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/))([A-Za-z0-9_-]{11})~', $line, $m)) {
        $ytIds[] = $m[1];
    } elseif (preg_match('~^[A-Za-z0-9_-]{11}$~', $line)) {
        $ytIds[] = $line;
    }
    if (count($ytIds) >= 10) break;
}
$ytIds = array_values(array_unique($ytIds));
$ytCount = count($ytIds);
?>
<style>
  .pr-nav-card { display:flex; flex-direction:column; gap:.5rem; height:100%; text-decoration:none;
    background:#fff; border:1px solid #e6eaf1; border-radius:16px; padding:20px; color:#1e293b;
    transition:transform .12s ease, box-shadow .12s ease, border-color .12s ease; }
  .pr-nav-card:hover { transform:translateY(-4px); box-shadow:0 12px 28px rgba(2,32,71,.12); border-color:#cfd8e6; }
  .pr-nav-card .pr-ic { width:56px; height:56px; border-radius:14px; display:flex; align-items:center;
    justify-content:center; font-size:28px; }
  .pr-nav-card .pr-t { font-weight:800; font-size:1.06rem; line-height:1.2; }
  .pr-nav-card .pr-s { color:#64748b; font-size:.82rem; flex:1 1 auto; }
  .pr-nav-card .pr-go { color:#94a3b8; font-size:.8rem; font-weight:700; }
  .pr-nav-card:hover .pr-go { color:#334155; }
</style>

<div class="container">
  <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
    <a href="<?= e($base) ?: '/' ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Events</a>
    <?php if (!empty($event['logo'])): ?><img src="<?= e($event['logo']) ?>" alt="" style="height:46px" class="rounded"><?php endif; ?>
    <div>
      <h4 class="fw-bold mb-0"><?= e($event['name']) ?></h4>
      <span class="badge bg-success-subtle text-success-emphasis">Published Results</span>
    </div>
  </div>

  <div class="row g-3">
    <?php foreach ($cards as $cd): [$slug2, $title, $sub, $icon, $tone, $cnt, $unit] = $cd; ?>
      <div class="col-6 col-lg-4 col-xl-3">
        <a class="pr-nav-card" href="<?= e($base) ?>/event/<?= $eid ?>/<?= $slug2 ?>">
          <div class="pr-ic bg-<?= $tone ?>-subtle text-<?= $tone ?>-emphasis"><i class="bi <?= $icon ?>"></i></div>
          <div class="pr-t"><?= e($title) ?></div>
          <div class="pr-s"><?= e($sub) ?></div>
          <div class="d-flex align-items-center justify-content-between">
            <span class="badge bg-<?= $tone ?>-subtle text-<?= $tone ?>-emphasis"><?= $cnt ?> <?= e($unit) ?></span>
            <span class="pr-go">Open <i class="bi bi-arrow-right"></i></span>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($ytCount > 0):
    // 1 video → centered single column; 2+ → two-column grid (10 → 2×5).
    $colClass = $ytCount === 1 ? 'col-12 col-lg-8 mx-auto' : 'col-12 col-md-6';
  ?>
    <div class="pr-card p-3 pr-cardify mt-4">
      <h6 class="fw-bold mb-3"><i class="bi bi-youtube text-danger me-2"></i>Event Video<?= $ytCount > 1 ? 's' : '' ?>
        <?php if ($ytCount > 1): ?><span class="badge bg-danger-subtle text-danger-emphasis"><?= $ytCount ?></span><?php endif; ?>
      </h6>
      <div class="row g-3">
        <?php foreach ($ytIds as $vid): ?>
          <div class="<?= $colClass ?>">
            <div style="position:relative;width:100%;aspect-ratio:16/9;">
              <iframe style="position:absolute;inset:0;width:100%;height:100%;border:0;border-radius:10px"
                      src="https://www.youtube-nocookie.com/embed/<?= e($vid) ?>?rel=0"
                      title="Event video" loading="lazy" allowfullscreen
                      allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
