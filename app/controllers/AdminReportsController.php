<?php
namespace Controllers;

use Core\{Controller, Razorpay};
use Models\{EventRegistrationPayment, Schema};
use Services\PaymentApprovalService;

class AdminReportsController extends Controller
{
    private function boot(): void
    {
        $this->requireAuth('super_admin');
        // The report SELECTs events.bank_* and event_registration_payments.event_id,
        // both added by ensureRegistrationFlow(). Trigger it here so visiting the
        // report is enough to provision the columns even on a fresh DB.
        try { Schema::ensureRegistrationFlow(); } catch (\Throwable $e) {
            error_log('[AdminReports] schema: ' . $e->getMessage());
        }
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

    /**
     * GET /admin/reports/epayments/pending
     * Per-row list of pending ePayment transactions with a per-row
     * "Re-check with Razorpay" action button.
     */
    public function pendingEpayments(): void
    {
        $this->boot();
        $this->renderWith('app', 'admin/reports/pending-epayments', [
            'rows'  => EventRegistrationPayment::pendingEpaymentsForAdmin(),
            'flash' => $this->flash(),
        ]);
    }

    /**
     * POST /admin/reports/epayments/recheck
     * Same per-order logic as the reconcile cron, triggered manually
     * from the admin UI. JSON response so the row can update inline.
     */
    public function recheckEpayment(): void
    {
        $this->boot();
        $this->verifyCsrf();
        $rowId = (int)($_POST['row_id'] ?? 0);
        $row   = EventRegistrationPayment::find($rowId);
        if (!$row || ($row['payment_method'] ?? '') !== 'epayment') {
            $this->json(['success' => false, 'message' => 'Not an ePayment row.'], 404);
        }
        $orderId = (string)($row['razorpay_order_id'] ?? '');
        if ($orderId === '') {
            $this->json(['success' => false, 'message' => 'Row has no razorpay_order_id to query.'], 400);
        }
        try {
            $payments = (new Razorpay())->fetchOrderPayments($orderId);
            $outcome  = PaymentApprovalService::applyOrderPayments($rowId, $payments, 'admin');
            EventRegistrationPayment::updateRow($rowId, ['reconciled_at' => date('Y-m-d H:i:s')]);
            $fresh    = EventRegistrationPayment::find($rowId);
            $this->json([
                'success' => true,
                'outcome' => $outcome,
                'status'  => $fresh['status'] ?? null,
                'message' => 'Razorpay says: ' . $outcome,
            ]);
        } catch (\Throwable $e) {
            error_log('[admin/recheck] ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Razorpay query failed: ' . $e->getMessage()], 500);
        }
    }
}
