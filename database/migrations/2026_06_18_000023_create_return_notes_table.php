<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إذن الارتجاع — clinic_inventory_returns في inventory-returns.js
     */
    public function up(): void
    {
        Schema::create('return_notes', function (Blueprint $table) {
            $table->id();
            $table->string('return_no')->unique(); // RTN-001
            $table->foreignId('bom_id')->constrained('boms')->cascadeOnDelete();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->string('order_ref');
            $table->string('work_order_no')->nullable();
            $table->string('patient_name');
            $table->string('status')->default('authorized'); // authorized | partial | completed
            $table->string('created_by')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('audit_trail')->nullable(); // سجل مسح الباركود — auditTrail[]
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_notes');
    }
};
