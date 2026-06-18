<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * مديونيات جهات التعاقد — clinic_contract_debts في credit-notes.js
     */
    public function up(): void
    {
        Schema::create('contract_company_debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_company_id')->constrained('contract_companies')->cascadeOnDelete();
            $table->decimal('due', 15, 2)->default(0); // المبلغ المستحق
            $table->decimal('collected', 15, 2)->default(0); // المبلغ المحصّل
            $table->string('status')->default('pending'); // paid | partial | pending
            $table->timestamps();

            $table->unique('contract_company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_company_debts');
    }
};
