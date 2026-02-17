<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PaymentService
{
    /**
     * Registra un pago de una deuda y acredita la wallet del cobrador.
     *
     * Todo ocurre en una única DB::transaction() con lock pesimista en la wallet.
     * El observer PaymentObserver actualiza el estado del billing automáticamente.
     *
     * @param  Billing  $billing        La deuda a pagar (debe estar en pending o partial)
     * @param  User     $collector      El cobrador que registra el pago (role: collector)
     * @param  float    $amount         Monto recibido (puede ser pago parcial)
     * @param  string   $paymentMethod  'cash' | 'bank_transfer' | 'mobile_payment'
     * @param  string|null $reference   Referencia bancaria o de transferencia (opcional)
     * @param  string|null $notes       Notas adicionales
     *
     * @throws InvalidArgumentException  Si el billing no es pagable o el monto es inválido
     * @throws \App\Exceptions\InsufficientBalanceException  (propagada desde Wallet::credit si ocurre)
     * @throws \Throwable
     */
    public function register(
        Billing $billing,
        User    $collector,
        float   $amount,
        string  $paymentMethod,
        ?string $reference = null,
        ?string $notes = null,
    ): Payment {
        $this->validateBilling($billing);
        $this->validateAmount($amount, $billing);

        return DB::transaction(function () use (
            $billing, $collector, $amount, $paymentMethod, $reference, $notes
        ) {
            // 1. Crear el pago con status pending_remittance:
            //    el dinero entra a la wallet del cobrador y espera ser liquidado
            $payment = Payment::create([
                'tenant_id'      => $billing->tenant_id,
                'billing_id'     => $billing->id,
                'collector_id'   => $collector->id,
                'amount'         => $amount,
                'payment_method' => $paymentMethod,
                'status'         => 'pending_remittance',
                'reference'      => $reference,
                'payment_date'   => CarbonImmutable::today()->toDateString(),
                'notes'          => $notes,
            ]);

            // 2. Obtener o crear la wallet del cobrador
            $wallet = Wallet::findOrCreateForCollector($collector);

            // 3. Acreditar la wallet con lock pesimista (Wallet::credit internamente
            //    usa DB::transaction + lockForUpdate — el anidamiento es seguro en PgSQL)
            $wallet->credit(
                amount:      $amount,
                description: "Cobro billing #{$billing->id} — {$billing->service->name} {$billing->period}",
                paymentId:   $payment->id,
            );

            Log::info('PaymentService: pago registrado', [
                'payment_id'  => $payment->id,
                'billing_id'  => $billing->id,
                'collector'   => $collector->email,
                'amount'      => $amount,
                'method'      => $paymentMethod,
                'wallet_id'   => $wallet->id,
            ]);

            activity()
                ->causedBy($collector)
                ->performedOn($payment)
                ->withProperties([
                    'billing_id'     => $billing->id,
                    'amount'         => $amount,
                    'payment_method' => $paymentMethod,
                    'period'         => $billing->period,
                    'family'         => $billing->family->name ?? null,
                    'service'        => $billing->service->name ?? null,
                ])
                ->log('Pago registrado');

            // El PaymentObserver::created() recalcula el status del billing
            // automáticamente (pending → partial → paid)

            return $payment;
        });
    }

    // ─── Validaciones previas ──────────────────────────────────────────────────

    private function validateBilling(Billing $billing): void
    {
        if (in_array($billing->status, ['paid', 'cancelled', 'void'])) {
            throw new InvalidArgumentException(
                "El billing #{$billing->id} tiene status '{$billing->status}' y no acepta más pagos."
            );
        }
    }

    private function validateAmount(float $amount, Billing $billing): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException(
                "El monto debe ser mayor que cero. Recibido: {$amount}."
            );
        }

        $pending = (float) $billing->amount_pending;

        if (bccomp((string) $amount, (string) $pending, 2) > 0) {
            throw new InvalidArgumentException(
                "El monto ({$amount}) supera el saldo pendiente del billing ({$pending})."
            );
        }
    }
}
