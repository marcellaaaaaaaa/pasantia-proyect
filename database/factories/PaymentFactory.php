<?php

namespace Database\Factories;

use App\Models\Billing;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'tenant_id'      => Tenant::factory(),
            'billing_id'     => Billing::factory(),
            'collector_id'   => User::factory(),
            'jornada_id'     => null,
            'amount'         => fake()->randomFloat(2, 10, 200),
            'payment_method' => 'cash',
            'status'         => 'paid',
            'reference'      => null,
            'payment_date'   => now()->toDateString(),
            'notes'          => null,
        ];
    }
}
