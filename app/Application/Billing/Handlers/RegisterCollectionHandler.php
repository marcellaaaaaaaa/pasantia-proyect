<?php

namespace App\Application\Billing\Handlers;

use App\Application\Billing\Commands\RegisterCollectionCommand;
use App\Models\Collection;
use App\Models\Invoice;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class RegisterCollectionHandler
{
    public function handle(RegisterCollectionCommand $command): Collection
    {
        return DB::transaction(function () use ($command) {
            $invoice = Invoice::findOrFail($command->invoice_id);

            $amountUsd = $command->currency === 'USD'
                ? round($command->amount, 2)
                : round($command->amount / $command->exchange_rate, 2);

            // Evitar cobros que superen el saldo pendiente
            if ($amountUsd > $invoice->balance_usd) {
                throw new \DomainException(
                    "El monto ({$amountUsd} USD) supera el saldo pendiente ({$invoice->balance_usd} USD)."
                );
            }

            $collection = Collection::create([
                'tenant_id'    => $invoice->tenant_id,
                'invoice_id'   => $invoice->id,
                'collector_id' => $command->collector_id,
                'amount'       => $command->amount,
                'currency'     => $command->currency,
                'exchange_rate' => $command->exchange_rate,
                'amount_usd'   => $amountUsd,
                'method'       => $command->method,
                'reference'    => $command->reference,
                'collected_at' => now()->toDateString(),
                'status'       => 'verified',
                'notes'        => $command->notes,
            ]);

            // Actualizar saldo de la factura
            $invoice->collected_amount_usd += $amountUsd;
            $invoice->status = $invoice->collected_amount_usd >= $invoice->amount_usd
                ? 'collected'
                : 'partial';
            $invoice->save();

            // Registrar en la caja del cobrador para control de efectivo
            $this->creditCollectorCash($collection);

            return $collection;
        });
    }

    private function creditCollectorCash(Collection $collection): void
    {
        if (! $collection->collector_id) {
            return;
        }

        $wallet = Wallet::firstOrCreate(
            [
                'tenant_id'  => $collection->tenant_id,
                'owner_id'   => $collection->collector_id,
                'owner_type' => \App\Models\User::class,
            ],
            ['balance_usd' => 0, 'currency' => 'USD']
        );

        $wallet->increment('balance_usd', $collection->amount_usd);

        WalletTransaction::create([
            'tenant_id'   => $collection->tenant_id,
            'wallet_id'   => $wallet->id,
            'type'        => 'credit',
            'amount_usd'  => $collection->amount_usd,
            'description' => "Cobro Factura #{$collection->invoice_id}",
            'source_id'   => $collection->id,
            'source_type' => Collection::class,
        ]);
    }
}
