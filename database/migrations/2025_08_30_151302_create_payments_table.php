<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('provider')->default('mpesa');
            $t->decimal('amount', 12, 2)->default(0);
            $t->string('phone', 20)->nullable();
    
            $t->string('status')->default('pending'); // pending|success|failed
    
            // STK meta
            $t->string('merchant_request_id')->nullable();
            $t->string('checkout_request_id')->nullable();
            $t->string('mpesa_receipt')->nullable();
            $t->string('result_code')->nullable();
            $t->string('result_desc')->nullable();
    
            $t->json('raw_callback')->nullable();
            $t->timestamps();
    
            $t->index(['checkout_request_id']);
        });
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
