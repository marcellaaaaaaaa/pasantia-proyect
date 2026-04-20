<?php

namespace App\Filament\Resources\CollectionRoundResource\Pages;

use App\Filament\Resources\CollectionRoundResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCollectionRound extends EditRecord
{
    protected static string $resource = CollectionRoundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
