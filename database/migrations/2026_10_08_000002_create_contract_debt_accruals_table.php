<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قيود مستحق جهات التعاقد بتاريخها (ترحيل حالة +، إشعار دائن −) —
 * حتى يُحسب رصيد أول/آخر المدة والحركة لفترة محددة بدلاً من اعتبار كل المستحق الحالي «رصيد أول المدة».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_debt_accruals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_company_debt_id')->constrained('contract_company_debts')->cascadeOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamp('accrued_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_debt_accruals');
    }
};
