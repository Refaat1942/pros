<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * السماح بكميات سالبة في التوصيف الفني وBOM (تعديلات / إرجاع حجز).
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE tech_order_spec_items MODIFY qty INT NOT NULL DEFAULT 1');
            DB::statement('ALTER TABLE bom_items MODIFY qty INT NOT NULL DEFAULT 1');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE tech_order_spec_items MODIFY qty INT UNSIGNED NOT NULL DEFAULT 1');
            DB::statement('ALTER TABLE bom_items MODIFY qty INT UNSIGNED NOT NULL DEFAULT 1');
        }
    }
};
