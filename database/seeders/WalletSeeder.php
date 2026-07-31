<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WalletSeeder extends Seeder
{
    public function run(): void
    {
        if (User::count() === 0) {
            $this->command?->warn('No users found. Seed users first (or create one) before WalletSeeder.');
            return;
        }

        DB::transaction(function () {
            // Create/refresh a KES wallet for every user
            User::cursor()->each(function (User $user) {
                // KES 0.00 – 20,000.00 (integer math to avoid float issues)
                $starting = mt_rand(0, 2_000_000) / 100;

                Wallet::updateOrCreate(
                    ['user_id' => $user->id, 'currency' => 'KES'],
                    ['balance' => $starting]
                );
            });
        });

        $this->command?->info('Wallets seeded/upserted for all users.');
    }
}
