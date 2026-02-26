<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use App\Models\Jornada;
use App\Models\Wallet;
use App\Services\PaymentService;
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

        $billings = Billing::with(['family.property.sector', 'lines.service'])
            ->whereHas('family.property', fn ($q) => $q->whereIn('sector_id', $sectorIds))
            ->pending()
            ->orderBy('due_date')
            ->orderBy('id')
            ->get()
            ->map(fn ($b) => array_merge($b->toArray(), [
                'amount_paid'    => (float) $b->amount_paid,
                'amount_pending' => (float) $b->amount_pending,
            ]));

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $collector->id],
            ['tenant_id' => $collector->tenant_id, 'balance' => 0],
        );

        $activeJornada = Jornada::withoutGlobalScopes()
            ->where('collector_id', $collector->id)
            ->where('status', 'open')
            ->with('payments.billing.lines.service')
            ->first();

        return Inertia::render('collector/dashboard', [
            'billings'       => $billings,
            'wallet'         => $wallet,
            'activeJornada'  => $activeJornada,
        ]);
    }

    /**
     * Formulario de pago para un billing específico.
     */
    public function showBilling(Billing $billing): Response
    {
        $billing->load(['family.property.sector', 'lines.service', 'payments']);

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

        $collector = Auth::user();

        // Buscar jornada activa del cobrador
        $activeJornada = Jornada::withoutGlobalScopes()
            ->where('collector_id', $collector->id)
            ->where('status', 'open')
            ->first();

        try {
            $paymentService->register(
                billing:       $billing,
                collector:     $collector,
                amount:        (float) $validated['amount'],
                paymentMethod: $validated['payment_method'],
                reference:     $validated['reference'] ?? null,
                notes:         $validated['notes'] ?? null,
                jornada:       $activeJornada,
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

        // Buscar jornada activa del cobrador
        $activeJornada = Jornada::withoutGlobalScopes()
            ->where('collector_id', $collector->id)
            ->where('status', 'open')
            ->first();

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
                    jornada:       $activeJornada,
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
     * Página de jornadas del cobrador.
     */
    public function jornadaPage(): Response
    {
        $collector = Auth::user();

        $activeJornada = Jornada::withoutGlobalScopes()
            ->where('collector_id', $collector->id)
            ->where('status', 'open')
            ->with('payments.billing.lines.service', 'payments.billing.family')
            ->first();

        $pastJornadas = Jornada::withoutGlobalScopes()
            ->where('collector_id', $collector->id)
            ->where('status', 'closed')
            ->withCount('payments')
            ->orderByDesc('closed_at')
            ->take(20)
            ->get();

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $collector->id],
            ['tenant_id' => $collector->tenant_id, 'balance' => 0],
        );

        return Inertia::render('collector/jornadas', [
            'activeJornada' => $activeJornada,
            'pastJornadas'  => $pastJornadas,
            'wallet'        => $wallet,
        ]);
    }

    /**
     * Abre una nueva jornada de trabajo.
     */
    public function openJornada(): RedirectResponse
    {
        $collector = Auth::user();

        // Validar que no haya otra jornada abierta
        $existingOpen = Jornada::withoutGlobalScopes()
            ->where('collector_id', $collector->id)
            ->where('status', 'open')
            ->exists();

        if ($existingOpen) {
            return back()->withErrors(['general' => 'Ya tienes una jornada abierta.']);
        }

        $jornada = Jornada::create([
            'tenant_id'    => $collector->tenant_id,
            'collector_id' => $collector->id,
            'status'       => 'open',
            'opened_at'    => now(),
        ]);

        activity()
            ->causedBy($collector)
            ->performedOn($jornada)
            ->log('Jornada abierta');

        return redirect()->route('collector.jornadas')
            ->with('status', 'jornada-opened');
    }

    /**
     * Cierra una jornada de trabajo.
     */
    public function closeJornada(Request $request, Jornada $jornada): RedirectResponse
    {
        $collector = Auth::user();

        // Validar ownership
        if ((int) $jornada->collector_id !== (int) $collector->id) {
            abort(403, 'No puedes cerrar una jornada que no te pertenece.');
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $jornada->close($validated['notes'] ?? null);

            activity()
                ->causedBy($collector)
                ->performedOn($jornada)
                ->withProperties([
                    'total_collected' => $jornada->total_collected,
                    'payments_count'  => $jornada->payments()->count(),
                ])
                ->log('Jornada cerrada');

            return redirect()->route('collector.jornadas')
                ->with('status', 'jornada-closed');
        } catch (\LogicException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }
    }
}
