<?php

namespace App\Filament\Resources\VaultResource\Pages;

use App\Filament\Resources\VaultResource;
use Filament\Resources\Pages\ListRecords;

class ListVaults extends ListRecords
{
    protected static string $resource = VaultResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
