<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Models\MedicalRecord;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;

/**
 * إنشاء الحالات التشغيلية ونقطة الدخول لانتقالات الـ workflow.
 */
class CaseService
{
    public function __construct(private readonly WorkflowService $workflowService)
    {
    }

    /**
     * يُنشئ CaseRecord من تقرير طبي معتمد.
     * يُستدعى حصراً بعد اعتماد الكشف (MedicalRecordService::lock).
     */
    public function initiate(Patient $patient, MedicalRecord $record): CaseRecord
    {
        return DB::transaction(function () use ($patient, $record) {
            [$caseNo, $orderRef] = $this->nextCaseNumbers();

            $path = $patient->isMilitary()
                ? CaseRecord::PATH_MILITARY
                : CaseRecord::PATH_STANDARD;

            $case = CaseRecord::create([
                'case_no'              => $caseNo,
                'order_ref'            => $orderRef,
                'patient_id'           => $patient->id,
                'contract_company_id'  => $patient->contract_company_id,
                'company_name'         => $patient->company_name,
                'patient_type'         => $patient->patient_type,
                'path'                 => $path,
                'stage_key'            => CaseRecord::STAGE_RECEPTION,
                'rank'                 => $patient->rank,
                'sovereign_entity'     => $patient->sovereign_entity,
            ]);

            $record->update(['case_id' => $case->id]);

            AuditService::log(
                action:      'create',
                description: "إنشاء حالة جديدة {$case->case_no} للمريض {$patient->patient_code}",
                tag:         'medical',
                after:       $this->caseAuditSnapshot($case),
            );

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

        $lastCode = CaseRecord::where('case_no', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc('case_no')
            ->value('case_no');

        $num = $lastCode
            ? ((int) substr($lastCode, strlen($prefix)) + 1)
            : 1;

        $seq = sprintf('%04d', $num);

        return ["CASE-{$year}-{$seq}", "ORD-{$year}-{$seq}"];
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
