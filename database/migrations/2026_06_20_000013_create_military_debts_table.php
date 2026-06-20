<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('military_debts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id')->unique();
            $table->string('work_order_no')->nullable();
            $table->string('patient_name');
            $table->string('patient_national_id')->nullable();  // الرقم العسكري / القومي
            $table->string('sovereign_entity');                 // الجهة العسكرية الضامنة
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->date('delivered_at')->nullable();
            $table->string('status')->default('pending_collection'); // pending_collection | collected
            $table->timestamp('collected_at')->nullable();           // يُعبأ عند التحصيل ويُجمَّد السجل
            $table->timestamps();

            $table->foreign('case_id')->references('id')->on('cases')->cascadeOnDelete();
            $table->index('status');
            $table->index('sovereign_entity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('military_debts');
    }
};
