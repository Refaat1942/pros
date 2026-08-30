<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bom_items', function (Blueprint $table) {
            $table->decimal('qty', 10, 3)->default(1)->change();
        });

        Schema::table('tech_order_spec_items', function (Blueprint $table) {
            $table->decimal('qty', 10, 3)->default(1)->change();
        });

        Schema::table('pricing_request_items', function (Blueprint $table) {
            $table->decimal('qty', 10, 3)->default(1)->change();
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->decimal('qty', 10, 3)->default(1)->change();
        });

        Schema::table('stock_items', function (Blueprint $table) {
            $table->decimal('reserved', 10, 3)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('bom_items', function (Blueprint $table) {
            $table->integer('qty')->default(1)->change();
        });

        Schema::table('tech_order_spec_items', function (Blueprint $table) {
            $table->integer('qty')->default(1)->change();
        });

        Schema::table('pricing_request_items', function (Blueprint $table) {
            $table->unsignedInteger('qty')->default(1)->change();
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->unsignedInteger('qty')->default(1)->change();
        });

        Schema::table('stock_items', function (Blueprint $table) {
            $table->integer('reserved')->default(0)->change();
        });
    }
};
