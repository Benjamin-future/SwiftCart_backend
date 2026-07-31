<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'name')) {
                $table->string('name')->nullable()->after('product_id');
            }
            if (!Schema::hasColumn('order_items', 'product_name')) {
                $table->string('product_name')->nullable()->after('name');
            }
            // No index logic here to keep it DBAL-free
        });

        // Build a COALESCE expression from whichever product name columns exist
        $sources = [];
        foreach (['name', 'title', 'product_name', 'label'] as $col) {
            if (Schema::hasColumn('products', $col)) {
                $sources[] = "p.`{$col}`";
            }
        }

        $coalesce = !empty($sources)
            ? 'COALESCE(' . implode(', ', $sources) . ')'
            : "CONCAT('Item ', oi.`product_id`)"; // fallback if none of the above columns exist

        // Populate the new columns
        DB::statement("
            UPDATE `order_items` oi
            LEFT JOIN `products` p ON p.`id` = oi.`product_id`
            SET
                oi.`name`         = COALESCE(NULLIF(oi.`name`, ''), {$coalesce}),
                oi.`product_name` = COALESCE(NULLIF(oi.`product_name`, ''), {$coalesce})
        ");
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'product_name')) {
                $table->dropColumn('product_name');
            }
            if (Schema::hasColumn('order_items', 'name')) {
                $table->dropColumn('name');
            }
        });
    }
};


