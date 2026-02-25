<?php

namespace Database\Factories;

use App\Models\Billing;
use App\Models\Family;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillingFactory extends Factory
{
    protected $model = Billing::class;

    public function definition(): array
    {
        $period = CarbonImmutable::now()->format('Y-m');

        return [
            'tenant_id'    => Tenant::factory(),
            'family_id'    => Family::factory(),
            'period'       => $period,
            'amount'       => fake()->randomFloat(2, 20, 300),
            'status'       => 'pending',
            'due_date'     => CarbonImmutable::createFromFormat('Y-m', $period)
                                ->endOfMonth()->toDateString(),
            'generated_at' => now(),
        ];
    }

    public function paid(): static
    {
        return $this->state(['status' => 'paid']);
    }

    public function forPeriod(string $period): static
    {
        return $this->state([
            'period'   => $period,
            'due_date' => CarbonImmutable::createFromFormat('Y-m', $period)
                            ->endOfMonth()->toDateString(),
        ]);
    }
}
