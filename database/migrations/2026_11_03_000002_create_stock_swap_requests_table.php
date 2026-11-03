<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** تبديل صنف مع المورد: صنف خارج من المخزون مقابل صنف داخل (موجود أو جديد) — بطلب واعتماد أدمن المخازن. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_swap_requests', function (Blueprint $table) {
            $table->id();
            $table->string('swap_no', 30)->unique();
            $table->string('status', 20)->default('pending');
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->foreignId('out_stock_item_id')->constrained('stock_items');
            $table->decimal('out_qty', 15, 4);
            $table->foreignId('in_stock_item_id')->nullable()->constrained('stock_items')->nullOnDelete();
            $table->json('new_item')->nullable();
            $table->decimal('in_qty', 15, 4);
            $table->decimal('price_difference', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('requested_by_user_id')->constrained('users');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->decimal('out_value', 15, 2)->nullable();
            $table->foreignId('out_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->foreignId('in_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_swap_requests');
    }
};
