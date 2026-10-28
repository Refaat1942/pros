<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * خصم يدوي للعميل المدني الكاش فقط — quotes.total يبقى الصافي (اللي بتطلبه الخزنة وتقرأه التقارير)،
 * والأعمدة دي للطباعة والرقابة: الإجمالي قبل الخصم، قيمة/نسبة الخصم، السبب، ومين منحه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->decimal('gross_total', 15, 2)->nullable()->after('total');
            $table->decimal('discount_amount', 15, 2)->default(0)->after('gross_total');
            $table->decimal('discount_percent', 5, 2)->nullable()->after('discount_amount');
            $table->string('discount_reason', 255)->nullable()->after('discount_percent');
            $table->string('discount_by', 255)->nullable()->after('discount_reason');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn(['gross_total', 'discount_amount', 'discount_percent', 'discount_reason', 'discount_by']);
        });
    }
};
