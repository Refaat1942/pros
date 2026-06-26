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
        private readonly WorkOrderService $workOrderService,
        private readonly FinancialPostingService $financialPostingService,
    ) {
    }

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
                    action:      'insufficient',
                    description: "فشل فحص المخزون عند إنشاء BOM — الصنف: {$e->stockItemCode}",
                    tag:         'pricing',
                    after:       [
                        'pricing_request_id' => $e->pricingRequestId,
                        'missing_code'       => $e->stockItemCode,
                        'available'          => $e->available,
                        'required'           => $e->required,
                    ],
                );
            }

            abort(422, $e->getMessage());
        }
    }

    /**
     * BOM خام من التوصيف الفني — بدون حجز مخزني وبدون تكلفة (عمى مالي للفني).
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
                $existing->items()->delete();
                $existing->update(['stage' => Bom::STAGE_RAW]);
                $bom = $existing;
            } else {
                $case->load('patient:id,name');

                $bom = Bom::create([
                    'bom_no'       => $this->nextBomNo(),
                    'case_id'      => $case->id,
                    'order_ref'    => $case->order_ref,
                    'quote_no'     => $case->quote_no,
                    'patient_name' => $case->patient?->name ?? '—',
                    'stage'        => Bom::STAGE_RAW,
                ]);
            }

            foreach ($items as $row) {
                $code = $row['stock_item_code'];
                $qty  = (int) $row['qty'];

                if (! StockItem::where('code', $code)->exists()) {
                    abort(422, "الصنف غير موجود: {$code}");
                }

                BomItem::create([
                    'bom_id'          => $bom->id,
                    'stock_item_code' => $code,
                    'name'            => $row['name'] ?? $code,
                    'source'          => BomItem::SOURCE_SPEC,
                    'qty'             => $qty,
                    'unit_cost'       => 0,
                    'issued_qty'      => 0,
                    'returned_qty'    => 0,
                ]);
            }

            AuditService::log(
                action:      'create',
                description: "BOM خام من التوصيف — {$bom->bom_no}",
                tag:         'spec',
                after:       $bom->load('items')->only(['id', 'bom_no', 'case_id', 'stage']),
            );

            return $bom;
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
                $qty  = (int) $row['qty'];

                $stockItem = StockItem::where('code', $code)->first();

                if (! $stockItem) {
                    abort(422, "الصنف غير موجود: {$code}");
                }

                $alreadyInBom = (int) $bom->items
                    ->where('stock_item_code', $code)
                    ->sum('qty');

                $maxAllowed = $stockItem->availableQty() - $alreadyInBom;

                if ($qty > $maxAllowed) {
                    abort(422, "الكمية المطلوبة ({$qty}) تتجاوز المتاح للصنف {$code} — الحد الأقصى: ".max(0, $maxAllowed).'.');
                }

                $existingAdj = $bom->items->first(
                    fn (BomItem $i) => $i->stock_item_code === $code && $i->source === BomItem::SOURCE_ADJUSTMENT
                );

                if ($existingAdj) {
                    $existingAdj->update(['qty' => $existingAdj->qty + $qty]);
                    $bom->load('items');
                    continue;
                }

                BomItem::create([
                    'bom_id'          => $bom->id,
                    'stock_item_code' => $code,
                    'name'            => $row['name'] ?? $stockItem->name,
                    'source'          => BomItem::SOURCE_ADJUSTMENT,
                    'qty'             => $qty,
                    'unit_cost'       => 0,
                    'issued_qty'      => 0,
                    'returned_qty'    => 0,
                ]);
            }

            AuditService::log(
                action:      'update',
                description: "إضافة بنود مستشار المعدلات — {$bom->bom_no}",
                tag:         'spec',
                after:       ['bom_id' => $bom->id, 'added' => count($items)],
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
            if ($case->stage_key !== CaseRecord::STAGE_ADJUSTMENTS) {
                abort(422, 'الحالة ليست في مرحلة المعدلات.');
            }

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
            $item->delete();

            AuditService::log(
                action:      'delete',
                description: "حذف بند مستشار المعدلات — {$bom->bom_no}",
                tag:         'spec',
                before:      $snapshot,
                after:       ['bom_id' => $bom->id],
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

        foreach ($bom->items as $bomItem) {
            $stockItem = StockItem::where('code', $bomItem->stock_item_code)->lockForUpdate()->first();

            if (! $stockItem) {
                abort(422, "الصنف غير موجود: {$bomItem->stock_item_code}");
            }

            if ($stockItem->availableQty() < $bomItem->qty) {
                throw new InsufficientStockException(
                    stockItemCode:    $bomItem->stock_item_code,
                    required:         $bomItem->qty,
                    available:        $stockItem->availableQty(),
                    pricingRequestId: $case->pricing_request_id,
                );
            }

            $bomItem->update([
                'unit_cost' => $this->stockPriceService->wacUnitPrice($bomItem->stock_item_code),
            ]);

            $stockItem->increment('reserved', $bomItem->qty);
        }

        AuditService::log(
            action:      'reserve',
            description: "حجز مواد فوري عند اعتماد التشغيل — {$bom->bom_no}",
            tag:         'warehouse',
            after:       ['bom_id' => $bom->id, 'case_id' => $case->id],
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
                'bom_no'       => $this->nextBomNo(),
                'case_id'      => $case->id,
                'order_ref'    => $case->order_ref,
                'quote_no'     => $case->quote_no,
                'patient_name' => $case->patient?->name ?? '—',
                'stage'        => Bom::STAGE_RAW,
            ]);

            foreach ($items as $row) {
                $this->appendBomItemWithReservation($bom, $row, $case);
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

    private function activateSpecRawBom(Bom $bom, CaseRecord $case): Bom
    {
        $bom->load('items');

        foreach ($bom->items as $bomItem) {
            $stockItem = StockItem::where('code', $bomItem->stock_item_code)->lockForUpdate()->first();

            if (! $stockItem) {
                abort(422, "الصنف غير موجود: {$bomItem->stock_item_code}");
            }

            if ($stockItem->availableQty() < $bomItem->qty) {
                throw new InsufficientStockException(
                    stockItemCode:    $bomItem->stock_item_code,
                    required:         $bomItem->qty,
                    available:        $stockItem->availableQty(),
                    pricingRequestId: $case->pricing_request_id,
                );
            }

            $bomItem->update([
                'unit_cost' => $this->stockPriceService->highestUnitPrice($bomItem->stock_item_code),
            ]);

            $stockItem->increment('reserved', $bomItem->qty);
        }

        AuditService::log(
            action:      'update',
            description: "تفعيل BOM خام للصرف — {$bom->bom_no}",
            tag:         'warehouse',
            after:       ['bom_id' => $bom->id, 'stage' => $bom->stage],
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
     * @param  array{stock_item_code: string, name?: string, qty: int}  $row
     */
    private function appendBomItemWithReservation(Bom $bom, array $row, CaseRecord $case): void
    {
        $code = $row['stock_item_code'];
        $qty  = (int) $row['qty'];

        $stockItem = StockItem::where('code', $code)->lockForUpdate()->first();

        if (! $stockItem) {
            abort(422, "الصنف غير موجود: {$code}");
        }

        if ($stockItem->availableQty() < $qty) {
            throw new InsufficientStockException(
                stockItemCode:    $code,
                required:         $qty,
                available:        $stockItem->availableQty(),
                pricingRequestId: $case->pricing_request_id,
            );
        }

        $unitCost = $this->stockPriceService->highestUnitPrice($code);

        BomItem::create([
            'bom_id'          => $bom->id,
            'stock_item_code' => $code,
            'name'            => $row['name'] ?? $stockItem->name,
            'qty'             => $qty,
            'unit_cost'       => $unitCost,
            'issued_qty'      => 0,
            'returned_qty'    => 0,
        ]);

        $stockItem->increment('reserved', $qty);
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

            $case = $bom->caseRecord;

            // الحجز تم مسبقاً في مكتب التشغيل — هنا نضمن فقط ضبط تكلفة الوحدة (WAC).
            if ($bom->items->contains(fn ($i) => (float) $i->unit_cost <= 0)) {
                $this->ensureUnitCosts($bom);
                $bom->refresh()->load('items');
            }

            $items  = $bom->items;
            $groups = BomItemAggregator::groupModels($items);

            if (count($scannedBarcodes) !== $groups->count()) {
                throw BarcodeDispenseMismatchException::forItem('عدد الباركود لا يطابق بنود BOM');
            }

            $remaining = $scannedBarcodes;
            $stockBefore = [];

            foreach ($groups as $code => $rows) {
                $representative = $rows->first();
                $matchedBarcode = null;

                foreach ($remaining as $idx => $barcode) {
                    if ($this->barcodeValidation->validateScan($barcode, $representative)) {
                        $matchedBarcode = $barcode;
                        unset($remaining[$idx]);
                        break;
                    }
                }

                if ($matchedBarcode === null) {
                    throw BarcodeDispenseMismatchException::forItem($code);
                }

                $totalQty  = (int) $rows->sum('qty');
                $stockItem = StockItem::where('code', $code)
                    ->lockForUpdate()
                    ->firstOrFail();

                $stockBefore[$stockItem->code] = [
                    'qty'      => $stockItem->qty,
                    'reserved' => $stockItem->reserved,
                ];

                if ($stockItem->qty < $totalQty) {
                    abort(422, "رصيد غير كافٍ للصنف {$stockItem->code}");
                }
            }

            $performedById = Auth::id();

            foreach ($groups as $code => $rows) {
                foreach ($rows as $bomItem) {
                    $stockItem = StockItem::where('code', $bomItem->stock_item_code)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $qty          = $bomItem->qty;
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

                    // BOM خام من التوصيف/seed قد يُصرف بدون حجز مسبق — لا ننقص reserved تحت الصفر.
                    $reservedRelease = min($stockItem->reserved, $qty);
                    if ($reservedRelease > 0) {
                        $stockItem->decrement('reserved', $reservedRelease);
                    }

                    $newQty = $stockItem->fresh()->qty;
                    $stockItem->update([
                        'last_moved_at' => now()->toDateString(),
                        'status'        => $newQty <= StockItem::LOW_QTY_THRESHOLD
                            ? StockItem::STATUS_LOW
                            : StockItem::STATUS_OK,
                    ]);

                    $bomItem->update(['issued_qty' => $qty]);
                }
            }

            $bom->update([
                'stage'       => Bom::STAGE_WIP,
                'released_at' => now(),
            ]);

            $case = $bom->caseRecord;
            if ($case) {
                $this->promoteCaseAfterDispense($case);
            }

            $stockAfter = [];
            foreach ($groups as $code => $rows) {
                foreach ($rows as $bomItem) {
                    $stockItem = StockItem::where('code', $bomItem->stock_item_code)->first();
                    if ($stockItem) {
                        $stockAfter[$stockItem->code] = [
                            'qty'      => $stockItem->qty,
                            'reserved' => $stockItem->reserved,
                        ];
                    }
                }
            }

            AuditService::log(
                action:      'dispense',
                description: "صرف BOM بالباركود — {$bom->bom_no}",
                tag:         'warehouse',
                before:      $stockBefore,
                after:       $stockAfter,
            );

            if ($case) {
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

            abort(422, 'لا يمكن صرف المواد — الحالة ليست جاهزة لدخول الورشة.');
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
     * يمنع إجراءات الورشة قبل صرف المواد من المخزن (BOM خام).
     */
    public function assertReleasedToWorkshop(CaseRecord $case): void
    {
        $case->loadMissing('bom');
        $bomStage = $case->bom?->stage;

        if (! in_array($bomStage, [Bom::STAGE_WIP, Bom::STAGE_FINISHED], true)) {
            abort(422, 'لا يمكن تنفيذ إجراءات الورشة قبل صرف المواد وتحويلها من المخزن.');
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
     * إتمام التصنيع — إغلاق BOM من مرحلة تحت التشغيل (wip) دون مراحل فرعية.
     */
    public function finish(Bom $bom): Bom
    {
        $bom->loadMissing('caseRecord');
        $case = $bom->caseRecord;

        if (! $case || $case->stage_key !== CaseRecord::STAGE_MANUFACTURING) {
            abort(422, 'الحالة ليست في مرحلة التصنيع.');
        }

        $this->assertReleasedToWorkshop($case);

        if ($bom->stage !== Bom::STAGE_WIP) {
            abort(422, 'BOM ليست تحت التشغيل — لا يمكن إتمام التصنيع.');
        }

        $case->update(['manufacturing_stage' => CaseRecord::MFG_CLOSED]);

        return $this->closeFinished($bom);
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
