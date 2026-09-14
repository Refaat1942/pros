<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

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
            $counts['stock_movements'] = DB::table('stock_movements')->delete();
            $counts['supplier_stock_item'] = DB::table('supplier_stock_item')->delete();
            $counts['stock_item_prices'] = DB::table('stock_item_prices')->delete();
            $counts['stock_item_attribute_values'] = DB::table('stock_item_attribute_values')->delete();
            $counts['stock_items'] = DB::table('stock_items')->delete();

            AuditService::log(
                action: 'purge',
                description: 'مسح كatalog الأصناف بالكامل — استعداد لرفع بيانات جديدة',
                tag: 'admin',
            );
        });

        return $counts;
    }
}
