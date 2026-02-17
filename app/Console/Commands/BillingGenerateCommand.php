<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\BillingGenerationService;
use Illuminate\Console\Command;

class BillingGenerateCommand extends Command
{
    /**
     * CMD-001 — Genera cobros mensuales para todos los tenants activos.
     *
     * Uso:
     *   artisan billing:generate
     *   artisan billing:generate --period=2026-03
     *   artisan billing:generate --tenant=urbanizacion-los-pinos
     */
    protected $signature = 'billing:generate
                            {--period=   : Período YYYY-MM (por defecto: mes actual)}
                            {--tenant=   : Slug del tenant (por defecto: todos los activos)}';

    protected $description = 'Genera cobros mensuales para todos los tenants activos (o uno específico)';

    public function handle(BillingGenerationService $service): int
    {
        $period     = $this->option('period') ?? now()->format('Y-m');
        $tenantSlug = $this->option('tenant');

        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->error("Período inválido: '{$period}'. Formato esperado: YYYY-MM");
            return self::FAILURE;
        }

        $query = Tenant::where('status', 'active');

        if ($tenantSlug) {
            $query->where('slug', $tenantSlug);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->warn('No se encontraron tenants activos' . ($tenantSlug ? " con slug '{$tenantSlug}'" : '') . '.');
            return self::SUCCESS;
        }

        $this->info("Generando cobros para período <comment>{$period}</comment>...");

        $totalCreated = 0;
        $totalSkipped = 0;
        $errors       = 0;

        $this->withProgressBar($tenants, function (Tenant $tenant) use (
            $service, $period, &$totalCreated, &$totalSkipped, &$errors
        ) {
            try {
                $result = $service->generateForTenant($tenant, $period);
                $totalCreated += $result['created'];
                $totalSkipped += $result['skipped'];
            } catch (\Throwable $e) {
                $errors++;
                $this->newLine();
                $this->error("Error en tenant '{$tenant->slug}': {$e->getMessage()}");
            }
        });

        $this->newLine(2);
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Cobros nuevos creados',   $totalCreated],
                ['Cobros ya existían',       $totalSkipped],
                ['Tenants procesados',       $tenants->count()],
                ['Errores',                  $errors],
            ]
        );

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
