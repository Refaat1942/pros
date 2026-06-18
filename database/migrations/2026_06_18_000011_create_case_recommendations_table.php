<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * التوصيات المرتبطة بالحالة — recommendations[] في cases-workflow.js
     */
    public function up(): void
    {
        Schema::create('case_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->string('stock_item_code'); // ITM-001
            $table->string('name'); // اسم الصنف
            $table->unsignedInteger('qty')->default(1);
            $table->timestamps();

            $table->index(['case_id', 'stock_item_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_recommendations');
    }
};
