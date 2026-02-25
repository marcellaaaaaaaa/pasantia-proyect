<?php

namespace App\Filament\Resources\JornadaResource\Pages;

use App\Filament\Resources\JornadaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJornadas extends ListRecords
{
    protected static string $resource = JornadaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Abrir Jornada'),
        ];
    }
}
