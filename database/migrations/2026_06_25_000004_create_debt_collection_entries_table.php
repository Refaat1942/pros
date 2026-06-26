<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debt_collection_entries', function (Blueprint $table) {
            $table->id();
            $table->string('payable_type');
            $table->unsignedBigInteger('payable_id');
            $table->unsignedSmallInteger('installment_no');
            $table->decimal('amount', 15, 2);
            $table->decimal('running_collected', 15, 2);
            $table->decimal('remaining_after', 15, 2);
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->string('recorded_by_name')->nullable();
            $table->timestamp('collected_at');
            $table->timestamps();

            $table->index(['payable_type', 'payable_id']);
            $table->unique(['payable_type', 'payable_id', 'installment_no'], 'debt_coll_payable_inst_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_collection_entries');
    }
};
