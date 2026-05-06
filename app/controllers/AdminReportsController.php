<?php
namespace Controllers;

use Core\Controller;
use Models\EventRegistrationPayment;

class AdminReportsController extends Controller
{
    private function boot(): void
    {
        $this->requireAuth('super_admin');
    }

    public function index(): void
    {
        $this->boot();
        $this->renderWith('app', 'admin/reports/index', []);
    }

    /**
     * GET /admin/reports/epayments
     * Event-administrator-wise summary of ePayment transactions, including
     * the receiving event's bank account details.
     */
    public function epayments(): void
    {
        $this->boot();

        $filters = [
            'from'   => trim($_GET['from']   ?? ''),
            'to'     => trim($_GET['to']     ?? ''),
            'status' => trim($_GET['status'] ?? ''),
            'q'      => trim($_GET['q']      ?? ''),
        ];
        $rows = EventRegistrationPayment::epaymentSummaryByEvent($filters);

        $totals = [
            'txn_count'        => 0,
            'approved_count'   => 0,
            'pending_count'    => 0,
            'rejected_count'   => 0,
            'approved_amount'  => 0.0,
            'pending_amount'   => 0.0,
            'rejected_amount'  => 0.0,
            'total_amount'     => 0.0,
        ];
        foreach ($rows as $r) {
            foreach ($totals as $k => $_) $totals[$k] += (float)$r[$k];
        }

        $this->renderWith('app', 'admin/reports/epayments', [
            'rows'    => $rows,
            'totals'  => $totals,
            'filters' => $filters,
        ]);
    }
}
