<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** منتجات الصرف السريع اللي التوصيف بيكتبها كتابة (اسم + كمية) — المعدلات بتحددها من المخزن. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tech_order_specs', 'quick_dispense_items')) {
            Schema::table('tech_order_specs', function (Blueprint $table) {
                $table->json('quick_dispense_items')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tech_order_specs', 'quick_dispense_items')) {
            Schema::table('tech_order_specs', function (Blueprint $table) {
                $table->dropColumn('quick_dispense_items');
            });
        }
    }
};
