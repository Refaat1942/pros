<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * قفل التوصيف بعد الإرسال + حالة واحدة لكل حالة (1:1).
     */
    public function up(): void
    {
        Schema::table('tech_order_specs', function (Blueprint $table) {
            $table->boolean('locked')->default(false)->after('tech_notes');
            $table->unique('case_id');
        });
    }

    public function down(): void
    {
        Schema::table('tech_order_specs', function (Blueprint $table) {
            $table->dropUnique(['case_id']);
            $table->dropColumn('locked');
        });
    }
};
