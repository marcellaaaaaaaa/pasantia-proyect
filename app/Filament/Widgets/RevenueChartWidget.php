<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use App\Models\Service;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/**
 * FIL-013 Widget 1 — Cobros del mes actual agrupados por servicio.
 *
 * Muestra un gráfico de barras con el monto total cobrado por cada servicio
 * en el mes en curso (solo pagos válidos: no reversed).
 */
class RevenueChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Cobros del Mes por Servicio';

    protected static ?string $description = 'Monto total recaudado este mes';

    protected static ?int $sort = 2;

    protected static string $color = 'info';

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $period = now()->format('Y-m');
        $tenant = auth()->user()?->tenant;

        $query = Payment::withoutGlobalScopes()
            ->join('billings', 'payments.billing_id', '=', 'billings.id')
            ->join('billing_lines', 'billings.id', '=', 'billing_lines.billing_id')
            ->join('services', 'billing_lines.service_id', '=', 'services.id')
            ->where('billings.period', $period)
            ->where('payments.status', '!=', 'reversed')
            ->select('services.name', DB::raw('SUM(billing_lines.amount) as total'))
            ->groupBy('services.id', 'services.name')
            ->orderByDesc('total');

        if ($tenant) {
            $query->where('payments.tenant_id', $tenant->id);
        }

        $rows = $query->get();

        return [
            'datasets' => [
                [
                    'label'           => 'Monto cobrado ($)',
                    'data'            => $rows->pluck('total')->map(fn ($v) => round($v, 2))->all(),
                    'backgroundColor' => [
                        '#3b82f6', '#10b981', '#f59e0b', '#ef4444',
                        '#8b5cf6', '#06b6d4', '#f97316', '#84cc16',
                    ],
                ],
            ],
            'labels' => $rows->pluck('name')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
