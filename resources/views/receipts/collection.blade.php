<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #111; padding: 8px; }
        .center { text-align: center; }
        .bold   { font-weight: bold; }
        .divider { border-top: 1px dashed #555; margin: 6px 0; }
        .row { display: flex; justify-content: space-between; margin: 3px 0; }
        .label { color: #555; }
        .status { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; }
        .status-collected { background: #d1fae5; color: #065f46; }
        .status-partial    { background: #fef3c7; color: #92400e; }
    </style>
</head>
<body>

    <div class="center bold" style="font-size: 13px; margin-bottom: 4px;">
        {{ $collection->invoice?->family?->property?->sector?->tenant?->name ?? 'Comunidad' }}
    </div>
    <div class="center" style="font-size: 9px; margin-bottom: 2px;">
        {{ $collection->invoice?->family?->property?->sector?->name ?? '' }}
    </div>
    <div class="center" style="font-size: 9px; color: #555;">COMPROBANTE DE COBRO</div>

    <div class="divider"></div>

    <div class="row">
        <span class="label">Comprobante #</span>
        <span class="bold">{{ $collection->id }}</span>
    </div>
    <div class="row">
        <span class="label">Fecha</span>
        <span>{{ $collection->collected_at->format('d/m/Y') }}</span>
    </div>
    <div class="row">
        <span class="label">Cobrador</span>
        <span>{{ $collection->collector?->name ?? '—' }}</span>
    </div>

    <div class="divider"></div>

    <div class="row">
        <span class="label">Familia</span>
        <span class="bold">{{ $collection->invoice?->family?->name ?? '—' }}</span>
    </div>
    <div class="row">
        <span class="label">Dirección</span>
        <span>{{ $collection->invoice?->family?->property?->address ?? '—' }}</span>
    </div>
    @php
        $contact = $collection->invoice?->family?->people?->first();
    @endphp
    @if($contact)
    <div class="row">
        <span class="label">Titular</span>
        <span>{{ $contact->full_name }}</span>
    </div>
    <div class="row">
        <span class="label">Cédula</span>
        <span>{{ $contact->id_number ?? '—' }}</span>
    </div>
    @endif

    <div class="divider"></div>

    <div class="row">
        <span class="label">Factura</span>
        <span>{{ $collection->invoice?->description ?? '—' }}</span>
    </div>
    <div class="row">
        <span class="label">Monto factura</span>
        <span>$ {{ number_format($collection->invoice?->amount_usd ?? 0, 2) }}</span>
    </div>

    <div class="divider"></div>

    <div class="row bold">
        <span>MONTO COBRADO</span>
        <span>{{ number_format($collection->amount, 2) }} {{ $collection->currency }}</span>
    </div>
    @if($collection->currency !== 'USD')
    <div class="row">
        <span class="label">Equivalente USD</span>
        <span>$ {{ number_format($collection->amount_usd, 2) }}</span>
    </div>
    <div class="row">
        <span class="label">Tasa aplicada</span>
        <span>{{ number_format($collection->exchange_rate, 2) }}</span>
    </div>
    @endif

    <div class="row">
        <span class="label">Método</span>
        <span>{{ match($collection->method) {
            'cash'           => 'Efectivo',
            'transfer'       => 'Transferencia',
            'mobile_payment' => 'Pago Móvil',
            default          => $collection->method,
        } }}</span>
    </div>
    @if($collection->reference)
    <div class="row">
        <span class="label">Referencia</span>
        <span>{{ $collection->reference }}</span>
    </div>
    @endif

    <div class="divider"></div>

    <div class="row">
        <span class="label">Estado factura</span>
        @php $status = $collection->invoice?->status @endphp
        <span class="status {{ $status === 'collected' ? 'status-collected' : 'status-partial' }}">
            {{ match($status) {
                'collected' => 'SOLVENTE',
                'partial'   => 'PAGO PARCIAL',
                'pending'   => 'PENDIENTE',
                default     => strtoupper($status ?? ''),
            } }}
        </span>
    </div>
    @if($collection->invoice && $collection->invoice->status !== 'collected')
    <div class="row">
        <span class="label">Saldo restante</span>
        <span class="bold">$ {{ number_format($collection->invoice->balance_usd, 2) }}</span>
    </div>
    @endif

    <div class="divider"></div>

    <div class="center" style="font-size: 8px; color: #888; margin-top: 4px;">
        Generado el {{ now()->format('d/m/Y H:i') }}<br>
        Este documento es su comprobante oficial de pago.
    </div>

</body>
</html>
