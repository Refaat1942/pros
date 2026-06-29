<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('fax', 30)->nullable()->after('phone');
            $table->string('tax_number', 50)->nullable()->after('address');
            $table->string('commercial_registry', 50)->nullable()->after('tax_number');
            $table->string('bank_name', 191)->nullable()->after('commercial_registry');
            $table->string('bank_branch', 191)->nullable()->after('bank_name');
            $table->string('bank_account', 64)->nullable()->after('bank_branch');
            $table->string('iban', 34)->nullable()->after('bank_account');
            $table->softDeletes();
        });

        Schema::create('supplier_debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->unique()->constrained('suppliers')->cascadeOnDelete();
            $table->decimal('due', 15, 2)->default(0);
            $table->decimal('collected', 15, 2)->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('supplier_stock_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained('stock_items')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['supplier_id', 'stock_item_id'], 'supplier_stock_item_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_stock_item');
        Schema::dropIfExists('supplier_debts');
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'fax',
                'tax_number',
                'commercial_registry',
                'bank_name',
                'bank_branch',
                'bank_account',
                'iban',
            ]);
        });
    }
};
