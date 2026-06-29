<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_item_attribute_values')) {
            return;
        }

        Schema::create('stock_item_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained('stock_items')->cascadeOnDelete();
            $table->foreignId('category_field_id')->constrained('stock_category_fields')->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['stock_item_id', 'category_field_id'], 'stock_item_attr_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_item_attribute_values');
    }
};
