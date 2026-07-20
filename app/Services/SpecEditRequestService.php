<?php

namespace App\Services;

use App\Enums\SpecEditRequestSource;
use App\Enums\SpecEditRequestStatus;
use App\Enums\WorkflowEvent;
use App\Exceptions\InvalidSpecItemException;
use App\Models\Bom;
use App\Models\BomItem;
use App\Models\CaseRecord;
use App\Models\Quote;
use App\Models\SpecEditRequest;
use App\Models\StockItem;
use App\Models\TechOrderSpec;
use App\Models\TechOrderSpecItem;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Support\ClinicTime;
use App\Support\SpecEditRequestItemDiff;
use Illuminate\Support\Facades\DB;

/**
 * طلبات تعديل بنود التوصيف أو المعدلات — موافقة الإدارة مطلوبة.
 */
class SpecEditRequestService
{
    public function __construct(
        private readonly BomService $bomService,
        private readonly PricingService $pricingService,
        private readonly NotificationService $notifications,
        private readonly WorkflowService $workflowService,
        private readonly ReturnNoteService $returnNoteService,
    ) {}

    public function canRequestSpecEdit(TechOrderSpec $spec): bool
    {
        $spec->loadMissing('caseRecord', 'pendingEditRequest', 'rejectedSpecEditRequest');

        if (! $spec->locked || $spec->pendingEditRequest || $spec->rejectedSpecEditRequest) {
            return false;
        }

        return $spec->caseRecord?->stage_key === CaseRecord::STAGE_ADJUSTMENTS;
    }

    /** @deprecated Use canRequestSpecEdit */
    public function canRequestEdit(TechOrderSpec $spec): bool
    {
        return $this->canRequestSpecEdit($spec);
    }

    public function canRequestAdjustmentEdit(CaseRecord $case): bool
    {
        $case->loadMissing('pendingAdjustmentEditRequest');

        if ($case->pendingAdjustmentEditRequest) {
            return false;
        }

        return $case->stage_key === CaseRecord::STAGE_COST_CALC;
    }

    public function assertNoPendingForCase(CaseRecord $case): void
    {
        $pending = SpecEditRequest::query()
            ->where('case_id', $case->id)
            ->where('status', SpecEditRequestStatus::Pending)
            ->exists();

        if ($pending) {
            abort(422, 'يوجد طلب تعديل معلّق — انتظر موافقة الإدارة أو ارفضه أولاً.');
        }
    }

    /**
     * @param  list<array{stock_item_code: string, name: string, qty: int}>  $items
     */
    public function submitSpecEdit(TechOrderSpec $spec, User $requester, array $items, ?string $techNotes): SpecEditRequest
    {
        if (! $this->canRequestSpecEdit($spec)) {
            $spec->loadMissing('rejectedSpecEditRequest', 'pendingEditRequest');

            if ($spec->rejectedSpecEditRequest) {
                abort(422, 'تم رفض طلب تعديل سابق من الإدارة — لا يمكن إرسال طلب جديد على هذا التوصيف.');
            }

            abort(422, 'لا يمكن طلب تعديل التوصيف — تأكد أن الحالة في المعدلات ولم تُرسَل للتكاليف بعد، ولا يوجد طلب معلّق.');
        }

        $this->validateItems($items, requireAtLeastOne: true);

        $spec->load('items');

        return DB::transaction(function () use ($spec, $requester, $items, $techNotes) {
            $originalItems = $spec->items->map(fn (TechOrderSpecItem $i) => [
                'stock_item_code' => $i->stock_item_code,
                'name' => $i->name,
                'qty' => $i->qty,
            ])->values()->all();

            $request = SpecEditRequest::create([
                'source' => SpecEditRequestSource::Spec,
                'tech_order_spec_id' => $spec->id,
                'case_id' => $spec->case_id,
                'requested_by_user_id' => $requester->id,
                'status' => SpecEditRequestStatus::Pending,
                'original_items' => $originalItems,
                'proposed_items' => $items,
                'original_tech_notes' => $spec->tech_notes,
                'proposed_tech_notes' => $techNotes,
            ]);

            $case = $spec->caseRecord;

            AuditService::log(
                action: 'request',
                description: "طلب تعديل توصيف — {$case?->case_no}",
                tag: 'spec',
                after: [
                    'spec_edit_request_id' => $request->id,
                    'source' => SpecEditRequestSource::Spec->value,
                ],
            );

            $this->notifications->notifyEditRequestSubmitted($request->load('caseRecord.patient', 'requestedBy'));

            return $request;
        });
    }

    /** @deprecated Use submitSpecEdit */
    public function submit(TechOrderSpec $spec, User $requester, array $items, ?string $techNotes): SpecEditRequest
    {
        return $this->submitSpecEdit($spec, $requester, $items, $techNotes);
    }

    /**
     * @param  list<array{stock_item_code: string, name?: string, qty: int}>  $items
     */
    public function submitAdjustmentEdit(CaseRecord $case, User $requester, array $items): SpecEditRequest
    {
        if (! $this->canRequestAdjustmentEdit($case)) {
            abort(422, 'لا يمكن طلب تعديل بنود المعدلات — تأكد أن التكاليف لم تؤكد السعر بعد ولا يوجد طلب معلّق.');
        }

        $this->validateItems($items, requireAtLeastOne: false);

        $case->load(['bom.items', 'techOrderSpec']);

        $spec = $case->techOrderSpec;
        if (! $spec) {
            abort(422, 'لا يوجد توصيف مرتبط بهذه الحالة.');
        }

        return DB::transaction(function () use ($case, $requester, $items, $spec) {
            $originalItems = $this->adjustmentItemsSnapshot($case);

            $request = SpecEditRequest::create([
                'source' => SpecEditRequestSource::Adjustments,
                'tech_order_spec_id' => $spec->id,
                'case_id' => $case->id,
                'requested_by_user_id' => $requester->id,
                'status' => SpecEditRequestStatus::Pending,
                'original_items' => $originalItems,
                'proposed_items' => $this->normalizeItems($items),
                'original_tech_notes' => null,
                'proposed_tech_notes' => null,
            ]);

            AuditService::log(
                action: 'request',
                description: "طلب تعديل بنود المعدلات — {$case->case_no}",
                tag: 'spec',
                after: [
                    'spec_edit_request_id' => $request->id,
                    'source' => SpecEditRequestSource::Adjustments->value,
                ],
            );

            $this->notifications->notifyEditRequestSubmitted($request->load('caseRecord.patient', 'requestedBy'));

            return $request;
        });
    }

    public function approve(SpecEditRequest $request, User $reviewer): SpecEditRequest
    {
        if (! $request->isPending()) {
            abort(422, 'تمت معالجة هذا الطلب مسبقاً.');
        }

        $request->load('techOrderSpec.caseRecord', 'caseRecord');

        return match ($request->source) {
            SpecEditRequestSource::Adjustments => $this->approveAdjustmentEdit($request, $reviewer),
            SpecEditRequestSource::PostWorkOrder => $this->approvePostWorkOrderEdit($request, $reviewer),
            default => $this->approveSpecEdit($request, $reviewer),
        };
    }

    public function reject(
        SpecEditRequest $request,
        User $reviewer,
        ?string $reasonKey = null,
        ?string $notes = null,
    ): SpecEditRequest {
        if (! $request->isPending()) {
            abort(422, 'تمت معالجة هذا الطلب مسبقاً.');
        }

        $reasons = config('spec_edit.rejection_reasons', []);

        if ($reasonKey !== null && $reasonKey !== '' && ! array_key_exists($reasonKey, $reasons)) {
            abort(422, 'سبب الرفض غير صالح.');
        }

        return DB::transaction(function () use ($request, $reviewer, $reasonKey, $notes) {
            $request->update([
                'status' => SpecEditRequestStatus::Rejected,
                'rejection_reason_key' => $reasonKey ?: null,
                'rejection_notes' => $notes,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $case = $request->caseRecord;

            AuditService::log(
                action: 'reject',
                description: "رفض طلب تعديل — {$case?->case_no}",
                tag: 'spec',
                after: [
                    'spec_edit_request_id' => $request->id,
                    'source' => $request->source->value,
                    'reason_key' => $reasonKey,
                ],
            );

            $this->notifications->notifyEditRequestRejected(
                $request->fresh()->load('caseRecord.patient', 'requestedBy.role')
            );

            return $request->fresh(['techOrderSpec.items', 'caseRecord', 'requestedBy', 'reviewedBy']);
        });
    }

    public function pendingCount(): int
    {
        return SpecEditRequest::query()
            ->where('status', SpecEditRequestStatus::Pending)
            ->count();
    }

    /** @return list<array<string, mixed>> */
    public function listForAdmin(?string $status = null, ?string $search = null): array
    {
        return SpecEditRequest::query()
            ->with([
                'techOrderSpec:id,order_ref,patient_name',
                'caseRecord:id,case_no,order_ref,stage_key',
                'requestedBy:id,name',
                'reviewedBy:id,name',
            ])
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->when($search, fn ($q, $term) => $q->where(function ($q) use ($term) {
                $q->whereHas('caseRecord', fn ($q) => $q->where('case_no', 'like', "%{$term}%")
                    ->orWhere('order_ref', 'like', "%{$term}%"))
                    ->orWhereHas('techOrderSpec', fn ($q) => $q->where('patient_name', 'like', "%{$term}%")
                        ->orWhere('order_ref', 'like', "%{$term}%"));
            }))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->limit((int) config('dashboards.table_fetch_limit', 500))
            ->get()
            ->map(fn (SpecEditRequest $row) => $this->format($row))
            ->all();
    }

    /** @return array<string, mixed> */
    public function format(SpecEditRequest $request): array
    {
        $request->loadMissing('techOrderSpec', 'caseRecord', 'requestedBy', 'reviewedBy');

        $originalItems = $request->original_items ?? [];
        $proposedItems = $request->proposed_items ?? [];
        $modifiedItems = SpecEditRequestItemDiff::modifiedItems($originalItems, $proposedItems);

        return [
            'id' => $request->id,
            'source' => $request->source->value,
            'source_label' => $request->source->label(),
            'status' => $request->status->value,
            'status_label' => $request->status->label(),
            'status_badge_class' => $request->status->badgeClass(),
            'case_id' => $request->case_id,
            'case_no' => $request->caseRecord?->case_no,
            'order_ref' => $request->techOrderSpec?->order_ref ?? $request->caseRecord?->order_ref,
            'patient_name' => $request->techOrderSpec?->patient_name,
            'stage_key' => $request->caseRecord?->stage_key,
            'requested_by' => $request->requestedBy?->name,
            'requested_at' => $request->created_at?->toIso8601String(),
            'requested_at_label' => ClinicTime::format($request->created_at),
            'reviewed_by' => $request->reviewedBy?->name,
            'reviewed_at_label' => ClinicTime::format($request->reviewed_at),
            'rejection_reason_key' => $request->rejection_reason_key,
            'rejection_reason_label' => $request->rejectionReasonLabel(),
            'rejection_notes' => $request->rejection_notes,
            'original_items' => $originalItems,
            'proposed_items' => $proposedItems,
            'modified_items' => $modifiedItems,
            'modified_summary' => SpecEditRequestItemDiff::summaryText($modifiedItems),
            'original_tech_notes' => $request->original_tech_notes,
            'proposed_tech_notes' => $request->proposed_tech_notes,
            'tech_order_spec_id' => $request->tech_order_spec_id,
        ];
    }

    private function approveSpecEdit(SpecEditRequest $request, User $reviewer): SpecEditRequest
    {
        $spec = $request->techOrderSpec;
        $case = $request->caseRecord;

        if ($case->stage_key !== CaseRecord::STAGE_ADJUSTMENTS) {
            abort(422, 'الحالة لم تعد في مرحلة المعدلات — لا يمكن تطبيق تعديل التوصيف.');
        }

        return DB::transaction(function () use ($request, $reviewer, $spec, $case) {
            $items = $request->proposed_items ?? [];

            $spec->update(['tech_notes' => $request->proposed_tech_notes]);

            $spec->items()->delete();
            foreach ($items as $row) {
                TechOrderSpecItem::create([
                    'tech_order_spec_id' => $spec->id,
                    'stock_item_code' => $row['stock_item_code'],
                    'name' => $row['name'],
                    'qty' => (int) $row['qty'],
                ]);
            }

            $this->bomService->replaceSpecSourceItems($case, $items);

            $request->update([
                'status' => SpecEditRequestStatus::Approved,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            AuditService::log(
                action: 'approve',
                description: "اعتماد تعديل توصيف — {$case->case_no}",
                tag: 'spec',
                after: ['spec_edit_request_id' => $request->id],
            );

            $this->notifications->notifyEditRequestApproved($request->fresh()->load('caseRecord.patient', 'requestedBy.role'));

            return $request->fresh(['techOrderSpec.items', 'caseRecord', 'requestedBy', 'reviewedBy']);
        });
    }

    private function approveAdjustmentEdit(SpecEditRequest $request, User $reviewer): SpecEditRequest
    {
        $case = $request->caseRecord;

        if ($case->stage_key !== CaseRecord::STAGE_COST_CALC) {
            abort(422, 'التكاليف أكّدت السعر أو تجاوزت المرحلة — لا يمكن تطبيق تعديل المعدلات.');
        }

        return DB::transaction(function () use ($request, $reviewer, $case) {
            $items = $request->proposed_items ?? [];

            $this->bomService->replaceAdjustmentSourceItems($case, $items);

            if ($case->pricing_request_id) {
                $pricing = $case->pricingRequest()->first();
                if ($pricing) {
                    $this->pricingService->syncItemsFromBom($case, $pricing);
                    $this->pricingService->refreshLinePrices($pricing);
                }
            }

            $request->update([
                'status' => SpecEditRequestStatus::Approved,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            AuditService::log(
                action: 'approve',
                description: "اعتماد تعديل بنود المعدلات — {$case->case_no}",
                tag: 'spec',
                after: ['spec_edit_request_id' => $request->id],
            );

            $this->notifications->notifyEditRequestApproved($request->fresh()->load('caseRecord.patient', 'requestedBy.role'));

            return $request->fresh(['techOrderSpec.items', 'caseRecord', 'requestedBy', 'reviewedBy']);
        });
    }

    public function canRequestPostWorkOrderEdit(CaseRecord $case): bool
    {
        $case->loadMissing('bom', 'pendingEditRequest');

        if ($case->pendingEditRequest || ! $case->work_order_no || ! $case->bom) {
            return false;
        }

        if ($case->stage_key !== CaseRecord::STAGE_MANUFACTURING) {
            return false;
        }

        return in_array($case->bom->stage, [Bom::STAGE_RAW, Bom::STAGE_WIP], true);
    }

    /**
     * @param  list<array{stock_item_code: string, name?: string, qty: int}>  $items
     */
    public function submitPostWorkOrderEdit(CaseRecord $case, User $requester, array $items, ?string $techNotes): SpecEditRequest
    {
        if (! $this->canRequestPostWorkOrderEdit($case)) {
            abort(422, 'لا يمكن طلب تعديل التوصيف بعد أمر الشغل في هذه المرحلة.');
        }

        $this->validateItems($items, requireAtLeastOne: true);
        $case->load(['bom.items', 'techOrderSpec.items']);

        $spec = $case->techOrderSpec;
        if (! $spec) {
            abort(422, 'لا يوجد توصيف مرتبط.');
        }

        $beforeSnapshot = [
            'case' => $case->only(['id', 'case_no', 'work_order_no', 'stage_key', 'manufacturing_stage']),
            'bom_stage' => $case->bom->stage,
            'items' => $spec->items->map(fn ($i) => $i->only(['stock_item_code', 'name', 'qty']))->values()->all(),
            'tech_notes' => $spec->tech_notes,
        ];

        return DB::transaction(function () use ($case, $requester, $items, $techNotes, $spec, $beforeSnapshot) {
            $request = SpecEditRequest::create([
                'source' => SpecEditRequestSource::PostWorkOrder,
                'tech_order_spec_id' => $spec->id,
                'case_id' => $case->id,
                'requested_by_user_id' => $requester->id,
                'status' => SpecEditRequestStatus::Pending,
                'original_items' => $beforeSnapshot['items'],
                'proposed_items' => $this->normalizeItems($items),
                'original_tech_notes' => $spec->tech_notes,
                'proposed_tech_notes' => $techNotes,
                'before_snapshot' => $beforeSnapshot,
                'after_snapshot' => [
                    'items' => $this->normalizeItems($items),
                    'tech_notes' => $techNotes,
                ],
            ]);

            AuditService::log(
                action: 'request',
                description: "طلب تعديل توصيف بعد WO — {$case->case_no}",
                tag: 'spec',
                after: ['spec_edit_request_id' => $request->id],
            );

            $this->notifications->notifyEditRequestSubmitted($request->load('caseRecord.patient', 'requestedBy'));

            return $request;
        });
    }

    private function approvePostWorkOrderEdit(SpecEditRequest $request, User $reviewer): SpecEditRequest
    {
        $case = $request->caseRecord;
        $case->loadMissing('bom', 'techOrderSpec', 'quotes');

        if ($case->stage_key !== CaseRecord::STAGE_MANUFACTURING || ! $case->bom) {
            abort(422, 'الحالة لم تعد في مرحلة التصنيع.');
        }

        return DB::transaction(function () use ($request, $reviewer, $case) {
            $bom = $case->bom;
            $dispensed = $bom->stage === Bom::STAGE_WIP;

            if ($dispensed) {
                $diffLines = SpecEditRequestItemDiff::modifiedItems(
                    $request->original_items ?? [],
                    $request->proposed_items ?? [],
                );

                $returnLines = collect($diffLines)
                    ->flatMap(function (array $row) {
                        if (($row['change'] ?? '') === 'removed') {
                            return [[
                                'stock_item_code' => $row['stock_item_code'],
                                'name' => $row['name'] ?? $row['stock_item_code'],
                                'qty' => (int) ($row['qty'] ?? 0),
                            ]];
                        }

                        if (($row['change'] ?? '') === 'updated') {
                            $prev = (int) ($row['previous_qty'] ?? 0);
                            $next = (int) ($row['qty'] ?? 0);
                            if ($prev > $next) {
                                return [[
                                    'stock_item_code' => $row['stock_item_code'],
                                    'name' => $row['name'] ?? $row['stock_item_code'],
                                    'qty' => $prev - $next,
                                ]];
                            }
                        }

                        return [];
                    })
                    ->values()
                    ->all();

                if ($returnLines !== []) {
                    $note = $this->returnNoteService->create(
                        $bom,
                        $returnLines,
                        'ارتجاع مرتبط بتعديل توصيف بعد WO',
                        $reviewer,
                    );
                    $note->update(['spec_edit_request_id' => $request->id]);
                }
            } else {
                $this->bomService->releaseBomReservation($bom);
                $case->update([
                    'work_order_no' => null,
                    'workshop_section_id' => null,
                    'assigned_technician_id' => null,
                    'workshop_assigned_at' => null,
                    'workshop_progress_pct' => 0,
                    'manufacturing_stage' => null,
                ]);
                $this->workflowService->advance($case->fresh(), WorkflowEvent::SpecEditPostWoRollback->value);
            }

            $spec = $request->techOrderSpec;
            $items = $request->proposed_items ?? [];

            if ($spec) {
                $spec->update(['tech_notes' => $request->proposed_tech_notes]);
                $spec->items()->delete();
                foreach ($items as $row) {
                    TechOrderSpecItem::create([
                        'tech_order_spec_id' => $spec->id,
                        'stock_item_code' => $row['stock_item_code'],
                        'name' => $row['name'],
                        'qty' => (int) $row['qty'],
                    ]);
                }
            }

            if (! $dispensed) {
                $this->bomService->replaceSpecSourceItems($case->fresh(), $items);
            }

            $quote = $case->quotes->sortByDesc('id')->first();
            if ($quote && in_array($quote->status, [Quote::STATUS_ISSUED, Quote::STATUS_APPROVED, Quote::STATUS_PENDING], true)) {
                $quote->update(['status' => Quote::STATUS_PENDING]);
            }

            $request->update([
                'status' => SpecEditRequestStatus::Approved,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'after_snapshot' => [
                    'case' => $case->fresh()->only(['id', 'case_no', 'work_order_no', 'stage_key']),
                    'bom_stage' => $case->bom?->fresh()?->stage,
                    'dispensed' => $dispensed,
                ],
            ]);

            AuditService::log(
                action: 'approve',
                description: "اعتماد تعديل توصيف بعد WO — {$case->case_no}",
                tag: 'spec',
                after: ['spec_edit_request_id' => $request->id, 'dispensed' => $dispensed],
            );

            return $request->fresh(['techOrderSpec.items', 'caseRecord', 'requestedBy', 'reviewedBy']);
        });
    }

    /** @return list<array{stock_item_code: string, name: string, qty: int}> */
    private function adjustmentItemsSnapshot(CaseRecord $case): array
    {
        if (! $case->relationLoaded('bom')) {
            $case->load('bom.items');
        }

        return collect($case->bom?->items ?? [])
            ->where('source', BomItem::SOURCE_ADJUSTMENT)
            ->map(fn (BomItem $i) => [
                'stock_item_code' => $i->stock_item_code,
                'name' => $i->name,
                'qty' => $i->qty,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{stock_item_code: string, name?: string, qty: int}>  $items
     * @return list<array{stock_item_code: string, name: string, qty: int}>
     */
    private function normalizeItems(array $items): array
    {
        return collect($items)->map(function (array $item) {
            $code = $item['stock_item_code'] ?? '';
            $stock = StockItem::where('code', $code)->first();

            return [
                'stock_item_code' => $code,
                'name' => $item['name'] ?? $stock?->name ?? $code,
                'qty' => (int) ($item['qty'] ?? 0),
            ];
        })->values()->all();
    }

    /**
     * @param  list<array{stock_item_code: string, name?: string, qty: int}>  $items
     */
    private function validateItems(array $items, bool $requireAtLeastOne): void
    {
        if ($requireAtLeastOne && $items === []) {
            abort(422, 'يجب إضافة بند واحد على الأقل.');
        }

        foreach ($items as $item) {
            $code = $item['stock_item_code'] ?? '';
            $qty = (int) ($item['qty'] ?? 0);

            if (! StockItem::where('code', $code)->exists()) {
                throw new InvalidSpecItemException($code);
            }

            if ($qty < 1) {
                abort(422, 'الكمية يجب أن تكون 1 على الأقل لكل بند.');
            }
        }
    }
}
