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
     * @param  list<string>|list<array{barcode: string, qty?: mixed, qty_uom?: string}>  $dispensePayload
     */
    public function submit(Bom $bom, array $dispensePayload, User $requester): StockDispenseRequest
    {
        $bomId = $bom->id;

        return DB::transaction(function () use ($bomId, $dispensePayload, $requester) {
            $bom = Bom::lockForUpdate()->with(['caseRecord', 'items'])->findOrFail($bomId);

            if ($bom->stage !== Bom::STAGE_RAW) {
                abort(422, 'BOM ليست جاهزة للصرف.');
            }

            $case = $bom->caseRecord;
            if (! $case) {
                abort(422, 'لا توجد حالة مرتبطة.');
            }

            app(WorkshopAssignmentService::class)->assertDispenseAllowed($case);

            if (StockDispenseRequest::query()
                ->where('bom_id', $bom->id)
                ->where('status', StockDispenseRequest::STATUS_PENDING)
                ->exists()) {
                abort(422, 'يوجد طلب صرف معلّق لهذه BOM.');
            }

            $this->bomService->validateDispenseLines($bom, $dispensePayload);

            $storedLines = $this->bomService->resolveDispenseLinesForStorage($bom, $dispensePayload);

            $request = StockDispenseRequest::create([
                'case_id' => $case->id,
                'bom_id' => $bom->id,
                'work_order_no' => $case->work_order_no,
                'status' => StockDispenseRequest::STATUS_PENDING,
                'requested_by_user_id' => $requester->id,
                'lines' => $storedLines,
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

        $requestId = $request->id;

        return DB::transaction(function () use ($requestId, $approver) {
            $request = StockDispenseRequest::lockForUpdate()->findOrFail($requestId);

            if ($request->status !== StockDispenseRequest::STATUS_PENDING) {
                abort(422, 'طلب الصرف ليس معلّقاً.');
            }

            $bom = Bom::lockForUpdate()->with('caseRecord')->findOrFail($request->bom_id);

            if ($bom->stage !== Bom::STAGE_RAW) {
                abort(422, 'تم تنفيذ الصرف مسبقاً.');
            }

            $payload = $this->normalizeStoredLines($request->lines ?? []);

            $this->bomService->releaseToWip($bom, $payload);

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

    /**
     * @param  list<mixed>  $lines
     * @return list<string>|list<array{barcode: string, qty?: mixed, qty_uom?: string}>
     */
    private function normalizeStoredLines(array $lines): array
    {
        if ($lines === []) {
            return [];
        }

        if (is_string($lines[0] ?? null)) {
            return array_values(array_filter(array_map('strval', $lines)));
        }

        $payload = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $barcode = trim((string) ($line['barcode'] ?? ''));
            if ($barcode === '') {
                continue;
            }

            $row = ['barcode' => $barcode];
            if (array_key_exists('qty', $line)) {
                $row['qty'] = $line['qty'];
            }
            if (isset($line['qty_uom'])) {
                $row['qty_uom'] = $line['qty_uom'];
            }

            $payload[] = $row;
        }

        return $payload;
    }

    public function reject(StockDispenseRequest $request, User $approver, string $reason): StockDispenseRequest
    {
        if (! $approver->hasPermission('approve-dispense')) {
            abort(403, 'لا تملك صلاحية اعتماد الصرف.');
        }

        $requestId = $request->id;

        return DB::transaction(function () use ($requestId, $approver, $reason) {
            $request = StockDispenseRequest::lockForUpdate()
                ->with(['caseRecord', 'requestedBy'])
                ->findOrFail($requestId);

            if ($request->status !== StockDispenseRequest::STATUS_PENDING) {
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

            $case = $request->caseRecord;
            if ($case) {
                try {
                    $body = "الحالة {$case->case_no} — رُفض طلب الصرف.";
                    if ($reason) {
                        $body .= " السبب: {$reason}";
                    }
                    $this->notifications->push(
                        roleSlug: Role::SLUG_TECHNICAL,
                        title: '❌ رُفض طلب صرف مخزني',
                        body: $body,
                        case: $case,
                        event: 'dispense_request_rejected',
                        data: ['url' => '/technical/bom', 'request_id' => (string) $request->id],
                    );
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            return $request->fresh();
        });
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
