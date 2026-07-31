<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();

            // Foreign key to users table with cascade delete
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('currency', 3)->default('KES');
            $table->decimal('balance', 14, 2)->default(0.00);

            $table->timestamps();

            // Prevent duplicate wallets for the same currency per user
            $table->unique(['user_id', 'currency']);
            $table->index('currency');
        });

        // Add non-negative constraint if supported by the database engine
        try {
            DB::statement('ALTER TABLE wallets ADD CONSTRAINT chk_wallet_balance_nonnegative CHECK (balance >= 0)');
        } catch (\Throwable $e) {
            // Silently ignore on older MySQL versions that ignore CHECK constraints
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};