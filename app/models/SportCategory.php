<?php
namespace Models;

use Core\Model;

class SportCategory extends Model
{
    public static function bySport(int $sportId): array
    {
        return static::rows(
            "SELECT * FROM sport_categories WHERE sport_id = ? AND status='active' ORDER BY sort_order, name",
            [$sportId]
        );
    }

    public static function find(int $id): ?array
    {
        return static::row("SELECT * FROM sport_categories WHERE id = ?", [$id]);
    }

    public static function create(array $data): int
    {
        return static::insert('sport_categories', $data);
    }

    public static function updateRow(int $id, array $data): void
    {
        static::update('sport_categories', $data, ['id' => $id]);
    }

    public static function deleteRow(int $id): void
    {
        static::query("DELETE FROM sport_categories WHERE id = ?", [$id]);
    }
}
