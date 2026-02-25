<?php

namespace App\Filament\Pages;

use App\Exports\MonthlyReportExport;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Maatwebsite\Excel\Facades\Excel;

/**
 * FIL-014 — Página de Reportes Mensuales.
 *
 * Permite al admin descargar el reporte de cobros y pagos del mes seleccionado
 * en formato Excel (.xlsx) con dos hojas: Cobros y Pagos.
 */
class MonthlyReportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Opciones';

    protected static ?string $navigationLabel = 'Reportes';

    protected static ?string $title = 'Reporte Mensual';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.monthly-report';

    public ?string $period = null;

    public function mount(): void
    {
        $this->period = now()->format('Y-m');
        $this->form->fill(['period' => $this->period]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('period')
                    ->label('Período (YYYY-MM)')
                    ->required()
                    ->regex('/^\d{4}-\d{2}$/')
                    ->helperText('Ejemplo: ' . now()->format('Y-m'))
                    ->default(now()->format('Y-m')),
            ])
            ->statePath('data');
    }

    public function exportExcel(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $data   = $this->form->getState();
        $period = $data['period'];
        $tenant = auth()->user()?->tenant;

        $filename = "reporte-{$period}" . ($tenant ? "-{$tenant->slug}" : '') . '.xlsx';

        Notification::make()
            ->success()
            ->title("Generando reporte para {$period}…")
            ->send();

        return Excel::download(
            new MonthlyReportExport($period, $tenant),
            $filename,
        );
    }
}
