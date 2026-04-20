<?php

namespace App\Filament\Resources\CollectionResource\Pages;

use App\Application\Billing\Commands\RegisterCollectionCommand;
use App\Application\Billing\Handlers\RegisterCollectionHandler;
use App\Application\Billing\Services\ExchangeRateService;
use App\Filament\Resources\CollectionResource;
use App\Models\Invoice;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateCollection extends CreateRecord
{
    protected static string $resource = CollectionResource::class;

    protected ?string $heading = 'Registrar Cobro';

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function handleRecordCreation(array $data): Model
    {
        $currency = $data['currency'];

        // Bloquear si no hay tasa cargada para cobros en bolívares
        if ($currency === 'VED') {
            $exchangeRateService = app(ExchangeRateService::class);
            $tenantId = auth()->user()->tenant_id;

            if (! $exchangeRateService->hasTodayRate($tenantId)) {
                Notification::make()
                    ->title('Tasa BCV no disponible')
                    ->body('No se ha cargado la tasa de cambio del día. El administrador debe cargarla antes de registrar cobros.')
                    ->danger()
                    ->persistent()
                    ->send();

                $this->halt();
            }
        }

        // Validar que el cobro no supere el saldo pendiente
        $invoice   = Invoice::findOrFail($data['invoice_id']);
        $amountUsd = $currency === 'USD'
            ? round((float) $data['amount'], 2)
            : round((float) $data['amount'] / (float) $data['exchange_rate'], 2);

        if ($amountUsd > $invoice->balance_usd) {
            Notification::make()
                ->title('Monto inválido')
                ->body("El cobro ({$amountUsd} USD) supera el saldo pendiente de la factura ({$invoice->balance_usd} USD).")
                ->danger()
                ->send();

            $this->halt();
        }

        $command = new RegisterCollectionCommand(
            invoice_id:   $data['invoice_id'],
            amount:       (float) $data['amount'],
            currency:     $currency,
            exchange_rate: (float) ($data['exchange_rate'] ?? 1),
            method:       $data['method'],
            reference:    $data['reference'] ?? null,
            notes:        $data['notes'] ?? null,
            collector_id: auth()->id(),
        );

        return app(RegisterCollectionHandler::class)->handle($command);
    }
}
