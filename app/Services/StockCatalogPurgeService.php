<?php

namespace App\Services;

use App\Support\StockCatalogPicker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * مسح كatalog الأصناف بالكامل — للاستعداد لرفع/import بيانات جديدة.
 */
class StockCatalogPurgeService
{
    public function hasStockItems(): bool
    {
        return DB::table('stock_items')->exists();
    }

    public function hasBlockingReferences(): bool
    {
        return DB::table('bom_items')->exists()
            || DB::table('pricing_request_items')->exists()
            || DB::table('patients')->exists();
    }

    /** @return array<string, int> */
    public function purge(): array
    {
        $counts = [];

        DB::transaction(function () use (&$counts) {
            // طلبات استلام الوارد وتبديل الأصناف مربوطة بالأصناف (من غير حذف تلقائي) — لازم تتمسح الأول.
            foreach (['stock_swap_requests', 'stock_receipt_requests'] as $table) {
                if (Schema::hasTable($table)) {
                    $counts[$table] = DB::table($table)->delete();
                }
            }
            $counts['stock_movements'] = DB::table('stock_movements')->delete();
            $counts['stock_item_prices'] = DB::table('stock_item_prices')->delete();
            $counts['supply_request_lines'] = DB::table('supply_request_lines')->delete();
            $counts['supply_requests'] = DB::table('supply_requests')->delete();
            $counts['supplier_stock_item'] = DB::table('supplier_stock_item')->delete();
            $counts['stock_item_attribute_values'] = DB::table('stock_item_attribute_values')->delete();
            $counts['stock_items'] = DB::table('stock_items')->delete();
            // عدّادات أرقام الأصناف الداخلية وطلبات التوريد تبدأ من جديد مع الكتالوج الجديد.
            DB::table('document_sequences')->where('key', 'ITM')->orWhere('key', 'like', 'SR-%')->delete();

            AuditService::log(
                action: 'purge',
                description: 'مسح كatalog الأصناف بالكامل — استعداد لرفع بيانات جديدة',
                tag: 'admin',
            );
        });

        StockCatalogPicker::forgetCachedRows();

        return $counts;
    }
}
