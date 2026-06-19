<?php

namespace App\Services;

use App\Enums\WorkflowEvent;
use App\Exceptions\BarcodeDispenseMismatchException;
use App\Models\Bom;
use App\Models\BomItem;
use App\Models\CaseRecord;
use App\Models\StockItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * دورة حياة BOM — إنشاء، صرف بالباركود، مراحل التصنيع، إغلاق.
 */
class BomService
{
    /** @var array<string, string> */
    private const MFG_SEQUENCE = [
        CaseRecord::MFG_WAREHOUSE  => CaseRecord::MFG_ISSUE,
        CaseRecord::MFG_ISSUE      => CaseRecord::MFG_GENERATION,
        CaseRecord::MFG_GENERATION => CaseRecord::MFG_ASSEMBLY,
        CaseRecord::MFG_ASSEMBLY   => CaseRecord::MFG_CASTING,
        CaseRecord::MFG_CASTING    => CaseRecord::MFG_FINISHING,
    ];

    public function __construct(
        private readonly BarcodeValidationService $barcodeValidation,
        private readonly StockPriceService $stockPriceService,
        private readonly WorkflowService $workflowService,
    ) {
    }

    /**
     * إنشاء BOM (raw) وحجز الكميات المطلوبة.
     *
     * @param  list<array{stock_item_code: string, name?: string, qty: int}>  $items
     */
    public function create(CaseRecord $case, array $items): Bom
    {
        return DB::transaction(function () use ($case, $items) {
            $case = CaseRecord::lockForUpdate()->findOrFail($case->id);

            if ($case->stage_key !== CaseRecord::STAGE_MANUFACTURING) {
                abort(422, 'الحالة ليست في مرحلة التصنيع.');
            }

            if (! $case->isMilitary() && empty($case->work_order_no)) {
                abort(422, 'لا يمكن إنشاء BOM — أمر الشغل غير موجود (مسار مدني).');
            }

            if (Bom::where('case_id', $case->id)->exists()) {
                abort(422, 'توجد قائمة مواد تشغيل لهذه الحالة بالفعل.');
            }

            if ($items === []) {
                abort(422, 'يجب إضافة بند واحد على الأقل.');
            }

            $case->load('patient:id,name');

            $bom = Bom::create([
                'bom_no'       => $this->nextBomNo(),
                'case_id'      => $case->id,
                'order_ref'    => $case->order_ref,
                'quote_no'     => $case->quote_no,
                'patient_name' => $case->patient?->name ?? '—',
                'stage'        => Bom::STAGE_RAW,
            ]);

            foreach ($items as $row) {
                $code = $row['stock_item_code'];
                $qty  = (int) $row['qty'];

                $stockItem = StockItem::where('code', $code)->lockForUpdate()->first();

                if (! $stockItem) {
                    abort(422, "الصنف غير موجود: {$code}");
                }

                if ($stockItem->availableQty() < $qty) {
                    abort(422, "الكمية غير كافية للصنف {$code} — متاح: {$stockItem->availableQty()}");
                }

                $unitCost = $this->stockPriceService->highestUnitPrice($code);

                BomItem::create([
                    'bom_id'           => $bom->id,
                    'stock_item_code'  => $code,
                    'name'             => $row['name'] ?? $stockItem->name,
                    'qty'              => $qty,
                    'unit_cost'        => $unitCost,
                    'issued_qty'       => 0,
                    'returned_qty'     => 0,
                ]);

                $stockItem->increment('reserved', $qty);
            }

            AuditService::log(
                action:      'create',
                description: "إنشاء BOM {$bom->bom_no}",
                tag:         'warehouse',
                after:       $bom->load('items')->toArray(),
            );

            return $bom;
        });
    }

    /**
     * التحقق من الباركود وصرف المواد إلى WIP.
     *
     * @param  list<string>  $scannedBarcodes
     */
    public function releaseToWip(Bom $bom, array $scannedBarcodes): Bom
    {
        return DB::transaction(function () use ($bom, $scannedBarcodes) {
            $bom = Bom::lockForUpdate()->with(['items', 'caseRecord'])->findOrFail($bom->id);

            if ($bom->stage !== Bom::STAGE_RAW) {
                abort(422, 'BOM ليست في مرحلة raw — لا يمكن الصرف.');
            }

            $items = $bom->items;

            if (count($scannedBarcodes) !== $items->count()) {
                throw BarcodeDispenseMismatchException::forItem('عدد الباركود لا يطابق بنود BOM');
            }

            $remaining = $scannedBarcodes;
            $stockBefore = [];

            foreach ($items as $bomItem) {
                $matchedBarcode = null;

                foreach ($remaining as $idx => $barcode) {
                    if ($this->barcodeValidation->validateScan($barcode, $bomItem)) {
                        $matchedBarcode = $barcode;
                        unset($remaining[$idx]);
                        break;
                    }
                }

                if ($matchedBarcode === null) {
                    throw BarcodeDispenseMismatchException::forItem($bomItem->stock_item_code);
                }

                $stockItem = StockItem::where('code', $bomItem->stock_item_code)
                    ->lockForUpdate()
                    ->firstOrFail();

                $stockBefore[$stockItem->code] = [
                    'qty'      => $stockItem->qty,
                    'reserved' => $stockItem->reserved,
                ];

                if ($stockItem->qty < $bomItem->qty) {
                    abort(422, "رصيد غير كافٍ للصنف {$stockItem->code}");
                }

                if ($stockItem->qty - $stockItem->reserved < 0) {
                    abort(422, "رصيد متاح سالب للصنف {$stockItem->code}");
                }
            }

            $performedById = Auth::id();

            foreach ($items as $bomItem) {
                $stockItem = StockItem::where('code', $bomItem->stock_item_code)
                    ->lockForUpdate()
                    ->firstOrFail();

                $qty         = $bomItem->qty;
                $balanceAfter = $stockItem->qty - $qty;

                StockMovement::create([
                    'stock_item_id'        => $stockItem->id,
                    'movement_type'        => StockMovement::TYPE_ISSUE,
                    'quantity'             => -$qty,
                    'unit_cost'            => $bomItem->unit_cost,
                    'balance_after'        => $balanceAfter,
                    'reference_type'       => 'bom',
                    'reference_id'         => $bom->id,
                    'performed_by_user_id' => $performedById,
                    'moved_at'             => now(),
                ]);

                $stockItem->decrement('qty', $qty);
                $stockItem->decrement('reserved', $qty);

                $newQty = $stockItem->fresh()->qty;
                $stockItem->update([
                    'last_moved_at' => now()->toDateString(),
                    'status'        => $newQty <= StockItem::LOW_QTY_THRESHOLD
                        ? StockItem::STATUS_LOW
                        : StockItem::STATUS_OK,
                ]);

                $bomItem->update(['issued_qty' => $qty]);
            }

            $bom->update([
                'stage'       => Bom::STAGE_WIP,
                'released_at' => now(),
            ]);

            $case = $bom->caseRecord;
            if ($case && $case->manufacturing_stage === CaseRecord::MFG_WAREHOUSE) {
                $this->advanceManufacturingStage($case, CaseRecord::MFG_ISSUE);
            }

            $stockAfter = [];
            foreach ($items as $bomItem) {
                $stockItem = StockItem::where('code', $bomItem->stock_item_code)->first();
                if ($stockItem) {
                    $stockAfter[$stockItem->code] = [
                        'qty'      => $stockItem->qty,
                        'reserved' => $stockItem->reserved,
                    ];
                }
            }

            AuditService::log(
                action:      'dispense',
                description: "صرف BOM بالباركود — {$bom->bom_no}",
                tag:         'warehouse',
                before:      $stockBefore,
                after:       $stockAfter,
            );

            return $bom->fresh()->load('items');
        });
    }

    /**
     * تقدم مرحلة التصنيع الفرعية — تسلسل ثابت.
     */
    public function advanceManufacturingStage(CaseRecord $case, string $newStage): CaseRecord
    {
        return DB::transaction(function () use ($case, $newStage) {
            $case = CaseRecord::lockForUpdate()->findOrFail($case->id);

            if ($case->stage_key !== CaseRecord::STAGE_MANUFACTURING) {
                abort(422, 'الحالة ليست في مرحلة التصنيع.');
            }

            $current = $case->manufacturing_stage;
            $allowed = self::MFG_SEQUENCE[$current] ?? null;

            if ($allowed !== $newStage) {
                abort(422, "انتقال غير مسموح: {$current} → {$newStage}");
            }

            $before = ['manufacturing_stage' => $current];

            $case->update(['manufacturing_stage' => $newStage]);

            AuditService::log(
                action:      'stage',
                description: 'تقدم مرحلة التصنيع',
                tag:         'operations',
                before:      $before,
                after:       ['manufacturing_stage' => $newStage],
            );

            return $case->fresh();
        });
    }

    /**
     * إغلاق BOM كـ finished وتقديم الحالة إلى ready_delivery.
     */
    public function closeFinished(Bom $bom): Bom
    {
        return DB::transaction(function () use ($bom) {
            $bom = Bom::lockForUpdate()->with(['items', 'caseRecord'])->findOrFail($bom->id);

            if ($bom->stage !== Bom::STAGE_WIP) {
                abort(422, 'BOM ليست في مرحلة wip — لا يمكن الإغلاق.');
            }

            foreach ($bom->items as $item) {
                if ($item->issued_qty <= 0) {
                    abort(422, "بند غير مصروف: {$item->stock_item_code}");
                }
            }

            $before = ['stage' => $bom->stage, 'case_stage' => $bom->caseRecord?->stage_key];

            $bom->update([
                'stage'       => Bom::STAGE_FINISHED,
                'finished_at' => now(),
            ]);

            $case = $bom->caseRecord;

            if ($case) {
                $this->workflowService->advance($case->fresh(), WorkflowEvent::BomFinished->value);
            }

            AuditService::log(
                action:      'finish',
                description: "إغلاق BOM — تام — {$bom->bom_no}",
                tag:         'warehouse',
                before:      $before,
                after:       [
                    'stage'      => Bom::STAGE_FINISHED,
                    'case_stage' => CaseRecord::STAGE_READY_DELIVERY,
                ],
            );

            return $bom->fresh()->load('items');
        });
    }

    /**
     * بوابة التسليم — Task 10 يستدعي هذا قبل التسليم.
     */
    public function canDeliver(CaseRecord $case): bool
    {
        $case->loadMissing('bom');

        return $case->stage_key === CaseRecord::STAGE_READY_DELIVERY
            && $case->bom?->stage === Bom::STAGE_FINISHED;
    }

    private function nextBomNo(): string
    {
        $last = Bom::lockForUpdate()
            ->orderByDesc('id')
            ->value('bom_no');

        $num = $last && preg_match('/BOM-(\d+)/', $last, $m)
            ? ((int) $m[1]) + 1
            : 1;

        return sprintf('BOM-%04d', $num);
    }
}
