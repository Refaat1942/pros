<?php

namespace App\Services;

use App\Enums\CaseStage;
use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\Quote;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * بيانات صفحة المتابعة العامة — بدون اسم مريض ولا تفاصيل مالية/طبية.
 */
class PublicTrackingService
{
    public function __construct(private readonly TrackingUidService $trackingUidService)
    {
    }

    /**
     * @return array{
     *     tracking_uid: string,
     *     pathway: string,
     *     stage_label: string,
     *     current_index: int,
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

        $isMilitary = $this->resolveIsMilitary($case, $patient);
        $pathway = $isMilitary ? 'military' : 'civilian';

        $steps = $this->stepsForPath($isMilitary);
        $stageKey = $case?->stage_key;
        $currentIndex = $this->currentStepIndex($case, $isMilitary, $case === null);

        $mappedSteps = array_map(function (array $step, int $index) use ($currentIndex) {
            $status = match (true) {
                $index < $currentIndex  => 'done',
                $index === $currentIndex => 'current',
                default                  => 'pending',
            };

            return $step + ['status' => $status];
        }, $steps, array_keys($steps));

        return [
            'tracking_uid'  => $uid,
            'pathway'       => $pathway,
            'stage_label'   => $this->publicStageLabel($case, $case === null, $isMilitary),
            'current_index' => $currentIndex,
            'steps'         => $mappedSteps,
            'tracking_url'  => $this->trackingUidService->trackingUrl($uid),
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

    /** @return list<array{key: string, label: string}> */
    private function stepsForPath(bool $isMilitary): array
    {
        if ($isMilitary) {
            return [
                ['key' => 'registered', 'label' => 'تسجيل واستقبال'],
                ['key' => 'exam', 'label' => 'الكشف الطبي'],
                ['key' => 'technical', 'label' => 'التوصيف الفني والتحضير'],
                ['key' => 'manufacturing', 'label' => 'التصنيع بالورشة'],
                ['key' => 'ready', 'label' => 'جاهز للتسليم'],
                ['key' => 'delivered', 'label' => 'تم التسليم'],
            ];
        }

        return [
            ['key' => 'registered', 'label' => 'تسجيل واستقبال'],
            ['key' => 'exam', 'label' => 'الكشف الطبي'],
            ['key' => 'technical', 'label' => 'التوصيف الفني والتحضير'],
            ['key' => 'approval', 'label' => 'التسعير واعتماد التشغيل'],
            ['key' => 'manufacturing', 'label' => 'التصنيع بالورشة'],
            ['key' => 'ready', 'label' => 'جاهز للتسليم'],
            ['key' => 'delivered', 'label' => 'تم التسليم'],
        ];
    }

    private function currentStepIndex(?CaseRecord $case, bool $isMilitary, bool $noCase): int
    {
        if ($noCase || ! $case) {
            return 0;
        }

        $stageKey = $case->stage_key;

        if (! $stageKey) {
            return 0;
        }

        if (! $isMilitary && $this->isAwaitingEntityApproval($case)) {
            return 3;
        }

        if ($isMilitary) {
            return match ($stageKey) {
                CaseRecord::STAGE_RECEPTION       => 0,
                CaseRecord::STAGE_EXAM            => 1,
                CaseRecord::STAGE_TECHNICAL,
                CaseRecord::STAGE_ADJUSTMENTS,
                CaseRecord::STAGE_COST_CALC,
                CaseRecord::STAGE_QUOTE,
                CaseRecord::STAGE_OPERATIONS      => 2,
                CaseRecord::STAGE_MANUFACTURING   => 3,
                CaseRecord::STAGE_READY_DELIVERY  => 4,
                CaseRecord::STAGE_DELIVERED       => 5,
                default                           => 2,
            };
        }

        return match ($stageKey) {
            CaseRecord::STAGE_RECEPTION       => 0,
            CaseRecord::STAGE_EXAM            => 1,
            CaseRecord::STAGE_TECHNICAL,
            CaseRecord::STAGE_ADJUSTMENTS,
            CaseRecord::STAGE_COST_CALC       => 2,
            CaseRecord::STAGE_QUOTE,
            CaseRecord::STAGE_OPERATIONS      => 3,
            CaseRecord::STAGE_MANUFACTURING   => 4,
            CaseRecord::STAGE_READY_DELIVERY  => 5,
            CaseRecord::STAGE_DELIVERED       => 6,
            default                           => 2,
        };
    }

    private function publicStageLabel(?CaseRecord $case, bool $noCase, bool $isMilitary): string
    {
        if ($noCase || ! $case) {
            return 'تم التسجيل — في انتظار الكشف الطبي';
        }

        $stageKey = $case->stage_key;

        if (! $isMilitary && $this->isAwaitingEntityApproval($case)) {
            return 'بانتظار موافقة الجهة';
        }

        if (! $isMilitary) {
            return match ($stageKey) {
                CaseRecord::STAGE_RECEPTION      => 'في الاستقبال',
                CaseRecord::STAGE_EXAM           => 'في مرحلة الكشف الطبي',
                CaseRecord::STAGE_TECHNICAL      => 'التوصيف الفني',
                CaseRecord::STAGE_ADJUSTMENTS    => 'مراجعة المعدلات والتحضير',
                CaseRecord::STAGE_COST_CALC      => 'جاري احتساب التكاليف',
                CaseRecord::STAGE_QUOTE          => 'إعداد عرض السعر',
                CaseRecord::STAGE_OPERATIONS     => 'بمكتب التشغيل — بانتظار الاعتماد',
                CaseRecord::STAGE_MANUFACTURING  => 'جاري التصنيع بالورشة',
                CaseRecord::STAGE_READY_DELIVERY => 'جاهز للتسليم',
                CaseRecord::STAGE_DELIVERED      => 'تم التسليم',
                default                          => CaseStage::labelFor($stageKey),
            };
        }

        return match ($stageKey) {
            CaseRecord::STAGE_RECEPTION      => 'في الاستقبال',
            CaseRecord::STAGE_EXAM           => 'في مرحلة الكشف الطبي',
            CaseRecord::STAGE_TECHNICAL,
            CaseRecord::STAGE_ADJUSTMENTS,
            CaseRecord::STAGE_COST_CALC,
            CaseRecord::STAGE_QUOTE,
            CaseRecord::STAGE_OPERATIONS     => 'جاري التحضير للتصنيع',
            CaseRecord::STAGE_MANUFACTURING  => 'جاري التصنيع بالورشة',
            CaseRecord::STAGE_READY_DELIVERY => 'جاهز للتسليم',
            CaseRecord::STAGE_DELIVERED      => 'تم التسليم',
            default                          => CaseStage::labelFor($stageKey),
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
