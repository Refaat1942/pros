<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * السماح برصيد سالب (backorder) — الصرف/الحجز قد يتجاوز المتاح فيصبح الرصيد سالباً.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE stock_items MODIFY qty INT NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE stock_movements MODIFY balance_after INT NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE stock_items MODIFY qty INT UNSIGNED NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE stock_movements MODIFY balance_after INT UNSIGNED NULL');
        }
    }
};
