<?php

namespace App\Support;

use App\Models\StockItem;
use App\Services\StockKitService;

/** صفوف اختيار الأصناف + الأطقم في التوصيف والمعدلات. */
final class StockCatalogPicker
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function rows(): array
    {
        $items = StockItem::pickerCatalogRows();
        $kits = app(StockKitService::class)->pickerRows();

        return array_merge($kits, $items);
    }
}
