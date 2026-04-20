<?php

namespace App\Services;

use App\Models\Collection;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

class ReceiptService
{
    /**
     * Genera el PDF del comprobante de cobro.
     * Llama a ->stream() sobre el resultado para descarga inline.
     */
    public function generate(Collection $collection): DomPdf
    {
        $collection->loadMissing([
            'invoice.family.property.sector',
            'invoice.family.people' => fn ($q) => $q->where('is_primary_contact', true),
            'collector',
        ]);

        return Pdf::loadView('receipts.collection', ['collection' => $collection])
            ->setPaper([0, 0, 226.77, 453.54]); // 80mm × 160mm (ticket térmico)
    }
}
