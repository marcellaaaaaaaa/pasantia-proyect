<?php

namespace App\Filament\Resources\JornadaResource\Pages;

use App\Filament\Resources\JornadaResource;
use App\Models\Jornada;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateJornada extends CreateRecord
{
    protected static string $resource = JornadaResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! auth()->user()->isSuperAdmin()) {
            $data['tenant_id'] = auth()->user()->tenant_id;
        }

        $data['status'] = filled($data['closed_at'] ?? null) ? 'closed' : 'open';

        return $data;
    }

    protected function beforeCreate(): void
    {
        $collectorId = $this->data['collector_id'];

        $hasOpen = Jornada::withoutGlobalScopes()
            ->where('collector_id', $collectorId)
            ->where('status', 'open')
            ->exists();

        if ($hasOpen) {
            Notification::make()
                ->title('Este cobrador ya tiene una jornada abierta.')
                ->danger()
                ->send();

            $this->halt();
        }
    }
}
