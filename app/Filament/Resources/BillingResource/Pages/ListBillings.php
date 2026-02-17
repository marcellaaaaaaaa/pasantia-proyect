<?php

namespace App\Filament\Resources\BillingResource\Pages;

use App\Filament\Resources\BillingResource;
use Filament\Resources\Pages\ListRecords;

class ListBillings extends ListRecords
{
    protected static string $resource = BillingResource::class;

    // Sin CreateAction: los cobros se generan con el header action "Generar Cobros"
    protected function getHeaderActions(): array
    {
        return [];
    }
}
