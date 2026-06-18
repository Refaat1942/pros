<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * عروض الأسعار — quotations[] في reception-dashboard.js
     */
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('quote_no')->unique(); // QT-2026-0847
            $table->string('order_ref');
            $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
            $table->foreignId('pricing_request_id')->nullable()->unique()->constrained('pricing_requests')->nullOnDelete();
            $table->string('patient_name');
            $table->string('company_name')->nullable();
            $table->date('quote_date');
            $table->string('status')->default('pending'); // pending | approved | issued
            $table->string('status_label')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();

            $table->index('order_ref');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
