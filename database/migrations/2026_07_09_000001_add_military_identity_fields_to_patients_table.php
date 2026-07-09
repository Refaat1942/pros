<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('military_number', 30)->nullable()->after('military_rank_id');
            $table->string('seniority_number', 30)->nullable()->after('military_number');
            $table->string('military_weapon', 100)->nullable()->after('seniority_number');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['military_number', 'seniority_number', 'military_weapon']);
        });
    }
};
