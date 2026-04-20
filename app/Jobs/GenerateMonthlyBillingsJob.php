<?php

namespace App\Jobs;

use App\Application\Billing\Services\InvoicingService;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateMonthlyBillingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public readonly string $period) {}

    public function handle(InvoicingService $invoicingService): void
    {
        $tenants = Tenant::where('status', 'active')->get();

        foreach ($tenants as $tenant) {
            try {
                $count = $invoicingService->generateMonthlyInvoices($tenant, $this->period);
                Log::info("GenerateMonthlyBillings: tenant={$tenant->id} period={$this->period} created={$count}");
            } catch (\Throwable $e) {
                Log::error("GenerateMonthlyBillings: fallo en tenant={$tenant->id}", [
                    'period' => $this->period,
                    'error'  => $e->getMessage(),
                ]);
            }
        }
    }
}
