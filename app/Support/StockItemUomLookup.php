<?php

namespace App\Support;

use App\Models\StockItem;

/** خريطة كود الصنف (alt_codes) → وحدة القياس (uom) لعرضها في BOM والمعدلات والصرف. */
final class StockItemUomLookup
{
    /**
     * @param  list<string>  $codes
     * @return array<string, string>
     */
    public static function forCodes(array $codes): array
    {
        return StockItem::mapByOperationalCodes($codes, 'uom');
    }
}
