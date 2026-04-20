<?php

namespace App\Filament\Resources\CollectionRoundResource\Pages;

use App\Filament\Resources\CollectionRoundResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCollectionRound extends CreateRecord
{
    protected static string $resource = CollectionRoundResource::class;

    protected ?string $heading = 'Nueva Jornada de Cobro';

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
