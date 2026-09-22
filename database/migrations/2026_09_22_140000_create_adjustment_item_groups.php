<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adjustment_item_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('name');
        });

        Schema::create('adjustment_item_group_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adjustment_item_group_id')
                ->constrained('adjustment_item_groups')
                ->cascadeOnDelete();
            $table->string('stock_item_code', 64);
            $table->decimal('qty', 15, 4)->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['adjustment_item_group_id', 'stock_item_code'], 'adj_group_line_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adjustment_item_group_lines');
        Schema::dropIfExists('adjustment_item_groups');
    }
};
