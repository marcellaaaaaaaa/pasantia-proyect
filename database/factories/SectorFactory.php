<?php

namespace Database\Factories;

use App\Models\Sector;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class SectorFactory extends Factory
{
    protected $model = Sector::class;

    public function definition(): array
    {
        return [
            'tenant_id'   => Tenant::factory(),
            'name'        => fake()->streetName(),
            'description' => null,
        ];
    }
}
