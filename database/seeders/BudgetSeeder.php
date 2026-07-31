<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Budget;
use Illuminate\Database\Seeder;

class BudgetSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure some users exist (optional safety)
        if (User::count() === 0) {
            User::factory()->count(5)->create();
        }

        // Create or update a budget for each user
        User::all()->each(function (User $user) {
            Budget::updateOrCreate(
                ['user_id' => $user->id],
                ['amount'  => fake()->randomFloat(2, 0, 50000)]
            );
        });

        // Optionally, set a known budget for a specific user:
        // Budget::updateOrCreate(
        //     ['user_id' => 1],
        //     ['amount'  => 15000.00]
        // );
    }
}


