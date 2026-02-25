<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\BillingLine;
use App\Models\Family;
use App\Models\Jornada;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Log;

class BillingGenerationService
{
    /**
     * Genera los billings de un tenant para un período dado.
     *
     * Crea 1 billing por familia×período con N billing_lines (una por servicio).
     * Idempotente: usa UNIQUE(family_id, period) y UNIQUE(billing_id, service_id).
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

        $families = Family::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->with(['services' => fn ($q) => $q->where('is_active', true)])
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($families as $family) {
            $familyServices = $family->services;

            if ($familyServices->isEmpty()) {
                continue;
            }

            $billing = Billing::withoutGlobalScopes()->firstOrCreate(
                [
                    'family_id' => $family->id,
                    'period'    => $period,
                ],
                [
                    'tenant_id'    => $tenant->id,
                    'amount'       => 0,
                    'status'       => 'pending',
                    'due_date'     => $dueDate,
                    'generated_at' => now(),
                ]
            );

            $billing->wasRecentlyCreated ? $created++ : $skipped++;

            foreach ($familyServices as $service) {
                BillingLine::firstOrCreate(
                    [
                        'billing_id' => $billing->id,
                        'service_id' => $service->id,
                    ],
                    [
                        'amount' => $service->default_price,
                    ]
                );
            }

            $billing->recalculateAmount();
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

    /**
     * Genera billings para una jornada:
     *  - Solo familias activas en los sectores de la jornada
     *  - Solo los servicios asignados a la jornada
     *  - Un billing por cada familia×mes entre opened_at y closed_at, con N líneas
     *
     * Idempotente: usa firstOrCreate con UNIQUE(family_id, period) y UNIQUE(billing_id, service_id).
     *
     * @return array{periods: list<string>, created: int, skipped: int}
     */
    public function generateForJornada(Jornada $jornada): array
    {
        $tenant = $jornada->tenant;

        // Familias activas en los sectores de la jornada (con sus servicios asignados)
        $sectorIds = $jornada->sectors()->pluck('sectors.id');

        $families = Family::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereHas('property', fn ($q) => $q->whereIn('sector_id', $sectorIds))
            ->with(['services' => fn ($q) => $q->where('is_active', true)])
            ->get();

        // Servicios asignados a la jornada
        $jornadaServiceIds = $jornada->services()->where('is_active', true)->pluck('services.id');

        // Períodos mensuales entre opened_at y closed_at
        $periods = collect(
            CarbonPeriod::create(
                $jornada->opened_at->startOfMonth(),
                '1 month',
                $jornada->closed_at->startOfMonth(),
            )
        )->map(fn ($date) => $date->format('Y-m'))->values();

        $created = 0;
        $skipped = 0;

        foreach ($periods as $period) {
            $dueDate = CarbonImmutable::createFromFormat('Y-m', $period)
                ->endOfMonth()
                ->toDateString();

            foreach ($families as $family) {
                // Intersectar servicios de la jornada con servicios de la familia
                $services = $family->services->filter(
                    fn ($s) => $jornadaServiceIds->contains($s->id)
                );

                if ($services->isEmpty()) {
                    continue;
                }

                $billing = Billing::withoutGlobalScopes()->firstOrCreate(
                    [
                        'family_id' => $family->id,
                        'period'    => $period,
                    ],
                    [
                        'tenant_id'    => $tenant->id,
                        'amount'       => 0,
                        'status'       => 'pending',
                        'due_date'     => $dueDate,
                        'generated_at' => now(),
                    ]
                );

                $billing->wasRecentlyCreated ? $created++ : $skipped++;

                foreach ($services as $service) {
                    BillingLine::firstOrCreate(
                        [
                            'billing_id' => $billing->id,
                            'service_id' => $service->id,
                        ],
                        [
                            'amount' => $service->default_price,
                        ]
                    );
                }

                $billing->recalculateAmount();
            }
        }

        Log::info('BillingGenerationService::generateForJornada completado', [
            'jornada_id' => $jornada->id,
            'tenant'     => $tenant->slug,
            'periods'    => $periods->toArray(),
            'families'   => $families->count(),
            'services'   => $services->count(),
            'created'    => $created,
            'skipped'    => $skipped,
        ]);

        return [
            'periods' => $periods->toArray(),
            'created' => $created,
            'skipped' => $skipped,
        ];
    }
}
