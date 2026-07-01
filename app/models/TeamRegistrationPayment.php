<?php
namespace Models;

use Core\Model;

class TeamRegistrationPayment extends Model
{
    public static function forTeam(int $teamId): array
    {
        return static::rows(
            "SELECT * FROM team_registration_payments
              WHERE team_registration_id = ?
              ORDER BY transaction_date DESC, id DESC",
            [$teamId]
        );
    }

    public static function find(int $id): ?array
    {
        return static::row("SELECT * FROM team_registration_payments WHERE id = ?", [$id]);
    }

    public static function create(array $data): int
    {
        return static::insert('team_registration_payments', $data);
    }

    public static function updateRow(int $id, array $data): void
    {
        static::update('team_registration_payments', $data, ['id' => $id]);
    }

    public static function deleteRow(int $id): void
    {
        static::query("DELETE FROM team_registration_payments WHERE id = ?", [$id]);
    }

    /** Non-rejected (pending + approved) amount claimed against a team's demand. */
    public static function claimed(int $teamId): float
    {
        $r = static::row(
            "SELECT COALESCE(SUM(amount), 0) AS c
               FROM team_registration_payments
              WHERE team_registration_id = ? AND status <> 'rejected'",
            [$teamId]
        );
        return (float)($r['c'] ?? 0);
    }

    public static function totals(int $teamId): array
    {
        $r = static::row(
            "SELECT
                COUNT(*)                              AS total,
                COUNT(CASE WHEN status='approved' THEN 1 END) AS approved,
                COUNT(CASE WHEN status='rejected' THEN 1 END) AS rejected,
                COUNT(CASE WHEN status='pending'  THEN 1 END) AS pending,
                COALESCE(SUM(CASE WHEN status='approved' THEN amount END), 0) AS approved_amount,
                COALESCE(SUM(amount), 0) AS submitted_amount
               FROM team_registration_payments
              WHERE team_registration_id = ?",
            [$teamId]
        );
        return $r ?: ['total'=>0,'approved'=>0,'rejected'=>0,'pending'=>0,'approved_amount'=>0,'submitted_amount'=>0];
    }

    public static function recomputeTeamPaymentStatus(int $teamId): string
    {
        $team    = TeamRegistration::findById($teamId);
        $totals  = self::totals($teamId);
        $required = (float)($team['total_amount'] ?? 0);

        if ($totals['approved'] > 0 && $required > 0 && (float)$totals['approved_amount'] + 0.001 >= $required) {
            $status = 'paid';
        } elseif ($totals['rejected'] > 0 && $totals['approved'] === 0 && $totals['pending'] === 0) {
            $status = 'failed';
        } else {
            $status = 'pending';
        }
        TeamRegistration::updateRow($teamId, ['payment_status' => $status]);
        return $status;
    }
}
