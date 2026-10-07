<?php

namespace App\Support;

/**
 * مقارنة بنود طلب التعديل — إضافة، تعديل كمية، أو حذف.
 */
final class SpecEditRequestItemDiff
{
    /**
     * @param  list<array{stock_item_code: string, name?: string, qty: float}>  $original
     * @param  list<array{stock_item_code: string, name?: string, qty: float}>  $proposed
     * @return list<array{stock_item_code: string, name: string, qty: float, change: string, previous_qty?: float}>
     */
    public static function modifiedItems(array $original, array $proposed): array
    {
        $origByCode = collect($original)->keyBy('stock_item_code');
        $propByCode = collect($proposed)->keyBy('stock_item_code');
        $changes = [];

        foreach ($original as $item) {
            $code = (string) ($item['stock_item_code'] ?? '');
            if ($code === '') {
                continue;
            }

            $name = (string) ($item['name'] ?? $code);
            $prev = round((float) ($item['qty'] ?? 0), 4);
            $next = $propByCode->get($code);

            if ($next === null) {
                $changes[] = [
                    'stock_item_code' => $code,
                    'name' => $name,
                    'qty' => $prev,
                    'change' => 'removed',
                ];

                continue;
            }

            $newQty = round((float) ($next['qty'] ?? 0), 4);
            if (abs($newQty - $prev) >= 0.00005) {
                $changes[] = [
                    'stock_item_code' => $code,
                    'name' => (string) ($next['name'] ?? $name),
                    'qty' => $newQty,
                    'previous_qty' => $prev,
                    'change' => 'updated',
                ];
            }
        }

        foreach ($proposed as $item) {
            $code = (string) ($item['stock_item_code'] ?? '');
            if ($code === '' || $origByCode->has($code)) {
                continue;
            }

            $changes[] = [
                'stock_item_code' => $code,
                'name' => (string) ($item['name'] ?? $code),
                'qty' => round((float) ($item['qty'] ?? 0), 4),
                'change' => 'added',
            ];
        }

        return $changes;
    }

    /** @param array{stock_item_code?: string, name?: string, qty?: float, change?: string, previous_qty?: float} $item */
    public static function summaryLine(array $item): string
    {
        $name = $item['name'] ?? $item['stock_item_code'] ?? '—';

        return match ($item['change'] ?? '') {
            'removed' => 'حذف: '.$name.' (×'.StockQuantity::format((float) ($item['qty'] ?? 0), null).')',
            'updated' => $name.' × '.StockQuantity::format((float) ($item['qty'] ?? 0), null)
                .' (كان ×'.StockQuantity::format((float) ($item['previous_qty'] ?? 0), null).')',
            default => $name.' × '.StockQuantity::format((float) ($item['qty'] ?? 0), null),
        };
    }

    /**
     * @param  list<array{stock_item_code: string, name?: string, qty: float, change?: string, previous_qty?: float}>  $items
     */
    public static function summaryText(array $items): string
    {
        if ($items === []) {
            return '—';
        }

        return collect($items)->map(fn (array $i) => self::summaryLine($i))->implode(' | ');
    }
}
