<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * حركات المخزون — receiveStock / issueQty / returnQty في stock-catalog.js
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained('stock_items')->cascadeOnDelete();
            $table->string('movement_type'); // receive | issue | return
            $table->integer('quantity'); // موجب للوارد والارتجاع، سالب للصرف
            $table->decimal('unit_cost', 15, 2)->nullable(); // تكلفة الوحدة عند الوارد
            $table->unsignedInteger('balance_after')->nullable(); // الرصيد بعد الحركة
            $table->string('invoice_no')->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('reference_type')->nullable(); // bom | return_note | manual
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moved_at');
            $table->timestamps();

            $table->index(['stock_item_id', 'movement_type']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('moved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
