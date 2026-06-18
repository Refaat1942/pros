<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * بطاقة الصنف الرئيسية — clinic_stock_catalog في stock-catalog.js
     */
    public function up(): void
    {
        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // ITM-001
            $table->string('name'); // اسم الصنف
            $table->string('spec')->nullable(); // المواصفات الفنية
            $table->string('category')->nullable(); // مفاصل، أقدام، بطانات...
            $table->string('store_class')->nullable(); // تصنيف شجري المخزن (قطع خام، مواد مساعدة...)
            $table->string('uom')->default('قطعة'); // وحدة القياس
            $table->string('barcode')->unique(); // BC-1 — للمسح بالباركود
            $table->unsignedInteger('qty')->default(0); // الرصيد الحالي
            $table->unsignedInteger('reserved')->default(0); // الكمية المحجوزة
            $table->string('status')->default('ok'); // ok | low — syncStatus()
            $table->date('last_moved_at')->nullable(); // آخر حركة مخزنية
            $table->string('last_return_ref')->nullable(); // مرجع آخر ارتجاع
            $table->timestamps();

            $table->index('category');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_items');
    }
};
