<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * أعمدة قالب الأصناف — رقم الصفحة، الأكواد، رصيد أول المدة، الإضافة، الخصم.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->string('page_number', 50)->nullable()->after('code');
            $table->string('alt_codes', 500)->nullable()->after('barcode');
            $table->integer('opening_qty')->default(0)->after('qty');
            $table->integer('addition')->default(0)->after('opening_qty');
            $table->integer('discount')->default(0)->after('addition');
        });

        DB::table('stock_items')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('stock_items')->where('id', $row->id)->update([
                    'opening_qty' => (int) $row->qty,
                    'alt_codes' => $row->barcode ?: null,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->dropColumn(['page_number', 'alt_codes', 'opening_qty', 'addition', 'discount']);
        });
    }
};
