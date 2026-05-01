<?php
namespace Models;

use Core\Model;

class AgeCategory extends Model
{
    public static function all(): array
    {
        return static::rows("SELECT * FROM age_categories ORDER BY sort_order, name");
    }

    public static function active(): array
    {
        return static::rows("SELECT * FROM age_categories WHERE status='active' ORDER BY sort_order, name");
    }

    public static function find(int $id): ?array
    {
        return static::row("SELECT * FROM age_categories WHERE id = ?", [$id]);
    }

    public static function create(array $data): int
    {
        return static::insert('age_categories', $data);
    }

    public static function updateRow(int $id, array $data): void
    {
        static::update('age_categories', $data, ['id' => $id]);
    }

    public static function deleteRow(int $id): void
    {
        static::query("DELETE FROM age_categories WHERE id = ?", [$id]);
    }
}
