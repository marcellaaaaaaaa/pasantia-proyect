<?php

namespace App\Filament\Resources\FamilyResource\Pages;

use App\Filament\Resources\FamilyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFamily extends CreateRecord
{
    protected static string $resource = FamilyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! auth()->user()->isSuperAdmin()) {
            $data['tenant_id'] = auth()->user()->tenant_id;
        }

        return $data;
    }
}
