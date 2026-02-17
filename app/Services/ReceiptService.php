<?php

namespace App\Services;

use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\URL;

class ReceiptService
{
    /** Tiempo de expiración de la URL firmada (horas) */
    private const URL_EXPIRY_HOURS = 48;

    /**
     * Genera el PDF del comprobante de pago.
     *
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generate(Payment $payment)
    {
        $payment->loadMissing([
            'billing.service',
            'billing.family.property.sector',
            'collector',
            'tenant',
        ]);

        return Pdf::loadView('receipts.payment', [
            'payment' => $payment,
            'billing' => $payment->billing,
            'family'  => $payment->billing->family,
            'service' => $payment->billing->service,
            'tenant'  => $payment->tenant,
        ])->setPaper('a5', 'portrait');
    }

    /**
     * Genera una URL firmada (sin autenticación) para que el cobrador
     * la comparta por WhatsApp. Expira en 48 horas.
     */
    public function getSignedUrl(Payment $payment): string
    {
        return URL::temporarySignedRoute(
            'receipts.show',
            now()->addHours(self::URL_EXPIRY_HOURS),
            ['payment' => $payment->id],
        );
    }

    /**
     * Marca el timestamp de envío en el payment y genera la URL firmada.
     * Retorna la URL para que el llamador la use (WhatsApp, email, etc.)
     */
    public function markSent(Payment $payment): string
    {
        $url = $this->getSignedUrl($payment);

        $payment->update(['receipt_sent_at' => now()]);

        return $url;
    }
}
