<?php

namespace App\Imports;

use App\Models\Family;
use App\Models\Property;
use App\Models\Sector;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Importador de censo de familias desde Excel/CSV.
 *
 * Columnas esperadas en la hoja (row de cabecera):
 *   sector_name | address | type | unit_number | family_name
 *
 * Comportamiento:
 *   - Si el sector no existe, se crea automáticamente.
 *   - Si la propiedad (address + sector) no existe, se crea.
 *   - Si la familia ya existe en esa propiedad (mismo nombre), se omite (idempotente).
 *   - Errores de fila se acumulan en $errors sin detener la importación completa.
 */
class FamilyImport implements ToCollection, WithHeadingRow, WithValidation
{
    public array $errors = [];

    /** Contadores de resultado */
    public int $created = 0;
    public int $skipped = 0;

    public function __construct(
        private readonly Tenant $tenant,
    ) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            try {
                $this->processRow($row->toArray(), $index + 2); // +2: cabecera + índice 0
            } catch (\Throwable $e) {
                $rowNum = $index + 2;
                $this->errors[] = "Fila {$rowNum}: {$e->getMessage()}";
                Log::warning("FamilyImport: error en fila {$rowNum}", [
                    'tenant' => $this->tenant->slug,
                    'row'    => $row->toArray(),
                    'error'  => $e->getMessage(),
                ]);
            }
        }
    }

    private function processRow(array $row, int $rowNum): void
    {
        $sectorName  = trim((string) ($row['sector_name'] ?? ''));
        $address     = trim((string) ($row['address']     ?? ''));
        $type        = strtolower(trim((string) ($row['type'] ?? 'house')));
        $unitNumber  = trim((string) ($row['unit_number'] ?? '')) ?: null;
        $familyName  = trim((string) ($row['family_name'] ?? ''));

        if (! $sectorName || ! $address || ! $familyName) {
            throw new \InvalidArgumentException('sector_name, address y family_name son requeridos.');
        }

        if (! in_array($type, ['house', 'apartment', 'commercial'])) {
            $type = 'house';
        }

        // Obtener o crear sector
        $sector = Sector::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => $sectorName],
            ['description' => null],
        );

        // Obtener o crear propiedad
        $property = Property::firstOrCreate(
            [
                'tenant_id' => $this->tenant->id,
                'sector_id' => $sector->id,
                'address'   => $address,
            ],
            [
                'type'        => $type,
                'unit_number' => $unitNumber,
            ],
        );

        // Obtener o crear familia
        $family = Family::withoutGlobalScopes()->firstOrCreate(
            [
                'tenant_id'   => $this->tenant->id,
                'property_id' => $property->id,
                'name'        => $familyName,
            ],
            ['is_active' => true],
        );

        if ($family->wasRecentlyCreated) {
            $this->created++;
        } else {
            $this->skipped++;
        }
    }

    public function rules(): array
    {
        return [
            'family_name' => ['required', 'string'],
            'address'     => ['required', 'string'],
            'sector_name' => ['required', 'string'],
        ];
    }
}
