<?php

namespace App\Filament\Resources\CollectionRoundResource\Pages;

use App\Filament\Resources\CollectionRoundResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCollectionRounds extends ListRecords
{
    protected static string $resource = CollectionRoundResource::class;

    protected ?string $heading = 'Listado de Jornadas de Cobro';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
