<?php

namespace App\Services;

use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * استلام بضاعة وارد — حركة receive + دفعة سعر + إعادة حساب WAC.
 */
class StockReceiveService
{
    public function __construct(
        private readonly StockPriceService $stockPriceService,
        private readonly SupplierDebtService $supplierDebtService,
    ) {}

    /**
     * استلام بضاعة من مورد — يُحدِّث الرصيد وWAC ويُنشئ سجل حركة غير قابل للتعديل.
     */
    public function receive(
        StockItem $item,
        int $qty,
        float $unitPrice,
        Supplier $supplier,
        string $invoiceNo,
        Carbon $movedAt,
        User $performedBy,
        ?string $documentPath = null,
        ?string $documentOriginalName = null,
        ?string $documentMime = null,
    ): StockMovement {
        return DB::transaction(function () use ($item, $qty, $unitPrice, $supplier, $invoiceNo, $movedAt, $performedBy, $documentPath, $documentOriginalName, $documentMime) {
            $item = StockItem::lockForUpdate()->findOrFail($item->id);

            $before = [
                'qty' => $item->qty,
                'wac' => (float) ($item->wac ?? 0),
            ];

            $balanceAfter = $item->qty + $qty;

            $movement = StockMovement::create([
                'stock_item_id' => $item->id,
                'movement_type' => StockMovement::TYPE_RECEIVE,
                'quantity' => $qty,
                'unit_cost' => $unitPrice,
                'balance_after' => $balanceAfter,
                'invoice_no' => $invoiceNo,
                'document_path' => $documentPath,
                'document_original_name' => $documentOriginalName,
                'document_mime' => $documentMime,
                'supplier_id' => $supplier->id,
                'reference_type' => null,
                'reference_id' => null,
                'performed_by_user_id' => $performedBy->id,
                'moved_at' => $movedAt,
            ]);

            $this->stockPriceService->createPriceBatch(
                $item, $qty, $unitPrice, $supplier, $invoiceNo, $movedAt
            );

            $this->supplierDebtService->increaseDue($supplier, round($qty * $unitPrice, 2));
            app(SupplierService::class)->attachStockItem($supplier, $item);

            $this->stockPriceService->recalcWac($item, $qty, $unitPrice);

            $item->update([
                'qty' => $balanceAfter,
                'last_moved_at' => $movedAt->toDateString(),
            ]);

            $item->refresh();
            $item->recalculateAndSaveStatus();

            AuditService::log(
                action: 'receive',
                description: 'استلام بضاعة: '.$item->code,
                tag: 'warehouse',
                before: $before,
                after: [
                    'qty' => $item->qty,
                    'wac' => (float) $item->wac,
                ],
            );

            return $movement->load(['supplier:id,name', 'stockItem', 'performedBy:id,name']);
        });
    }
}
