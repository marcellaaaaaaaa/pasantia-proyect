<?php

namespace App\Filament\Resources\SectorResource\Pages;

use App\Filament\Resources\SectorResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSector extends CreateRecord
{
    protected static string $resource = SectorResource::class;

    /**
     * Para admin (no super_admin): inyecta automáticamente su tenant_id
     * antes de guardar, ya que el campo Select está oculto en el form.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! auth()->user()->isSuperAdmin()) {
            $data['tenant_id'] = auth()->user()->tenant_id;
        }

        return $data;
    }
}
