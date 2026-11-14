<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * خصم Up/Down (ظاهري): السعر المعروض = الصافي ÷ (1 − النسبة)، والخصم المعروض = المعروض − الصافي.
 * للطباعة فقط — الإيراد والمديونية بالصافي (quotes.total / cases.total_cost) زي ما هم.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['quotes', 'cases'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'updown_list_price')) {
                    $table->decimal('updown_list_price', 15, 2)->nullable();
                }
                if (! Schema::hasColumn($tableName, 'updown_discount_amount')) {
                    $table->decimal('updown_discount_amount', 15, 2)->nullable();
                }
                if (! Schema::hasColumn($tableName, 'updown_discount_percent')) {
                    $table->decimal('updown_discount_percent', 8, 2)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['quotes', 'cases'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (['updown_list_price', 'updown_discount_amount', 'updown_discount_percent'] as $col) {
                    if (Schema::hasColumn($tableName, $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
