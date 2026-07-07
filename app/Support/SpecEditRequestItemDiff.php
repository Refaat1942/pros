<?php

namespace App\Support;

/**
 * مقارنة بنود طلب التعديل — إضافة، تعديل كمية، أو حذف.
 */
final class SpecEditRequestItemDiff
{
    /**
     * @param  list<array{stock_item_code: string, name?: string, qty: int}>  $original
     * @param  list<array{stock_item_code: string, name?: string, qty: int}>  $proposed
     * @return list<array{stock_item_code: string, name: string, qty: int, change: string, previous_qty?: int}>
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
            $prev = (int) ($item['qty'] ?? 0);
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

            $newQty = (int) ($next['qty'] ?? 0);
            if ($newQty !== $prev) {
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
                'qty' => (int) ($item['qty'] ?? 0),
                'change' => 'added',
            ];
        }

        return $changes;
    }

    /** @param array{stock_item_code?: string, name?: string, qty?: int, change?: string, previous_qty?: int} $item */
    public static function summaryLine(array $item): string
    {
        $name = $item['name'] ?? $item['stock_item_code'] ?? '—';

        return match ($item['change'] ?? '') {
            'removed' => 'حذف: '.$name.' (×'.(int) ($item['qty'] ?? 0).')',
            'updated' => $name.' × '.(int) ($item['qty'] ?? 0)
                .' (كان ×'.(int) ($item['previous_qty'] ?? 0).')',
            default => $name.' × '.(int) ($item['qty'] ?? 0),
        };
    }

    /**
     * @param  list<array{stock_item_code: string, name?: string, qty: int, change?: string, previous_qty?: int}>  $items
     */
    public static function summaryText(array $items): string
    {
        if ($items === []) {
            return '—';
        }

        return collect($items)->map(fn (array $i) => self::summaryLine($i))->implode(' | ');
    }
}
