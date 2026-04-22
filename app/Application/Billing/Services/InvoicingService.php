<?php

namespace App\Application\Billing\Services;

use App\Models\CollectionRound;
use App\Models\Family;
use App\Models\Invoice;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class InvoicingService
{
    /**
     * Genera una factura mensual por familia activa que tenga servicios fijos asignados.
     * Usa period_month como clave única para evitar duplicados aunque el job corra más de una vez.
     *
     * @return int Número de facturas creadas
     */
    public function generateMonthlyInvoices(Tenant $tenant, ?string $period = null): int
    {
        $period = $period ?: now()->format('Y-m');
        $dueDate = CarbonImmutable::createFromFormat('Y-m', $period)->endOfMonth()->toDateString();
        $created = 0;

        $families = Family::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->with('services', 'exoneratedServices')
            ->get();

        DB::transaction(function () use ($tenant, $period, $dueDate, $families, &$created) {
            foreach ($families as $family) {
                if ($family->is_exonerated && $family->exoneratedServices->isEmpty()) {
                    continue;
                }

                $exoneratedIds = $family->is_exonerated
                    ? $family->exoneratedServices->pluck('id')
                    : collect();

                $amountUsd = $family->services
                    ->where('is_active', true)
                    ->where('type', 'fixed')
                    ->whereNotIn('id', $exoneratedIds)
                    ->sum('default_price_usd');

                if ($amountUsd <= 0) {
                    continue;
                }

                // Deduplicación robusta por period_month, sin depender del texto
                $alreadyExists = Invoice::where('tenant_id', $tenant->id)
                    ->where('family_id', $family->id)
                    ->where('period_month', $period)
                    ->exists();

                if ($alreadyExists) {
                    continue;
                }

                Invoice::create([
                    'tenant_id'    => $tenant->id,
                    'family_id'    => $family->id,
                    'description'  => "Servicios {$period}",
                    'period_month' => $period,
                    'amount_usd'   => $amountUsd,
                    'status'       => 'pending',
                    'due_date'     => $dueDate,
                ]);

                $created++;
            }
        });

        return $created;
    }

    /**
     * Genera facturas para todas las familias activas de los sectores de una jornada.
     * Las jornadas no tienen period_month; la unicidad la garantiza que solo se
     * puede generar facturas una vez por jornada (el botón se oculta tras generarlas).
     *
     * @return int Número de facturas creadas
     * @throws \DomainException Si la jornada no tiene sectores o servicios configurados
     */
    public function generateInvoicesForRound(CollectionRound $round): int
    {
        $services = $round->services->where('is_active', true)->where('type', 'jornada');

        if ($services->isEmpty()) {
            throw new \DomainException('La jornada no tiene servicios activos configurados.');
        }

        $sectorIds = $round->sectors->pluck('id');

        if ($sectorIds->isEmpty()) {
            throw new \DomainException('La jornada no tiene sectores asignados.');
        }

        $families = Family::where('is_active', true)
            ->whereHas('property', fn ($q) => $q->whereIn('sector_id', $sectorIds))
            ->where('tenant_id', $round->tenant_id)
            ->with('exoneratedServices')
            ->get();

        $created = 0;

        DB::transaction(function () use ($round, $families, $services, &$created) {
            foreach ($families as $family) {
                if ($family->is_exonerated && $family->exoneratedServices->isEmpty()) {
                    continue;
                }

                $exoneratedIds = $family->is_exonerated
                    ? $family->exoneratedServices->pluck('id')
                    : collect();

                $amountUsd = $services->whereNotIn('id', $exoneratedIds)->sum('default_price_usd');

                if ($amountUsd <= 0) {
                    continue;
                }

                // Evitar duplicados si se llama accidentalmente dos veces
                $alreadyExists = Invoice::where('collection_round_id', $round->id)
                    ->where('family_id', $family->id)
                    ->exists();

                if ($alreadyExists) {
                    continue;
                }

                Invoice::create([
                    'tenant_id'           => $round->tenant_id,
                    'family_id'           => $family->id,
                    'collection_round_id' => $round->id,
                    'description'         => $round->name,
                    'amount_usd'          => $amountUsd,
                    'status'              => 'pending',
                    'due_date'            => now()->toDateString(),
                ]);

                $created++;
            }
        });

        return $created;
    }
}
