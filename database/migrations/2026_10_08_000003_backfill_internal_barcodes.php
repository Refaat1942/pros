<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * الأصناف المرفوعة بلا «كود صنف» كانت بلا باركود — لا تُطبع لها ملصقات ولا تُصرف بالمسح.
 * تأخذ باركوداً من رقمها الداخلي الفريد: BCI-{code}.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('stock_items')
            ->whereNull('barcode')
            ->orWhere('barcode', '')
            ->orderBy('id')
            ->get(['id', 'code'])
            ->each(function ($item) {
                $barcode = 'BCI-'.trim((string) $item->code);
                if (DB::table('stock_items')->where('barcode', $barcode)->exists()) {
                    $barcode .= '-'.$item->id;
                }

                DB::table('stock_items')->where('id', $item->id)->update(['barcode' => $barcode]);
            });
    }

    public function down(): void
    {
        DB::table('stock_items')->where('barcode', 'like', 'BCI-%')->update(['barcode' => null]);
    }
};
