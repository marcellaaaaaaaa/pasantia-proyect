<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                // No se puede eliminar la propia cuenta desde la página de edición
                ->hidden(fn () => $this->record->id === auth()->id()),
        ];
    }

    /**
     * Antes de guardar: elimina las claves de contraseña vacías
     * para no sobreescribir el hash existente.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }
}
