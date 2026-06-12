<?php
namespace Controllers;

use Core\{Controller, Auth};
use Models\{Schema, AgeCategory, SportCategory, SportEvent, SportItem};

class AdminSettingsController extends Controller
{
    private function boot(): void
    {
        $this->requireAuth('super_admin');
        try { Schema::ensureSportHierarchy(); }
        catch (\Throwable $e) {
            error_log('[admin/settings/ensureSchema] ' . $e->getMessage());
        }
    }

    /** GET /admin/settings/sports — landing for Sports Setting (two sub-pages). */
    public function sportsForm(): void
    {
        $this->boot();
        $this->renderWith('app', 'admin/settings/sports-landing', [
            'flash' => $this->flash(),
        ]);
    }

    /** GET /admin/settings/sports/age-categories — Age Categories CRUD. */
    public function ageCategoriesForm(): void
    {
        $this->boot();
        $ageCats = AgeCategory::all();
        foreach ($ageCats as &$a) {
            $a['upgrades'] = AgeCategory::upgradesFor((int)$a['id']);
        }
        unset($a);

        $this->renderWith('app', 'admin/settings/age-categories', [
            'age_categories' => $ageCats,
            'flash'          => $this->flash(),
        ]);
    }

    /** GET /admin/settings/sports/catalog — Sports visibility (table). */
    public function sportCatalogForm(): void
    {
        $this->boot();
        $sports = \Models\Athlete::getAllSports();
        // Per-sport summary counts for the table — categories and events.
        $sportRows = [];
        foreach ($sports as $s) {
            $sid = (int)$s['id'];
            $catCount = (int)(\Models\Event::rowsRaw(
                "SELECT COUNT(*) AS c FROM sport_categories WHERE sport_id = ?", [$sid]
            )[0]['c'] ?? 0);
            $evtCount = (int)(\Models\Event::rowsRaw(
                "SELECT COUNT(*) AS c
                   FROM sport_events sev
                   JOIN sport_categories sc ON sc.id = sev.category_id
                  WHERE sc.sport_id = ?", [$sid]
            )[0]['c'] ?? 0);
            $sportRows[] = [
                'id'                 => $sid,
                'name'               => $s['name'],
                'enabled_for_events' => (int)($s['enabled_for_events'] ?? 0),
                'category_count'     => $catCount,
                'event_count'        => $evtCount,
            ];
        }
        $this->renderWith('app', 'admin/settings/sport-catalog', [
            'sports' => $sportRows,
            'flash'  => $this->flash(),
        ]);
    }

    /** GET /admin/settings/sports/{sportId}/categories — Categories under a sport. */
    public function sportCategoriesForm(string $sportId): void
    {
        $this->boot();
        $sportId = (int)$sportId;
        $sport = \Models\Event::rowsRaw(
            "SELECT id, name FROM sports WHERE id = ?", [$sportId]
        )[0] ?? null;
        if (!$sport) $this->abort(404);

        // Categories with rolled-up event count per row.
        $cats = \Models\Event::rowsRaw(
            "SELECT sc.*,
                    (SELECT COUNT(*) FROM sport_events sev
                       WHERE sev.category_id = sc.id) AS event_count
               FROM sport_categories sc
              WHERE sc.sport_id = ?
              ORDER BY sc.sort_order, sc.name",
            [$sportId]
        );

        $this->renderWith('app', 'admin/settings/sport-categories', [
            'sport'      => $sport,
            'categories' => $cats,
            'flash'      => $this->flash(),
        ]);
    }

    /** GET /admin/settings/sport-categories/{categoryId}/sport-events — Events under a category. */
    public function sportEventsForm(string $categoryId): void
    {
        $this->boot();
        $catId = (int)$categoryId;
        $cat = \Models\Event::rowsRaw(
            "SELECT sc.*, s.name AS sport_name
               FROM sport_categories sc
               JOIN sports s ON s.id = sc.sport_id
              WHERE sc.id = ?", [$catId]
        )[0] ?? null;
        if (!$cat) $this->abort(404);

        $sportEvents = \Models\Event::rowsRaw(
            "SELECT sev.*, ac.name AS age_category_name
               FROM sport_events sev
          LEFT JOIN age_categories ac ON ac.id = sev.age_category_id
              WHERE sev.category_id = ?
              ORDER BY ac.sort_order, ac.name, sev.gender, sev.name",
            [$catId]
        );

        $this->renderWith('app', 'admin/settings/sport-events', [
            'category'       => $cat,
            'sport_events'   => $sportEvents,
            'age_categories' => AgeCategory::all(),
            'flash'          => $this->flash(),
        ]);
    }

    /** POST /admin/settings/sports/toggle — flip enabled_for_events. */
    public function toggleSport(): void
    {
        $this->boot();
        $this->verifyCsrf();
        $sportId = (int)($_POST['sport_id'] ?? 0);
        $enabled = !empty($_POST['enabled']);
        try {
            \Models\Athlete::setSportEnabled($sportId, $enabled);
            $this->json(['success' => true, 'message' => $enabled ? 'Sport enabled.' : 'Sport disabled.']);
        } catch (\Throwable $e) {
            error_log('[admin/sport_toggle] ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Toggle failed: ' . $e->getMessage()]);
        }
    }

    // ── Age Categories AJAX ──────────────────────────────────────────────────

    public function ageCategorySave(): void
    {
        $this->boot();
        $this->verifyCsrf();

        $id        = (int)($_POST['id'] ?? 0);
        $name      = trim($_POST['name'] ?? '');
        $intOrNull = fn($k) => (isset($_POST[$k]) && $_POST[$k] !== '' && $_POST[$k] !== null) ? (int)$_POST[$k] : null;
        $minAge    = $intOrNull('min_age');
        $maxAge    = $intOrNull('max_age');
        $minYear   = $intOrNull('min_age_year');
        $maxYear   = $intOrNull('max_age_year');
        $sort      = (int)($_POST['sort_order'] ?? 0);

        if ($name === '') $this->json(['success' => false, 'message' => 'Name is required.']);

        try {
            $payload = [
                'name'         => $name,
                'min_age'      => $minAge,
                'max_age'      => $maxAge,
                'min_age_year' => $minYear,
                'max_age_year' => $maxYear,
                'sort_order'   => $sort,
            ];
            if ($id) {
                AgeCategory::updateRow($id, $payload);
            } else {
                $id = AgeCategory::create($payload);
            }
            // Persist the "also eligible" upgrade list. Empty array clears.
            $upgrades = $_POST['upgrades'] ?? [];
            if (!is_array($upgrades)) $upgrades = [];
            AgeCategory::setUpgrades((int)$id, $upgrades);

            $this->json([
                'success'  => true,
                'message'  => 'Age category saved.',
                'id'       => $id,
                'upgrades' => AgeCategory::upgradesFor((int)$id),
            ]);
        } catch (\Throwable $e) {
            error_log('[admin/age_category/save] ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Save failed: ' . $e->getMessage()]);
        }
    }

    public function ageCategoryDelete(): void
    {
        $this->boot();
        $this->verifyCsrf();
        $id = (int)($_POST['id'] ?? 0);
        try {
            AgeCategory::deleteRow($id);
            $this->json(['success' => true, 'message' => 'Age category deleted.']);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => 'Cannot delete — ' . $e->getMessage()]);
        }
    }

    // ── Sport Categories AJAX ────────────────────────────────────────────────

    public function categorySave(): void
    {
        $this->boot();
        $this->verifyCsrf();

        $id       = (int)($_POST['id'] ?? 0);
        $sportId  = (int)($_POST['sport_id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $abbr     = trim((string)($_POST['abbreviation'] ?? ''));
        $sort     = (int)($_POST['sort_order'] ?? 0);
        $pwd      = strtolower(trim((string)($_POST['pwd_status'] ?? 'no')));
        if (!in_array($pwd, ['no', 'deaf', 'para'], true)) $pwd = 'no';

        if (!$sportId || $name === '') {
            $this->json(['success' => false, 'message' => 'Sport and name are required.']);
        }
        if (mb_strlen($abbr) > 20) {
            $this->json(['success' => false, 'message' => 'Abbreviation must be 20 characters or fewer.']);
        }

        // Scoring defaults — series / shots / score type / inner ten.
        $series = (int)($_POST['default_series_count']     ?? 0) ?: null;
        $shots  = (int)($_POST['default_shots_per_series'] ?? 0) ?: null;
        $stype  = trim((string)($_POST['default_score_type'] ?? ''));
        if (!in_array($stype, ['integer','decimal_1','decimal_2'], true)) $stype = null;
        $innerTen = !empty($_POST['inner_ten']) ? 1 : 0;

        $payload = [
            'sport_id'                 => $sportId,
            'name'                     => $name,
            'abbreviation'             => $abbr ?: null,
            'sort_order'               => $sort,
            'pwd_status'               => $pwd,
            'default_series_count'     => $series,
            'default_shots_per_series' => $shots,
            'default_score_type'       => $stype,
            'inner_ten'                => $innerTen,
        ];

        try {
            if ($id) {
                SportCategory::updateRow($id, $payload);
            } else {
                $id = SportCategory::create($payload);
            }
            $this->json(['success' => true, 'message' => 'Category saved.', 'id' => $id]);
        } catch (\Throwable $e) {
            error_log('[admin/category/save] ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Save failed: ' . $e->getMessage()]);
        }
    }

    public function categoryDelete(): void
    {
        $this->boot();
        $this->verifyCsrf();
        $id = (int)($_POST['id'] ?? 0);
        try {
            SportCategory::deleteRow($id);
            $this->json(['success' => true, 'message' => 'Category deleted.']);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => 'Cannot delete — ' . $e->getMessage()]);
        }
    }

    public function categorySportEvents(string $id): void
    {
        $this->boot();
        $this->json([
            'success' => true,
            'sport_events' => SportEvent::byCategory((int)$id),
        ]);
    }

    // ── Sport Events AJAX ────────────────────────────────────────────────────

    public function sportEventSave(): void
    {
        $this->boot();
        $this->verifyCsrf();

        $id          = (int)($_POST['id'] ?? 0);
        $categoryId  = (int)($_POST['category_id'] ?? 0);
        $ageCatId    = (int)($_POST['age_category_id'] ?? 0);
        $gender      = $_POST['gender'] ?? '';
        $weight      = trim($_POST['weight'] ?? '');
        $height      = trim($_POST['height'] ?? '');
        $para        = !empty($_POST['para']) ? 1 : 0;
        $name        = trim($_POST['name'] ?? '');

        if (!$categoryId || !$ageCatId || !in_array($gender, ['male', 'female', 'mixed'], true)) {
            $this->json(['success' => false, 'message' => 'Category, age category, and gender are required.']);
        }
        $cat = SportCategory::find($categoryId);
        if (!$cat) $this->json(['success' => false, 'message' => 'Invalid category.']);

        $ageCat = AgeCategory::find($ageCatId);
        if (!$ageCat) $this->json(['success' => false, 'message' => 'Invalid age category.']);

        if ($name === '') {
            $name = SportEvent::buildName($cat['name'], $ageCat['name'], $gender, $weight ?: null, (bool)$para);
        }

        try {
            $payload = [
                'sport_id'        => (int)$cat['sport_id'],
                'category_id'     => $categoryId,
                'age_category_id' => $ageCatId,
                'gender'          => $gender,
                'weight'          => $weight ?: null,
                'height'          => $height ?: null,
                'para'            => $para,
                'name'            => $name,
            ];
            if ($id) {
                SportEvent::updateRow($id, $payload);
            } else {
                $id = SportEvent::create($payload);
            }
            $this->json(['success' => true, 'message' => 'Sport event saved.', 'id' => $id, 'name' => $name]);
        } catch (\Throwable $e) {
            error_log('[admin/sport_event/save] ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Save failed: ' . $e->getMessage()]);
        }
    }

    public function sportEventDelete(): void
    {
        $this->boot();
        $this->verifyCsrf();
        $id = (int)($_POST['id'] ?? 0);
        try {
            SportEvent::deleteRow($id);
            $this->json(['success' => true, 'message' => 'Sport event deleted.']);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => 'Cannot delete — ' . $e->getMessage()]);
        }
    }

    // ── Settings landing + Sports Items / Weapons master ────────────────────

    /** GET /admin/settings — group landing page. */
    public function index(): void
    {
        $this->boot();
        $this->renderWith('app', 'admin/settings/index', []);
    }

    /** GET /admin/settings/sport-items — per-sport items master CRUD. */
    public function sportItemsForm(): void
    {
        $this->boot();
        $sports = \Models\Athlete::getAllSports();
        $sportData = [];
        foreach ($sports as $s) {
            $sportData[] = [
                'id'    => (int)$s['id'],
                'name'  => $s['name'],
                'items' => SportItem::bySport((int)$s['id']),
            ];
        }
        $this->renderWith('app', 'admin/settings/sport-items', [
            'sports' => $sportData,
            'flash'  => $this->flash(),
        ]);
    }

    public function sportItemSave(): void
    {
        $this->boot();
        $this->verifyCsrf();

        $id      = (int)($_POST['id']       ?? 0);
        $sportId = (int)($_POST['sport_id'] ?? 0);
        $name    = trim($_POST['name']        ?? '');
        $desc    = trim($_POST['description'] ?? '');
        $status  = in_array($_POST['status'] ?? 'active', ['active','inactive'], true) ? $_POST['status'] : 'active';

        if (!$sportId || $name === '') {
            $this->json(['success' => false, 'message' => 'Sport and name are required.']);
        }
        try {
            $payload = ['sport_id' => $sportId, 'name' => $name, 'description' => $desc ?: null, 'status' => $status];
            if ($id) {
                SportItem::updateRow($id, $payload);
            } else {
                $id = SportItem::create($payload);
            }
            $this->json(['success' => true, 'message' => 'Item saved.', 'id' => $id]);
        } catch (\Throwable $e) {
            error_log('[admin/sport_item/save] ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Save failed: ' . $e->getMessage()]);
        }
    }

    public function sportItemDelete(): void
    {
        $this->boot();
        $this->verifyCsrf();
        $id = (int)($_POST['id'] ?? 0);
        try {
            SportItem::deleteRow($id);
            $this->json(['success' => true, 'message' => 'Item deleted.']);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => 'Cannot delete — ' . $e->getMessage()]);
        }
    }
}
