<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected ?string $heading = 'Nueva Factura';

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
