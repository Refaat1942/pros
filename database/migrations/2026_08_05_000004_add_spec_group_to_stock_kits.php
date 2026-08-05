<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_kits', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_kits', 'spec_group')) {
                $table->string('spec_group', 32)->nullable()->after('type')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_kits', function (Blueprint $table) {
            if (Schema::hasColumn('stock_kits', 'spec_group')) {
                $table->dropColumn('spec_group');
            }
        });
    }
};
