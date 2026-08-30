<?php

namespace App\Support;

use App\Models\StockItem;
use App\Services\StockCategorySchemaService;

/**
 * خلايا جداول قوائم الأصناف — ملفات خارج admin_catalog.
 */
final class CatalogListCells
{
    /**
     * @return array{html: string, class: string, align?: string}
     */
    public static function inventoryOverviewCell(StockItem $item, string $key, StockCategorySchemaService $schema): array
    {
        $available = $item->availableQty();
        $backorder = $item->backorderQty();
        $expirySoon = $item->expiry_date && $item->expiry_date->lte(now()->addDays(60));
        $displayPrice = (float) $item->price > 0
            ? (float) $item->price
            : max((float) $item->wac, (float) ($item->prices->max('amount') ?? 0));
        $history = $item->prices->take(3)->map(fn ($p) => number_format((float) $p->amount, 2)
            .' ('.($p->received_at?->format('Y-m-d') ?? '—').')')->implode(' • ');
        $availColor = $available < 0 ? '#dc2626' : ($available > 0 ? '#059669' : '#d97706');
        $attrSummary = collect($schema->formatItemAttributes($item))
            ->map(fn ($a) => $a['label'].': '.$a['display_value'])
            ->implode(' · ');

        return match ($key) {
            'code' => [
                'html' => '<strong>'.e($item->code).'</strong>'
                    .'<div style="font-size:11px;color:var(--text-muted);">'.e($item->barcode ?? '').'</div>',
                'class' => 'direction:ltr;text-align:right;',
            ],
            'name' => ['html' => e($item->name), 'class' => ''],
            'brand' => [
                'html' => e(trim((string) ($item->brand ?? '')) !== '' ? $item->brand : '—'),
                'class' => 'color:var(--text-muted);',
            ],
            'category' => [
                'html' => '<div>'.e($item->category?->name ?? '—').'</div>'
                    .($attrSummary ? '<div style="font-size:11px;margin-top:4px;">'.e($attrSummary).'</div>' : ''),
                'class' => 'font-size:12px;color:var(--text-muted);',
            ],
            'qty' => ['html' => (string) ((int) $item->qty), 'class' => 'text-align:center;font-weight:700;'],
            'reserved' => ['html' => (string) ((int) $item->reserved), 'class' => 'text-align:center;color:#d97706;'],
            'available' => [
                'html' => (string) $available,
                'class' => 'text-align:center;font-weight:700;color:'.$availColor.';',
            ],
            'backorder' => [
                'html' => $backorder > 0 ? (string) $backorder : '—',
                'class' => 'text-align:center;font-weight:700;color:'.($backorder > 0 ? '#dc2626' : 'var(--text-muted)').';',
            ],
            'price' => ['html' => number_format($displayPrice, 2), 'class' => 'text-align:center;font-weight:700;'],
            'wac' => ['html' => number_format((float) $item->wac, 2), 'class' => 'text-align:center;color:var(--text-muted);'],
            'expiry' => [
                'html' => e($item->expiry_date?->format('Y-m-d') ?? '—'),
                'class' => 'text-align:center;'.($expirySoon ? 'color:#dc2626;font-weight:700;' : ''),
            ],
            'price_history' => [
                'html' => e($history ?: '—'),
                'class' => 'text-align:center;font-size:11px;color:var(--text-muted);',
            ],
            'print' => [
                'html' => '<a href="'.e(route('admin.catalog.labels', $item)).'" target="_blank" class="btn-action" style="font-size:12px;">🏷️ باركود</a>',
                'class' => 'text-align:center;',
            ],
            default => ['html' => '—', 'class' => ''],
        };
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{html: string, class: string}
     */
    public static function technicalInventoryCell(array $item, string $key): array
    {
        $isLow = ($item['status'] ?? '') === 'low';
        $isBackorder = ($item['status'] ?? '') === 'backorder';
        $available = $item['available'] ?? (($item['qty'] ?? 0) - ($item['reserved'] ?? 0));
        $statusLabel = $isBackorder
            ? 'طلب توريد ('.($item['backorder'] ?? 0).')'
            : ($isLow ? 'كمية منخفضة' : 'متوفر');
        $statusClass = $isBackorder ? 'backorder' : ($isLow ? 'low' : 'available');
        $availClass = $isBackorder ? 'backorder' : ($item['status'] ?? 'ok');

        return match ($key) {
            'code' => [
                'html' => '<span class="item-code">'.e($item['code'] ?? '').'</span>',
                'class' => 'item-code-cell',
            ],
            'name' => ['html' => '<div class="item-name">'.e($item['name'] ?? '').'</div>', 'class' => ''],
            'brand' => [
                'html' => e(trim((string) ($item['brand'] ?? '')) !== '' ? $item['brand'] : '—'),
                'class' => 'color:var(--text-muted);',
            ],
            'uom' => [
                'html' => e($item['uom'] ?? '—'),
                'class' => 'text-align:center;color:var(--text-muted);',
            ],
            'available' => [
                'html' => '<div class="qty-badge '.$availClass.'">'.$available.'</div>',
                'class' => 'qty-cell',
            ],
            'status' => [
                'html' => '<span class="stock-status '.$statusClass.'"><span class="status-dot"></span>'.$statusLabel.'</span>',
                'class' => 'status-cell',
            ],
            'qty' => ['html' => (string) ((int) ($item['qty'] ?? 0)), 'class' => 'text-align:center;'],
            'reserved' => ['html' => (string) ((int) ($item['reserved'] ?? 0)), 'class' => 'text-align:center;'],
            'category' => [
                'html' => e($item['category'] ?? '—'),
                'class' => 'color:var(--text-muted);font-size:12px;',
            ],
            default => ['html' => '—', 'class' => ''],
        };
    }
}
