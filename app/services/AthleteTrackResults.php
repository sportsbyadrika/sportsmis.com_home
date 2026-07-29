<?php
namespace Services;

use Models\{Event, TrackConfig, Schema};

/**
 * Round-wise Athletics / Skating results for one athlete registration:
 * per registered track/field event-sport, the per-round placement (heat/track
 * or order no, result value, rank, qualified, published) plus per-round
 * participant and result counts. Shared by the Staff athlete view and the
 * public results page.
 */
class AthleteTrackResults
{
    /** @return array<int,array> one entry per track/field event-sport */
    public static function forRegistration(int $regId): array
    {
        if ($regId <= 0) return [];
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}

        $teRows = Event::rowsRaw(
            "SELECT es.id AS esid, es.event_code, es.track_event_type,
                    es.track_result_unit, es.track_num_laps,
                    sev.name AS sport_event_name, sc.name AS category_name,
                    ac.name AS age_name, sev.gender
               FROM event_registration_items eri
               JOIN event_sports     es  ON es.id  = eri.event_sport_id
          LEFT JOIN sport_events     sev ON sev.id = es.sport_event_id
          LEFT JOIN sport_categories sc  ON sc.id  = sev.category_id
          LEFT JOIN age_categories   ac  ON ac.id  = sev.age_category_id
              WHERE eri.registration_id = ? AND es.track_event_type IS NOT NULL
              ORDER BY sc.name, sev.name, es.event_code",
            [$regId]
        );
        if (!$teRows) return [];

        $esids    = array_map(fn($r) => (int)$r['esid'], $teRows);
        $roundMap = TrackConfig::roundsForMany($esids);
        $assignByRound = [];
        foreach (Event::rowsRaw(
            "SELECT round_id, heat_no, track_no, result_time, result_rank, is_qualified, is_published
               FROM track_heat_assignments WHERE registration_id = ?", [$regId]) as $ar) {
            $assignByRound[(int)$ar['round_id']] = $ar;
        }

        $out = [];
        foreach ($teRows as $te) {
            $esid    = (int)$te['esid'];
            $isField = (($te['track_event_type'] ?? '') === 'field');
            $rr = []; $hasResult = false; $placed = false;
            foreach (($roundMap[$esid] ?? []) as $rd) {
                $a = $assignByRound[(int)$rd['id']] ?? null;
                if ($a) {
                    $placed = true;
                    if (($a['result_time'] ?? '') !== '' || $a['result_rank'] !== null) $hasResult = true;
                }
                $rr[] = [
                    'round_name'   => (string)$rd['round_name'],
                    'assign'       => $a,
                    'participants' => (int)($rd['assigned_count'] ?? 0),
                    'results'      => (int)($rd['result_count'] ?? 0),
                ];
            }
            $out[] = [
                'esid'        => $esid,
                'event'       => trim((string)($te['sport_event_name'] ?? '')) ?: (string)($te['event_code'] ?? ''),
                'category'    => (string)($te['category_name'] ?? ''),
                'age'         => (string)($te['age_name'] ?? ''),
                'gender'      => (string)($te['gender'] ?? ''),
                'is_field'    => $isField,
                'result_unit' => (string)($te['track_result_unit'] ?? 'time'),
                'num_laps'    => (int)($te['track_num_laps'] ?? 0),
                'rounds'      => $rr,
                'placed'      => $placed,
                'has_result'  => $hasResult,
            ];
        }
        return $out;
    }
}
