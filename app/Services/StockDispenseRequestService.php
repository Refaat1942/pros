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

        $this->bomService->validateDispenseLines($bom, $dispensePayload);

        $storedLines = $this->bomService->resolveDispenseLinesForStorage($bom, $dispensePayload);

        return DB::transaction(function () use ($bom, $case, $storedLines, $requester) {
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