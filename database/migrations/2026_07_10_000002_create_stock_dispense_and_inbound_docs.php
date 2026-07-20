<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('document_path')->nullable()->after('invoice_no');
            $table->string('document_original_name')->nullable()->after('document_path');
            $table->string('document_mime', 100)->nullable()->after('document_original_name');
        });

        Schema::create('stock_dispense_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('bom_id')->constrained('boms')->cascadeOnDelete();
            $table->string('work_order_no')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('lines');
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_dispense_requests');

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn(['document_path', 'document_original_name', 'document_mime']);
        });
    }
};
