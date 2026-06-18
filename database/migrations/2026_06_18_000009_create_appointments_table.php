<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * المواعيد — appointments[] في reception-dashboard.js
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->date('appointment_date');
            $table->string('appointment_time', 10)->nullable(); // 08:00 أو «جديد»
            $table->string('visit_type')->default('exam'); // exam | followup | fitting | delivery | review
            $table->string('patient_name'); // اسم المريض — للمواعيد قبل ربط الملف
            $table->string('phone', 20)->nullable();
            $table->string('company_name')->nullable();
            $table->string('patient_type')->default('civilian');
            $table->string('status')->default('waiting'); // waiting | in_clinic | quoted | done
            $table->string('status_label')->nullable();
            $table->boolean('transferred_to_clinic')->default(false);
            $table->timestamps();

            $table->index(['appointment_date', 'appointment_time']);
            $table->index('visit_type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
