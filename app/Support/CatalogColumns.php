<?php

namespace App\Support;

/**
 * ترتيب وأعمدة كتالوج الأصناف — مصدر واحد للجدول، القالب، والاستيراد.
 */
final class CatalogColumns
{
    /** @return array<string, array{label: string, field: string, template?: bool, table?: bool, align?: string, computed?: bool}> */
    public static function definitions(): array
    {
        return config('catalog.columns', []);
    }

    /** @return list<string> */
    public static function templateOrder(): array
    {
        return self::normalizeOrder(
            config('catalog.template_column_order', []),
            fn (string $key) => (bool) (self::definitions()[$key]['template'] ?? false),
        );
    }

    /** @return list<string> */
    public static function tableOrder(): array
    {
        return self::normalizeOrder(
            config('catalog.table_column_order', []),
            fn (string $key) => (bool) (self::definitions()[$key]['table'] ?? false),
        );
    }

    /** @return list<string> */
    public static function templateHeaders(): array
    {
        return array_map(
            fn (string $key) => self::definitions()[$key]['label'] ?? $key,
            self::templateOrder(),
        );
    }

    /** عدد أعمدة الجدول + عمود الإجراء (+ اختياري: تحديد الباركود). */
    public static function tableColspan(bool $withBarcodeCheckbox = false): int
    {
        return count(self::tableOrder()) + 1 + ($withBarcodeCheckbox ? 1 : 0);
    }

    /** @return array<string, list<string>> */
    public static function importAliases(): array
    {
        $defaults = [
            'catalog_number' => ['رقم الصنف', 'كود الصنف'],
            'page_number' => ['رقم الصفحة'],
            'name' => ['اسم الصنف'],
            'brand' => ['الماركة', 'ماركة', 'البراند'],
            'alt_codes' => ['الأكواد', 'أكواد', 'اكواد', 'الاكواد', 'codes', 'code', 'كود'],
            'uom' => ['الوحدة'],
            'opening_qty_raw' => ['رصيد أول المده', 'رصيد أول المدة', 'الكمية'],
            'addition_raw' => ['الاضافة', 'الإضافة'],
            'discount_raw' => ['الخصم'],
            'balance_raw' => ['الرصيد', 'الكمية'],
            'price_raw' => ['السعر الأساسي', 'السعر', 'سعر التكلفة', 'أعلى سعر'],
        ];

        return array_merge($defaults, config('catalog.import_aliases', []));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public static function templateValue(array $item, string $key): string
    {
        return match ($key) {
            'code', 'catalog_number' => (string) ($item['catalog_number'] ?? $item['code'] ?? ''),
            'page_number' => (string) ($item['page_number'] ?? ''),
            'name' => (string) ($item['name'] ?? ''),
            'brand' => (string) ($item['brand'] ?? ''),
            'alt_codes' => self::operationalCodeForExport($item),
            'uom' => (string) ($item['uom'] ?? ''),
            'opening_qty' => (string) ((int) ($item['opening_qty'] ?? 0)),
            'addition' => (string) ((int) ($item['addition'] ?? 0)),
            'discount' => (string) ((int) ($item['discount'] ?? 0)),
            'balance' => (string) ((int) ($item['catalog_balance'] ?? $item['balance'] ?? $item['qty'] ?? 0)),
            'price' => (string) round((float) ($item['price'] ?? 0), 2),
            default => (string) ($item[self::definitions()[$key]['field'] ?? $key] ?? ''),
        };
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{html: string, class: string}
     */
    public static function tableCell(array $item, string $key): array
    {
        $def = self::definitions()[$key] ?? [];
        $align = $def['align'] ?? 'right';

        if ($key === 'code') {
            $value = $item['catalog_number'] ?? $item['code'] ?? '';

            return [
                'html' => '<strong>'.e($value).'</strong>',
                'class' => 'direction:ltr;text-align:right;',
            ];
        }

        if ($key === 'page_number') {
            return ['html' => e($item['page_number'] ?? '—'), 'class' => 'text-align:center;color:var(--text-muted);'];
        }

        if ($key === 'name') {
            return ['html' => e($item['name'] ?? ''), 'class' => ''];
        }

        if ($key === 'brand') {
            $brand = trim((string) ($item['brand'] ?? ''));

            return [
                'html' => e($brand !== '' ? $brand : '—'),
                'class' => 'color:var(--text-muted);',
            ];
        }

        if ($key === 'alt_codes') {
            return [
                'html' => e($item['alt_codes'] ?? '—'),
                'class' => 'color:var(--text-muted);font-size:12px;direction:ltr;text-align:right;',
            ];
        }

        if ($key === 'uom') {
            return ['html' => e($item['uom'] ?? 'قطعة'), 'class' => 'text-align:center;color:var(--text-muted);'];
        }

        if (in_array($key, ['opening_qty', 'addition', 'discount'], true)) {
            return ['html' => (string) ((int) ($item[$key] ?? 0)), 'class' => 'text-align:center;'];
        }

        if ($key === 'catalog_balance') {
            $catalogBal = (int) ($item['catalog_balance'] ?? $item['balance'] ?? 0);

            return ['html' => (string) $catalogBal, 'class' => 'text-align:center;color:var(--text-muted);'];
        }

        if ($key === 'warehouse_qty') {
            $catalogBal = (int) ($item['catalog_balance'] ?? $item['balance'] ?? 0);
            $warehouseQty = (int) ($item['warehouse_qty'] ?? $item['qty'] ?? 0);
            $qtyMismatch = $catalogBal !== $warehouseQty;
            $style = $qtyMismatch ? 'color:#b45309;font-weight:700;' : 'color:#059669;font-weight:700;';
            $title = $qtyMismatch
                ? 'رصيد الكتالوج ≠ رصيد المخزن — راجع الحركات أو عدّل بيانات الاستيراد'
                : 'رصيد المخزن الفعلي';

            return [
                'html' => (string) $warehouseQty,
                'class' => 'text-align:center;'.$style,
                'title' => $title,
            ];
        }

        if ($key === 'price') {
            return [
                'html' => number_format((float) ($item['price'] ?? 0), 2),
                'class' => 'text-align:center;',
                'cell_class' => 'catalog-price-cell',
            ];
        }

        $field = $def['field'] ?? $key;
        $value = $item[$field] ?? '—';

        return [
            'html' => e((string) $value),
            'class' => $align === 'center' ? 'text-align:center;' : '',
        ];
    }

    /** @param  array<string, mixed>  $item */
    private static function operationalCodeForExport(array $item): string
    {
        $operational = trim((string) ($item['alt_codes'] ?? $item['operational_code'] ?? ''));
        if ($operational === '' && ! empty($item['barcode'])) {
            $operational = preg_replace('/^BC-/i', '', (string) $item['barcode']) ?: '';
        }

        return $operational;
    }

    /**
     * @param  list<string>  $order
     * @param  callable(string): bool  $filter
     * @return list<string>
     */
    private static function normalizeOrder(array $order, callable $filter): array
    {
        $defs = self::definitions();
        $valid = array_values(array_filter(
            $order,
            fn (string $key) => isset($defs[$key]) && $filter($key),
        ));

        if ($valid !== []) {
            return $valid;
        }

        return array_values(array_filter(
            array_keys($defs),
            $filter,
        ));
    }
}
