<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * بنود إذن الارتجاع — lines[] في inventory-returns.js
     */
    public function up(): void
    {
        Schema::create('return_note_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_note_id')->constrained('return_notes')->cascadeOnDelete();
            $table->string('stock_item_code');
            $table->string('name');
            $table->unsignedInteger('qty_requested')->default(1);
            $table->unsignedInteger('qty_returned')->default(0);
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_note_lines');
    }
};
