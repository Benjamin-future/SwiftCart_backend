<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('business_name');
            $table->string('business_email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mpesa_number')->nullable();
            $table->string('address')->nullable();
            $table->string('id_number')->nullable();
            $table->string('kra_pin')->nullable();
            $table->text('description')->nullable();

            // Path to the uploaded business permit file on the
            // 'public' disk, e.g. 'vendor-permits/xyz.pdf'. Nullable
            // since the form marks this field optional.
            $table->string('permit_path')->nullable();

            // pending | approved | rejected
            $table->string('status')->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_requests');
    }
};