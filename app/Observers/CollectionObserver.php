<?php

namespace App\Observers;

use App\Models\Collection;
use App\Models\Invoice;

class CollectionObserver
{
    public function created(Collection $collection): void
    {
        $this->updateInvoice($collection->invoice);
    }

    public function updated(Collection $collection): void
    {
        $this->updateInvoice($collection->invoice);
        
        if ($collection->isDirty('invoice_id')) {
            $oldInvoice = Invoice::find($collection->getOriginal('invoice_id'));
            if ($oldInvoice) {
                $this->updateInvoice($oldInvoice);
            }
        }
    }

    public function deleted(Collection $collection): void
    {
        $this->updateInvoice($collection->invoice);
    }

    protected function updateInvoice(Invoice $invoice): void
    {
        $totalCollectedUsd = $invoice->collections()
            ->where('status', 'verified')
            ->sum('amount_usd');

        $invoice->collected_amount_usd = $totalCollectedUsd;

        if ($invoice->collected_amount_usd >= $invoice->amount_usd) {
            $invoice->status = 'collected';
        } elseif ($invoice->collected_amount_usd > 0) {
            $invoice->status = 'partial';
        } else {
            $invoice->status = 'pending';
        }

        $invoice->saveQuietly();
    }
}
