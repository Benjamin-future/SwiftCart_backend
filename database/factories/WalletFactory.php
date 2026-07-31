<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    public function definition(): array
    {
        return [
            'user_id'  => User::factory(),
            'currency' => $this->faker->randomElement(['KES','USD','EUR']),
            'balance'  => $this->faker->randomFloat(2, 0, 50000),
        ];
    }
}

