<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use App\Jobs\SendReceiptJob;
use App\Services\ReceiptService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('enviar_comprobante')
                ->label('Enviar Comprobante')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->requiresConfirmation()
                ->action(function (): void {
                    SendReceiptJob::dispatch($this->record->id);
                    $url = app(ReceiptService::class)->getSignedUrl($this->record);

                    Notification::make()
                        ->success()
                        ->title('Comprobante generado')
                        ->body("Enlace válido 48h: {$url}")
                        ->persistent()
                        ->send();
                }),

            Actions\Action::make('descargar_pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn () => app(ReceiptService::class)->getSignedUrl($this->record))
                ->openUrlInNewTab(),
        ];
    }
}
