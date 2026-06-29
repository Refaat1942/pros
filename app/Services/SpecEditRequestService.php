<?php

namespace App\Services;

use App\Enums\SpecEditRequestStatus;
use App\Exceptions\InvalidSpecItemException;
use App\Models\CaseRecord;
use App\Models\SpecEditRequest;
use App\Models\StockItem;
use App\Models\TechOrderSpec;
use App\Models\TechOrderSpecItem;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\DB;

/**
 * طلبات تعديل التوصيف الفني بعد الإرسال للمعدلات — موافقة الإدارة مطلوبة.
 */
class SpecEditRequestService
{
    public function __construct(
        private readonly BomService $bomService,
        private readonly NotificationService $notifications,
    ) {
    }

    public function canRequestEdit(TechOrderSpec $spec): bool
    {
        $spec->loadMissing('caseRecord', 'pendingEditRequest');

        if (! $spec->locked) {
            return false;
        }

        if ($spec->pendingEditRequest) {
            return false;
        }

        return $spec->caseRecord?->stage_key === CaseRecord::STAGE_ADJUSTMENTS;
    }

    /**
     * @param  list<array{stock_item_code: string, name: string, qty: int}>  $items
     */
    public function submit(TechOrderSpec $spec, User $requester, array $items, ?string $techNotes): SpecEditRequest
    {
        if (! $this->canRequestEdit($spec)) {
            abort(422, 'لا يمكن طلب تعديل هذا التوصيف — تأكد أن الحالة في المعدلات ولا يوجد طلب معلّق.');
        }

        $this->validateItems($items);

        $spec->load('items');

        return DB::transaction(function () use ($spec, $requester, $items, $techNotes) {
            $originalItems = $spec->items->map(fn (TechOrderSpecItem $i) => [
                'stock_item_code' => $i->stock_item_code,
                'name'            => $i->name,
                'qty'             => $i->qty,
            ])->values()->all();

            $request = SpecEditRequest::create([
                'tech_order_spec_id'  => $spec->id,
                'case_id'             => $spec->case_id,
                'requested_by_user_id'=> $requester->id,
                'status'              => SpecEditRequestStatus::Pending,
                'original_items'      => $originalItems,
                'proposed_items'      => $items,
                'original_tech_notes' => $spec->tech_notes,
                'proposed_tech_notes' => $techNotes,
            ]);

            $case = $spec->caseRecord()->first();

            AuditService::log(
                action:      'request',
                description: "طلب تعديل توصيف — {$case?->case_no}",
                tag:         'spec',
                after:       [
                    'spec_edit_request_id' => $request->id,
                    'case_id'              => $spec->case_id,
                    'items_count'          => count($items),
                ],
            );

            $this->notifications->notifySpecEditRequested($request->load('caseRecord.patient', 'requestedBy'));

            return $request;
        });
    }

    public function approve(SpecEditRequest $request, User $reviewer): SpecEditRequest
    {
        if (! $request->isPending()) {
            abort(422, 'تمت معالجة هذا الطلب مسبقاً.');
        }

        $request->load('techOrderSpec.caseRecord');

        $spec = $request->techOrderSpec;
        $case = $request->caseRecord;

        if ($case->stage_key !== CaseRecord::STAGE_ADJUSTMENTS) {
            abort(422, 'الحالة لم تعد في مرحلة المعدلات — لا يمكن تطبيق التعديل.');
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

            $this->notifications->notifySpecEditApproved($request->fresh()->load('caseRecord.patient'));

            return $request->fresh(['techOrderSpec.items', 'caseRecord', 'requestedBy', 'reviewedBy']);
        });
    }

    public function reject(SpecEditRequest $request, User $reviewer, string $reasonKey, ?string $notes = null): SpecEditRequest
    {
        if (! $request->isPending()) {
            abort(422, 'تمت معالجة هذا الطلب مسبقاً.');
        }

        $reasons = config('spec_edit.rejection_reasons', []);

        if (! array_key_exists($reasonKey, $reasons)) {
            abort(422, 'سبب الرفض غير صالح.');
        }

        return DB::transaction(function () use ($request, $reviewer, $reasonKey, $notes) {
            $request->update([
                'status'               => SpecEditRequestStatus::Rejected,
                'rejection_reason_key' => $reasonKey,
                'rejection_notes'      => $notes,
                'reviewed_by_user_id'  => $reviewer->id,
                'reviewed_at'          => now(),
            ]);

            $case = $request->caseRecord;

            AuditService::log(
                action:      'reject',
                description: "رفض تعديل توصيف — {$case?->case_no}",
                tag:         'spec',
                after:       [
                    'spec_edit_request_id' => $request->id,
                    'reason_key'           => $reasonKey,
                ],
            );

            $this->notifications->notifySpecEditRejected(
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

    /**
     * @param  list<array{stock_item_code: string, name?: string, qty: int}>  $items
     */
    private function validateItems(array $items): void
    {
        if ($items === []) {
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
