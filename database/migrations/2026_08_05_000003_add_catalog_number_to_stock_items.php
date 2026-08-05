<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * رقم الصنف من Excel (قد يتكرر) — مُميَز عن code الداخلي الفريد (ITM-xxx).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->string('catalog_number', 50)->nullable()->after('code');
            $table->index('catalog_number');
            $table->index('page_number');
        });

        DB::table('stock_items')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                if ($row->catalog_number !== null && $row->catalog_number !== '') {
                    continue;
                }

                DB::table('stock_items')->where('id', $row->id)->update([
                    'catalog_number' => $row->code,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->dropIndex(['catalog_number']);
            $table->dropIndex(['page_number']);
            $table->dropColumn('catalog_number');
        });
    }
};
