<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تخزين نتيجة احتساب التسعير (أعلى سعر شراء × الكمية — ليس WAC).
     */
    public function up(): void
    {
        Schema::table('pricing_requests', function (Blueprint $table) {
            $table->decimal('computed_total', 15, 2)->default(0)->after('items_count');
        });

        Schema::table('pricing_request_items', function (Blueprint $table) {
            $table->decimal('unit_price', 15, 2)->nullable()->after('qty');
            $table->decimal('line_total', 15, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('pricing_request_items', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'line_total']);
        });

        Schema::table('pricing_requests', function (Blueprint $table) {
            $table->dropColumn('computed_total');
        });
    }
};
