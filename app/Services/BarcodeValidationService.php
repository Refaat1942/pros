<?php

namespace App\Services;

use App\Models\BomItem;
use App\Models\StockItem;

/**
 * التحقق من مطابقة المسح مع بنود BOM — الاعتماد على كود الصنف (stock_items.code) فقط.
 * لا يُقبل عمود «الأكواد» (alt_codes) كمعرّف للصنف.
 */
class BarcodeValidationService
{
    /**
     * يتحقق من أن المسح يطابق stock_item_code للبند (كود الصنف).
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
     * يُرجع كود الصنف الرسمي (stock_items.code) أو null — لا يستخدم alt_codes.
     */
    public function resolveStockItemCode(string $scan): ?string
    {
        return $this->resolveStockItem($scan)?->code;
    }

    private function barcodeMatchesCode(string $scan, string $stockItemCode): bool
    {
        $stockItem = $this->resolveStockItem($scan);

        return $stockItem !== null && $stockItem->code === $stockItemCode;
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

        return StockItem::query()->where('code', $scan)->first();
    }
}
