<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

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
