<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * طابور التسعير — clinic_pricing_queue في pricing-queue.js
     */
    public function up(): void
    {
        Schema::create('pricing_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no')->unique(); // QT-PENDING-001
            $table->string('order_ref'); // ORD-2026-0847
            $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
            $table->string('patient_name');
            $table->string('company_name')->nullable();
            $table->date('request_date');
            $table->unsignedSmallInteger('items_count')->default(0);
            $table->string('doctor_name')->nullable();
            $table->foreignId('doctor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('patient_type')->default('civilian');
            $table->string('status_key')->default('processing'); // processing | awaiting_admin_approval | sent_to_reception | insufficient
            $table->unsignedTinyInteger('step')->default(1); // 1=موافقة الأدمن، 2=جاهز لعرض السعر
            $table->timestamp('approved_at')->nullable();
            $table->string('approved_by')->nullable(); // اسم المعتمد
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('order_ref');
            $table->index('status_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_requests');
    }
};
