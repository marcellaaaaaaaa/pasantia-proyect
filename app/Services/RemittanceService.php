<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Models\CommunityVault;
use App\Models\Payment;
use App\Models\Remittance;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use LogicException;

class RemittanceService
{
    /**
     * Tolerancia máxima permitida sobre el monto declarado al confirmar.
     * 0.05 = el admin puede confirmar hasta un 5% más que lo declarado
     * (contempla errores de conteo menores).
     */
    private const AMOUNT_TOLERANCE = 0.05;

    /**
     * Agrupa todos los pagos pending_remittance de un cobrador en una
     * nueva Remittance con status 'draft'.
     *
     * El cobrador después puede llamar a submit() antes de entregar el dinero.
     *
     * @throws LogicException si el cobrador no tiene pagos pendientes de liquidar.
     * @throws \Throwable
     */
    public function create(User $collector, ?string $notes = null): Remittance
    {
        $payments = Payment::withoutGlobalScopes()
            ->where('collector_id', $collector->id)
            ->where('status', 'pending_remittance')
            // R-2: excluir pagos que ya están en alguna remesa (draft o submitted)
            ->whereNotIn('id', fn ($q) => $q->select('payment_id')->from('remittance_payments'))
            ->get();

        if ($payments->isEmpty()) {
            throw new LogicException(
                "El cobrador {$collector->email} no tiene pagos pendientes de liquidar."
            );
        }

        $amountDeclared = $payments->sum('amount');

        return DB::transaction(function () use ($collector, $payments, $amountDeclared, $notes) {
            $remittance = Remittance::create([
                'tenant_id'       => $collector->tenant_id,
                'collector_id'    => $collector->id,
                'amount_declared' => $amountDeclared,
                'status'          => 'draft',
                'collector_notes' => $notes,
            ]);

            // Insertar pivot remittance_payments (immutable: solo insert, sin timestamps extra)
            // UNIQUE(payment_id) a nivel DB previene duplicados (R-2)
            $pivotRows = $payments->mapWithKeys(
                fn (Payment $p) => [$p->id => ['created_at' => now()]]
            )->all();

            $remittance->payments()->attach($pivotRows);

            Log::info('RemittanceService: remesa creada', [
                'remittance_id'   => $remittance->id,
                'collector'       => $collector->email,
                'payments_count'  => $payments->count(),
                'amount_declared' => $amountDeclared,
            ]);

            return $remittance;
        });
    }

    /**
     * Envía una remesa en draft al admin para su revisión.
     * Transiciona draft → submitted.
     *
     * @throws LogicException si la remesa no está en draft.
     */
    public function submit(Remittance $remittance, ?string $notes = null): void
    {
        $remittance->submit($notes);

        Log::info('RemittanceService: remesa enviada para revisión', [
            'remittance_id'   => $remittance->id,
            'amount_declared' => $remittance->amount_declared,
        ]);
    }

    /**
     * Aprueba una remesa submitted. Todo ocurre en una única DB::transaction().
     *
     * Flujo:
     *  1. Valida que amount_confirmed no excede amount_declared + tolerancia (R-2)
     *  2. Marca la remesa como approved
     *  3. Cambia todos sus Payment a 'conciliated'
     *  4. Debita la Wallet del cobrador por amount_confirmed
     *  5. Acredita el CommunityVault del tenant por amount_confirmed
     *
     * Los pasos 4 y 5 generan sus propios ledger entries (WalletTransaction y VaultTransaction).
     *
     * @throws InvalidArgumentException  si amount_confirmed es inválido o excede la tolerancia.
     * @throws LogicException            si la remesa no está en submitted.
     * @throws InsufficientBalanceException si el saldo de la wallet es insuficiente.
     * @throws \Throwable
     */
    public function approve(
        Remittance $remittance,
        User       $admin,
        float      $amountConfirmed,
        ?string    $notes = null,
    ): void {
        $this->validateApproval($remittance, $amountConfirmed);

        DB::transaction(function () use ($remittance, $admin, $amountConfirmed, $notes) {
            // 1. Marcar la remesa como aprobada
            $remittance->markApproved($admin, $amountConfirmed, $notes);

            // 2. Conciliar todos los pagos incluidos (sin disparar observer de billing)
            $paymentIds = $remittance->payments()->pluck('payments.id');

            Payment::withoutGlobalScopes()
                ->whereIn('id', $paymentIds)
                ->update(['status' => 'conciliated']);

            // 3. Debitar la wallet del cobrador
            $wallet = Wallet::withoutGlobalScopes()
                ->where('user_id', $remittance->collector_id)
                ->firstOrFail();

            $wallet->debit(
                amount:       $amountConfirmed,
                description:  "Liquidación remesa #{$remittance->id} — aprobada por {$admin->name}",
                remittanceId: $remittance->id,
            );

            // 4. Acreditar el vault central del tenant
            $tenant = $remittance->tenant;
            $vault  = CommunityVault::findOrCreateForTenant($tenant);

            $vault->credit(
                amount:       $amountConfirmed,
                description:  "Remesa #{$remittance->id} — cobrador: {$remittance->collector->name}",
                remittanceId: $remittance->id,
            );

            Log::info('RemittanceService: remesa aprobada', [
                'remittance_id'    => $remittance->id,
                'admin'            => $admin->email,
                'amount_declared'  => $remittance->amount_declared,
                'amount_confirmed' => $amountConfirmed,
                'vault_id'         => $vault->id,
            ]);

            activity()
                ->causedBy($admin)
                ->performedOn($remittance)
                ->withProperties([
                    'amount_declared'  => $remittance->amount_declared,
                    'amount_confirmed' => $amountConfirmed,
                    'collector'        => $remittance->collector->email ?? null,
                ])
                ->log('Remesa aprobada');
        });
    }

    /**
     * Rechaza una remesa submitted.
     * Los pagos mantienen su status 'pending_remittance' y se desvinculan del pivot
     * para poder ser incluidos en una futura remesa.
     *
     * @throws LogicException si la remesa no está en submitted.
     * @throws \Throwable
     */
    public function reject(
        Remittance $remittance,
        User       $admin,
        string     $notes,
    ): void {
        DB::transaction(function () use ($remittance, $admin, $notes) {
            // Desligar pagos del pivot: UNIQUE(payment_id) impediría re-liquidarlos
            // si los registros permanecieran. Los pagos conservan pending_remittance.
            $remittance->payments()->detach();

            $remittance->markRejected($admin, $notes);
        });

        Log::info('RemittanceService: remesa rechazada', [
            'remittance_id' => $remittance->id,
            'admin'         => $admin->email,
        ]);

        activity()
            ->causedBy($admin)
            ->performedOn($remittance)
            ->withProperties([
                'amount_declared' => $remittance->amount_declared,
                'notes'           => $notes,
                'collector'       => $remittance->collector->email ?? null,
            ])
            ->log('Remesa rechazada');
    }

    // ─── Validaciones ──────────────────────────────────────────────────────────

    private function validateApproval(Remittance $remittance, float $amountConfirmed): void
    {
        if (! $remittance->isSubmitted()) {
            throw new LogicException(
                "La remesa #{$remittance->id} tiene status '{$remittance->status}' y no puede ser aprobada."
            );
        }

        if ($amountConfirmed <= 0) {
            throw new InvalidArgumentException(
                "El monto confirmado debe ser mayor que cero. Recibido: {$amountConfirmed}."
            );
        }

        $declared   = (float) $remittance->amount_declared;
        $maxAllowed = (float) bcadd(
            (string) $declared,
            (string) bcmul((string) $declared, (string) self::AMOUNT_TOLERANCE, 6),
            2,
        );

        if (bccomp((string) $amountConfirmed, (string) $maxAllowed, 2) > 0) {
            $tolerancePct = (int) (self::AMOUNT_TOLERANCE * 100);
            throw new InvalidArgumentException(
                "El monto confirmado ({$amountConfirmed}) supera el declarado ({$declared}) "
                . "más la tolerancia permitida ({$tolerancePct}%). Máximo: {$maxAllowed}."
            );
        }
    }
}
