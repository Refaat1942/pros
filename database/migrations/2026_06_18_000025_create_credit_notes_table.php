<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إشعارات الدائن — clinic_credit_notes في credit-notes.js
     */
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('credit_note_no')->unique(); // CN-001
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->string('order_ref');
            $table->string('patient_name');
            $table->string('company_name')->nullable();
            $table->string('type')->default('partial'); // partial | full
            $table->decimal('amount', 15, 2);
            $table->decimal('original_total', 15, 2);
            $table->text('reason')->nullable();
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->timestamp('approved_at')->nullable();
            $table->string('approved_by')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('company_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
