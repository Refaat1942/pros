<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بنود مكتوبة (وصف حر) للتوصيف الفني — بند في كل سطر.
 * وصفية فقط: لا تدخل التسعير ولا حجز المخزون (اختيار الكاتلوج يبقى للمسار المُسعّر).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tech_order_specs', function (Blueprint $table) {
            $table->text('written_items')->nullable()->after('tech_notes');
        });
    }

    public function down(): void
    {
        Schema::table('tech_order_specs', function (Blueprint $table) {
            $table->dropColumn('written_items');
        });
    }
};
