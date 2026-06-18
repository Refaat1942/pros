<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * بنود طلب التسعير — recommendations[] في pricing-queue.js
     */
    public function up(): void
    {
        Schema::create('pricing_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pricing_request_id')->constrained('pricing_requests')->cascadeOnDelete();
            $table->string('stock_item_code')->nullable();
            $table->string('name');
            $table->unsignedInteger('qty')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_request_items');
    }
};
