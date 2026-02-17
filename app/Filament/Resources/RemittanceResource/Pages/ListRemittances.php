<?php

namespace App\Filament\Resources\RemittanceResource\Pages;

use App\Filament\Resources\RemittanceResource;
use App\Models\Remittance;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListRemittances extends ListRecords
{
    protected static string $resource = RemittanceResource::class;

    // Sin CreateAction: las remesas se crean desde la PWA o vía RemittanceService
    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Tabs para filtrar por estado sin necesidad de usar el panel de filtros.
     * La tab "Pendientes" es la vista por defecto y muestra el conteo en badge.
     */
    public function getTabs(): array
    {
        return [
            'submitted' => Tab::make('Pendientes de revisión')
                ->badge(Remittance::pendingReview()->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $q) => $q->submitted())
                ->icon('heroicon-m-clock'),

            'all' => Tab::make('Todas')
                ->icon('heroicon-m-queue-list'),

            'approved' => Tab::make('Aprobadas')
                ->modifyQueryUsing(fn (Builder $q) => $q->approved())
                ->icon('heroicon-m-check-circle'),

            'rejected' => Tab::make('Rechazadas')
                ->modifyQueryUsing(fn (Builder $q) => $q->rejected())
                ->icon('heroicon-m-x-circle'),
        ];
    }
}
