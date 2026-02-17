<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante #{{ $payment->id }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #1a1a1a;
            background: #fff;
            padding: 24px;
        }
        .header {
            border-bottom: 3px solid #1d4ed8;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .header-row {
            display: table;
            width: 100%;
        }
        .header-left { display: table-cell; vertical-align: top; }
        .header-right { display: table-cell; text-align: right; vertical-align: top; }
        .brand { font-size: 20px; font-weight: 700; color: #1d4ed8; }
        .tenant-name { font-size: 13px; font-weight: 600; color: #374151; margin-top: 2px; }
        .receipt-title { font-size: 18px; font-weight: 700; color: #1d4ed8; }
        .receipt-number { font-size: 11px; color: #6b7280; margin-top: 2px; }

        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 6px;
        }
        .badge-success { background: #d1fae5; color: #065f46; }

        .section { margin-bottom: 14px; }
        .section-title {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #6b7280;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }

        table.detail {
            width: 100%;
            border-collapse: collapse;
        }
        table.detail td {
            padding: 4px 2px;
            vertical-align: top;
        }
        table.detail td:first-child {
            color: #6b7280;
            width: 38%;
            font-weight: 500;
        }
        table.detail td:last-child {
            color: #111827;
            font-weight: 600;
        }

        .amount-box {
            background: #eff6ff;
            border: 2px solid #1d4ed8;
            border-radius: 8px;
            padding: 12px 16px;
            text-align: center;
            margin: 16px 0;
        }
        .amount-label { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.8px; }
        .amount-value { font-size: 28px; font-weight: 900; color: #1d4ed8; margin-top: 2px; }

        .footer {
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
            margin-top: 20px;
            text-align: center;
            color: #9ca3af;
            font-size: 9px;
        }
        .validity-note {
            background: #fefce8;
            border: 1px solid #fde68a;
            border-radius: 4px;
            padding: 6px 10px;
            font-size: 9px;
            color: #92400e;
            text-align: center;
        }
    </style>
</head>
<body>

    {{-- Encabezado --}}
    <div class="header">
        <div class="header-row">
            <div class="header-left">
                <div class="brand">CommunityERP</div>
                <div class="tenant-name">{{ $tenant->name }}</div>
            </div>
            <div class="header-right">
                <div class="receipt-title">COMPROBANTE DE PAGO</div>
                <div class="receipt-number">N° {{ str_pad($payment->id, 6, '0', STR_PAD_LEFT) }}</div>
                <div class="badge badge-success">✓ Pago Registrado</div>
            </div>
        </div>
    </div>

    {{-- Monto destacado --}}
    <div class="amount-box">
        <div class="amount-label">Monto Pagado</div>
        <div class="amount-value">${{ number_format($payment->amount, 2) }}</div>
    </div>

    {{-- Datos del pago --}}
    <div class="section">
        <div class="section-title">Datos del Pago</div>
        <table class="detail">
            <tr>
                <td>Fecha:</td>
                <td>{{ $payment->payment_date->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td>Método:</td>
                <td>
                    @switch($payment->payment_method)
                        @case('cash') Efectivo @break
                        @case('bank_transfer') Transferencia Bancaria @break
                        @case('mobile_payment') Pago Móvil @break
                        @default {{ $payment->payment_method }}
                    @endswitch
                </td>
            </tr>
            @if($payment->reference)
            <tr>
                <td>Referencia:</td>
                <td>{{ $payment->reference }}</td>
            </tr>
            @endif
            <tr>
                <td>Cobrador:</td>
                <td>{{ $payment->collector->name }}</td>
            </tr>
        </table>
    </div>

    {{-- Datos del servicio --}}
    <div class="section">
        <div class="section-title">Concepto</div>
        <table class="detail">
            <tr>
                <td>Servicio:</td>
                <td>{{ $service->name }}</td>
            </tr>
            <tr>
                <td>Período:</td>
                <td>{{ $billing->period }}</td>
            </tr>
            <tr>
                <td>Monto total deuda:</td>
                <td>${{ number_format($billing->amount, 2) }}</td>
            </tr>
            <tr>
                <td>Estado deuda:</td>
                <td>
                    @switch($billing->status)
                        @case('paid') Pagado completamente @break
                        @case('partial') Pago parcial @break
                        @default Pendiente @break
                    @endswitch
                </td>
            </tr>
        </table>
    </div>

    {{-- Datos del residente --}}
    <div class="section">
        <div class="section-title">Residente</div>
        <table class="detail">
            <tr>
                <td>Familia:</td>
                <td>{{ $family->name }}</td>
            </tr>
            <tr>
                <td>Inmueble:</td>
                <td>{{ $family->property->address }}</td>
            </tr>
            @if($family->property->sector)
            <tr>
                <td>Sector / Calle:</td>
                <td>{{ $family->property->sector->name }}</td>
            </tr>
            @endif
            <tr>
                <td>Comunidad:</td>
                <td>{{ $tenant->name }}</td>
            </tr>
        </table>
    </div>

    @if($payment->notes)
    <div class="section">
        <div class="section-title">Notas</div>
        <p style="color:#374151;">{{ $payment->notes }}</p>
    </div>
    @endif

    <div class="validity-note">
        Este comprobante es válido como constancia de pago. Consérvelo para su archivo.
    </div>

    <div class="footer">
        Generado el {{ now()->format('d/m/Y H:i') }} · CommunityERP · Documento digital
    </div>

</body>
</html>
