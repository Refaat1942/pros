<?php

namespace App\Services;

use App\Enums\WorkflowEvent;
use App\Models\CaseRecord;
use App\Models\ServicesApproval;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ServicesApprovalService
{
    public function __construct(
        private readonly WorkflowService $workflowService,
        private readonly OperationsService $operationsService,
    ) {}

    public function openForCase(CaseRecord $case): ServicesApproval
    {
        return ServicesApproval::firstOrCreate(
            ['case_id' => $case->id],
            ['status' => ServicesApproval::STATUS_PENDING],
        );
    }

    public function approve(CaseRecord $case, User $approver, ?UploadedFile $document = null, ?string $notes = null): CaseRecord
    {
        if (! $approver->hasPermission('approve-services')) {
            abort(403, 'لا تملك صلاحية تصديق إدارة الخدمات.');
        }

        if ($case->stage_key !== CaseRecord::STAGE_SERVICES_APPROVAL) {
            abort(422, 'الحالة ليست بانتظار تصديق إدارة الخدمات.');
        }

        return DB::transaction(function () use ($case, $approver, $document, $notes) {
            $case = CaseRecord::lockForUpdate()->findOrFail($case->id);

            $approval = ServicesApproval::query()
                ->where('case_id', $case->id)
                ->lockForUpdate()
                ->first();

            if (! $approval || ! $approval->isPending()) {
                abort(422, 'لا يوجد طلب تصديق معلّق.');
            }

            $docPath = null;
            $docName = null;

            if ($document) {
                $docName = $document->getClientOriginalName();
                $docPath = $document->store('services-approvals/'.$case->id, 'local');
            }

            $approval->update([
                'status' => ServicesApproval::STATUS_APPROVED,
                'approved_by_user_id' => $approver->id,
                'approved_at' => now(),
                'document_path' => $docPath,
                'document_original_name' => $docName,
                'notes' => $notes,
            ]);

            $this->workflowService->advance($case->fresh(), WorkflowEvent::ServicesApproved->value);

            $case = $this->operationsService->approve(
                $case->fresh(),
                $approver->name.' — تصديق إدارة الخدمات',
            );

            AuditService::log(
                action: 'approve',
                description: "تصديق إدارة الخدمات — {$case->case_no}",
                tag: 'admin',
                after: ['services_approval_id' => $approval->id],
            );

            return $case;
        });
    }

    /** @return list<array<string, mixed>> */
    public function listPending(): array
    {
        return ServicesApproval::query()
            ->where('status', ServicesApproval::STATUS_PENDING)
            ->with([
                'caseRecord:id,case_no,patient_id,stage_key',
                'caseRecord.patient:id,name,patient_code,military_beneficiary_category',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ServicesApproval $a) => [
                'id' => $a->id,
                'status' => $a->status,
                'notes' => $a->notes,
                'created_at' => $a->created_at?->toIso8601String(),
                'case' => $a->caseRecord?->only(['id', 'case_no', 'stage_key']),
                'patient' => $a->caseRecord?->patient?->only([
                    'id', 'name', 'patient_code', 'military_beneficiary_category',
                ]),
            ])
            ->values()
            ->all();
    }
}
