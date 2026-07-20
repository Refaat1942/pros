<?php

namespace App\Services;

use App\Enums\WorkflowEvent;
use App\Models\Bom;
use App\Models\Role;
use App\Models\StockDispenseRequest;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\DB;

class StockDispenseRequestService
{
    public function __construct(
        private readonly BomService $bomService,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @param  list<string>  $scannedBarcodes
     */
    public function submit(Bom $bom, array $scannedBarcodes, User $requester): StockDispenseRequest
    {
        $bom->loadMissing(['caseRecord', 'items']);

        if ($bom->stage !== Bom::STAGE_RAW) {
            abort(422, 'BOM ليست جاهزة للصرف.');
        }

        $case = $bom->caseRecord;
        if (! $case) {
            abort(422, 'لا توجد حالة مرتبطة.');
        }

        $pending = StockDispenseRequest::query()
            ->where('bom_id', $bom->id)
            ->where('status', StockDispenseRequest::STATUS_PENDING)
            ->exists();

        if ($pending) {
            abort(422, 'يوجد طلب صرف معلّق لهذه BOM.');
        }

        $this->bomService->validateDispenseBarcodes($bom, $scannedBarcodes);

        return DB::transaction(function () use ($bom, $case, $scannedBarcodes, $requester) {
            $request = StockDispenseRequest::create([
                'case_id' => $case->id,
                'bom_id' => $bom->id,
                'work_order_no' => $case->work_order_no,
                'status' => StockDispenseRequest::STATUS_PENDING,
                'requested_by_user_id' => $requester->id,
                'lines' => array_values($scannedBarcodes),
            ]);

            AuditService::log(
                action: 'create',
                description: "طلب صرف مخزني — {$case->case_no} — BOM {$bom->bom_no}",
                tag: 'warehouse',
                after: $request->only(['id', 'case_id', 'bom_id', 'status']),
            );

            try {
                $this->notifications->push(
                    roleSlug: Role::SLUG_ADMIN,
                    title: '📦 طلب صرف مخزني بانتظار الاعتماد',
                    body: "الحالة {$case->case_no} — أمر شغل {$case->work_order_no}",
                    case: $case,
                    event: 'dispense_request_pending',
                    data: ['url' => '/admin/dispense-approvals'],
                );
            } catch (\Throwable $e) {
                report($e);
            }

            return $request;
        });
    }

    public function approve(StockDispenseRequest $request, User $approver): StockDispenseRequest
    {
        if (! $approver->hasPermission('approve-dispense')) {
            abort(403, 'لا تملك صلاحية اعتماد الصرف.');
        }

        if (! $request->isPending()) {
            abort(422, 'طلب الصرف ليس معلّقاً.');
        }

        return DB::transaction(function () use ($request, $approver) {
            $request = StockDispenseRequest::lockForUpdate()->findOrFail($request->id);
            $bom = Bom::lockForUpdate()->with('caseRecord')->findOrFail($request->bom_id);

            if ($bom->stage !== Bom::STAGE_RAW) {
                abort(422, 'تم تنفيذ الصرف مسبقاً.');
            }

            $fromStage = $bom->caseRecord?->stage_key ?? 'manufacturing';

            $this->bomService->releaseToWip($bom, $request->lines ?? []);

            $request->update([
                'status' => StockDispenseRequest::STATUS_EXECUTED,
                'approved_by_user_id' => $approver->id,
                'approved_at' => now(),
            ]);

            AuditService::log(
                action: 'approve',
                description: "اعتماد صرف مخزني — طلب #{$request->id}",
                tag: 'warehouse',
                after: ['status' => StockDispenseRequest::STATUS_EXECUTED],
            );

            return $request->fresh(['caseRecord', 'bom', 'requestedBy', 'approvedBy']);
        });
    }

    public function reject(StockDispenseRequest $request, User $approver, ?string $reason): StockDispenseRequest
    {
        if (! $approver->hasPermission('approve-dispense')) {
            abort(403, 'لا تملك صلاحية اعتماد الصرف.');
        }

        if (! $request->isPending()) {
            abort(422, 'طلب الصرف ليس معلّقاً.');
        }

        $request->update([
            'status' => StockDispenseRequest::STATUS_REJECTED,
            'approved_by_user_id' => $approver->id,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        AuditService::log(
            action: 'reject',
            description: "رفض طلب صرف مخزني — #{$request->id}",
            tag: 'warehouse',
            after: ['reason' => $reason],
        );

        return $request->fresh();
    }

    /** @return list<array<string, mixed>> */
    public function listPending(): array
    {
        return StockDispenseRequest::query()
            ->where('status', StockDispenseRequest::STATUS_PENDING)
            ->with([
                'caseRecord:id,case_no,work_order_no,patient_id',
                'caseRecord.patient:id,name',
                'bom:id,bom_no,stage',
                'requestedBy:id,name',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (StockDispenseRequest $r) => [
                'id' => $r->id,
                'status' => $r->status,
                'work_order_no' => $r->work_order_no,
                'lines_count' => count($r->lines ?? []),
                'created_at' => $r->created_at?->toIso8601String(),
                'case' => $r->caseRecord?->only(['id', 'case_no', 'work_order_no']),
                'patient_name' => $r->caseRecord?->patient?->name,
                'bom' => $r->bom?->only(['id', 'bom_no', 'stage']),
                'requested_by' => $r->requestedBy?->only(['id', 'name']),
            ])
            ->values()
            ->all();
    }
}
