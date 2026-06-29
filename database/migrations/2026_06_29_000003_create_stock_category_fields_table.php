<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_category_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('stock_categories')->cascadeOnDelete();
            $table->string('field_key', 64);
            $table->string('label');
            $table->string('type', 32);
            $table->json('options')->nullable();
            $table->json('config')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('required')->default(false);
            $table->timestamps();

            $table->unique(['category_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_category_fields');
    }
};
