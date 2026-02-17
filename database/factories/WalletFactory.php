<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id'   => User::factory(),
            'balance'   => '0.00',
        ];
    }

    public function withBalance(float $amount): static
    {
        return $this->state(['balance' => number_format($amount, 2, '.', '')]);
    }
}
