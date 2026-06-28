<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * مصدر البند — spec (التوصيف) | adjustment (المعدلات).
     */
    public function up(): void
    {
        Schema::table('pricing_request_items', function (Blueprint $table) {
            $table->string('source', 20)->default('spec')->after('name');
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->string('source', 20)->default('spec')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('pricing_request_items', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
