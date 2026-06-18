<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * بنود التوصيف الفني — recommendations[] في saveTechOrderSpec
     */
    public function up(): void
    {
        Schema::create('tech_order_spec_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tech_order_spec_id')->constrained('tech_order_specs')->cascadeOnDelete();
            $table->string('stock_item_code')->nullable();
            $table->string('name');
            $table->unsignedInteger('qty')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tech_order_spec_items');
    }
};
