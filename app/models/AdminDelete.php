<?php
namespace Models;

use Core\Model;
use Core\FileUpload;

/**
 * Structured cascade-delete helpers for the super admin.
 *
 * Each public method returns a *log* — an array of strings describing
 * exactly what was removed (rows + files + skipped items). The caller
 * shows that log on screen so the admin sees a paper trail of what
 * just happened.
 *
 * All deletes are wrapped in a transaction. File deletions happen
 * after the SQL commit so a failed COMMIT doesn't leave us with
 * orphaned rows pointing at deleted files.
 */
class AdminDelete extends Model
{
    /**
     * Delete an event ONLY if it has zero athlete registrations. If any
     * registrations exist (regardless of status) the delete is refused
     * and the log explains what's blocking it.
     */
    public static function event(int $eventId): array
    {
        $log = [];
        $event = Event::findById($eventId);
        if (!$event) return ['Event #' . $eventId . ' not found.'];

        $count = (int)(static::row(
            "SELECT COUNT(*) AS c FROM event_registrations WHERE event_id = ?",
            [$eventId]
        )['c'] ?? 0);

        if ($count > 0) {
            return [
                'BLOCKED — event has ' . $count . ' athlete registration(s). '
                . 'Delete the registrations first, or archive the event by '
                . 'setting its status to Suspended / Completed.',
            ];
        }

        // Files attached directly to the event.
        $files = [];
        if (!empty($event['logo']))         $files[] = $event['logo'];
        if (!empty($event['bank_qr_code'])) $files[] = $event['bank_qr_code'];
        foreach (static::rows("SELECT file FROM event_documents WHERE event_id = ?", [$eventId]) as $r) {
            if (!empty($r['file'])) $files[] = $r['file'];
        }

        $pdo = static::db();
        $pdo->beginTransaction();
        try {
            // Children that reference event_id (most are ON DELETE CASCADE,
            // but explicit deletes give us accurate row counts for the log).
            $log[] = 'event_documents:        ' . static::query("DELETE FROM event_documents      WHERE event_id = ?", [$eventId])->rowCount() . ' row(s) deleted';
            $log[] = 'event_units:            ' . static::query("DELETE FROM event_units          WHERE event_id = ?", [$eventId])->rowCount() . ' row(s) deleted';
            $log[] = 'event_sports:           ' . static::query("DELETE FROM event_sports         WHERE event_id = ?", [$eventId])->rowCount() . ' row(s) deleted';
            $log[] = 'event_payment_modes:    ' . static::query("DELETE FROM event_payment_modes  WHERE event_id = ?", [$eventId])->rowCount() . ' row(s) deleted';
            $log[] = 'events:                 ' . static::query("DELETE FROM events               WHERE id = ?",       [$eventId])->rowCount() . ' row(s) deleted (#' . $eventId . ' "' . $event['name'] . '")';
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $log[] = 'ERROR — rolled back: ' . $e->getMessage();
            return $log;
        }

        $log = array_merge($log, self::cleanupFiles($files));
        return $log;
    }

    /**
     * Delete a single registration plus everything that hangs off it
     * (line items, payments, transaction proofs, NOC letter file).
     */
    public static function registration(int $regId): array
    {
        $log = [];
        $reg = static::row("SELECT * FROM event_registrations WHERE id = ?", [$regId]);
        if (!$reg) return ['Registration #' . $regId . ' not found.'];

        // Files attached to this registration.
        $files = [];
        if (!empty($reg['noc_letter']))        $files[] = $reg['noc_letter'];
        if (!empty($reg['transaction_proof'])) $files[] = $reg['transaction_proof'];
        foreach (static::rows("SELECT proof_file FROM event_registration_payments WHERE registration_id = ?", [$regId]) as $r) {
            if (!empty($r['proof_file'])) $files[] = $r['proof_file'];
        }

        $pdo = static::db();
        $pdo->beginTransaction();
        try {
            $log[] = 'event_registration_payments: ' . static::query("DELETE FROM event_registration_payments WHERE registration_id = ?", [$regId])->rowCount() . ' row(s)';
            $log[] = 'event_registration_items:    ' . static::query("DELETE FROM event_registration_items    WHERE registration_id = ?", [$regId])->rowCount() . ' row(s)';
            $log[] = 'event_registrations:         ' . static::query("DELETE FROM event_registrations          WHERE id = ?",              [$regId])->rowCount() . ' row(s) (registration #' . $regId . ' for athlete #' . (int)$reg['athlete_id'] . ' on event #' . (int)$reg['event_id'] . ')';
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $log[] = 'ERROR — rolled back: ' . $e->getMessage();
            return $log;
        }

        $log = array_merge($log, self::cleanupFiles($files));
        return $log;
    }

    /**
     * Delete an athlete profile + their user account + every registration
     * they ever made + all attached files (passport photo, ID-proof file,
     * DOB-proof file, NOC letters, transaction proofs).
     */
    public static function athlete(int $athleteId): array
    {
        $log = [];
        $athlete = static::row("SELECT * FROM athletes WHERE id = ?", [$athleteId]);
        if (!$athlete) return ['Athlete #' . $athleteId . ' not found.'];

        // All registrations for this athlete — used both for the per-row
        // log and to gather the files those registrations attached.
        $regs = static::rows("SELECT id, noc_letter, transaction_proof FROM event_registrations WHERE athlete_id = ?", [$athleteId]);
        $regIds = array_map('intval', array_column($regs, 'id'));

        // Athlete-attached files.
        $files = [];
        if (!empty($athlete['passport_photo'])) $files[] = $athlete['passport_photo'];
        if (!empty($athlete['id_proof_file']))  $files[] = $athlete['id_proof_file'];
        if (!empty($athlete['dob_proof_file'])) $files[] = $athlete['dob_proof_file'];
        // Files from the athlete's registrations.
        foreach ($regs as $r) {
            if (!empty($r['noc_letter']))        $files[] = $r['noc_letter'];
            if (!empty($r['transaction_proof'])) $files[] = $r['transaction_proof'];
        }
        if ($regIds) {
            $ph = implode(',', array_fill(0, count($regIds), '?'));
            foreach (static::rows("SELECT proof_file FROM event_registration_payments WHERE registration_id IN ({$ph})", $regIds) as $r) {
                if (!empty($r['proof_file'])) $files[] = $r['proof_file'];
            }
        }

        $pdo = static::db();
        $pdo->beginTransaction();
        try {
            if ($regIds) {
                $ph = implode(',', array_fill(0, count($regIds), '?'));
                $log[] = 'event_registration_payments: ' . static::query("DELETE FROM event_registration_payments WHERE registration_id IN ({$ph})", $regIds)->rowCount() . ' row(s)';
                $log[] = 'event_registration_items:    ' . static::query("DELETE FROM event_registration_items    WHERE registration_id IN ({$ph})", $regIds)->rowCount() . ' row(s)';
            }
            $log[] = 'event_registrations:         ' . static::query("DELETE FROM event_registrations WHERE athlete_id = ?", [$athleteId])->rowCount() . ' row(s)';
            $log[] = 'athlete_sports:              ' . static::query("DELETE FROM athlete_sports      WHERE athlete_id = ?", [$athleteId])->rowCount() . ' row(s)';

            // The athlete row holds an FK to athlete_registrations (the
            // pre-approval queue) and to users. Capture both before the
            // athlete row goes.
            $regId = (int)($athlete['registration_id'] ?? 0);
            $userId = (int)($athlete['user_id'] ?? 0);

            $log[] = 'athletes:                    ' . static::query("DELETE FROM athletes WHERE id = ?", [$athleteId])->rowCount() . ' row(s) (#' . $athleteId . ' "' . $athlete['name'] . '")';
            if ($regId) {
                $log[] = 'athlete_registrations queue: ' . static::query("DELETE FROM athlete_registrations WHERE id = ?", [$regId])->rowCount() . ' row(s)';
            }
            if ($userId) {
                $log[] = 'password_resets:             ' . static::query("DELETE FROM password_resets WHERE email IN (SELECT email FROM users WHERE id = ?)", [$userId])->rowCount() . ' row(s)';
                $log[] = 'users:                       ' . static::query("DELETE FROM users WHERE id = ?", [$userId])->rowCount() . ' row(s) (login account #' . $userId . ')';
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $log[] = 'ERROR — rolled back: ' . $e->getMessage();
            return $log;
        }

        $log = array_merge($log, self::cleanupFiles($files));
        return $log;
    }

    /**
     * Delete an institution ONLY if it is not linked to any event — neither an
     * event it owns (events.institution_id) nor an approved unit participation
     * (event_units.linked_institution_id). If either exists the delete is
     * refused and the log explains what's blocking it.
     *
     * When allowed, removes the institution row (cascades its participation
     * requests), its registration row, its institution-staff rows, and drops
     * organiser access from the owning login. The login account itself is kept
     * when it also has an athlete profile (organiser access is simply removed);
     * an institution-only login is deleted with it.
     */
    public static function institution(int $id): array
    {
        $log = [];
        $inst = static::row("SELECT * FROM institutions WHERE id = ?", [$id]);
        if (!$inst) return ['Institution #' . $id . ' not found.'];

        $owned = (int)(static::row("SELECT COUNT(*) AS c FROM events WHERE institution_id = ?", [$id])['c'] ?? 0);
        $parts = 0;
        try {
            $parts = (int)(static::row("SELECT COUNT(*) AS c FROM event_units WHERE linked_institution_id = ?", [$id])['c'] ?? 0);
        } catch (\Throwable $e) { /* table may be absent on old installs */ }

        if ($owned > 0 || $parts > 0) {
            return [
                'BLOCKED — this institution is linked to '
                . $owned . ' event(s) it owns'
                . ($parts > 0 ? ' and ' . $parts . ' event participation(s)' : '')
                . '. Reassign or delete those events / participations first.',
            ];
        }

        $userId = (int)($inst['user_id'] ?? 0);
        $regId  = (int)($inst['registration_id'] ?? 0);

        $files = [];
        if (!empty($inst['logo']))         $files[] = $inst['logo'];
        if (!empty($inst['reg_document'])) $files[] = $inst['reg_document'];

        // Does the owning login also have an athlete profile? If so, keep it.
        $hasAthlete = false;
        if ($userId) {
            try { $hasAthlete = (bool)static::row("SELECT id FROM athletes WHERE user_id = ?", [$userId]); }
            catch (\Throwable $e) {}
        }

        $pdo = static::db();
        $pdo->beginTransaction();
        try {
            // Institution staff (best-effort — the table may not exist everywhere).
            try {
                $staffIds = array_map('intval', array_column(
                    static::rows("SELECT id FROM staff WHERE institution_id = ?", [$id]), 'id'));
                if ($staffIds) {
                    $ph = implode(',', array_fill(0, count($staffIds), '?'));
                    $log[] = 'staff_role_assignments: ' . static::query("DELETE FROM staff_role_assignments WHERE staff_id IN ({$ph})", $staffIds)->rowCount() . ' row(s)';
                }
                $log[] = 'staff:                  ' . static::query("DELETE FROM staff WHERE institution_id = ?", [$id])->rowCount() . ' row(s)';
            } catch (\Throwable $e) { $log[] = 'staff cleanup skipped: ' . $e->getMessage(); }

            // Institution row (cascades event_participation_requests via FK).
            $log[] = 'institutions:           ' . static::query("DELETE FROM institutions WHERE id = ?", [$id])->rowCount() . ' row(s) (#' . $id . ' "' . (string)($inst['name'] ?? '') . '")';
            if ($regId) {
                $log[] = 'institution_registrations: ' . static::query("DELETE FROM institution_registrations WHERE id = ?", [$regId])->rowCount() . ' row(s)';
            }
            if ($userId) {
                try { $log[] = 'user_capabilities (organiser): ' . static::query("DELETE FROM user_capabilities WHERE user_id = ? AND capability = 'organiser'", [$userId])->rowCount() . ' row(s)'; } catch (\Throwable $e) {}
                try { $log[] = 'access_requests (organiser):   ' . static::query("DELETE FROM access_requests WHERE user_id = ? AND type = 'organiser'", [$userId])->rowCount() . ' row(s)'; } catch (\Throwable $e) {}
                if (!$hasAthlete) {
                    try { static::query("DELETE FROM password_resets WHERE email IN (SELECT email FROM users WHERE id = ?)", [$userId]); } catch (\Throwable $e) {}
                    $log[] = 'users:                  ' . static::query("DELETE FROM users WHERE id = ?", [$userId])->rowCount() . ' row(s) (institution-only login #' . $userId . ')';
                } else {
                    $log[] = 'users:                  kept (login #' . $userId . ' also has an athlete profile — organiser access removed)';
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $log[] = 'ERROR — rolled back: ' . $e->getMessage();
            return $log;
        }

        $log = array_merge($log, self::cleanupFiles($files));
        return $log;
    }

    private static function cleanupFiles(array $relativeUrls): array
    {
        $log = [];
        $upload = new FileUpload();
        $unique = array_unique(array_filter($relativeUrls));
        foreach ($unique as $rel) {
            try {
                $upload->delete($rel);
                $log[] = 'file removed: ' . $rel;
            } catch (\Throwable $e) {
                $log[] = 'file SKIPPED (' . $e->getMessage() . '): ' . $rel;
            }
        }
        if (!$unique) $log[] = '(no attached files to clean up)';
        return $log;
    }
}
