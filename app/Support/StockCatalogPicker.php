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

    /**
     * @return list<array<string, mixed>>
     */
    public static function search(string $q, int $limit = 50): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }

        $limit = max(10, min(60, $limit));
        $kits = app(StockKitService::class)->searchPickerRows($q, min(20, (int) floor($limit / 3)));
        $items = StockItem::searchPickerRows($q, $limit);

        return array_merge($kits, $items);
    }
}
