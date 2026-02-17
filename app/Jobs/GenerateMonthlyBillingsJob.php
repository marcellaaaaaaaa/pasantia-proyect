<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\BillingGenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * JOB-001 — Genera los cobros mensuales para todos los tenants activos.
 *
 * Este job es encolado por el scheduler el 1er día de cada mes.
 * Puede ejecutarse también manualmente para un tenant específico.
 *
 * Queue: 'billing' (separada de notifications para priorización)
 */
class GenerateMonthlyBillingsJob implements ShouldQueue
{
    use Queueable;

    /** Número máximo de intentos antes de fallar el job */
    public int $tries = 3;

    /** Tiempo de timeout en segundos */
    public int $timeout = 300;

    public function __construct(
        /** Tenant específico o null para procesar todos los activos */
        public readonly ?int $tenantId = null,
        /** Período en formato YYYY-MM, o null para el mes actual */
        public readonly ?string $period = null,
    ) {
        $this->onQueue('billing');
    }

    public function handle(BillingGenerationService $service): void
    {
        $period = $this->period ?? now()->format('Y-m');

        if ($this->tenantId !== null) {
            // Procesar un solo tenant
            $tenant = Tenant::findOrFail($this->tenantId);
            $this->processOne($service, $tenant, $period);
            return;
        }

        // Procesar todos los tenants activos
        Tenant::active()->each(function (Tenant $tenant) use ($service, $period) {
            $this->processOne($service, $tenant, $period);
        });
    }

    private function processOne(BillingGenerationService $service, Tenant $tenant, string $period): void
    {
        try {
            $result = $service->generateForTenant($tenant, $period);

            Log::info('GenerateMonthlyBillingsJob: tenant procesado', [
                'tenant'  => $tenant->slug,
                'period'  => $period,
                'created' => $result['created'],
                'skipped' => $result['skipped'],
            ]);
        } catch (\Throwable $e) {
            Log::error('GenerateMonthlyBillingsJob: error al procesar tenant', [
                'tenant' => $tenant->slug,
                'period' => $period,
                'error'  => $e->getMessage(),
            ]);

            throw $e; // re-lanza para que Laravel reintente el job
        }
    }
}
