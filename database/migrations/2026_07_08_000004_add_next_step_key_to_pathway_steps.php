<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pathway_steps', function (Blueprint $table) {
            $table->string('next_step_key', 64)->nullable()->after('on_complete');
        });
    }

    public function down(): void
    {
        Schema::table('pathway_steps', function (Blueprint $table) {
            $table->dropColumn('next_step_key');
        });
    }
};
