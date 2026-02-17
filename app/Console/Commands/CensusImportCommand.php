<?php

namespace App\Console\Commands;

use App\Imports\FamilyImport;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

/**
 * CMD-002 — Importa el censo de familias desde un Excel o CSV.
 *
 * Uso:
 *   php artisan census:import {tenant-slug} {path/to/file.xlsx}
 *
 * Formato del archivo (con cabecera en la primera fila):
 *   sector_name | address | type | unit_number | family_name
 *
 * Ejemplo:
 *   php artisan census:import comunidad-norte /tmp/censo.xlsx
 */
class CensusImportCommand extends Command
{
    protected $signature = 'census:import
                            {tenant   : Slug del tenant (ej: comunidad-norte)}
                            {file     : Ruta al archivo Excel o CSV}';

    protected $description = 'Importa el censo de familias desde un archivo Excel/CSV';

    public function handle(): int
    {
        $slug = $this->argument('tenant');
        $file = $this->argument('file');

        // Verificar que el tenant existe
        $tenant = Tenant::where('slug', $slug)->first();
        if (! $tenant) {
            $this->error("Tenant con slug '{$slug}' no encontrado.");
            return self::FAILURE;
        }

        // Verificar que el archivo existe
        if (! file_exists($file)) {
            $this->error("Archivo no encontrado: {$file}");
            return self::FAILURE;
        }

        $this->info("Importando censo para: <comment>{$tenant->name}</comment>");
        $this->info("Archivo: <comment>{$file}</comment>");

        $import = new FamilyImport($tenant);

        try {
            Excel::import($import, $file);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            foreach ($e->failures() as $failure) {
                $this->warn("Fila {$failure->row()}: " . implode(', ', $failure->errors()));
            }
            // Continuar con filas válidas (ya procesadas)
        }

        // Resumen
        $this->newLine();
        $this->table(
            ['Resultado', 'Cantidad'],
            [
                ['Familias creadas', $import->created],
                ['Familias omitidas (ya existen)', $import->skipped],
                ['Errores', count($import->errors)],
            ]
        );

        if (! empty($import->errors)) {
            $this->newLine();
            $this->warn('Errores encontrados:');
            foreach ($import->errors as $err) {
                $this->warn("  · {$err}");
            }
        }

        $status = count($import->errors) > 0 && $import->created === 0
            ? self::FAILURE
            : self::SUCCESS;

        $this->newLine();
        if ($status === self::SUCCESS) {
            $this->info('Importación completada.');
        } else {
            $this->warn('Importación completada con errores.');
        }

        return $status;
    }
}
