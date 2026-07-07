<?php

namespace App\Services;

use App\Models\BomItem;
use App\Models\StockItem;

/**
 * التحقق من مطابقة باركود المسح مع بنود BOM والارتجاع.
 */
class BarcodeValidationService
{
    /**
     * يتحقق من أن الباركود يطابق stock_item_code للبند.
     */
    public function validateScan(string $barcode, BomItem $bomItem): bool
    {
        if ($this->barcodeMatchesCode($barcode, $bomItem->stock_item_code)) {
            return true;
        }

        AuditService::log(
            action: 'blocked',
            description: 'مسح باركود خاطئ',
            tag: 'warehouse',
            before: [
                'barcode' => $barcode,
                'expected_code' => $bomItem->stock_item_code,
                'bom_item_id' => $bomItem->id,
            ],
        );

        return false;
    }

    /**
     * تحقق عام — يُستخدم في إتمام إذن الارتجاع.
     */
    public function validateBarcodeForCode(string $barcode, string $stockItemCode): bool
    {
        if ($this->barcodeMatchesCode($barcode, $stockItemCode)) {
            return true;
        }

        AuditService::log(
            action: 'blocked',
            description: 'مسح باركود خاطئ — ارتجاع',
            tag: 'warehouse',
            before: [
                'barcode' => $barcode,
                'expected_code' => $stockItemCode,
            ],
        );

        return false;
    }

    private function barcodeMatchesCode(string $barcode, string $stockItemCode): bool
    {
        $stockItem = StockItem::where('barcode', $barcode)->first();

        return $stockItem !== null && $stockItem->code === $stockItemCode;
    }
}
