<?php
namespace Models;

use Core\Model;

/**
 * Per-event shooting-range catalogue:
 *   event_shooting_ranges (facility)
 *     └─ event_shooting_range_distances (10m / 25m / 50m …)
 *         └─ event_shooting_range_lanes (lane number + manual / mechanical / electronic)
 *
 * The forEventTree() helper builds the full nested array in three SELECTs,
 * so the event-edit page can render the entire panel without N+1 queries.
 */
class ShootingRange extends Model
{
    // ── Facility (event_shooting_ranges) ─────────────────────────────────────

    public static function forEventTree(int $eventId): array
    {
        $ranges = static::rows(
            "SELECT * FROM event_shooting_ranges WHERE event_id = ? ORDER BY id",
            [$eventId]
        );
        if (!$ranges) return [];

        $rangeIds = array_map(fn($r) => (int)$r['id'], $ranges);
        $rangeIn  = implode(',', array_fill(0, count($rangeIds), '?'));

        $distances = static::rows(
            "SELECT * FROM event_shooting_range_distances
              WHERE shooting_range_id IN ({$rangeIn})
              ORDER BY distance_meters",
            $rangeIds
        );

        $distIds = array_map(fn($d) => (int)$d['id'], $distances);
        $lanes   = [];
        if ($distIds) {
            $distIn = implode(',', array_fill(0, count($distIds), '?'));
            $lanes  = static::rows(
                "SELECT * FROM event_shooting_range_lanes
                  WHERE distance_id IN ({$distIn})
                  ORDER BY lane_number",
                $distIds
            );
        }

        // Group lanes under distances, distances under ranges.
        $lanesByDist = [];
        foreach ($lanes as $l) $lanesByDist[(int)$l['distance_id']][] = $l;
        $distByRange = [];
        foreach ($distances as $d) {
            $d['lanes'] = $lanesByDist[(int)$d['id']] ?? [];
            $distByRange[(int)$d['shooting_range_id']][] = $d;
        }
        foreach ($ranges as &$r) {
            $r['distances'] = $distByRange[(int)$r['id']] ?? [];
        }
        return $ranges;
    }

    public static function findRange(int $id): ?array
    {
        return static::row("SELECT * FROM event_shooting_ranges WHERE id = ?", [$id]);
    }

    public static function createRange(array $data): int
    {
        return static::insert('event_shooting_ranges', $data);
    }

    public static function updateRange(int $id, array $data): void
    {
        static::update('event_shooting_ranges', $data, ['id' => $id]);
    }

    public static function deleteRange(int $id): void
    {
        static::query("DELETE FROM event_shooting_ranges WHERE id = ?", [$id]);
    }

    // ── Distance (event_shooting_range_distances) ────────────────────────────

    public static function findDistance(int $id): ?array
    {
        return static::row("SELECT * FROM event_shooting_range_distances WHERE id = ?", [$id]);
    }

    public static function createDistance(array $data): int
    {
        return static::insert('event_shooting_range_distances', $data);
    }

    public static function updateDistance(int $id, array $data): void
    {
        static::update('event_shooting_range_distances', $data, ['id' => $id]);
    }

    public static function deleteDistance(int $id): void
    {
        static::query("DELETE FROM event_shooting_range_distances WHERE id = ?", [$id]);
    }

    // ── Lane (event_shooting_range_lanes) ────────────────────────────────────

    public static function findLane(int $id): ?array
    {
        return static::row("SELECT * FROM event_shooting_range_lanes WHERE id = ?", [$id]);
    }

    public static function createLane(array $data): int
    {
        return static::insert('event_shooting_range_lanes', $data);
    }

    public static function updateLane(int $id, array $data): void
    {
        static::update('event_shooting_range_lanes', $data, ['id' => $id]);
    }

    public static function deleteLane(int $id): void
    {
        static::query("DELETE FROM event_shooting_range_lanes WHERE id = ?", [$id]);
    }
}
