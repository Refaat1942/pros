<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * مدفوعات الخزنة — تحصيل نقدي للمرضى على نفقتهم الشخصية (كاش).
     * كل سجل يمثل تأكيد استلام مبلغ من الخزنة قبل تحويل الحالة للمخزن.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_no')->unique(); // PAY-2026-0001
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->string('patient_name')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('method'); // cash | instapay | vodafone_cash
            $table->string('reference')->nullable(); // رقم عملية إنستاباي/فودافون كاش
            $table->string('received_by')->nullable(); // اسم موظف الخزنة
            $table->timestamp('received_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('case_id');
            $table->index('method');
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
