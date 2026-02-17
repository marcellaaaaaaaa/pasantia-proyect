<?php

namespace Database\Factories;

use App\Models\Family;
use App\Models\Property;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class FamilyFactory extends Factory
{
    protected $model = Family::class;

    public function definition(): array
    {
        return [
            'tenant_id'   => Tenant::factory(),
            'property_id' => Property::factory(),
            'name'        => 'Familia ' . fake()->lastName(),
            'is_active'   => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
