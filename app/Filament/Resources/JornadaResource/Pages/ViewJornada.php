<?php

namespace App\Filament\Resources\JornadaResource\Pages;

use App\Filament\Resources\JornadaResource;
use App\Services\BillingGenerationService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewJornada extends ViewRecord
{
    protected static string $resource = JornadaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generateBillings')
                ->label('Generar Cobros')
                ->icon('heroicon-o-document-plus')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Generar cobros para esta jornada')
                ->modalDescription(fn () => sprintf(
                    'Se crearán cobros para las familias en %d calle(s) × %d servicio(s), cubriendo los meses entre apertura y cierre.',
                    $this->record->sectors()->count(),
                    $this->record->services()->count(),
                ))
                ->action(function () {
                    $result = app(BillingGenerationService::class)
                        ->generateForJornada($this->record);

                    Notification::make()
                        ->title('Cobros generados')
                        ->body(sprintf(
                            '%d cobros creados, %d ya existían. Períodos: %s',
                            $result['created'],
                            $result['skipped'],
                            implode(', ', $result['periods']),
                        ))
                        ->success()
                        ->send();
                }),
        ];
    }
}
