<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * وحدة التوريد مقابل وحدة المخزن/الصرف — معامل التحويل لكل وحدة توريد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->string('supply_uom', 50)->nullable()->after('uom');
            $table->decimal('units_per_supply_unit', 20, 6)->default(1)->after('supply_uom');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('supply_quantity', 15, 4)->nullable()->after('quantity');
            $table->decimal('supply_unit_cost', 15, 4)->nullable()->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn(['supply_quantity', 'supply_unit_cost']);
        });

        Schema::table('stock_items', function (Blueprint $table) {
            $table->dropColumn(['supply_uom', 'units_per_supply_unit']);
        });
    }
};
