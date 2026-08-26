<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supply_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no')->unique();
            $table->string('status', 32)->default('open');
            $table->text('notes')->nullable();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('supply_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supply_request_id')->constrained('supply_requests')->cascadeOnDelete();
            $table->string('line_type', 32);
            $table->foreignId('stock_item_id')->nullable()->constrained('stock_items')->nullOnDelete();
            $table->string('description')->nullable();
            $table->unsignedInteger('qty');
            $table->string('uom', 50)->nullable();
            $table->text('spec')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 32)->default('pending');
            $table->foreignId('resolved_stock_item_id')->nullable()->constrained('stock_items')->nullOnDelete();
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'line_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supply_request_lines');
        Schema::dropIfExists('supply_requests');
    }
};
