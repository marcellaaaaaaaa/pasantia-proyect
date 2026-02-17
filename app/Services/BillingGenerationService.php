<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\Family;
use App\Models\Service;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

class BillingGenerationService
{
    /**
     * Genera los billings de un tenant para un período dado.
     *
     * Idempotente: si el billing (family_id, service_id, period) ya existe,
     * lo omite silenciosamente (protege el UNIQUE constraint / R-4 del plan).
     *
     * @param  string|null  $period  Formato YYYY-MM. Si null, usa el mes actual.
     * @return array{tenant: string, period: string, created: int, skipped: int}
     */
    public function generateForTenant(Tenant $tenant, ?string $period = null): array
    {
        $period  = $period ?? CarbonImmutable::now()->format('Y-m');
        $dueDate = CarbonImmutable::createFromFormat('Y-m', $period)
            ->endOfMonth()
            ->toDateString();

        // Queries sin TenantScope: el tenant llega explícito como parámetro
        $families = Family::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->get();

        $services = Service::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($families as $family) {
            foreach ($services as $service) {
                // firstOrCreate aprovecha el UNIQUE(family_id, service_id, period)
                // wasRecentlyCreated distingue si fue creado o ya existía
                $billing = Billing::withoutGlobalScopes()->firstOrCreate(
                    [
                        'family_id'  => $family->id,
                        'service_id' => $service->id,
                        'period'     => $period,
                    ],
                    [
                        'tenant_id'    => $tenant->id,
                        'amount'       => $service->default_price,
                        'status'       => 'pending',
                        'due_date'     => $dueDate,
                        'generated_at' => now(),
                    ]
                );

                $billing->wasRecentlyCreated ? $created++ : $skipped++;
            }
        }

        Log::info('BillingGenerationService completado', [
            'tenant'  => $tenant->slug,
            'period'  => $period,
            'created' => $created,
            'skipped' => $skipped,
        ]);

        return [
            'tenant'  => $tenant->slug,
            'period'  => $period,
            'created' => $created,
            'skipped' => $skipped,
        ];
    }
}
