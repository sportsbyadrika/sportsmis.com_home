<?php
namespace Models;

use Core\Model;

class SportEvent extends Model
{
    /**
     * Catalog rows under a sport_category. When $ageSet is given
     * ('master' / 'cbse' / ...), only rows whose age_category belongs
     * to that set come back — used to scope the sport-events picker on
     * an event configured for a non-default Age Category Set. An empty
     * / null $ageSet returns every active row (legacy behaviour).
     */
    public static function byCategory(int $categoryId, ?string $ageSet = null): array
    {
        $sql = "SELECT se.*, ac.name AS age_category_name, ac.set_code AS age_category_set_code
                  FROM sport_events se
                  JOIN age_categories ac ON ac.id = se.age_category_id
                 WHERE se.category_id = ? AND se.status = 'active'";
        $params = [$categoryId];
        if ($ageSet !== null && $ageSet !== '') {
            $sql      .= " AND ac.set_code = ?";
            $params[] = $ageSet;
        }
        $sql .= " ORDER BY ac.sort_order, se.gender, se.name";
        return static::rows($sql, $params);
    }

    public static function bySport(int $sportId): array
    {
        return static::rows(
            "SELECT se.*, sc.name AS category_name, ac.name AS age_category_name
               FROM sport_events se
               JOIN sport_categories sc ON sc.id = se.category_id
               JOIN age_categories   ac ON ac.id = se.age_category_id
              WHERE se.sport_id = ? AND se.status='active'
              ORDER BY sc.sort_order, ac.sort_order, se.gender, se.name",
            [$sportId]
        );
    }

    public static function find(int $id): ?array
    {
        return static::row(
            "SELECT se.*, s.name AS sport_name, sc.name AS category_name, ac.name AS age_category_name
               FROM sport_events se
               JOIN sports           s  ON s.id  = se.sport_id
               JOIN sport_categories sc ON sc.id = se.category_id
               JOIN age_categories   ac ON ac.id = se.age_category_id
              WHERE se.id = ?",
            [$id]
        );
    }

    public static function create(array $data): int
    {
        return static::insert('sport_events', $data);
    }

    public static function updateRow(int $id, array $data): void
    {
        static::update('sport_events', $data, ['id' => $id]);
    }

    public static function deleteRow(int $id): void
    {
        static::query("DELETE FROM sport_events WHERE id = ?", [$id]);
    }

    /**
     * Build a default name like "10m Air Pistol Senior Men" from the parts.
     */
    public static function buildName(string $categoryName, string $ageCatName, string $gender, ?string $weight = null, bool $para = false): string
    {
        $g = match ($gender) { 'male' => 'Men', 'female' => 'Women', default => 'Mixed' };
        $parts = [$categoryName, $ageCatName, $g];
        if ($weight) $parts[] = $weight;
        if ($para)   $parts[] = '(Para)';
        return implode(' ', $parts);
    }
}
