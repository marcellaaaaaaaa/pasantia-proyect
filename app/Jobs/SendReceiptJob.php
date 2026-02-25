<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\ReceiptService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * JOB-002 — Genera la URL firmada del comprobante y marca el timestamp de envío.
 *
 * En producción este job enviaría el link por WhatsApp/email usando el contacto
 * principal de la familia. Por ahora registra el evento y actualiza receipt_sent_at.
 *
 * Queue: 'notifications'
 */
class SendReceiptJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public readonly int $paymentId,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(ReceiptService $service): void
    {
        $payment = Payment::withoutGlobalScopes()->with([
            'billing.lines.service',
            'billing.family.property.sector',
            'collector',
            'tenant',
        ])->findOrFail($this->paymentId);

        // Genera la URL firmada y marca receipt_sent_at
        $url = $service->markSent($payment);

        Log::info('SendReceiptJob: comprobante enviado', [
            'payment_id' => $payment->id,
            'family'     => $payment->billing->family->name ?? '—',
            'url'        => $url,
        ]);

        // TODO (producción): enviar $url por WhatsApp al contacto principal
        // $this->sendWhatsApp($payment->billing->family->primaryContact(), $url);
    }
}
