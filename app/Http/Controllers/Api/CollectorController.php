<?php

namespace App\Http\Controllers\Api;

use App\Application\Billing\Commands\RegisterCollectionCommand;
use App\Application\Billing\Handlers\RegisterCollectionHandler;
use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\CollectionRound;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class CollectorController extends Controller
{
    public function dashboard(): Response
    {
        $collector = auth()->user();

        $sectorIds = $collector->assignedSectors()->pluck('sectors.id');

        $invoices = Invoice::with(['family.property', 'family.people' => fn ($q) => $q->where('is_primary_contact', true)])
            ->whereHas('family.property', fn ($q) => $q->whereIn('sector_id', $sectorIds))
            ->whereIn('status', ['pending', 'partial'])
            ->orderBy('due_date')
            ->paginate(30);

        $openRound = CollectionRound::where('collector_id', $collector->id)
            ->where('status', 'open')
            ->latest()
            ->first();

        return Inertia::render('Collector/Dashboard', compact('invoices', 'openRound'));
    }

    public function showBilling(Invoice $billing): Response
    {
        $billing->load(['family.property.sector', 'family.people', 'collections.collector', 'collectionRound']);

        return Inertia::render('Collector/Billing', ['invoice' => $billing]);
    }

    public function pay(Request $request, Invoice $billing): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'amount'        => ['required', 'numeric', 'min:0.01'],
            'currency'      => ['required', 'in:VED,USD'],
            'exchange_rate' => ['required_if:currency,VED', 'numeric', 'min:0.0001'],
            'method'        => ['required', 'in:cash,transfer,mobile_payment'],
            'reference'     => ['nullable', 'string', 'max:100'],
            'notes'         => ['nullable', 'string'],
        ]);

        $command = new RegisterCollectionCommand(
            invoice_id:    $billing->id,
            amount:        (float) $data['amount'],
            currency:      $data['currency'],
            exchange_rate: (float) ($data['exchange_rate'] ?? 1),
            method:        $data['method'],
            reference:     $data['reference'] ?? null,
            notes:         $data['notes'] ?? null,
            collector_id:  auth()->id(),
        );

        $collection = app(RegisterCollectionHandler::class)->handle($command);

        return redirect()
            ->route('collector.billing', $billing->id)
            ->with('receipt_url', URL::signedRoute('receipts.show', $collection, now()->addHours(48)));
    }

    public function jornadaPage(): Response
    {
        $collector = auth()->user();

        $rounds = CollectionRound::where('collector_id', $collector->id)
            ->with('sectors', 'services')
            ->latest()
            ->paginate(20);

        return Inertia::render('Collector/Jornadas', compact('rounds'));
    }

    public function openJornada(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'sector_ids' => ['required', 'array', 'min:1'],
            'sector_ids.*' => ['exists:sectors,id'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['exists:services,id'],
        ]);

        $round = CollectionRound::create([
            'tenant_id'    => auth()->user()->tenant_id,
            'collector_id' => auth()->id(),
            'name'         => $data['name'],
            'status'       => 'open',
            'opened_at'    => now(),
        ]);

        $round->sectors()->sync($data['sector_ids']);
        $round->services()->sync($data['service_ids']);

        return redirect()->route('collector.jornadas');
    }

    public function closeJornada(CollectionRound $jornada): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $jornada);

        $jornada->update([
            'status'    => 'closed',
            'closed_at' => now(),
            'total_collected_usd' => $jornada->invoices()
                ->join('collections', 'collections.invoice_id', '=', 'invoices.id')
                ->sum('collections.amount_usd'),
        ]);

        return redirect()->route('collector.jornadas');
    }

    public function sync(Request $request): \Illuminate\Http\JsonResponse
    {
        $payments = $request->validate([
            'payments'              => ['required', 'array'],
            'payments.*.invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'payments.*.amount'     => ['required', 'numeric', 'min:0.01'],
            'payments.*.currency'   => ['required', 'in:VED,USD'],
            'payments.*.exchange_rate' => ['required', 'numeric'],
            'payments.*.method'     => ['required', 'in:cash,transfer,mobile_payment'],
            'payments.*.reference'  => ['nullable', 'string'],
        ]);

        $results = [];
        foreach ($payments['payments'] as $payment) {
            try {
                $command = new RegisterCollectionCommand(
                    invoice_id:    $payment['invoice_id'],
                    amount:        (float) $payment['amount'],
                    currency:      $payment['currency'],
                    exchange_rate: (float) $payment['exchange_rate'],
                    method:        $payment['method'],
                    reference:     $payment['reference'] ?? null,
                    collector_id:  auth()->id(),
                );

                $collection    = app(RegisterCollectionHandler::class)->handle($command);
                $results[]     = ['invoice_id' => $payment['invoice_id'], 'status' => 'ok', 'collection_id' => $collection->id];
            } catch (\Throwable $e) {
                $results[] = ['invoice_id' => $payment['invoice_id'], 'status' => 'error', 'message' => $e->getMessage()];
            }
        }

        return response()->json(['results' => $results]);
    }
}
