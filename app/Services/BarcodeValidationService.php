<?php

namespace App\Services;

use App\Models\BomItem;
use App\Models\StockItem;

/**
 * التحقق من مطابقة المسح مع بنود BOM — الاعتماد على كود الصنف (stock_items.alt_codes) فقط.
 * عمود «رقم الصنف» (code) ليس معرّفاً تشغيلياً.
 */
class BarcodeValidationService
{
    /**
     * يتحقق من أن المسح يطابق stock_item_code للبند (كود الصنف = alt_codes).
     */
    public function validateScan(string $scan, BomItem $bomItem): bool
    {
        if ($this->barcodeMatchesCode($scan, $bomItem->stock_item_code)) {
            return true;
        }

        AuditService::log(
            action: 'blocked',
            description: 'مسح كود/باركود خاطئ',
            tag: 'warehouse',
            before: [
                'scan' => $scan,
                'expected_code' => $bomItem->stock_item_code,
                'bom_item_id' => $bomItem->id,
            ],
        );

        return false;
    }

    /**
     * تحقق عام — يُستخدم في إتمام إذن الارتجاع.
     */
    public function validateBarcodeForCode(string $scan, string $stockItemCode): bool
    {
        if ($this->barcodeMatchesCode($scan, $stockItemCode)) {
            return true;
        }

        AuditService::log(
            action: 'blocked',
            description: 'مسح كود/باركود خاطئ — ارتجاع',
            tag: 'warehouse',
            before: [
                'scan' => $scan,
                'expected_code' => $stockItemCode,
            ],
        );

        return false;
    }

    /**
     * يُرجع كود الصنف التشغيلي (alt_codes) أو null.
     */
    public function resolveStockItemCode(string $scan): ?string
    {
        $item = $this->resolveStockItem($scan);

        return $item?->pickerCode() ?: $item?->code;
    }

    private function barcodeMatchesCode(string $scan, string $stockItemCode): bool
    {
        $stockItem = $this->resolveStockItem($scan);
        $expected = trim($stockItemCode);

        if ($stockItem === null || $expected === '') {
            return false;
        }

        return $stockItem->operationalCode() === $expected
            || $stockItem->code === $expected;
    }

    private function resolveStockItem(string $scan): ?StockItem
    {
        $scan = trim($scan);
        if ($scan === '') {
            return null;
        }

        $byBarcode = StockItem::query()->where('barcode', $scan)->first();
        if ($byBarcode !== null) {
            return $byBarcode;
        }

        $byOperational = StockItem::findByOperationalCode($scan);
        if ($byOperational !== null) {
            return $byOperational;
        }

        if (str_starts_with(strtoupper($scan), 'BC-')) {
            return StockItem::findByOperationalCode(substr($scan, 3));
        }

        return null;
    }
}
