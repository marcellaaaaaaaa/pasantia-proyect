<?php

namespace Database\Factories;

use App\Models\Jornada;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class JornadaFactory extends Factory
{
    protected $model = Jornada::class;

    public function definition(): array
    {
        return [
            'tenant_id'       => Tenant::factory(),
            'collector_id'    => User::factory(),
            'status'          => 'open',
            'opened_at'       => now(),
            'closed_at'       => null,
            'notes'           => null,
            'total_collected' => '0.00',
        ];
    }

    public function closed(): static
    {
        return $this->state([
            'status'    => 'closed',
            'closed_at' => now(),
        ]);
    }
}
