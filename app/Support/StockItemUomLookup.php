<?php

namespace App\Support;

use App\Models\StockItem;

/** خريطة كود الصنف → وحدة القياس (uom) لعرضها في BOM والمعدلات والصرف. */
final class StockItemUomLookup
{
    /**
     * @param  list<string>  $codes
     * @return array<string, string>
     */
    public static function forCodes(array $codes): array
    {
        $codes = array_values(array_unique(array_filter($codes)));

        if ($codes === []) {
            return [];
        }

        return StockItem::query()
            ->whereIn('code', $codes)
            ->pluck('uom', 'code')
            ->all();
    }
}
