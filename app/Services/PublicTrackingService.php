<?php

namespace App\Services;

use App\Enums\CaseStage;
use App\Models\CaseRecord;
use App\Models\Patient;
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
            $case = $patient->cases()->latest()->first();
        }

        if (! $patient && $case) {
            $patient = $case->patient;
        }

        $isMilitary = $this->resolveIsMilitary($case, $patient);
        $pathway = $isMilitary ? 'military' : 'civilian';

        $steps = $this->stepsForPath($isMilitary);
        $stageKey = $case?->stage_key;
        $currentIndex = $this->currentStepIndex($stageKey, $isMilitary, $case === null);

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
            'stage_label'   => $this->publicStageLabel($stageKey, $case === null, $isMilitary),
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
                ['key' => 'technical', 'label' => 'التوصيف الفني'],
                ['key' => 'manufacturing', 'label' => 'التصنيع بالورشة'],
                ['key' => 'ready', 'label' => 'جاهز للتسليم'],
                ['key' => 'delivered', 'label' => 'تم التسليم'],
            ];
        }

        return [
            ['key' => 'registered', 'label' => 'تسجيل واستقبال'],
            ['key' => 'exam', 'label' => 'الكشف الطبي'],
            ['key' => 'technical', 'label' => 'التوصيف الفني'],
            ['key' => 'approval', 'label' => 'اعتماد عروض الأسعار والموافقات'],
            ['key' => 'manufacturing', 'label' => 'التصنيع بالورشة'],
            ['key' => 'ready', 'label' => 'جاهز للتسليم'],
            ['key' => 'delivered', 'label' => 'تم التسليم'],
        ];
    }

    private function currentStepIndex(?string $stageKey, bool $isMilitary, bool $noCase): int
    {
        if ($noCase) {
            return 0;
        }

        if (! $stageKey) {
            return 0;
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
            CaseRecord::STAGE_TECHNICAL       => 2,
            CaseRecord::STAGE_ADJUSTMENTS,
            CaseRecord::STAGE_COST_CALC,
            CaseRecord::STAGE_QUOTE,
            CaseRecord::STAGE_OPERATIONS      => 3,
            CaseRecord::STAGE_MANUFACTURING   => 4,
            CaseRecord::STAGE_READY_DELIVERY  => 5,
            CaseRecord::STAGE_DELIVERED       => 6,
            default                           => 2,
        };
    }

    private function publicStageLabel(?string $stageKey, bool $noCase, bool $isMilitary): string
    {
        if ($noCase) {
            return 'تم التسجيل — في انتظار الكشف الطبي';
        }

        if (! $isMilitary) {
            return match ($stageKey) {
                CaseRecord::STAGE_RECEPTION      => 'في الاستقبال',
                CaseRecord::STAGE_EXAM           => 'في مرحلة الكشف الطبي',
                CaseRecord::STAGE_TECHNICAL      => 'التوصيف الفني',
                CaseRecord::STAGE_ADJUSTMENTS    => 'مراجعة المعدلات',
                CaseRecord::STAGE_COST_CALC      => 'جاري احتساب التكاليف',
                CaseRecord::STAGE_QUOTE          => 'عرض السعر — بانتظار مكتب التشغيل',
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
            CaseRecord::STAGE_TECHNICAL      => 'التوصيف الفني',
            CaseRecord::STAGE_ADJUSTMENTS    => 'مراجعة المعدلات',
            CaseRecord::STAGE_COST_CALC      => 'احتساب التكاليف — انتقال للورشة',
            CaseRecord::STAGE_QUOTE,
            CaseRecord::STAGE_OPERATIONS     => 'تجهيز أمر الشغل',
            CaseRecord::STAGE_MANUFACTURING  => 'جاري التصنيع بالورشة',
            CaseRecord::STAGE_READY_DELIVERY => 'جاهز للتسليم',
            CaseRecord::STAGE_DELIVERED      => 'تم التسليم',
            default                          => CaseStage::labelFor($stageKey),
        };
    }
}
