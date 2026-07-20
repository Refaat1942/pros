<?php

namespace App\Services;

use App\Enums\CaseStage;
use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\PathwayStep;
use App\Models\Quote;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * بيانات صفحة المتابعة العامة — بدون اسم مريض ولا تفاصيل مالية/طبية.
 */
class PublicTrackingService
{
    public function __construct(
        private readonly TrackingUidService $trackingUidService,
        private readonly PathwayConfigService $pathwayConfig,
    ) {}

    /**
     * @return array{
     *     tracking_uid: string,
     *     pathway: string,
     *     stage_label: string,
     *     current_index: int,
     *     progress_percent: int,
     *     steps: list<array{key: string, label: string, status: string}>
     * }
     */
    public function resolve(string $uid): array
    {
        $case = CaseRecord::where('tracking_uid', $uid)->first();
        $patient = Patient::where('tracking_uid', $uid)->first();

        if (! $case && ! $patient) {
            throw (new ModelNotFoundException)->setModel(CaseRecord::class, [$uid]);
        }

        if (! $case && $patient) {
            $case = $patient->cases()->with('quotes:id,case_id,status')->latest()->first();
        } elseif ($case) {
            $case->loadMissing('quotes:id,case_id,status');
        }

        if (! $patient && $case) {
            $patient = $case->patient;
        }

        $pathway = $this->pathwayConfig->resolvePathway($patient, $case);

        $steps = $this->pathwayConfig->displayStepsForPathway($pathway);
        $currentIndex = $this->pathwayConfig->resolveCurrentIndexForPathway(
            $case,
            $pathway,
            $case === null,
        );

        $mappedSteps = array_map(function (array $step, int $index) use ($currentIndex) {
            $status = match (true) {
                $index < $currentIndex => 'done',
                $index === $currentIndex => 'current',
                default => 'pending',
            };

            return $step + ['status' => $status];
        }, $steps, array_keys($steps));

        $totalSteps = count($mappedSteps);
        $progressPercent = $totalSteps > 1
            ? (int) round(($currentIndex / ($totalSteps - 1)) * 100)
            : 0;

        return [
            'tracking_uid' => $uid,
            'pathway' => $pathway,
            'stage_label' => $case
                ? $this->pathwayConfig->currentStepLabelForCase($case)
                : $this->publicStageLabel($case, $case === null, $pathway),
            'current_index' => $currentIndex,
            'progress_percent' => $progressPercent,
            'steps' => $mappedSteps,
            'tracking_url' => $this->trackingUidService->trackingUrl($uid),
        ];
    }

    private function resolveIsMilitary(?CaseRecord $case, ?Patient $patient): bool
    {
        if ($case) {
            return $case->patient_type === Patient::TYPE_MILITARY
                || $case->path === CaseRecord::PATH_MILITARY;
        }

        return $patient?->isMilitary() ?? false;
    }

    private function publicStageLabel(?CaseRecord $case, bool $noCase, string $pathway): string
    {
        if ($noCase || ! $case) {
            return 'تم التسجيل — في انتظار الكشف الطبي';
        }

        $stageKey = $case->stage_key;
        $isMilitary = $pathway === PathwayStep::PATHWAY_MILITARY;

        if ($pathway === PathwayStep::PATHWAY_ENTITY && $this->isAwaitingEntityApproval($case)) {
            return 'بانتظار موافقة الجهة — خطاب التأمين';
        }

        if (! $isMilitary) {
            return match ($stageKey) {
                CaseRecord::STAGE_RECEPTION => 'في الاستقبال',
                CaseRecord::STAGE_EXAM => 'في مرحلة الكشف الطبي',
                CaseRecord::STAGE_TECHNICAL => 'التوصيف الفني',
                CaseRecord::STAGE_ADJUSTMENTS => 'مراجعة المعدلات والتحضير',
                CaseRecord::STAGE_COST_CALC => 'جاري احتساب التكاليف',
                CaseRecord::STAGE_QUOTE => 'إعداد عرض السعر',
                CaseRecord::STAGE_OPERATIONS => 'بمكتب التشغيل — بانتظار الاعتماد',
                CaseRecord::STAGE_MANUFACTURING => 'جاري التصنيع بالورشة',
                CaseRecord::STAGE_READY_DELIVERY => 'جاهز للتسليم',
                CaseRecord::STAGE_DELIVERED => 'تم التسليم',
                default => CaseStage::labelFor($stageKey),
            };
        }

        return match ($stageKey) {
            CaseRecord::STAGE_RECEPTION => 'في الاستقبال',
            CaseRecord::STAGE_EXAM => 'في مرحلة الكشف الطبي',
            CaseRecord::STAGE_TECHNICAL,
            CaseRecord::STAGE_ADJUSTMENTS,
            CaseRecord::STAGE_COST_CALC,
            CaseRecord::STAGE_QUOTE,
            CaseRecord::STAGE_OPERATIONS => 'جاري التحضير للتصنيع',
            CaseRecord::STAGE_MANUFACTURING => 'جاري التصنيع بالورشة',
            CaseRecord::STAGE_READY_DELIVERY => 'جاهز للتسليم',
            CaseRecord::STAGE_DELIVERED => 'تم التسليم',
            default => CaseStage::labelFor($stageKey),
        };
    }

    /**
     * مدني — عُرض السعر للعميل ولم تُعتمد موافقة جهة التأمين بعد (OCR).
     */
    private function isAwaitingEntityApproval(CaseRecord $case): bool
    {
        if ($case->patient_type === Patient::TYPE_MILITARY
            || $case->path === CaseRecord::PATH_MILITARY) {
            return false;
        }

        if (! $case->relationLoaded('quotes')) {
            $case->load('quotes:id,case_id,status');
        }

        return $case->quotes->contains(fn (Quote $q) => $q->status === Quote::STATUS_ISSUED);
    }
}
