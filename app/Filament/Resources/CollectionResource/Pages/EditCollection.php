<?php

namespace App\Filament\Resources\CollectionResource\Pages;

use App\Filament\Resources\CollectionResource;
use Filament\Resources\Pages\EditRecord;

class EditCollection extends EditRecord
{
    protected static string $resource = CollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function isReadOnly(): bool
    {
        return $this->getRecord()->status !== 'pending';
    }

    protected function getFormActions(): array
    {
        if ($this->isReadOnly()) {
            return [
                $this->getCancelFormAction()->label('Volver'),
            ];
        }

        return parent::getFormActions();
    }
}
