<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * السماح بقيم سالبة في reserved — كمية سالبة في التوصيف تُخفّض الحجز.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE stock_items MODIFY reserved INT NOT NULL DEFAULT 0');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE stock_items MODIFY reserved INT UNSIGNED NOT NULL DEFAULT 0');
        }
    }
};
