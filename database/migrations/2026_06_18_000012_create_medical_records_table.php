<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * السجل الطبي — medicalRecords[] في doctor-dashboard.js
     */
    public function up(): void
    {
        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
            $table->string('patient_name'); // اسم المريض
            $table->string('national_id', 20)->nullable();
            $table->string('company_name')->nullable();
            $table->string('patient_type')->default('civilian');
            $table->text('diagnosis'); // التشخيص الدقيق
            $table->text('prescription')->nullable(); // الروشتة الطبية
            $table->string('doctor_name'); // اسم الطبيب المعالج
            $table->foreignId('doctor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('record_date');
            $table->string('status')->default('معتمد'); // معتمد | تحويل للمخزون | تم التحويل للمخزون
            $table->boolean('locked')->default(false); // غير قابل للتعديل بعد الاعتماد
            $table->timestamps();

            $table->index('record_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_records');
    }
};
