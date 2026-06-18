<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ملفات المرضى — patientsRegistry في reception-dashboard.js + EMR في التحليل
     */
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('patient_code')->unique(); // PT-CIV-0001 | PT-MIL-0001
            $table->string('patient_qr')->unique(); // QR-PT-CIV-0001
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('national_id', 20)->nullable(); // الرقم القومي — doctor queue
            $table->string('patient_type')->default('civilian'); // civilian | military
            $table->string('rank')->nullable(); // الرتبة العسكرية
            $table->string('sovereign_entity')->nullable(); // الجهة السيادية للعسكري
            $table->foreignId('contract_company_id')->nullable()->constrained('contract_companies')->nullOnDelete();
            $table->string('company_name')->nullable(); // نسخة نصية كما في الـ prototype
            $table->date('registered_at')->nullable();
            $table->date('last_visit_at')->nullable();
            $table->string('status')->default('active'); // active | inactive | quoted | done
            $table->timestamps();

            $table->index('patient_type');
            $table->index('national_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
