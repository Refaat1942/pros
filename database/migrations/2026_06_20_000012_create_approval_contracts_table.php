<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_no')->unique();          // CNT-2026-0001
            $table->unsignedBigInteger('case_id');
            $table->unsignedBigInteger('quote_id')->nullable();
            $table->string('patient_name');
            $table->string('company_name');
            $table->decimal('approved_amount', 12, 2);
            $table->date('approval_date');
            $table->string('work_order_no')->nullable();
            $table->string('letter_path')->nullable();        // storage relative path
            $table->string('letter_ref')->nullable();         // ref number from entity letter
            $table->string('letter_date')->nullable();
            $table->timestamps();

            $table->foreign('case_id')->references('id')->on('cases')->cascadeOnDelete();
            $table->foreign('quote_id')->references('id')->on('quotes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_contracts');
    }
};
