<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * بنود التوصيات الطبية — recommendations[] داخل medicalRecords
     */
    public function up(): void
    {
        Schema::create('medical_record_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_record_id')->constrained('medical_records')->cascadeOnDelete();
            $table->string('stock_item_code')->nullable();
            $table->string('name');
            $table->unsignedInteger('qty')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_record_items');
    }
};
