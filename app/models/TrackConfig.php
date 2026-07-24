<?php
namespace Models;

use Core\Model;

/**
 * Track / field configuration for the Athletics-Skating lane-allocation
 * workspace: per-event-sport event type (track|field) + number of tracks,
 * and the ordered list of rounds (Preliminary heats / Semifinal heats /
 * Final) with their heat counts.
 */
class TrackConfig extends Model
{
    public const ROUND_NAMES = ['Preliminary heats', 'Semifinal heats', 'Final'];

    /** Set the event type (and track count) for one event_sport row. */
    public static function setEventType(int $eventSportId, string $type, ?int $numTracks): void
    {
        $type = in_array($type, ['track', 'field'], true) ? $type : 'field';
        static::update('event_sports', [
            'track_event_type' => $type,
            'track_num_tracks' => $type === 'track' ? max(1, (int)$numTracks) : null,
        ], ['id' => $eventSportId]);
    }

    /** Rounds for a single event_sport, ordered. */
    public static function roundsFor(int $eventSportId): array
    {
        return static::rows(
            "SELECT * FROM event_sport_rounds WHERE event_sport_id = ? ORDER BY round_order, id",
            [$eventSportId]
        );
    }

    /** Rounds for many event_sport ids, grouped by event_sport_id. */
    public static function roundsForMany(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) return [];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $rows = static::rows(
            "SELECT * FROM event_sport_rounds WHERE event_sport_id IN ($ph) ORDER BY round_order, id",
            $ids
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r['event_sport_id']][] = $r;
        }
        return $out;
    }

    /** Append a round; the order is the next slot for that event_sport. */
    public static function addRound(int $eventSportId, string $name, int $numHeats): int
    {
        if (!in_array($name, self::ROUND_NAMES, true)) $name = self::ROUND_NAMES[0];
        $mx = static::row(
            "SELECT COALESCE(MAX(round_order), 0) AS mx FROM event_sport_rounds WHERE event_sport_id = ?",
            [$eventSportId]
        );
        $order = (int)($mx['mx'] ?? 0) + 1;
        return static::insert('event_sport_rounds', [
            'event_sport_id' => $eventSportId,
            'round_order'    => $order,
            'round_name'     => $name,
            'num_heats'      => max(1, $numHeats),
        ]);
    }

    public static function findRound(int $id): ?array
    {
        return static::row("SELECT * FROM event_sport_rounds WHERE id = ?", [$id]);
    }

    public static function deleteRound(int $id): void
    {
        static::query("DELETE FROM event_sport_rounds WHERE id = ?", [$id]);
    }
}
