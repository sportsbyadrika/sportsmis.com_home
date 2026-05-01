<?php
namespace Controllers;

use Core\{Controller, Auth};
use Models\{Schema, AgeCategory, SportCategory, SportEvent};

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

    /** GET /admin/settings/sports — combined settings page. */
    public function sportsForm(): void
    {
        $this->boot();

        $sports = \Models\Athlete::getAllSports();
        $sportData = [];
        foreach ($sports as $s) {
            $sportData[] = [
                'id'                 => (int)$s['id'],
                'name'               => $s['name'],
                'enabled_for_events' => (int)($s['enabled_for_events'] ?? 0),
                'categories'         => SportCategory::bySport((int)$s['id']),
            ];
        }

        $this->renderWith('app', 'admin/settings/sports', [
            'age_categories' => AgeCategory::all(),
            'sports'         => $sportData,
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

        $id      = (int)($_POST['id'] ?? 0);
        $name    = trim($_POST['name'] ?? '');
        $minAge  = $_POST['min_age']  !== '' && $_POST['min_age']  !== null ? (int)$_POST['min_age']  : null;
        $maxAge  = $_POST['max_age']  !== '' && $_POST['max_age']  !== null ? (int)$_POST['max_age']  : null;
        $sort    = (int)($_POST['sort_order'] ?? 0);

        if ($name === '') $this->json(['success' => false, 'message' => 'Name is required.']);

        try {
            if ($id) {
                AgeCategory::updateRow($id, [
                    'name' => $name, 'min_age' => $minAge, 'max_age' => $maxAge, 'sort_order' => $sort,
                ]);
            } else {
                $id = AgeCategory::create([
                    'name' => $name, 'min_age' => $minAge, 'max_age' => $maxAge, 'sort_order' => $sort,
                ]);
            }
            $this->json(['success' => true, 'message' => 'Age category saved.', 'id' => $id]);
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
        $sort     = (int)($_POST['sort_order'] ?? 0);

        if (!$sportId || $name === '') {
            $this->json(['success' => false, 'message' => 'Sport and name are required.']);
        }

        try {
            if ($id) {
                SportCategory::updateRow($id, ['sport_id' => $sportId, 'name' => $name, 'sort_order' => $sort]);
            } else {
                $id = SportCategory::create(['sport_id' => $sportId, 'name' => $name, 'sort_order' => $sort]);
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
}
