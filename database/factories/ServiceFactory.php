<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'tenant_id'     => Tenant::factory(),
            'name'          => fake()->randomElement(['Aseo Urbano', 'Vigilancia', 'Agua', 'Gas', 'Jardinería']),
            'default_price' => fake()->randomFloat(2, 20, 200),
            'is_active'     => true,
            'description'   => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
