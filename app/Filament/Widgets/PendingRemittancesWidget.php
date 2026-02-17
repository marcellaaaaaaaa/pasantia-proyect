<?php

namespace App\Filament\Widgets;

use App\Models\Remittance;
use App\Models\Wallet;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * FIL-013 Widget 3 — Alertas de remesas y wallets pendientes.
 *
 * Muestra:
 *   - Remesas pendientes de revisión (submitted)
 *   - Cobradores con saldo en wallet > $0 sin liquidar
 *   - Total acumulado en wallets (dinero en campo sin liquidar)
 */
class PendingRemittancesWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $tenant = auth()->user()?->tenant;

        // Remesas pendientes de revisión
        $submittedQuery = Remittance::withoutGlobalScopes()->where('status', 'submitted');
        if ($tenant) {
            $submittedQuery->where('tenant_id', $tenant->id);
        }
        $submittedCount = $submittedQuery->count();

        // Wallets con saldo > 0 (dinero sin liquidar en campo)
        $walletsQuery = Wallet::withoutGlobalScopes()->where('balance', '>', 0);
        if ($tenant) {
            $walletsQuery->where('tenant_id', $tenant->id);
        }
        $walletsWithBalance = $walletsQuery->count();
        $totalInField       = (float) $walletsQuery->sum('balance');

        // Remesas en draft (creadas pero no enviadas aún)
        $draftQuery = Remittance::withoutGlobalScopes()->where('status', 'draft');
        if ($tenant) {
            $draftQuery->where('tenant_id', $tenant->id);
        }
        $draftCount = $draftQuery->count();

        return [
            Stat::make('Remesas Pendientes de Revisión', $submittedCount)
                ->description('Enviadas por cobradores, esperando aprobación')
                ->descriptionIcon('heroicon-o-clock')
                ->color($submittedCount > 0 ? 'warning' : 'success')
                ->chart([0, $submittedCount]),

            Stat::make('Cobradores con Saldo', $walletsWithBalance)
                ->description('Cobradores con dinero en campo sin liquidar')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color($walletsWithBalance > 0 ? 'info' : 'success'),

            Stat::make('Total en Campo', '$' . number_format($totalInField, 2))
                ->description('Dinero acumulado en wallets sin liquidar')
                ->descriptionIcon('heroicon-o-currency-dollar')
                ->color($totalInField > 1000 ? 'danger' : ($totalInField > 0 ? 'warning' : 'success')),

            Stat::make('Remesas en Borrador', $draftCount)
                ->description('Creadas pero no enviadas al admin aún')
                ->descriptionIcon('heroicon-o-document')
                ->color('gray'),
        ];
    }
}
