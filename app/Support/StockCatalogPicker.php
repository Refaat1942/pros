<?php

namespace App\Support;

use App\Models\StockItem;
use App\Services\StockKitService;
use Illuminate\Support\Facades\Cache;

/** صفوف اختيار الأصناف + الأطقم في التوصيف والمعدلات. */
final class StockCatalogPicker
{
    private const FULL_ROWS_CACHE_KEY = 'stock_catalog_picker_rows_v2';

    private const FULL_ROWS_TTL_SECONDS = 300;

    /**
     * الكتالوج الكامل — مُخزَّن مؤقتاً للمعدلات وغيرها (ثقيل ~800+ صنف).
     *
     * @return list<array<string, mixed>>
     */
    public static function rows(): array
    {
        return Cache::remember(
            self::FULL_ROWS_CACHE_KEY,
            self::FULL_ROWS_TTL_SECONDS,
            fn () => self::buildFullRows(),
        );
    }

    public static function forgetCachedRows(): void
    {
        Cache::forget(self::FULL_ROWS_CACHE_KEY);
    }

    /**
     * تحميل خفيف للتوصيف — الأطقم + أصناف البنود الحالية فقط (البحث live من API).
     *
     * @param  list<string>  $itemCodes
     * @return list<array<string, mixed>>
     */
    public static function specBootstrapRows(array $itemCodes): array
    {
        $kits = app(StockKitService::class)->pickerRows();
        $items = StockItem::pickerRowsForCodes($itemCodes);

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

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildFullRows(): array
    {
        $items = StockItem::pickerCatalogRows();
        $kits = app(StockKitService::class)->pickerRows();

        return array_merge($kits, $items);
    }
}
