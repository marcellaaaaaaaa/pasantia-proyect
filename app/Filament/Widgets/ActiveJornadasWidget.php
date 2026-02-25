<?php

namespace App\Filament\Widgets;

use App\Models\Jornada;
use App\Models\Wallet;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ActiveJornadasWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $activeCount = Jornada::where('status', 'open')->count();

        $activeTotal = Jornada::where('status', 'open')
            ->sum('total_collected');

        $closedToday = Jornada::where('status', 'closed')
            ->whereDate('closed_at', today())
            ->count();

        $walletsTotal = Wallet::sum('balance');

        return [
            Stat::make('Jornadas activas', $activeCount)
                ->description('Cobradores en campo')
                ->color($activeCount > 0 ? 'warning' : 'gray')
                ->icon('heroicon-o-clock'),

            Stat::make('Cobrado en jornadas activas', '$' . number_format((float) $activeTotal, 2))
                ->description('Acumulado en sesiones abiertas')
                ->color('info')
                ->icon('heroicon-o-banknotes'),

            Stat::make('Jornadas cerradas hoy', $closedToday)
                ->description('Sesiones completadas')
                ->color('success')
                ->icon('heroicon-o-check-circle'),

            Stat::make('Total acumulado en wallets', '$' . number_format((float) $walletsTotal, 2))
                ->description('Histórico de todos los cobradores')
                ->color('primary')
                ->icon('heroicon-o-wallet'),
        ];
    }
}
