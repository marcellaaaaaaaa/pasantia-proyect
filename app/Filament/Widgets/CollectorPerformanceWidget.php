<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

/**
 * FIL-013 Widget 2 — Rendimiento de cobradores en el mes actual.
 *
 * Tabla que muestra para cada cobrador:
 *   - Cantidad de pagos registrados en el mes
 *   - Total recaudado
 *   - Saldo en wallet (pendiente de liquidar)
 */
class CollectorPerformanceWidget extends BaseWidget
{
    protected static ?string $heading = 'Rendimiento de Cobradores (Mes Actual)';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $period = now()->format('Y-m');
        $tenant = auth()->user()?->tenant;

        $query = Payment::withoutGlobalScopes()
            ->join('billings', 'payments.billing_id', '=', 'billings.id')
            ->join('users', 'payments.collector_id', '=', 'users.id')
            ->leftJoin('wallets', 'wallets.user_id', '=', 'users.id')
            ->where('billings.period', $period)
            ->where('payments.status', '!=', 'reversed')
            ->select(
                'users.id',
                'users.name as collector_name',
                DB::raw('COUNT(payments.id) as payment_count'),
                DB::raw('SUM(payments.amount) as total_amount'),
                DB::raw('COALESCE(wallets.balance, 0) as wallet_balance'),
            )
            ->groupBy('users.id', 'users.name', 'wallets.balance')
            ->orderByDesc('total_amount');

        if ($tenant) {
            $query->where('payments.tenant_id', $tenant->id);
        }

        return $table
            ->query(fn () => $query)
            ->columns([
                Tables\Columns\TextColumn::make('collector_name')
                    ->label('Cobrador'),

                Tables\Columns\TextColumn::make('payment_count')
                    ->label('Pagos del Mes')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Cobrado')
                    ->formatStateUsing(fn ($state) => '$' . number_format($state, 2))
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('wallet_balance')
                    ->label('Saldo en Wallet')
                    ->formatStateUsing(fn ($state) => '$' . number_format($state, 2))
                    ->color(fn ($state): string => match (true) {
                        $state <= 0    => 'gray',
                        $state < 100   => 'success',
                        $state < 500   => 'warning',
                        default        => 'danger',
                    })
                    ->alignEnd(),
            ])
            ->paginated(false);
    }
}
