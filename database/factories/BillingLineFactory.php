<?php

namespace Database\Factories;

use App\Models\Billing;
use App\Models\BillingLine;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillingLineFactory extends Factory
{
    protected $model = BillingLine::class;

    public function definition(): array
    {
        return [
            'billing_id' => Billing::factory(),
            'service_id' => Service::factory(),
            'amount'     => fake()->randomFloat(2, 10, 100),
        ];
    }
}
