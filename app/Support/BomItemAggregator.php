<?php

namespace App\Support;

use App\Models\BomItem;
use Illuminate\Support\Collection;

/**
 * دمج بنود BOM المتكررة بنفس كود الصنف — مثلاً بند الفني + بند المعدلات.
 */
final class BomItemAggregator
{
    /**
     * @param  iterable<int, BomItem|array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public static function byStockCode(iterable $items): array
    {
        $grouped = [];

        foreach ($items as $item) {
            $row = $item instanceof BomItem
                ? [
                    'id'              => $item->id,
                    'stock_item_code' => $item->stock_item_code,
                    'name'            => $item->name,
                    'qty'             => (int) $item->qty,
                    'issued_qty'      => (int) $item->issued_qty,
                    'returned_qty'    => (int) $item->returned_qty,
                    'unit_cost'       => $item->unit_cost,
                ]
                : $item;

            $code = $row['stock_item_code'];

            if (! isset($grouped[$code])) {
                $grouped[$code] = [
                    'id'              => $row['id'] ?? null,
                    'stock_item_code' => $code,
                    'name'            => $row['name'] ?? $code,
                    'qty'             => 0,
                    'issued_qty'      => 0,
                    'returned_qty'    => 0,
                    'unit_cost'       => $row['unit_cost'] ?? null,
                ];
            }

            $grouped[$code]['qty']          += (int) ($row['qty'] ?? 0);
            $grouped[$code]['issued_qty']   += (int) ($row['issued_qty'] ?? 0);
            $grouped[$code]['returned_qty'] += (int) ($row['returned_qty'] ?? 0);
        }

        return array_values($grouped);
    }

    /**
     * @param  iterable<int, BomItem|array<string, mixed>>  $items
     */
    public static function uniqueCodeCount(iterable $items): int
    {
        return count(self::byStockCode($items));
    }

    /**
     * @param  Collection<int, BomItem>  $items
     * @return Collection<string, Collection<int, BomItem>>
     */
    public static function groupModels(Collection $items): Collection
    {
        return $items->groupBy('stock_item_code');
    }
}
