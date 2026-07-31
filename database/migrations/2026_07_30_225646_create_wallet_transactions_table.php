<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('wallet_id')
                ->constrained('wallets')
                ->cascadeOnDelete();

            // Optional reference to track which order generated this transaction
            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();

            $table->string('type'); // Commission, Withdrawal, Admin Sale, etc.
            $table->string('description')->nullable();
            $table->string('vendor')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('completed');

            $table->timestamps();

            // Indexing for faster history & summary queries
            $table->index(['wallet_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};