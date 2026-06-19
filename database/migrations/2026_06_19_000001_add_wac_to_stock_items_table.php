<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إضافة حقل WAC (Weighted Average Cost) لبطاقة الصنف.
     * يُحدَّث تلقائياً عند كل حركة وارد عبر StockPriceService::recalcWac().
     */
    public function up(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->decimal('wac', 15, 4)->default(0)->after('reserved')
                ->comment('Weighted Average Cost — updated on every inward movement');
        });
    }

    public function down(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->dropColumn('wac');
        });
    }
};
