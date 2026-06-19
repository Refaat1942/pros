<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('transferred_to_clinic_at')->nullable()->after('transferred_to_clinic');
        });

        DB::table('appointments')
            ->where('transferred_to_clinic', true)
            ->whereNull('transferred_to_clinic_at')
            ->update(['transferred_to_clinic_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('transferred_to_clinic_at');
        });
    }
};
