<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use App\Models\Payment;
use App\Models\Remittance;
use App\Models\Wallet;
use App\Services\PaymentService;
use App\Services\RemittanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CollectorController extends Controller
{
    /**
     * Dashboard del cobrador: lista deudas pendientes de su sector.
     */
    public function dashboard(): Response
    {
        $collector = Auth::user();
        $sectorIds = $collector->sectors()->pluck('sectors.id');

        $billings = Billing::with(['family.property.sector', 'service'])
            ->whereHas('family.property', fn ($q) => $q->whereIn('sector_id', $sectorIds))
            ->pending()
            ->orderBy('due_date')
            ->get()
            ->map(fn ($b) => array_merge($b->toArray(), [
                'amount_paid'    => (float) $b->amount_paid,
                'amount_pending' => (float) $b->amount_pending,
            ]));

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $collector->id],
            ['tenant_id' => $collector->tenant_id, 'balance' => 0],
        );

        $pendingPaymentsCount = Payment::withoutGlobalScopes()
            ->where('collector_id', $collector->id)
            ->where('status', 'pending_remittance')
            ->whereNotIn('id', fn ($q) => $q->select('payment_id')->from('remittance_payments'))
            ->count();

        return Inertia::render('collector/dashboard', [
            'billings'             => $billings,
            'wallet'               => $wallet,
            'pendingPaymentsCount' => $pendingPaymentsCount,
        ]);
    }

    /**
     * Formulario de pago para un billing específico.
     */
    public function showBilling(Billing $billing): Response
    {
        $billing->load(['family.property.sector', 'service', 'payments']);

        return Inertia::render('collector/payment-form', [
            'billing' => array_merge($billing->toArray(), [
                'amount_paid'    => (float) $billing->amount_paid,
                'amount_pending' => (float) $billing->amount_pending,
            ]),
        ]);
    }

    /**
     * Registra un pago online desde el formulario del cobrador.
     * POST /collector/billing/{billing}
     */
    public function pay(Billing $billing, Request $request, PaymentService $paymentService): RedirectResponse
    {
        $validated = $request->validate([
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,bank_transfer,mobile_payment',
            'reference'      => 'nullable|string|max:100',
            'notes'          => 'nullable|string|max:500',
        ]);

        try {
            $paymentService->register(
                billing:       $billing,
                collector:     Auth::user(),
                amount:        (float) $validated['amount'],
                paymentMethod: $validated['payment_method'],
                reference:     $validated['reference'] ?? null,
                notes:         $validated['notes'] ?? null,
            );

            return redirect()->route('collector.dashboard')
                ->with('status', 'payment-registered');
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }
    }

    /**
     * Sincronización de pagos tomados offline.
     * POST /api/collector/payments/sync
     */
    public function sync(Request $request, PaymentService $paymentService): JsonResponse
    {
        $validated = $request->validate([
            'payments'                  => 'required|array|min:1',
            'payments.*.offline_id'     => 'required|string',
            'payments.*.billing_id'     => 'required|integer|exists:billings,id',
            'payments.*.amount'         => 'required|numeric|min:0.01',
            'payments.*.payment_method' => 'required|in:cash,bank_transfer,mobile_payment',
            'payments.*.reference'      => 'nullable|string|max:100',
            'payments.*.notes'          => 'nullable|string|max:500',
        ]);

        $collector = Auth::user();
        $results   = [];

        foreach ($validated['payments'] as $paymentData) {
            try {
                $billing = Billing::findOrFail($paymentData['billing_id']);

                // R-8: verificar que el billing sigue siendo pagable (conflicto offline)
                if (in_array($billing->status, ['paid', 'cancelled', 'void'])) {
                    $results[] = [
                        'offline_id' => $paymentData['offline_id'],
                        'status'     => 'conflict',
                        'message'    => "El billing #{$billing->id} ya fue pagado o cancelado.",
                    ];
                    continue;
                }

                $payment = $paymentService->register(
                    billing:       $billing,
                    collector:     $collector,
                    amount:        (float) $paymentData['amount'],
                    paymentMethod: $paymentData['payment_method'],
                    reference:     $paymentData['reference'] ?? null,
                    notes:         $paymentData['notes'] ?? null,
                );

                $results[] = [
                    'offline_id' => $paymentData['offline_id'],
                    'status'     => 'synced',
                    'payment_id' => $payment->id,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'offline_id' => $paymentData['offline_id'],
                    'status'     => 'error',
                    'message'    => $e->getMessage(),
                ];
            }
        }

        return response()->json(['results' => $results]);
    }

    /**
     * Página de remesas del cobrador.
     */
    public function remittancePage(): Response
    {
        $collector = Auth::user();

        $pendingPayments = Payment::withoutGlobalScopes()
            ->with(['billing.service', 'billing.family'])
            ->where('collector_id', $collector->id)
            ->where('status', 'pending_remittance')
            ->whereNotIn('id', fn ($q) => $q->select('payment_id')->from('remittance_payments'))
            ->orderByDesc('created_at')
            ->get();

        $remittances = Remittance::withoutGlobalScopes()
            ->where('collector_id', $collector->id)
            ->with(['payments.billing.service'])
            ->orderByDesc('created_at')
            ->take(10)
            ->get();

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $collector->id],
            ['tenant_id' => $collector->tenant_id, 'balance' => 0],
        );

        return Inertia::render('collector/remittance', [
            'pendingPayments' => $pendingPayments,
            'remittances'     => $remittances,
            'wallet'          => $wallet,
        ]);
    }

    /**
     * Crea y envía una nueva remesa con todos los pagos pending del cobrador.
     */
    public function createRemittance(
        Request $request,
        RemittanceService $remittanceService,
    ): RedirectResponse {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $collector = Auth::user();

        try {
            $remittance = $remittanceService->create($collector, $validated['notes'] ?? null);
            $remittanceService->submit($remittance, $validated['notes'] ?? null);

            return redirect()->route('collector.remittance')
                ->with('status', 'remittance-submitted');
        } catch (\LogicException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }
    }
}
