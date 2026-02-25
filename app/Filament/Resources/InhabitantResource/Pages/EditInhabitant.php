<?php

namespace App\Filament\Resources\InhabitantResource\Pages;

use App\Filament\Resources\InhabitantResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInhabitant extends EditRecord
{
    protected static string $resource = InhabitantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
