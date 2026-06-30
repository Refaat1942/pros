<?php

namespace App\Services;

use App\Enums\SpecEditRequestSource;
use App\Enums\SpecEditRequestStatus;
use App\Exceptions\InvalidSpecItemException;
use App\Models\BomItem;
use App\Models\CaseRecord;
use App\Models\SpecEditRequest;
use App\Models\StockItem;
use App\Models\TechOrderSpec;
use App\Models\TechOrderSpecItem;
use App\Models\User;
use App\Services\Notifications\NotificationService;
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
    ) {
    }

    public function canRequestSpecEdit(TechOrderSpec $spec): bool
    {
        $spec->loadMissing('caseRecord', 'pendingEditRequest');

        if (! $spec->locked || $spec->pendingEditRequest) {
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
            abort(422, 'لا يمكن طلب تعديل التوصيف — تأكد أن الحالة في المعدلات ولم تُرسَل للتكاليف بعد، ولا يوجد طلب معلّق.');
        }

        $this->validateItems($items, requireAtLeastOne: true);

        $spec->load('items');

        return DB::transaction(function () use ($spec, $requester, $items, $techNotes) {
            $originalItems = $spec->items->map(fn (TechOrderSpecItem $i) => [
                'stock_item_code' => $i->stock_item_code,
                'name'            => $i->name,
                'qty'             => $i->qty,
            ])->values()->all();

            $request = SpecEditRequest::create([
                'source'              => SpecEditRequestSource::Spec,
                'tech_order_spec_id'  => $spec->id,
                'case_id'             => $spec->case_id,
                'requested_by_user_id'=> $requester->id,
                'status'              => SpecEditRequestStatus::Pending,
                'original_items'      => $originalItems,
                'proposed_items'      => $items,
                'original_tech_notes' => $spec->tech_notes,
                'proposed_tech_notes' => $techNotes,
            ]);

            $case = $spec->caseRecord;

            AuditService::log(
                action:      'request',
                description: "طلب تعديل توصيف — {$case?->case_no}",
                tag:         'spec',
                after:       [
                    'spec_edit_request_id' => $request->id,
                    'source'               => SpecEditRequestSource::Spec->value,
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
                'source'              => SpecEditRequestSource::Adjustments,
                'tech_order_spec_id'  => $spec->id,
                'case_id'             => $case->id,
                'requested_by_user_id'=> $requester->id,
                'status'              => SpecEditRequestStatus::Pending,
                'original_items'      => $originalItems,
                'proposed_items'      => $this->normalizeItems($items),
                'original_tech_notes' => null,
                'proposed_tech_notes' => null,
            ]);

            AuditService::log(
                action:      'request',
                description: "طلب تعديل بنود المعدلات — {$case->case_no}",
                tag:         'spec',
                after:       [
                    'spec_edit_request_id' => $request->id,
                    'source'               => SpecEditRequestSource::Adjustments->value,
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
            default                            => $this->approveSpecEdit($request, $reviewer),
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
                'status'               => SpecEditRequestStatus::Rejected,
                'rejection_reason_key' => $reasonKey ?: null,
                'rejection_notes'      => $notes,
                'reviewed_by_user_id'  => $reviewer->id,
                'reviewed_at'          => now(),
            ]);

            $case = $request->caseRecord;

            AuditService::log(
                action:      'reject',
                description: "رفض طلب تعديل — {$case?->case_no}",
                tag:         'spec',
                after:       [
                    'spec_edit_request_id' => $request->id,
                    'source'               => $request->source->value,
                    'reason_key'           => $reasonKey,
                ],
            );

            $this->notifications->notifyEditRequestRejected(
                $request->fresh()->load('caseRecord.patient')
            );

            return $request->fresh(['techOrderSpec.items', 'caseRecord', 'requestedBy', 'reviewedBy']);
        });
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

        return [
            'id'                    => $request->id,
            'source'                => $request->source->value,
            'source_label'          => $request->source->label(),
            'status'                => $request->status->value,
            'status_label'          => $request->status->label(),
            'status_badge_class'    => $request->status->badgeClass(),
            'case_id'               => $request->case_id,
            'case_no'               => $request->caseRecord?->case_no,
            'order_ref'             => $request->techOrderSpec?->order_ref ?? $request->caseRecord?->order_ref,
            'patient_name'          => $request->techOrderSpec?->patient_name,
            'stage_key'             => $request->caseRecord?->stage_key,
            'requested_by'          => $request->requestedBy?->name,
            'requested_at'          => $request->created_at?->toIso8601String(),
            'requested_at_label'    => $request->created_at?->format('d/m/Y H:i'),
            'reviewed_by'           => $request->reviewedBy?->name,
            'reviewed_at_label'     => $request->reviewed_at?->format('d/m/Y H:i'),
            'rejection_reason_key'  => $request->rejection_reason_key,
            'rejection_reason_label'=> $request->rejectionReasonLabel(),
            'rejection_notes'       => $request->rejection_notes,
            'original_items'        => $request->original_items ?? [],
            'proposed_items'        => $request->proposed_items ?? [],
            'original_tech_notes'   => $request->original_tech_notes,
            'proposed_tech_notes'   => $request->proposed_tech_notes,
            'tech_order_spec_id'    => $request->tech_order_spec_id,
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
                    'stock_item_code'    => $row['stock_item_code'],
                    'name'               => $row['name'],
                    'qty'                => (int) $row['qty'],
                ]);
            }

            $this->bomService->replaceSpecSourceItems($case, $items);

            $request->update([
                'status'              => SpecEditRequestStatus::Approved,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at'         => now(),
            ]);

            AuditService::log(
                action:      'approve',
                description: "اعتماد تعديل توصيف — {$case->case_no}",
                tag:         'spec',
                after:       ['spec_edit_request_id' => $request->id],
            );

            $this->notifications->notifyEditRequestApproved($request->fresh()->load('caseRecord.patient'));

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
                'status'              => SpecEditRequestStatus::Approved,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at'         => now(),
            ]);

            AuditService::log(
                action:      'approve',
                description: "اعتماد تعديل بنود المعدلات — {$case->case_no}",
                tag:         'spec',
                after:       ['spec_edit_request_id' => $request->id],
            );

            $this->notifications->notifyEditRequestApproved($request->fresh()->load('caseRecord.patient'));

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
                'name'            => $i->name,
                'qty'             => $i->qty,
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
                'name'            => $item['name'] ?? $stock?->name ?? $code,
                'qty'             => (int) ($item['qty'] ?? 0),
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
            $qty  = (int) ($item['qty'] ?? 0);

            if (! StockItem::where('code', $code)->exists()) {
                throw new InvalidSpecItemException($code);
            }

            if ($qty < 1) {
                abort(422, 'الكمية يجب أن تكون 1 على الأقل لكل بند.');
            }
        }
    }
}
