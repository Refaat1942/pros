<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * التوصيف الفني — clinic_tech_order_specs في technical-dashboard.js
     */
    public function up(): void
    {
        Schema::create('tech_order_specs', function (Blueprint $table) {
            $table->id();
            $table->string('order_ref')->unique(); // ORD-2026-0847
            $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
            $table->string('patient_name');
            $table->string('company_name')->nullable();
            $table->string('doctor_name')->nullable();
            $table->text('tech_notes')->nullable();
            $table->date('submitted_at')->nullable();
            $table->timestamps();

            $table->index('order_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tech_order_specs');
    }
};
