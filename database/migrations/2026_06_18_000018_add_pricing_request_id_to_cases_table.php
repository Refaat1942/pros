<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ربط الحالة بطلب التسعير — pricingQueueId في cases-workflow.js
     */
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->foreignId('pricing_request_id')->nullable()->after('work_order_no')
                ->constrained('pricing_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropForeign(['pricing_request_id']);
            $table->dropColumn('pricing_request_id');
        });
    }
};
