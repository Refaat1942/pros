<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // FK — يُستخدم بدلاً من النص الحر في حقل rank
            $table->foreignId('military_rank_id')
                ->nullable()
                ->after('patient_type')
                ->constrained('military_ranks')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropForeign(['military_rank_id']);
            $table->dropColumn('military_rank_id');
        });
    }
};
