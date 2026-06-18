<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * قائمة مواد التشغيل — clinic_bom_inventory في bom-inventory.js
     */
    public function up(): void
    {
        Schema::create('boms', function (Blueprint $table) {
            $table->id();
            $table->string('bom_no')->unique(); // BOM-001
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->string('order_ref');
            $table->string('quote_no')->nullable();
            $table->string('patient_name');
            $table->string('stage')->default('raw'); // raw | wip | finished
            $table->timestamp('released_at')->nullable(); // تاريخ الصرف للورشة
            $table->timestamp('finished_at')->nullable(); // تاريخ الإغلاق «تام»
            $table->timestamps();

            $table->unique('case_id'); // BOM واحدة لكل حالة
            $table->index('order_ref');
            $table->index('stage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boms');
    }
};
