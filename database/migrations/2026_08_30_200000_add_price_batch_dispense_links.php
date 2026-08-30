<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_item_prices', function (Blueprint $table) {
            $table->foreignId('supply_request_line_id')
                ->nullable()
                ->after('received_at')
                ->constrained('supply_request_lines')
                ->nullOnDelete();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('stock_item_price_id')
                ->nullable()
                ->after('stock_item_id')
                ->constrained('stock_item_prices')
                ->nullOnDelete();
        });

        DB::statement('ALTER TABLE stock_item_prices MODIFY qty DECIMAL(12,4) NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_item_price_id');
        });

        Schema::table('stock_item_prices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supply_request_line_id');
        });

        DB::statement('ALTER TABLE stock_item_prices MODIFY qty INT UNSIGNED NULL');
    }
};
