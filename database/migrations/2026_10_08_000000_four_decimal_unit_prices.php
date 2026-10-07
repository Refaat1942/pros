<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * سعر الوحدة بأربع خانات عشرية — أصناف تُصرف بالسم² تكلفتها أقل من قرش
 * (مثال: فرخ 10000 سم² بـ 561 ج ← 0.0561 ج للسم²)؛ خانتان كانتا تقرّبانها إلى 0.06.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE stock_items MODIFY price DECIMAL(15,4) NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE stock_item_prices MODIFY amount DECIMAL(15,4) NOT NULL');

            return;
        }

        Schema::table('stock_items', function (Blueprint $table) {
            $table->decimal('price', 15, 4)->default(0)->change();
        });
        Schema::table('stock_item_prices', function (Blueprint $table) {
            $table->decimal('amount', 15, 4)->change();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE stock_items MODIFY price DECIMAL(15,2) NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE stock_item_prices MODIFY amount DECIMAL(15,2) NOT NULL');

            return;
        }

        Schema::table('stock_items', function (Blueprint $table) {
            $table->decimal('price', 15, 2)->default(0)->change();
        });
        Schema::table('stock_item_prices', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->change();
        });
    }
};
