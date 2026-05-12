<?php
namespace Models;

use Core\Model;

/**
 * Per-event Relay schedule. Each relay rolls up:
 *   - schedule fields (number, date, match time, reporting time)
 *   - the Shooting Range it runs on (event_shooting_range_distances.id)
 *   - the subset of that range's lanes that are active for the relay
 *     (event_relay_lanes junction)
 */
class Relay extends Model
{
    /** Full relay list for an event with venue / range labels + active lane ids. */
    public static function forEvent(int $eventId): array
    {
        $rows = static::rows(
            "SELECT r.*,
                    d.name AS range_name,
                    d.distance_meters,
                    sr.name AS venue_name,
                    sr.location AS venue_location
               FROM event_relays r
               JOIN event_shooting_range_distances d  ON d.id  = r.shooting_range_distance_id
               JOIN event_shooting_ranges          sr ON sr.id = d.shooting_range_id
              WHERE r.event_id = ?
              ORDER BY COALESCE(r.relay_date, '9999-12-31'),
                       COALESCE(r.match_time, '23:59:59'),
                       r.id",
            [$eventId]
        );
        if (!$rows) return [];

        $ids = array_map(fn($r) => (int)$r['id'], $rows);
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $laneRows = static::rows(
            "SELECT erl.relay_id, erl.lane_id, erl.category,
                    l.lane_number, l.lane_type
               FROM event_relay_lanes erl
               JOIN event_shooting_range_lanes l ON l.id = erl.lane_id
              WHERE erl.relay_id IN ({$in})
              ORDER BY l.lane_number",
            $ids
        );
        $byRelay = [];
        foreach ($laneRows as $l) {
            $byRelay[(int)$l['relay_id']][] = [
                'lane_id'     => (int)$l['lane_id'],
                'lane_number' => (int)$l['lane_number'],
                'lane_type'   => $l['lane_type'],
                'category'    => $l['category'],
            ];
        }
        foreach ($rows as &$r) {
            $r['lane_assignments'] = $byRelay[(int)$r['id']] ?? [];
        }
        return $rows;
    }

    public static function find(int $id): ?array
    {
        return static::row("SELECT * FROM event_relays WHERE id = ?", [$id]);
    }

    public static function create(array $data): int
    {
        return static::insert('event_relays', $data);
    }

    public static function updateRow(int $id, array $data): void
    {
        static::update('event_relays', $data, ['id' => $id]);
    }

    public static function deleteRow(int $id): void
    {
        static::query("DELETE FROM event_relays WHERE id = ?", [$id]);
    }

    /**
     * Replace the lane-junction for a relay with the given (lane_id, category)
     * pairs. Pairs with an empty / null category, or category == 'not_using',
     * are skipped so the junction only carries actually-used lanes.
     *
     * @param array<array{lane_id:int,category:?string}> $assignments
     */
    public static function setLaneAssignments(int $relayId, array $assignments): void
    {
        static::query("DELETE FROM event_relay_lanes WHERE relay_id = ?", [$relayId]);
        foreach ($assignments as $a) {
            $lid = (int)($a['lane_id'] ?? 0);
            $cat = trim((string)($a['category'] ?? ''));
            if (!$lid || $cat === '' || $cat === 'not_using') continue;
            try {
                static::query(
                    "INSERT IGNORE INTO event_relay_lanes (relay_id, lane_id, category) VALUES (?, ?, ?)",
                    [$relayId, $lid, $cat]
                );
            } catch (\Throwable $e) {
                error_log('[Relay::setLaneAssignments] ' . $e->getMessage());
            }
        }
    }
}
