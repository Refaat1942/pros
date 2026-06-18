<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * بنود عرض السعر — items[] في quotations
     */
    public function up(): void
    {
        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();
            $table->string('name'); // اسم البند مع الكود
            $table->string('stock_item_code')->nullable();
            $table->unsignedInteger('qty')->default(1);
            $table->decimal('amount', 15, 2)->default(0); // قيمة البند
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_items');
    }
};
