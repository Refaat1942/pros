<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('military_debts', function (Blueprint $table) {
            $table->decimal('collected', 15, 2)->default(0)->after('total_cost');
        });

        DB::table('military_debts')
            ->where('status', 'collected')
            ->update(['collected' => DB::raw('total_cost')]);
    }

    public function down(): void
    {
        Schema::table('military_debts', function (Blueprint $table) {
            $table->dropColumn('collected');
        });
    }
};
