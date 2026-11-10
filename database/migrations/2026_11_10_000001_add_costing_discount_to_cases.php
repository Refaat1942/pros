<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سعر الاعتماد قبل الخصم والخصم الإضافي على الحالة — للعسكري (مالوش عرض سعر):
 * مديونية الجهة العسكرية = سعر البيع حسب حسبة التكاليف − الخصم.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            if (! Schema::hasColumn('cases', 'costing_gross_price')) {
                $table->decimal('costing_gross_price', 15, 2)->nullable();
            }
            if (! Schema::hasColumn('cases', 'costing_discount_amount')) {
                $table->decimal('costing_discount_amount', 15, 2)->default(0);
            }
            if (! Schema::hasColumn('cases', 'costing_discount_percent')) {
                $table->decimal('costing_discount_percent', 8, 2)->nullable();
            }
            if (! Schema::hasColumn('cases', 'costing_discount_reason')) {
                $table->string('costing_discount_reason', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            foreach (['costing_gross_price', 'costing_discount_amount', 'costing_discount_percent', 'costing_discount_reason'] as $column) {
                if (Schema::hasColumn('cases', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
