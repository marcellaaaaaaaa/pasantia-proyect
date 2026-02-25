<?php

namespace App\Exports;

use App\Models\Billing;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * FIL-014 — Exportador de reporte mensual a Excel.
 *
 * Genera un Excel con dos hojas:
 *   1. "Cobros" — todos los billings del período con su estado
 *   2. "Pagos" — todos los pagos del período con datos del cobrador
 */
class MonthlyReportExport implements WithMultipleSheets
{
    public function __construct(
        private readonly string $period,
        private readonly ?Tenant $tenant = null,
    ) {}

    public function sheets(): array
    {
        return [
            new BillingsSheet($this->period, $this->tenant),
            new PaymentsSheet($this->period, $this->tenant),
        ];
    }
}

// ── Hoja 1: Cobros ─────────────────────────────────────────────────────────

class BillingsSheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles, WithColumnFormatting
{
    public function __construct(
        private readonly string $period,
        private readonly ?Tenant $tenant = null,
    ) {}

    public function title(): string
    {
        return 'Cobros';
    }

    public function collection()
    {
        $query = Billing::withoutGlobalScopes()
            ->with(['family.property.sector', 'lines.service'])
            ->where('period', $this->period);

        if ($this->tenant) {
            $query->where('tenant_id', $this->tenant->id);
        }

        return $query->orderBy('id')->get()->map(fn (Billing $b) => [
            'ID'          => $b->id,
            'Familia'     => $b->family?->name ?? '—',
            'Inmueble'    => $b->family?->property?->address ?? '—',
            'Sector'      => $b->family?->property?->sector?->name ?? '—',
            'Servicio'    => $b->lines->map(fn ($l) => $l->service?->name)->filter()->join(', ') ?: '—',
            'Período'     => $b->period,
            'Monto'       => (float) $b->amount,
            'Estado'      => match ($b->status) {
                'pending'   => 'Pendiente',
                'paid'      => 'Cobrado',
                'cancelled' => 'Cancelado',
                'void'      => 'Anulado',
                default     => $b->status,
            },
            'Vencimiento' => $b->due_date?->format('d/m/Y') ?? '—',
        ]);
    }

    public function headings(): array
    {
        return ['ID', 'Familia', 'Inmueble', 'Sector', 'Servicio', 'Período', 'Monto', 'Estado', 'Vencimiento'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFD1E8FF']]],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'G' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Monto
        ];
    }
}

// ── Hoja 2: Pagos ──────────────────────────────────────────────────────────

class PaymentsSheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles, WithColumnFormatting
{
    public function __construct(
        private readonly string $period,
        private readonly ?Tenant $tenant = null,
    ) {}

    public function title(): string
    {
        return 'Pagos';
    }

    public function collection()
    {
        $query = Payment::withoutGlobalScopes()
            ->with(['billing.lines.service', 'billing.family', 'collector'])
            ->join('billings', 'payments.billing_id', '=', 'billings.id')
            ->where('billings.period', $this->period)
            ->where('payments.status', '!=', 'reversed')
            ->select('payments.*');

        if ($this->tenant) {
            $query->where('payments.tenant_id', $this->tenant->id);
        }

        return $query->orderBy('payments.payment_date')->get()->map(fn (Payment $p) => [
            'ID Pago'         => $p->id,
            'Fecha'           => $p->payment_date?->format('d/m/Y') ?? '—',
            'Familia'         => $p->billing?->family?->name ?? '—',
            'Servicio'        => $p->billing?->lines?->map(fn ($l) => $l->service?->name)->filter()->join(', ') ?: '—',
            'Cobrador'        => $p->collector?->name ?? '—',
            'Monto'           => (float) $p->amount,
            'Método'          => match ($p->payment_method) {
                'cash'           => 'Efectivo',
                'bank_transfer'  => 'Transferencia',
                'mobile_payment' => 'Pago Móvil',
                default          => $p->payment_method,
            },
            'Estado'          => match ($p->status) {
                'paid'    => 'Pagado',
                default   => $p->status,
            },
            'Referencia'      => $p->reference ?? '—',
        ]);
    }

    public function headings(): array
    {
        return ['ID Pago', 'Fecha', 'Familia', 'Servicio', 'Cobrador', 'Monto', 'Método', 'Estado', 'Referencia'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFD1FAE5']]],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'F' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Monto
        ];
    }
}
