<?php

namespace App\Observers;

use App\Models\Billing;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class PaymentObserver
{
    /**
     * Cuando se crea un Payment:
     *
     * 1. Recalcula el estado del Billing (pending → partial → paid).
     * 2. El crédito a la Wallet del cobrador es responsabilidad de
     *    PaymentService::register(), que ejecuta todo en una DB::transaction()
     *    con lock pesimista (MOD-008). El observer no lo duplica.
     */
    public function created(Payment $payment): void
    {
        $this->syncBillingStatus($payment->billing);
    }

    /**
     * Si un pago se anula (reversed), recalcula el estado del billing.
     */
    public function updated(Payment $payment): void
    {
        if ($payment->wasChanged('status') && $payment->status === 'reversed') {
            $this->syncBillingStatus($payment->billing);
        }
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Actualiza el status del Billing según la suma de pagos válidos.
     *
     * - totalPaid >= amount  → 'paid'
     * - 0 < totalPaid < amount → 'partial'
     * - totalPaid == 0       → 'pending'  (solo si el billing no está cancelled/void)
     */
    private function syncBillingStatus(Billing $billing): void
    {
        // No tocar billings ya cancelados o anulados
        if (in_array($billing->status, ['cancelled', 'void'])) {
            return;
        }

        $totalPaid = (float) $billing->payments()->valid()->sum('amount');
        $amount    = (float) $billing->amount;

        $newStatus = match (true) {
            $totalPaid >= $amount && $amount > 0 => 'paid',
            $totalPaid > 0                       => 'partial',
            default                              => 'pending',
        };

        if ($billing->status !== $newStatus) {
            $billing->updateQuietly(['status' => $newStatus]);

            Log::info("Billing #{$billing->id} status: {$billing->status} → {$newStatus}", [
                'billing_id' => $billing->id,
                'total_paid' => $totalPaid,
                'amount'     => $amount,
            ]);
        }
    }
}
