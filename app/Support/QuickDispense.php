<?php

namespace App\Support;

use App\Models\CaseRecord;
use App\Models\StockItem;

/**
 * منتجات الصرف السريع (عكاكيز، كراسي…) — تمييز البنود والحالات اللي كلها صرف سريع.
 * البنود بتتخزن بالكود التشغيلي (alt_codes) أو كود الصنف، فالبحث بيدعم الاتنين.
 */
final class QuickDispense
{
    public static function isQuickCode(?string $code): bool
    {
        $code = trim((string) $code);
        if ($code === '') {
            return false;
        }

        return (bool) StockItem::findByOperationalCode($code)?->is_quick_dispense;
    }

    public static function isQuickItem(?StockItem $item, ?string $code): bool
    {
        return $item !== null ? (bool) $item->is_quick_dispense : self::isQuickCode($code);
    }

    /** الحالة كل بنودها (بكمية > 0) منتجات صرف سريع — يعني مالهاش دعوة بقسم الإنتاج. */
    public static function caseIsQuickOnly(CaseRecord $case): bool
    {
        $items = $case->bom()->first()?->items()->get(['stock_item_code', 'qty']) ?? collect();
        $items = $items->filter(fn ($i) => (float) $i->qty > 0);

        return $items->isNotEmpty()
            && $items->every(fn ($i) => self::isQuickCode($i->stock_item_code));
    }
}
