<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_item_attribute_values')) {
            return;
        }

        try {
            Schema::table('stock_item_attribute_values', function (Blueprint $table) {
                $table->unique(['stock_item_id', 'category_field_id'], 'stock_item_attr_unique');
            });
        } catch (\Throwable) {
            // الفهرس موجود مسبقاً أو الجدول أُنشئ بالفعل.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('stock_item_attribute_values')) {
            return;
        }

        try {
            Schema::table('stock_item_attribute_values', function (Blueprint $table) {
                $table->dropUnique('stock_item_attr_unique');
            });
        } catch (\Throwable) {
        }
    }
};
