<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * بنود BOM — items[] في bom-inventory.js
     */
    public function up(): void
    {
        Schema::create('bom_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_id')->constrained('boms')->cascadeOnDelete();
            $table->string('stock_item_code');
            $table->string('name');
            $table->unsignedInteger('qty')->default(1);
            $table->decimal('unit_cost', 15, 2)->default(0); // أعلى سعر شراء عند الإنشاء
            $table->unsignedInteger('issued_qty')->default(0); // الكمية المصروفة
            $table->unsignedInteger('returned_qty')->default(0); // الكمية المرتجعة
            $table->timestamps();

            $table->index(['bom_id', 'stock_item_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_items');
    }
};
