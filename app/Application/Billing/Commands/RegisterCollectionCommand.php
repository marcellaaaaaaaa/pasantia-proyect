<?php

namespace App\Application\Billing\Commands;

class RegisterCollectionCommand
{
    public function __construct(
        public int $invoice_id,
        public float $amount,
        public string $currency,
        public float $exchange_rate,
        public string $method,
        public ?string $reference = null,
        public ?string $notes = null,
        public ?int $collector_id = null,
    ) {}
}
