<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * الحالات الطبية/التشغيلية — Aggregate Root: clinic_cases_workflow
     */
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_no')->unique(); // CASE-2026-001
            $table->string('order_ref')->unique(); // ORD-2026-0847 — أمر التشغيل/الطلب
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('contract_company_id')->nullable()->constrained('contract_companies')->nullOnDelete();
            $table->string('company_name')->nullable(); // جهة التعاقد كنص
            $table->string('patient_type')->default('civilian'); // civilian | military
            $table->string('path')->default('standard'); // standard | military | ocr_bypass
            $table->string('stage_key')->default('reception'); // مرحلة المسار الرئيسية — STAGES[]
            $table->string('manufacturing_stage')->nullable(); // مرحلة التصنيع الفرعية — MANUFACTURING_STAGES
            $table->string('work_order_no')->nullable()->unique(); // WO-2026-0821
            $table->string('quote_no')->nullable(); // QT-2026-0847 — رقم عرض السعر المعتمد
            $table->date('quote_date')->nullable();
            $table->decimal('quote_total', 15, 2)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->decimal('paid', 15, 2)->default(0);
            $table->date('approval_date')->nullable();
            $table->timestamp('approval_confirmed_at')->nullable();
            $table->date('delivered_at')->nullable();
            $table->string('rank')->nullable();
            $table->string('sovereign_entity')->nullable();
            $table->string('credit_note_no')->nullable(); // CN-001 عند تطبيق إشعار دائن
            $table->decimal('credit_note_amount', 15, 2)->nullable();
            $table->timestamps();

            $table->index('stage_key');
            $table->index('manufacturing_stage');
            $table->index('patient_type');
            $table->index('quote_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cases');
    }
};
