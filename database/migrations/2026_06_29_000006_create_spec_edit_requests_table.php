<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spec_edit_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tech_order_spec_id')->constrained('tech_order_specs')->cascadeOnDelete();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users');
            $table->string('status', 20)->default('pending');
            $table->json('original_items');
            $table->json('proposed_items');
            $table->text('original_tech_notes')->nullable();
            $table->text('proposed_tech_notes')->nullable();
            $table->string('rejection_reason_key')->nullable();
            $table->text('rejection_notes')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spec_edit_requests');
    }
};
