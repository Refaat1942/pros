<?php

namespace App\Services;

use App\Models\Bom;
use App\Models\BomItem;
use App\Models\CaseRecord;
use App\Models\ReturnNote;
use App\Models\ReturnNoteLine;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * إذونات ارتجاع المواد — إنشاء وإتمام بالباركود.
 */
class ReturnNoteService
{
    public function __construct(
        private readonly BarcodeValidationService $barcodeValidation,
        private readonly StockPriceService $stockPriceService,
        private readonly PriceBatchDispenseService $priceBatchDispenseService,
    ) {}

    /**
     * إنشاء إذن ارتجاع مع بنوده.
     *
     * @param  list<array{stock_item_code: string, qty: int, name?: string}>  $lines
     */
    public function create(Bom $bom, array $lines, string $reason, User $createdBy): ReturnNote
    {
        return DB::transaction(function () use ($bom, $lines, $reason, $createdBy) {
            $bom = Bom::lockForUpdate()->with(['items', 'caseRecord'])->findOrFail($bom->id);

            if ($lines === []) {
                abort(422, 'يجب إضافة بند واحد على الأقل للارتجاع.');
            }

            $case = $bom->caseRecord;

            $note = ReturnNote::create([
                'return_no' => $this->nextReturnNo(),
                'bom_id' => $bom->id,
                'case_id' => $bom->case_id,
                'order_ref' => $bom->order_ref,
                'work_order_no' => $case?->work_order_no,
                'patient_name' => $bom->patient_name,
                'status' => ReturnNote::STATUS_AUTHORIZED,
                'created_by' => $createdBy->name,
                'created_by_user_id' => $createdBy->id,
                'authorized_at' => now(),
            ]);

            foreach ($lines as $row) {
                $code = $row['stock_item_code'];
                $qty = (int) $row['qty'];
                $bomItem = $bom->items->firstWhere('stock_item_code', $code);

                if (! $bomItem) {
                    abort(422, "الصنف {$code} غير موجود في BOM.");
                }

                if ($qty > $bomItem->returnRequestMaxQty(null, $bom->stage)) {
                    $max = $bomItem->returnRequestMaxQty(null, $bom->stage);
                    abort(422, $max === 0
                        ? "لا يمكن ارتجاع المزيد من الصنف {$code} — الكمية محجوزة في طلب ارتجاع أو يجب الإبقاء على وحدة في قسم الإنتاج."
                        : "لا يمكن ارتجاع كامل الكمية للصنف {$code} — الحد الأقصى {$max}.");
                }

                ReturnNoteLine::create([
                    'return_note_id' => $note->id,
                    'stock_item_code' => $code,
                    'name' => $row['name'] ?? $bomItem->name,
                    'qty_requested' => $qty,
                    'qty_returned' => 0,
                    'reason' => $reason,
                ]);
            }

            AuditService::log(
                action: 'create',
                description: "طلب ارتجاع مواد للمخزن {$note->return_no}",
                tag: 'operations',
                after: $note->load('lines')->toArray(),
            );

            return $note;
        });
    }

    /**
     * إتمام الارتجاع — مسح باركود وإرجاع الكميات للمخزون.
     *
     * @param  list<array{line_id: int, barcode: string, qty_returned: int}>  $scannedLines
     * @return array{note: ReturnNote, stock_updates: list<array{stock_item_code: string, name: string, qty_returned: int, qty_before: int, qty_after: int, unit_cost: float, line_value: float}>}
     */
    public function complete(ReturnNote $note, array $scannedLines): array
    {
        return DB::transaction(function () use ($note, $scannedLines) {
            $note = ReturnNote::lockForUpdate()
                ->with(['lines', 'bom.items'])
                ->findOrFail($note->id);

            if ($note->status === ReturnNote::STATUS_COMPLETED) {
                abort(422, 'إذن الارتجاع مكتمل بالفعل.');
            }

            if ($scannedLines === []) {
                abort(422, 'يجب مسح باركود واحد على الأقل.');
            }

            $stockBefore = [];
            $stockUpdates = [];
            $performedById = Auth::id();

            foreach ($scannedLines as $scan) {
                $line = $note->lines->firstWhere('id', $scan['line_id']);

                if (! $line) {
                    abort(422, 'بند الارتجاع غير موجود.');
                }

                $qtyReturned = (int) $scan['qty_returned'];
                $remaining = $line->qty_requested - $line->qty_returned;

                if ($qtyReturned < 1 || $qtyReturned > $remaining) {
                    abort(422, "كمية غير صالحة للصنف {$line->stock_item_code}.");
                }

                if (! $this->barcodeValidation->validateBarcodeForCode($scan['barcode'], $line->stock_item_code)) {
                    abort(422, "باركود غير مطابق للصنف {$line->stock_item_code}.");
                }

                $stockItem = StockItem::whereKey(
                    StockItem::findByOperationalCode($line->stock_item_code, true)?->id
                )->lockForUpdate()->first()
                    ?? abort(422, "الصنف غير موجود: {$line->stock_item_code}");

                if (! isset($stockBefore[$stockItem->code])) {
                    $stockBefore[$stockItem->code] = [
                        'qty' => $stockItem->qty,
                    ];
                }

                $bomItem = $note->bom?->items->firstWhere('stock_item_code', $line->stock_item_code);
                $bom = $note->bom;

                $qtyBefore = (float) $stockItem->qty;
                $allocations = $bom
                    ? $this->priceBatchDispenseService->allocateForReturn($bom, $stockItem, (float) $qtyReturned)
                    : [[
                        'batch_id' => 0,
                        'qty' => (float) $qtyReturned,
                        'unit_price' => $this->returnUnitCost($bomItem, $line->stock_item_code),
                    ]];

                if ($bom && $allocations !== []) {
                    $this->priceBatchDispenseService->applyIncrements($allocations);
                }

                $runningBalance = $qtyBefore;
                $lineValue = 0.0;
                $unitCostForLine = 0.0;

                foreach ($allocations as $alloc) {
                    if ((int) $alloc['batch_id'] === 0) {
                        $allocQty = (float) $alloc['qty'];
                        $unitCost = (float) $alloc['unit_price'];
                        $runningBalance += $allocQty;

                        StockMovement::create([
                            'stock_item_id' => $stockItem->id,
                            'movement_type' => StockMovement::TYPE_RETURN,
                            'quantity' => $allocQty,
                            'unit_cost' => $unitCost,
                            'balance_after' => $runningBalance,
                            'reference_type' => 'return_note',
                            'reference_id' => $note->id,
                            'performed_by_user_id' => $performedById,
                            'moved_at' => now(),
                        ]);

                        $lineValue += $allocQty * $unitCost;
                        $unitCostForLine = $unitCost;

                        continue;
                    }

                    $allocQty = (float) $alloc['qty'];
                    $unitCost = (float) $alloc['unit_price'];
                    $runningBalance += $allocQty;

                    StockMovement::create([
                        'stock_item_id' => $stockItem->id,
                        'stock_item_price_id' => $alloc['batch_id'],
                        'movement_type' => StockMovement::TYPE_RETURN,
                        'quantity' => $allocQty,
                        'unit_cost' => $unitCost,
                        'balance_after' => $runningBalance,
                        'reference_type' => 'return_note',
                        'reference_id' => $note->id,
                        'performed_by_user_id' => $performedById,
                        'moved_at' => now(),
                    ]);

                    $lineValue += $allocQty * $unitCost;
                }

                if ($allocations !== [] && $qtyReturned > 0) {
                    $unitCostForLine = round($lineValue / (float) $qtyReturned, 4);
                }

                $stockItem->increment('qty', $qtyReturned);

                $stockItem->refresh();
                $stockItem->update(['last_moved_at' => now()->toDateString()]);
                $stockItem->recalculateAndSaveStatus();

                $line->increment('qty_returned', $qtyReturned);

                $stockUpdates[] = [
                    'stock_item_code' => $line->stock_item_code,
                    'name' => $line->name,
                    'qty_returned' => $qtyReturned,
                    'qty_before' => (int) $qtyBefore,
                    'qty_after' => (int) $runningBalance,
                    'unit_cost' => $unitCostForLine,
                    'line_value' => round($lineValue, 2),
                ];

                if ($bomItem) {
                    BomItem::where('id', $bomItem->id)->increment('returned_qty', $qtyReturned);
                }
            }

            $this->syncCaseIssueCostAfterReturn($note);

            $note->refresh()->load('lines');

            $allComplete = $note->lines->every(
                fn (ReturnNoteLine $l) => $l->qty_returned >= $l->qty_requested
            );

            $note->update([
                'status' => $allComplete ? ReturnNote::STATUS_COMPLETED : ReturnNote::STATUS_PARTIAL,
                'completed_at' => $allComplete ? now() : null,
            ]);

            $stockAfter = [];
            foreach (array_keys($stockBefore) as $code) {
                $item = StockItem::findByOperationalCode($code);
                if ($item) {
                    $stockAfter[$code] = ['qty' => $item->qty];
                }
            }

            AuditService::log(
                action: 'return',
                description: "ارتجاع مواد — {$note->return_no}",
                tag: 'warehouse',
                before: $stockBefore,
                after: $stockAfter,
            );

            return [
                'note' => $note->fresh()->load('lines'),
                'stock_updates' => $stockUpdates,
            ];
        });
    }

    /** تكلفة الارتجاع = WAC وقت الصرف (نفس مصدر حركة issue) — لا أعلى سعر شراء. */
    private function returnUnitCost(?BomItem $bomItem, string $stockItemCode): float
    {
        $dispenseCost = (float) ($bomItem?->unit_cost ?? 0);

        if ($dispenseCost > 0) {
            return round($dispenseCost, 4);
        }

        return round($this->stockPriceService->wacUnitPrice($stockItemCode), 4);
    }

    /** يُحدّث تكلفة الصرف على الحالة بعد إرجاع مواد للمخزون. */
    private function syncCaseIssueCostAfterReturn(ReturnNote $note): void
    {
        if (! $note->case_id || ! $note->bom_id) {
            return;
        }

        $bom = Bom::with('items')->find($note->bom_id);
        if (! $bom) {
            return;
        }

        $netIssueCost = round($bom->items->sum(
            fn (BomItem $item) => max(0, $item->issued_qty - $item->returned_qty) * (float) $item->unit_cost
        ), 2);

        CaseRecord::whereKey($note->case_id)->update(['issue_cost' => $netIssueCost]);
    }

    private function nextReturnNo(): string
    {
        $last = ReturnNote::lockForUpdate()
            ->orderByDesc('id')
            ->value('return_no');

        $num = $last && preg_match('/RTN-(\d+)/', $last, $m)
            ? ((int) $m[1]) + 1
            : 1;

        return sprintf('RTN-%04d', $num);
    }
}
