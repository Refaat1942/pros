<?php

namespace App\Services;

use App\Enums\WorkflowEvent;
use App\Models\CaseRecord;
use App\Models\MedicalRecord;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;

/**
 * إنشاء الحالات التشغيلية ونقطة الدخول لانتقالات الـ workflow.
 */
class CaseService
{
    public function __construct(
        private readonly WorkflowService $workflowService,
        private readonly OrderRefService $orderRefService,
    ) {
    }

    /**
     * إنشاء حالة من الاستقبال مع تخطّي الكشف (اختياري) — تقفز مباشرةً للتوصيف.
     */
    public function initiateFromReception(Patient $patient): CaseRecord
    {
        $case = $this->createCase($patient, null);

        $this->workflowService->advance($case, WorkflowEvent::ExamSkipped->value);

        AuditService::log(
            action:      'create',
            description: "إنشاء حالة بتخطّي الكشف {$case->case_no} — {$patient->patient_code}",
            tag:         'medical',
            after:       $this->caseAuditSnapshot($case->fresh()),
        );

        return $case->fresh();
    }

    /**
     * يُنشئ CaseRecord من تقرير طبي معتمد.
     * يُستدعى حصراً بعد اعتماد الكشف (MedicalRecordService::lock).
     */
    public function initiate(Patient $patient, MedicalRecord $record): CaseRecord
    {
        $case = $this->createCase($patient, $record);

        AuditService::log(
            action:      'create',
            description: "إنشاء حالة جديدة {$case->case_no} للمريض {$patient->patient_code}",
            tag:         'medical',
            after:       $this->caseAuditSnapshot($case),
        );

        return $case->fresh();
    }

    /**
     * إنشاء سجل الحالة الأساسي عند الاستقبال (stage_key = reception).
     */
    private function createCase(Patient $patient, ?MedicalRecord $record): CaseRecord
    {
        return DB::transaction(function () use ($patient, $record) {
            [$caseNo, $orderRef] = $this->nextCaseNumbers();

            $path = $patient->isMilitary()
                ? CaseRecord::PATH_MILITARY
                : CaseRecord::PATH_STANDARD;

            $case = CaseRecord::create([
                'case_no'              => $caseNo,
                'order_ref'            => $orderRef,
                'tracking_uid'         => $patient->tracking_uid,
                'patient_id'           => $patient->id,
                'contract_company_id'  => $patient->isMilitary() ? null : $patient->contract_company_id,
                'company_name'         => $patient->isMilitary() ? null : $patient->company_name,
                'patient_type'         => $patient->patient_type,
                'path'                 => $path,
                'stage_key'            => CaseRecord::STAGE_RECEPTION,
                'rank'                 => $patient->rank,
                'sovereign_entity'     => $patient->isMilitary()
                    ? ($patient->sovereign_entity ?? Patient::MILITARY_SOVEREIGN_ENTITY)
                    : null,
            ]);

            $record?->update(['case_id' => $case->id]);

            return $case->fresh();
        });
    }

    /**
     * نقطة الدخول المركزية لكل انتقال workflow — يُفوِّض إلى WorkflowService.
     */
    public function advance(CaseRecord $case, string $event): void
    {
        $this->workflowService->advance($case, $event);
    }

    /**
     * @return array{0: string, 1: string} [case_no, order_ref]
     */
    private function nextCaseNumbers(): array
    {
        $year   = now()->year;
        $prefix = "CASE-{$year}-";

        $lastNum = CaseRecord::where('case_no', 'like', $prefix . '%')
            ->lockForUpdate()
            ->pluck('case_no')
            ->map(fn (string $code) => (int) substr($code, strlen($prefix)))
            ->max();

        $num = ($lastNum ?? 0) + 1;
        $seq = sprintf('%04d', $num);

        return ["CASE-{$year}-{$seq}", $this->orderRefService->generate()];
    }

    private function caseAuditSnapshot(CaseRecord $case): array
    {
        return $case->only([
            'case_no',
            'order_ref',
            'patient_id',
            'patient_type',
            'path',
            'stage_key',
            'contract_company_id',
        ]);
    }
}
