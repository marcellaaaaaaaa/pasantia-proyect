<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\Sector;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PropertyFactory extends Factory
{
    protected $model = Property::class;

    public function definition(): array
    {
        return [
            'tenant_id'   => Tenant::factory(),
            'sector_id'   => Sector::factory(),
            'address'     => fake()->streetAddress(),
            'type'        => 'house',
            'unit_number' => null,
        ];
    }

    public function apartment(): static
    {
        return $this->state([
            'type'        => 'apartment',
            'unit_number' => fake()->numerify('Apto ##'),
        ]);
    }
}
