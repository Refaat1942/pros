<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * دفعات أسعار الشراء — prices[] داخل كل صنف في stock-catalog.js
     * تُستخدم لحساب WAC وأعلى سعر شراء (PricingQueue.highestUnitPrice)
     */
    public function up(): void
    {
        Schema::create('stock_item_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained('stock_items')->cascadeOnDelete();
            $table->string('price_ref')->nullable(); // PR-001-1
            $table->string('label')->nullable(); // تسمية الدفعة
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('supplier_type')->nullable(); // محلي | مستورد | OEM | موزّع
            $table->string('supplier_item_code')->nullable(); // كود الصنف عند المورد
            $table->decimal('amount', 15, 2); // سعر الوحدة
            $table->unsignedInteger('qty')->nullable(); // كمية الدفعة — لحساب WAC
            $table->string('invoice_no')->nullable(); // رقم فاتورة الشراء
            $table->date('received_at')->nullable(); // تاريخ التوريد
            $table->timestamps();

            $table->index(['stock_item_id', 'amount']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_item_prices');
    }
};
