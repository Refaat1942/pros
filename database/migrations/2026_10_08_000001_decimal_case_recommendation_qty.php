<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * توصيات الطبيب بكميات عشرية (0.5 متر، 0.2 كيلو) — مثل التوصيف والصرف.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE case_recommendations MODIFY qty DECIMAL(15,4) NOT NULL DEFAULT 1');

            return;
        }

        Schema::table('case_recommendations', function (Blueprint $table) {
            $table->decimal('qty', 15, 4)->default(1)->change();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE case_recommendations MODIFY qty INT UNSIGNED NOT NULL DEFAULT 1');

            return;
        }

        Schema::table('case_recommendations', function (Blueprint $table) {
            $table->unsignedInteger('qty')->default(1)->change();
        });
    }
};
