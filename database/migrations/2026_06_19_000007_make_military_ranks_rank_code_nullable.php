<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('military_ranks', function (Blueprint $table) {
            $table->dropUnique(['rank_code']);
        });

        Schema::table('military_ranks', function (Blueprint $table) {
            $table->string('rank_code', 30)->nullable()->change();
            $table->unique('rank_code');
        });
    }

    public function down(): void
    {
        Schema::table('military_ranks', function (Blueprint $table) {
            $table->dropUnique(['rank_code']);
        });

        Schema::table('military_ranks', function (Blueprint $table) {
            $table->string('rank_code', 30)->nullable(false)->change();
            $table->unique('rank_code');
        });
    }
};
