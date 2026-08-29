<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * كميات عشرية للأصناف — كيلو/جرام/متر بكسور.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE stock_items MODIFY qty DECIMAL(15,4) NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE stock_items MODIFY opening_qty DECIMAL(15,4) NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE stock_items MODIFY addition DECIMAL(15,4) NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE stock_items MODIFY discount DECIMAL(15,4) NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE stock_items MODIFY reserved DECIMAL(15,4) NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE stock_items MODIFY min_qty DECIMAL(15,4) NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE bom_items MODIFY qty DECIMAL(15,4) NOT NULL DEFAULT 1');
            DB::statement('ALTER TABLE bom_items MODIFY issued_qty DECIMAL(15,4) NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE bom_items MODIFY returned_qty DECIMAL(15,4) NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE stock_movements MODIFY quantity DECIMAL(15,4) NOT NULL');
            DB::statement('ALTER TABLE stock_movements MODIFY balance_after DECIMAL(15,4) NULL');
        } else {
            Schema::table('stock_items', function (Blueprint $table) {
                $table->decimal('qty', 15, 4)->default(0)->change();
                $table->decimal('opening_qty', 15, 4)->default(0)->change();
                $table->decimal('addition', 15, 4)->default(0)->change();
                $table->decimal('discount', 15, 4)->default(0)->change();
                $table->decimal('reserved', 15, 4)->default(0)->change();
                $table->decimal('min_qty', 15, 4)->default(0)->change();
            });
            Schema::table('bom_items', function (Blueprint $table) {
                $table->decimal('qty', 15, 4)->default(1)->change();
                $table->decimal('issued_qty', 15, 4)->default(0)->change();
                $table->decimal('returned_qty', 15, 4)->default(0)->change();
            });
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->decimal('quantity', 15, 4)->change();
                $table->decimal('balance_after', 15, 4)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE stock_items MODIFY qty INT NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE stock_items MODIFY opening_qty INT NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE stock_items MODIFY addition INT NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE stock_items MODIFY discount INT NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE stock_items MODIFY reserved INT UNSIGNED NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE stock_items MODIFY min_qty INT UNSIGNED NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE bom_items MODIFY qty INT UNSIGNED NOT NULL DEFAULT 1');
            DB::statement('ALTER TABLE bom_items MODIFY issued_qty INT UNSIGNED NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE bom_items MODIFY returned_qty INT UNSIGNED NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE stock_movements MODIFY quantity INT NOT NULL');
            DB::statement('ALTER TABLE stock_movements MODIFY balance_after INT UNSIGNED NULL');
        }
    }
};
