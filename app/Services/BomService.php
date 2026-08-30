<?php

namespace App\Services;

use App\Enums\PricingRequestStatus;
use App\Enums\WorkflowEvent;
use App\Exceptions\BarcodeDispenseMismatchException;
use App\Exceptions\InsufficientStockException;
use App\Models\Bom;
use App\Models\BomItem;
use App\Models\CaseRecord;
use App\Models\PricingRequest;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Support\BomItemAggregator;
use App\Support\StockQuantity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * دورة حياة BOM — إنشاء، صرف بالباركود، مراحل التصنيع، إغلاق.
 */
class BomService
{
    /** @var array<string, string> */
    private const MFG_SEQUENCE = [
        CaseRecord::MFG_WAREHOUSE => CaseRecord::MFG_ISSUE,
        CaseRecord::MFG_ISSUE => CaseRecord::MFG_GENERATION,
        CaseRecord::MFG_GENERATION => CaseRecord::MFG_ASSEMBLY,
        CaseRecord::MFG_ASSEMBLY => CaseRecord::MFG_CASTING,
        CaseRecord::MFG_CASTING => CaseRecord::MFG_FINISHING,
    ];

    public function __construct(
        private readonly BarcodeValidationService $barcodeValidation,
        private readonly StockPriceService $stockPriceService,
        private readonly PriceBatchDispenseService $priceBatchDispenseService,
        private readonly WorkflowService $workflowService,
        private readonly WorkOrderService $workOrderService,
        private readonly FinancialPostingService $financialPostingService,
    ) {}

    /**
     * إنشاء BOM (raw) وحجز الكميات المطلوبة.
     *
     * @param  list<array{stock_item_code: string, name?: string, qty: int}>  $items
     */
    public function create(CaseRecord $case, array $items): Bom
    {
        try {
            return $this->doCreate($case, $items);
        } catch (InsufficientStockException $e) {
            // Runs OUTSIDE the rolled-back transaction — this commit succeeds
            if ($e->pricingRequestId) {
                PricingRequest::where('id', $e->pricingRequestId)
                    ->update(['status_key' => PricingRequestStatus::Insufficient->value]);

                AuditService::log(
                    action: 'insufficient',
                    description: "فشل فحص المخزون عند إنشاء BOM — الصنف: {$e->stockItemCode}",
                    tag: 'pricing',
                    after: [
                        'pricing_request_id' => $e->pricingRequestId,
                        'missing_code' => $e->stockItemCode,
                        'available' => $e->available,
                        'required' => $e->required,
                    ],
                );
            }

            abort(422, $e->getMessage());
        }
    }

    /**
     * BOM خام من التوصيف الفني — حجز backorder مسموح (متاح سالب عند نقص الرصيد).
     *
     * @param  list<array{stock_item_code: string, name?: string, qty: int}>  $items
     */
    public function createSpecRaw(CaseRecord $case, array $items): Bom
    {
        if ($items === []) {
            abort(422, 'يجب إضافة بند واحد على الأقل.');
        }

        return DB::transaction(function () use ($case, $items) {
            $case = CaseRecord::lockForUpdate()->findOrFail($case->id);

            $existing = Bom::where('case_id', $case->id)->first();

            if ($existing) {
                if ($existing->stock_reserved_at) {
                    $this->releaseBomReservation($existing);
                }

                $existing->items()->delete();
                $existing->update(['stage' => Bom::STAGE_RAW]);
                $bom = $existing;
            } else {
                $case->load('patient:id,name');

                $bom = Bom::create([
                    'bom_no' => $this->nextBomNo(),
                    'case_id' => $case->id,
                    'order_ref' => $case->order_ref,
                    'quote_no' => $case->quote_no,
                    'patient_name' => $case->patient?->name ?? '—',
                    'stage' => Bom::STAGE_RAW,
                ]);
            }

            foreach ($items as $row) {
                $code = $row['stock_item_code'];
                $stockItem = StockItem::findByOperationalCode($code);

                if (! $stockItem) {
                    abort(422, "الصنف غير موجود: {$code}");
                }

                $qty = $this->parseBomItemQty($row, $stockItem);

                BomItem::create([
                    'bom_id' => $bom->id,
                    'stock_item_code' => $code,
                    'name' => $row['name'] ?? $code,
                    'source' => BomItem::SOURCE_SPEC,
                    'qty' => $qty,
                    'group_label' => $row['group_label'] ?? null,
                    'unit_cost' => 0,
                    'issued_qty' => 0,
                    'returned_qty' => 0,
                ]);
            }

            AuditService::log(
                action: 'create',
                description: "BOM خام من التوصيف — {$bom->bom_no}",
                tag: 'spec',
                after: $bom->load('items')->only(['id', 'bom_no', 'case_id', 'stage']),
            );

            $this->reserveBackorderForBom($bom);

            return $bom->fresh()->load('items');
        });
    }

    /**
     * حجز بنود BOM كطلب توريد — يُسمح بتجاوز الرصيد (متاح سالب / backorder عند العجز).
     */
    public function reserveBackorderForBom(Bom $bom): void
    {
        $bom->loadMissing('items');

        foreach ($bom->items as $bomItem) {
            $stockItem = StockItem::findByOperationalCode($bomItem->stock_item_code, true);

            if (! $stockItem) {
                abort(422, "الصنف غير موجود: {$bomItem->stock_item_code}");
            }

            $stockItem->increment('reserved', $bomItem->qty);
        }

        $bom->update(['stock_reserved_at' => now()]);

        AuditService::log(
            action: 'reserve',
            description: "حجز/طلب توريد من التوصيف — {$bom->bom_no}",
            tag: 'spec',
            after: [
                'bom_id' => $bom->id,
                'items' => $bom->items->map(fn (BomItem $i) => [
                    'code' => $i->stock_item_code,
                    'qty' => $i->qty,
                ])->values()->all(),
            ],
        );
    }

    /**
     * عكس تأثير حجز BOM على reserved.
     */
    private function reverseReservedDelta(StockItem $stockItem, float|int $qty): void
    {
        $stockItem->decrement('reserved', $qty);
    }

    /**
     * إلغاء حجز BOM سابق قبل إعادة بناء بنود التوصيف.
     */
    public function releaseBomReservation(Bom $bom): void
    {
        $bom->loadMissing('items');

        foreach ($bom->items as $bomItem) {
            $stockItem = StockItem::findByOperationalCode($bomItem->stock_item_code, true);

            if (! $stockItem) {
                continue;
            }

            $this->reverseReservedDelta($stockItem, $bomItem->qty);
        }

        $bom->update(['stock_reserved_at' => null]);
    }

    /**
     * استبدال بنود الفني فقط في BOM — مع إعادة حساب الحجز دون مساس ببنود المعدلات.
     *
     * @param  list<array{stock_item_code: string, name?: string, qty: int}>  $items
     */
    public function replaceSpecSourceItems(CaseRecord $case, array $items): Bom
    {
        if ($items === []) {
            abort(422, 'يجب إضافة بند واحد على الأقل.');
        }

        return DB::transaction(function () use ($case, $items) {
            $bom = Bom::where('case_id', $case->id)->lockForUpdate()->first();

            if (! $bom) {
                abort(422, 'لا توجد قائمة مواد لهذه الحالة.');
            }

            if ($bom->stage !== Bom::STAGE_RAW) {
                abort(422, 'لا يمكن تعديل بنود التوصيف — قائمة المواد لم تعد في مرحلة الإعداد.');
            }

            $specItems = $bom->items()->where('source', BomItem::SOURCE_SPEC)->get();

            foreach ($specItems as $bomItem) {
                $stockItem = StockItem::findByOperationalCode($bomItem->stock_item_code, true);

                if ($stockItem) {
                    $this->reverseReservedDelta($stockItem, $bomItem->qty);
                }
            }

            $bom->items()->where('source', BomItem::SOURCE_SPEC)->delete();

            foreach ($items as $row) {
                $code = $row['stock_item_code'];
                $stockItem = StockItem::findByOperationalCode($code);

                if (! $stockItem) {
                    abort(422, "الصنف غير موجود: {$code}");
                }

                $qty = $this->parseBomItemQty($row, $stockItem);

                BomItem::create([
                    'bom_id' => $bom->id,
                    'stock_item_code' => $code,
                    'name' => $row['name'] ?? $code,
                    'source' => BomItem::SOURCE_SPEC,
                    'qty' => $qty,
                    'unit_cost' => 0,
                    'issued_qty' => 0,
                    'returned_qty' => 0,
                ]);
            }

            $bom->load('items');

            foreach ($bom->items->where('source', BomItem::SOURCE_SPEC) as $bomItem) {
                $stockItem = StockItem::findByOperationalCode($bomItem->stock_item_code, true);

                if ($stockItem) {
                    $stockItem->increment('reserved', $bomItem->qty);
                }
            }

            if (! $bom->stock_reserved_at) {
                $bom->update(['stock_reserved_at' => now()]);
            }

            AuditService::log(
                action: 'update',
                description: "تحديث بنود التوصيف في BOM — {$bom->bom_no}",
                tag: 'spec',
                after: [
                    'bom_id' => $bom->id,
                    'items' => $items,
                ],
            );

            return $bom->fresh()->load('items');
        });
    }

    /**
     * إضافة بنود مستشار المعدلات إلى نفس الـ BOM — بدون مساس بالبنود الأصلية (الفني).
     * البنود الأصلية source=spec للقراءة فقط؛ المضافة source=adjustment.
     *
     * @param  list<array{stock_item_code: string, name?: string, qty: int}>  $items
     */
    public function appendAdjustmentItems(CaseRecord $case, array $items): Bom
    {
        if ($items === []) {
            abort(422, 'يجب إضافة بند واحد على الأقل.');
        }

        return DB::transaction(function () use ($case, $items) {
            $bom = Bom::where('case_id', $case->id)->lockForUpdate()->first();

            if (! $bom) {
                abort(422, 'لا توجد قائمة مواد لهذه الحالة بعد.');
            }

            if ($bom->stage !== Bom::STAGE_RAW) {
                abort(422, 'لا يمكن إضافة بنود — قائمة المواد لم تعد في مرحلة الإعداد.');
            }

            foreach ($items as $row) {
                $code = $row['stock_item_code'];
                $stockItem = StockItem::findByOperationalCode($code, true);

                if (! $stockItem) {
                    abort(422, "الصنف غير موجود: {$code}");
                }

                $qty = $this->parseBomItemQty($row, $stockItem);

                $existingAdj = $bom->items->first(
                    fn (BomItem $i) => $i->stock_item_code === $code && $i->source === BomItem::SOURCE_ADJUSTMENT
                );

                // يُسمح بتجاوز الرصيد — يتحوّل الفائض إلى متاح سالب (backorder).
                $stockItem->increment('reserved', $qty);

                if ($existingAdj) {
                    $existingAdj->update(['qty' => $existingAdj->qty + $qty]);
                    $bom->load('items');

                    continue;
                }

                BomItem::create([
                    'bom_id' => $bom->id,
                    'stock_item_code' => $code,
                    'name' => $row['name'] ?? $stockItem->name,
                    'source' => BomItem::SOURCE_ADJUSTMENT,
                    'qty' => $qty,
                    'group_label' => $row['group_label'] ?? null,
                    'unit_cost' => 0,
                    'issued_qty' => 0,
                    'returned_qty' => 0,
                ]);
            }

            AuditService::log(
                action: 'update',
                description: "إضافة بنود مستشار المعدلات — {$bom->bom_no}",
                tag: 'spec',
                after: ['bom_id' => $bom->id, 'added' => count($items)],
            );

            return $bom->fresh()->load('items');
        });
    }

    /**
     * استبدال بنود المعدلات فقط — دون مساس ببنود التوصيف الفني.
     *
     * @param  list<array{stock_item_code: string, name?: string, qty: int}>  $items
     */
    public function replaceAdjustmentSourceItems(CaseRecord $case, array $items): Bom
    {
        return DB::transaction(function () use ($case, $items) {
            $bom = Bom::where('case_id', $case->id)->lockForUpdate()->first();

            if (! $bom) {
                abort(422, 'لا توجد قائمة مواد لهذه الحالة.');
            }

            if ($bom->stage !== Bom::STAGE_RAW) {
                abort(422, 'لا يمكن تعديل بنود المعدلات — قائمة المواد لم تعد في مرحلة الإعداد.');
            }

            $bom->loadMissing('items');

            foreach ($bom->items->where('source', BomItem::SOURCE_ADJUSTMENT) as $old) {
                if ($stockItem = StockItem::findByOperationalCode($old->stock_item_code, true)) {
                    $this->reverseReservedDelta($stockItem, $old->qty);
                }
            }

            $bom->items()->where('source', BomItem::SOURCE_ADJUSTMENT)->delete();

            foreach ($items as $row) {
                $code = $row['stock_item_code'];
                $stockItem = StockItem::findByOperationalCode($code, true);

                if (! $stockItem) {
                    abort(422, "الصنف غير موجود: {$code}");
                }

                $qty = $this->parseBomItemQty($row, $stockItem);

                // يُسمح بتجاوز الرصيد — متاح سالب (backorder).
                $stockItem->increment('reserved', $qty);

                BomItem::create([
                    'bom_id' => $bom->id,
                    'stock_item_code' => $code,
                    'name' => $row['name'] ?? $code,
                    'source' => BomItem::SOURCE_ADJUSTMENT,
                    'qty' => $qty,
                    'unit_cost' => 0,
                    'issued_qty' => 0,
                    'returned_qty' => 0,
                ]);
            }

            AuditService::log(
                action: 'update',
                description: "تحديث بنود المعدلات في BOM — {$bom->bom_no}",
                tag: 'spec',
                after: ['bom_id' => $bom->id, 'items' => $items],
            );

            return $bom->fresh()->load('items');
        });
    }

    /**
     * حذف بند أضافه مستشار المعدلات — بنود الفني (source=spec) غير قابلة للحذف.
     */
    public function removeAdjustmentItem(CaseRecord $case, BomItem $item): Bom
    {
        return DB::transaction(function () use ($case, $item) {
            $bom = Bom::where('case_id', $case->id)->lockForUpdate()->first();

            if (! $bom || $item->bom_id !== $bom->id) {
                abort(404, 'البند غير مرتبط بهذه الحالة.');
            }

            if ($bom->stage !== Bom::STAGE_RAW) {
                abort(422, 'لا يمكن حذف البند — قائمة المواد لم تعد في مرحلة الإعداد.');
            }

            if ($item->source !== BomItem::SOURCE_ADJUSTMENT) {
                abort(422, 'لا يمكن حذف بنود التوصيف الفني — للقراءة فقط.');
            }

            $snapshot = $item->only(['id', 'stock_item_code', 'name', 'qty', 'source']);

            if ($stockItem = StockItem::findByOperationalCode($item->stock_item_code, true)) {
                $this->reverseReservedDelta($stockItem, $item->qty);
            }

            $item->delete();

            AuditService::log(
                action: 'delete',
                description: "حذف بند مستشار المعدلات — {$bom->bom_no}",
                tag: 'spec',
                before: $snapshot,
                after: ['bom_id' => $bom->id],
            );

            return $bom->fresh()->load('items');
        });
    }

    /**
     * تعديل كمية بند من بنود مستشار المعدلات — بنود الفني (source=spec) غير قابلة للتعديل.
     * يضبط الحجز (reserved) بفرق الكمية فقط ويمنع تجاوز المتاح.
     */
    public function updateAdjustmentItemQty(CaseRecord $case, BomItem $item, float $newQty): Bom
    {
        return DB::transaction(function () use ($case, $item, $newQty) {
            $bom = Bom::where('case_id', $case->id)->lockForUpdate()->first();

            if (! $bom || $item->bom_id !== $bom->id) {
                abort(404, 'البند غير مرتبط بهذه الحالة.');
            }

            if ($bom->stage !== Bom::STAGE_RAW) {
                abort(422, 'لا يمكن تعديل البند — قائمة المواد لم تعد في مرحلة الإعداد.');
            }

            if ($item->source !== BomItem::SOURCE_ADJUSTMENT) {
                abort(422, 'لا يمكن تعديل بنود التوصيف الفني — للقراءة فقط.');
            }

            $newQty = $this->normalizeItemQty($newQty);

            $delta = round($newQty - (float) $item->qty, 3);

            if (abs($delta) < 0.0005) {
                return $bom->fresh()->load('items');
            }

            $stockItem = StockItem::findByOperationalCode($item->stock_item_code, true);

            if (! $stockItem) {
                abort(422, "الصنف غير موجود: {$item->stock_item_code}");
            }

            // يُسمح بتجاوز الرصيد — الزيادة تحجز أكثر من المتاح (backorder).
            $before = $item->only(['id', 'stock_item_code', 'name', 'qty', 'source']);

            $stockItem->increment('reserved', $delta);
            $item->update(['qty' => $newQty]);

            AuditService::log(
                action: 'update',
                description: "تعديل كمية بند المعدلات — {$bom->bom_no} / {$item->stock_item_code}",
                tag: 'spec',
                before: $before,
                after: $item->only(['id', 'stock_item_code', 'name', 'qty', 'source']),
            );

            return $bom->fresh()->load('items');
        });
    }

    /**
     * حجز كميات BOM في سجل المخزون عند اعتماد مكتب التشغيل (الخطوة 7).
     * يضبط تكلفة الوحدة على WAC (أساس التكلفة الداخلية) ويزيد reserved.
     * يرمي InsufficientStockException عند نقص الرصيد — يتعامل معها المنادي.
     */
    public function reserveForCase(CaseRecord $case): void
    {
        $bom = Bom::with('items')->where('case_id', $case->id)->lockForUpdate()->first();

        if (! $bom || $bom->items->isEmpty()) {
            abort(422, 'لا توجد قائمة مواد لحجزها.');
        }

        if ($bom->stock_reserved_at) {
            $this->ensureUnitCosts($bom);

            return;
        }

        foreach ($bom->items as $bomItem) {
            $stockItem = StockItem::findByOperationalCode($bomItem->stock_item_code, true);

            if (! $stockItem) {
                abort(422, "الصنف غير موجود: {$bomItem->stock_item_code}");
            }

            $bomItem->update([
                'unit_cost' => $this->stockPriceService->wacUnitPrice($bomItem->stock_item_code),
            ]);

            $stockItem->increment('reserved', $bomItem->qty);
        }

        $bom->update(['stock_reserved_at' => now()]);

        AuditService::log(
            action: 'reserve',
            description: "حجز مواد فوري عند اعتماد التشغيل — {$bom->bom_no}",
            tag: 'warehouse',
            after: ['bom_id' => $bom->id, 'case_id' => $case->id],
        );
    }

    private function doCreate(CaseRecord $case, array $items): Bom
    {
        return DB::transaction(function () use ($case, $items) {
            $case = CaseRecord::lockForUpdate()->findOrFail($case->id);

            if ($case->stage_key !== CaseRecord::STAGE_MANUFACTURING) {
                abort(422, 'الحالة ليست في مرحلة التصنيع.');
            }

            if (! $case->isMilitary() && empty($case->work_order_no)) {
                abort(422, 'لا يمكن إنشاء BOM — أمر الشغل غير موجود (مسار مدني).');
            }

            if ($existing = Bom::where('case_id', $case->id)->first()) {
                if ($existing->stage !== Bom::STAGE_RAW) {
                    abort(422, 'توجد قائمة مواد تشغيل لهذه الحالة بالفعل.');
                }

                return $this->activateSpecRawBom($existing, $case);
            }

            if ($items === []) {
                abort(422, 'يجب إضافة بند واحد على الأقل.');
            }

            $case->load('patient:id,name');

            $bom = Bom::create([
                'bom_no' => $this->nextBomNo(),
                'case_id' => $case->id,
                'order_ref' => $case->order_ref,
                'quote_no' => $case->quote_no,
                'patient_name' => $case->patient?->name ?? '—',
                'stage' => Bom::STAGE_RAW,
            ]);

            foreach ($items as $row) {
                $this->appendBomItemWithReservation($bom, $row, $case);
            }

            AuditService::log(
                action: 'create',
                description: "إنشاء BOM {$bom->bom_no}",
                tag: 'warehouse',
                after: $bom->load('items')->toArray(),
            );

            return $bom;
        });
    }

    private function activateSpecRawBom(Bom $bom, CaseRecord $case): Bom
    {
        $bom->load('items');

        // BOM القادم من التوصيف يكون قد حجز المواد مسبقاً (reserveBackorderForBom)؛
        // لا نُعيد الحجز حتى لا تُضاعَف الكمية المحجوزة.
        $alreadyReserved = (bool) $bom->stock_reserved_at;

        foreach ($bom->items as $bomItem) {
            $stockItem = StockItem::findByOperationalCode($bomItem->stock_item_code, true);

            if (! $stockItem) {
                abort(422, "الصنف غير موجود: {$bomItem->stock_item_code}");
            }

            $bomItem->update([
                'unit_cost' => $this->stockPriceService->wacUnitPrice($bomItem->stock_item_code),
            ]);

            if (! $alreadyReserved) {
                $stockItem->increment('reserved', $bomItem->qty);
            }
        }

        if (! $alreadyReserved) {
            $bom->update(['stock_reserved_at' => now()]);
        }

        AuditService::log(
            action: 'update',
            description: "تفعيل BOM خام للصرف — {$bom->bom_no}",
            tag: 'warehouse',
            after: ['bom_id' => $bom->id, 'stage' => $bom->stage],
        );

        return $bom->fresh()->load('items');
    }

    /**
     * ضبط تكلفة الوحدة (WAC) لبنود BOM بدون أي حجز إضافي — الحجز يتم في مكتب التشغيل.
     */
    private function ensureUnitCosts(Bom $bom): void
    {
        $bom->loadMissing('items');

        foreach ($bom->items as $bomItem) {
            if ((float) $bomItem->unit_cost > 0) {
                continue;
            }

            $bomItem->update([
                'unit_cost' => $this->stockPriceService->wacUnitPrice($bomItem->stock_item_code),
            ]);
        }
    }

    /**
     * عند الصرف: تثبيت تكلفة الوحدة من دفعات الأسعار المخصصة (FIFO).
     */
    private function stampDispenseUnitCostFromAllocations(BomItem $bomItem, array $allocations): void
    {
        $bomItem->update([
            'unit_cost' => $this->priceBatchDispenseService->weightedUnitCost($allocations),
        ]);
    }

    /**
     * @param  array{stock_item_code: string, name?: string, qty: mixed, qty_uom?: string}  $row
     */
    private function parseBomItemQty(array $row, ?StockItem $stockItem = null): float
    {
        $code = $row['stock_item_code'];
        $stockItem ??= StockItem::findByOperationalCode($code, true);

        if (! $stockItem) {
            abort(422, "الصنف غير موجود: {$code}");
        }

        $uom = $stockItem->uom ?? 'قطعة';

        try {
            $qty = StockQuantity::toItemUom($row['qty'] ?? null, $row['qty_uom'] ?? null, $uom);
        } catch (\InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        if ($qty <= 0) {
            abort(422, 'الكمية يجب أن تكون أكبر من صفر.');
        }

        if (! StockQuantity::isFractionalUom($uom) && $qty < 1) {
            abort(422, 'الكمية يجب أن تكون 1 على الأقل لكل بند.');
        }

        if (! StockQuantity::isFractionalUom($uom) && abs($qty - round($qty)) > 0.0001) {
            abort(422, 'الصنف يُدار بوحدات عدّ — الكمية يجب أن تكون صحيحة.');
        }

        return $qty;
    }

    /**
     * @param  array{stock_item_code: string, name?: string, qty: mixed, qty_uom?: string}  $row
     */
    private function appendBomItemWithReservation(Bom $bom, array $row, CaseRecord $case): void
    {
        $code = $row['stock_item_code'];
        $stockItem = StockItem::findByOperationalCode($code, true);

        if (! $stockItem) {
            abort(422, "الصنف غير موجود: {$code}");
        }

        $qty = $this->parseBomItemQty($row, $stockItem);

        // يُسمح بتجاوز الرصيد — متاح سالب (backorder) بدل رفض الإنشاء.
        // تكلفة BOM = WAC (تقييم مخزني) — أعلى سعر شراء يُستخدم في التسعير فقط.
        $unitCost = $this->stockPriceService->wacUnitPrice($code);

        BomItem::create([
            'bom_id' => $bom->id,
            'stock_item_code' => $code,
            'name' => $row['name'] ?? $stockItem->name,
            'qty' => $qty,
            'unit_cost' => $unitCost,
            'issued_qty' => 0,
            'returned_qty' => 0,
        ]);

        $stockItem->increment('reserved', $qty);
    }

    /**
     * @param  list<string>|list<array{barcode: string, qty?: mixed, qty_uom?: string}>  $dispenseInput
     * @return list<array{barcode: string, qty?: mixed, qty_uom?: string}>
     */
    private function normalizeDispenseInput(array $dispenseInput): array
    {
        $lines = [];

        foreach ($dispenseInput as $row) {
            if (is_string($row)) {
                $barcode = trim($row);
                if ($barcode !== '') {
                    $lines[] = ['barcode' => $barcode];
                }

                continue;
            }

            if (! is_array($row)) {
                continue;
            }

            $barcode = trim((string) ($row['barcode'] ?? ''));
            if ($barcode === '') {
                continue;
            }

            $line = ['barcode' => $barcode];
            if (array_key_exists('qty', $row) && $row['qty'] !== null && $row['qty'] !== '') {
                $line['qty'] = $row['qty'];
            }
            if (isset($row['qty_uom']) && trim((string) $row['qty_uom']) !== '') {
                $line['qty_uom'] = trim((string) $row['qty_uom']);
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * @param  list<string>|list<array{barcode: string, qty?: mixed, qty_uom?: string}>  $dispenseInput
     * @return list<array{barcode: string, qty: float, qty_uom: string, stock_item_code: string}>
     */
    public function resolveDispenseLinesForStorage(Bom $bom, array $dispenseInput): array
    {
        $bom->loadMissing('items');
        $resolved = [];

        foreach ($this->normalizeDispenseInput($dispenseInput) as $line) {
            $stockItem = $this->barcodeValidation->resolveStockItem($line['barcode']);
            $code = $stockItem?->operationalCode() ?? trim((string) ($line['stock_item_code'] ?? ''));
            $uom = $stockItem?->uom ?? 'قطعة';
            $qtyInUom = StockQuantity::toItemUom($line['qty'] ?? null, $line['qty_uom'] ?? null, $uom);

            $resolved[] = [
                'barcode' => $line['barcode'],
                'qty' => $qtyInUom,
                'qty_uom' => $uom,
                'stock_item_code' => $code,
            ];
        }

        return $resolved;
    }

    /**
     * التحقق من الباركود والكميات قبل تقديم طلب الصرف (بدون خصم).
     *
     * @param  list<string>|list<array{barcode: string, qty?: mixed, qty_uom?: string}>  $dispenseInput
     */
    public function validateDispenseLines(Bom $bom, array $dispenseInput): void
    {
        $bom->loadMissing(['items', 'caseRecord']);

        if ($bom->stage !== Bom::STAGE_RAW) {
            abort(422, 'BOM ليست في مرحلة raw — لا يمكن الصرف.');
        }

        $rawLines = $this->normalizeDispenseInput($dispenseInput);

        if ($rawLines === []) {
            throw BarcodeDispenseMismatchException::forItem('لا توجد بنود صرف.');
        }

        $groups = BomItemAggregator::groupModels($bom->items);
        $expectedByCode = [];
        foreach ($groups as $code => $rows) {
            $expectedByCode[$code] = (float) $rows->sum('qty');
        }

        $dispensedByCode = [];

        foreach ($rawLines as $line) {
            $barcode = $line['barcode'];
            $stockItem = $this->barcodeValidation->resolveStockItem($barcode);

            if ($stockItem === null) {
                throw BarcodeDispenseMismatchException::forItem($barcode);
            }

            $code = $stockItem->operationalCode();
            $representative = $groups->get($code)?->first();

            if ($representative === null || ! $this->barcodeValidation->validateScan($barcode, $representative)) {
                throw BarcodeDispenseMismatchException::forItem($code ?: $barcode);
            }

            $uom = $stockItem->uom ?? 'قطعة';
            $qtyInUom = StockQuantity::toItemUom($line['qty'] ?? null, $line['qty_uom'] ?? null, $uom);

            if ($qtyInUom <= 0) {
                throw BarcodeDispenseMismatchException::forItem(
                    "كمية غير صالحة للصنف {$code}"
                );
            }

            if (! StockQuantity::isFractionalUom($uom) && abs($qtyInUom - round($qtyInUom)) > 0.0001) {
                throw BarcodeDispenseMismatchException::forItem(
                    "الصنف {$code} يُصرف بوحدات عدّ — الكمية يجب أن تكون صحيحة"
                );
            }

            $dispensedByCode[$code] = ($dispensedByCode[$code] ?? 0.0) + $qtyInUom;
        }

        foreach ($expectedByCode as $code => $expected) {
            $got = $dispensedByCode[$code] ?? 0.0;
            if (abs($got - $expected) > 0.0001) {
                throw BarcodeDispenseMismatchException::forItem($code);
            }
        }

        foreach ($dispensedByCode as $code => $got) {
            if (! isset($expectedByCode[$code])) {
                throw BarcodeDispenseMismatchException::forItem('باركود زائد لا يطابق بنود BOM');
            }
        }
    }

    /**
     * @param  list<string>  $scannedBarcodes
     */
    public function validateDispenseBarcodes(Bom $bom, array $scannedBarcodes): void
    {
        $this->validateDispenseLines($bom, $scannedBarcodes);
    }

    /**
     * التحقق من الباركود وصرف المواد إلى WIP.
     *
     * @param  list<string>|list<array{barcode: string, qty?: mixed, qty_uom?: string}>  $dispenseInput
     */
    public function releaseToWip(Bom $bom, array $dispenseInput): Bom
    {
        return DB::transaction(function () use ($bom, $dispenseInput) {
            $bom = Bom::lockForUpdate()->with(['items', 'caseRecord'])->findOrFail($bom->id);

            $this->validateDispenseLines($bom, $dispenseInput);

            $case = $bom->caseRecord;
            if ($case) {
                app(WorkshopAssignmentService::class)->assertDispenseAllowed($case);
            }

            $bom->refresh()->load('items');

            $groups = BomItemAggregator::groupModels($bom->items);
            $stockBefore = [];
            $performedById = Auth::id();

            foreach ($groups as $code => $rows) {
                $stockItem = StockItem::findByOperationalCode($code, true)
                    ?? abort(422, "الصنف غير موجود: {$code}");

                $stockBefore[$stockItem->code] = [
                    'qty' => $stockItem->qty,
                    'reserved' => $stockItem->reserved,
                ];
            }

            foreach ($groups as $code => $rows) {
                foreach ($rows as $bomItem) {
                    $stockItem = StockItem::whereKey(
                        StockItem::findByOperationalCode($bomItem->stock_item_code, true)?->id
                    )->lockForUpdate()->first()
                        ?? abort(422, "الصنف غير موجود: {$bomItem->stock_item_code}");

                    $qty = (float) $bomItem->qty;
                    $allocations = $this->priceBatchDispenseService->allocateForDispense($stockItem, $qty);
                    $this->priceBatchDispenseService->applyDecrements($allocations);
                    $this->stampDispenseUnitCostFromAllocations($bomItem, $allocations);

                    $runningBalance = (float) $stockItem->qty;

                    foreach ($allocations as $alloc) {
                        $allocQty = (float) $alloc['qty'];
                        $runningBalance -= $allocQty;

                        StockMovement::create([
                            'stock_item_id' => $stockItem->id,
                            'stock_item_price_id' => $alloc['batch_id'],
                            'movement_type' => StockMovement::TYPE_ISSUE,
                            'quantity' => -$allocQty,
                            'unit_cost' => $alloc['unit_price'],
                            'balance_after' => $runningBalance,
                            'reference_type' => 'bom',
                            'reference_id' => $bom->id,
                            'performed_by_user_id' => $performedById,
                            'moved_at' => now(),
                        ]);
                    }

                    $stockItem->decrement('qty', $qty);

                    $reservedRelease = min((float) $stockItem->reserved, $qty);
                    if ($reservedRelease > 0) {
                        $stockItem->decrement('reserved', $reservedRelease);
                    }

                    $stockItem->refresh();
                    $stockItem->update(['last_moved_at' => now()->toDateString()]);
                    $stockItem->recalculateAndSaveStatus();

                    $bomItem->update(['issued_qty' => $qty]);
                }
            }

            $bom->update([
                'stage' => Bom::STAGE_WIP,
                'released_at' => now(),
            ]);

            $case = $bom->caseRecord;
            if ($case) {
                $this->promoteCaseAfterDispense($case);
            }

            $stockAfter = [];
            foreach ($groups as $code => $rows) {
                foreach ($rows as $bomItem) {
                    $stockItem = StockItem::findByOperationalCode($bomItem->stock_item_code);
                    if ($stockItem) {
                        $stockAfter[$stockItem->code] = [
                            'qty' => $stockItem->qty,
                            'reserved' => $stockItem->reserved,
                        ];
                    }
                }
            }

            AuditService::log(
                action: 'dispense',
                description: "صرف BOM بالباركود — {$bom->bom_no}",
                tag: 'warehouse',
                before: $stockBefore,
                after: $stockAfter,
            );

            if ($case) {
                $issueCost = round($bom->items->sum(
                    fn ($item) => (float) $item->qty * (float) $item->unit_cost
                ), 2);
                $case->update(['issue_cost' => $issueCost]);

                $this->financialPostingService->postOnDispense($case->fresh(), $bom->fresh(['items']));
            }

            return $bom->fresh()->load('items');
        });
    }

    /**
     * بعد صرف BOM: توليد WO إن لزم، ونقل الحالة لمكتب التشغيل (manufacturing + issue).
     */
    public function promoteCaseAfterDispense(CaseRecord $case): CaseRecord
    {
        return DB::transaction(function () use ($case) {
            $case = CaseRecord::lockForUpdate()->findOrFail($case->id);

            if (empty($case->work_order_no)) {
                $this->workOrderService->generate($case->fresh());
                $case->refresh();
            }

            if ($case->stage_key === CaseRecord::STAGE_MANUFACTURING) {
                if ($case->manufacturing_stage === CaseRecord::MFG_WAREHOUSE) {
                    $this->workflowService->advance($case, WorkflowEvent::BomDispensed->value);

                    return $case->fresh();
                }

                if ($case->manufacturing_stage === null || $case->manufacturing_stage === '') {
                    $case->update(['manufacturing_stage' => CaseRecord::MFG_ISSUE]);

                    return $case->fresh();
                }

                return $case;
            }

            abort(422, 'لا يمكن صرف المواد — الحالة ليست جاهزة لدخول قسم الإنتاج.');
        });
    }

    /**
     * إصلاح حالات BOM=WIP العالقة في مرحلة المخزن (صُرفت لكن لم تتقدم لمرحلة الإصدار).
     * في الهيكلة الجديدة الصرف لا يتم إلا بعد اعتماد مكتب التشغيل (manufacturing/warehouse).
     */
    public function repairOrphanWipCases(): void
    {
        CaseRecord::query()
            ->whereHas('bom', fn ($q) => $q->where('stage', Bom::STAGE_WIP))
            ->where('stage_key', CaseRecord::STAGE_MANUFACTURING)
            ->where(fn ($q) => $q->where('manufacturing_stage', CaseRecord::MFG_WAREHOUSE)
                ->orWhereNull('manufacturing_stage'))
            ->each(fn (CaseRecord $case) => $this->promoteCaseAfterDispense($case));
    }

    /**
     * يمنع إجراءات قسم الإنتاج قبل صرف المواد من المخزن (BOM خام).
     */
    public function assertReleasedToWorkshop(CaseRecord $case): void
    {
        $case->loadMissing('bom');
        $bomStage = $case->bom?->stage;

        if (! in_array($bomStage, [Bom::STAGE_WIP, Bom::STAGE_FINISHED], true)) {
            abort(422, 'لا يمكن تنفيذ إجراءات قسم الإنتاج قبل صرف المواد وتحويلها من المخزن.');
        }
    }

    /**
     * تقدم مرحلة التصنيع الفرعية — تسلسل ثابت.
     */
    public function advanceManufacturingStage(CaseRecord $case, string $newStage): CaseRecord
    {
        return DB::transaction(function () use ($case, $newStage) {
            $case = CaseRecord::lockForUpdate()->with('bom')->findOrFail($case->id);

            if ($case->stage_key !== CaseRecord::STAGE_MANUFACTURING) {
                abort(422, 'الحالة ليست في مرحلة التصنيع.');
            }

            $this->assertReleasedToWorkshop($case);

            $current = $case->manufacturing_stage;
            $allowed = self::MFG_SEQUENCE[$current] ?? null;

            if ($allowed !== $newStage) {
                abort(422, "انتقال غير مسموح: {$current} → {$newStage}");
            }

            $before = ['manufacturing_stage' => $current];

            $case->update(['manufacturing_stage' => $newStage]);

            AuditService::log(
                action: 'stage',
                description: 'تقدم مرحلة التصنيع',
                tag: 'operations',
                before: $before,
                after: ['manufacturing_stage' => $newStage],
            );

            return $case->fresh();
        });
    }

    /**
     * إتمام التصنيع — إغلاق BOM من مرحلة تحت التشغيل (wip) دون مراحل فرعية.
     */
    public function finish(Bom $bom): Bom
    {
        return DB::transaction(function () use ($bom) {
            $bom->loadMissing('caseRecord');
            $case = $bom->caseRecord;

            if (! $case || $case->stage_key !== CaseRecord::STAGE_MANUFACTURING) {
                abort(422, 'الحالة ليست في مرحلة التصنيع.');
            }

            $this->assertReleasedToWorkshop($case);

            if ($bom->stage !== Bom::STAGE_WIP) {
                abort(422, 'BOM ليست تحت التشغيل — لا يمكن إتمام التصنيع.');
            }

            $case->update([
                'manufacturing_stage' => CaseRecord::MFG_CLOSED,
                'workshop_progress_pct' => 100,
            ]);

            return $this->closeFinished($bom);
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
                'stage' => Bom::STAGE_FINISHED,
                'finished_at' => now(),
            ]);

            $case = $bom->caseRecord;

            if ($case) {
                $this->workflowService->advance($case->fresh(), WorkflowEvent::BomFinished->value);
            }

            AuditService::log(
                action: 'finish',
                description: "إغلاق BOM — تام — {$bom->bom_no}",
                tag: 'warehouse',
                before: $before,
                after: [
                    'stage' => Bom::STAGE_FINISHED,
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

    private function normalizeItemQty(mixed $qty): float
    {
        if (! is_numeric($qty)) {
            abort(422, 'الكمية يجب أن تكون رقماً صحيحاً أو عشرياً.');
        }

        $normalized = round((float) $qty, 3);

        if ($normalized < 0.001) {
            abort(422, 'الكمية يجب أن تكون 0.001 على الأقل لكل بند.');
        }

        return $normalized;
    }
}
