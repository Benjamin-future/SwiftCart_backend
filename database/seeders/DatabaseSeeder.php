<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // ✅ Benjamin: the ONLY Super Admin
        User::updateOrCreate(
            ['email' => 'benjaminmutua008@gmail.com'],
            [
                'name'              => 'Benjamin Mutua',
                'password'          => Hash::make('25432543'), // change in production
                'role'              => 'admin',       // super admin is also an admin
                'is_super_admin'    => true,          // <-- the special flag
                'email_verified_at' => now(),
            ]
        );

        // Domain seeders
        $this->call([
            CategorySeeder::class,
            ProductSeeder::class,
            BudgetSeeder::class,
            WalletSeeder::class,
            RiderSeeder::class,
        ]);
    }
}
