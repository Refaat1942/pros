<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * أصناف كشف الطبيب بكميات عشرية — على PostgreSQL كان حفظ 0.5 متر يفشل (عمود integer).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE medical_record_items MODIFY qty DECIMAL(15,4) NOT NULL DEFAULT 1');

            return;
        }

        Schema::table('medical_record_items', function (Blueprint $table) {
            $table->decimal('qty', 15, 4)->default(1)->change();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE medical_record_items MODIFY qty INT UNSIGNED NOT NULL DEFAULT 1');

            return;
        }

        Schema::table('medical_record_items', function (Blueprint $table) {
            $table->unsignedInteger('qty')->default(1)->change();
        });
    }
};
