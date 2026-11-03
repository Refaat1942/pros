<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * اعتماد أدمن المخازن: طلبات التوريد (رأس الطلب) واستلام الوارد (طلب استلام معلّق
 * لا يضيف رصيداً إلا بعد الاعتماد). الطلبات القديمة تبقى «open» = معتمدة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supply_requests', function (Blueprint $table) {
            $table->foreignId('approved_by_user_id')->nullable()->after('requested_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
            $table->string('rejection_reason', 500)->nullable()->after('approved_at');
        });

        Schema::create('stock_receipt_requests', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->default('pending');
            $table->foreignId('stock_item_id')->constrained('stock_items');
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->foreignId('supply_request_line_id')->nullable()->constrained('supply_request_lines')->nullOnDelete();
            $table->decimal('qty', 15, 4);
            $table->decimal('unit_price', 15, 4);
            $table->string('quantity_basis', 10)->default('auto');
            $table->string('invoice_no', 100);
            $table->date('moved_at');
            $table->string('document_path')->nullable();
            $table->string('document_original_name')->nullable();
            $table->string('document_mime', 100)->nullable();
            $table->foreignId('requested_by_user_id')->constrained('users');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_receipt_requests');
        Schema::table('supply_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn(['approved_at', 'rejection_reason']);
        });
    }
};
