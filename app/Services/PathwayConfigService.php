<?php

namespace App\Services;

use App\Enums\CaseStage;
use App\Models\CaseRecord;
use App\Models\PathwayStep;
use Illuminate\Support\Facades\DB;

/**
 * إعدادات مسار العمل — ترقيم وخطوات العرض (مدني / عسكري).
 * لا يغيّر محرك WorkflowService؛ يتحكم في الترقيم والعناوين والمتابعة فقط.
 */
class PathwayConfigService
{
    /** @var array<string, list<array<string, mixed>>> */
    private const DEFAULTS = [
        PathwayStep::PATHWAY_CIVILIAN => [
            [
                'key' => 'reception',
                'label' => 'الاستقبال',
                'sort' => 1,
                'stage_keys' => [CaseRecord::STAGE_RECEPTION],
                'description' => 'تسجيل + موعد',
            ],
            [
                'key' => 'exam',
                'label' => 'الطبيب',
                'sort' => 2,
                'stage_keys' => [CaseRecord::STAGE_EXAM],
                'description' => 'كشف وتحويل للتوصيف',
            ],
            [
                'key' => 'technical',
                'label' => 'التوصيف',
                'sort' => 3,
                'stage_keys' => [CaseRecord::STAGE_TECHNICAL],
                'description' => 'مواصفات فنية',
            ],
            [
                'key' => 'adjustments',
                'label' => 'المعدلات',
                'sort' => 4,
                'stage_keys' => [CaseRecord::STAGE_ADJUSTMENTS],
                'description' => 'إضافة/تعديل البنود',
            ],
            [
                'key' => 'costing',
                'label' => 'التكاليف',
                'sort' => 5,
                'stage_keys' => [CaseRecord::STAGE_COST_CALC, CaseRecord::STAGE_QUOTE],
                'description' => 'تسعير وعرض سعر',
            ],
            [
                'key' => 'operations',
                'label' => 'التشغيل',
                'sort' => 6,
                'stage_keys' => [CaseRecord::STAGE_OPERATIONS],
                'description' => 'إصدار العرض — خطاب موافقة — إصدار أمر الشغل',
            ],
            [
                'key' => 'cashier',
                'label' => 'الخزنة',
                'sort' => 7,
                'stage_keys' => [CaseRecord::STAGE_CASHIER],
                'description' => 'تحصيل (نقدي فقط)',
            ],
            [
                'key' => 'warehouse',
                'label' => 'المخزن',
                'sort' => 8,
                'stage_keys' => [CaseRecord::STAGE_MANUFACTURING],
                'description' => 'صرف بالباركود للورشة',
            ],
            [
                'key' => 'workshop',
                'label' => 'الورشة',
                'sort' => 9,
                'stage_keys' => [CaseRecord::STAGE_MANUFACTURING],
                'description' => 'تصنيع وإغلاق الجودة',
            ],
            [
                'key' => 'delivery',
                'label' => 'التسليمات',
                'sort' => 10,
                'stage_keys' => [CaseRecord::STAGE_READY_DELIVERY, CaseRecord::STAGE_DELIVERED],
                'description' => 'مسح QR وإغلاق الحالة',
            ],
        ],
        PathwayStep::PATHWAY_MILITARY => [
            [
                'key' => 'reception',
                'label' => 'الاستقبال',
                'sort' => 1,
                'stage_keys' => [CaseRecord::STAGE_RECEPTION],
                'description' => 'تسجيل عسكري',
            ],
            [
                'key' => 'exam',
                'label' => 'الطبيب',
                'sort' => 2,
                'stage_keys' => [CaseRecord::STAGE_EXAM],
                'description' => 'كشف طبي',
            ],
            [
                'key' => 'preparation',
                'label' => 'التوصيف والتحضير',
                'sort' => 3,
                'stage_keys' => [
                    CaseRecord::STAGE_TECHNICAL,
                    CaseRecord::STAGE_ADJUSTMENTS,
                    CaseRecord::STAGE_COST_CALC,
                    CaseRecord::STAGE_QUOTE,
                    CaseRecord::STAGE_OPERATIONS,
                ],
                'description' => 'توصيف → معدلات → تكاليف → اعتماد تلقائي',
            ],
            [
                'key' => 'production',
                'label' => 'المخزن والورشة',
                'sort' => 4,
                'stage_keys' => [CaseRecord::STAGE_MANUFACTURING],
                'description' => 'صرف وتصنيع',
            ],
            [
                'key' => 'delivery',
                'label' => 'التسليمات',
                'sort' => 5,
                'stage_keys' => [CaseRecord::STAGE_READY_DELIVERY, CaseRecord::STAGE_DELIVERED],
                'description' => 'تسليم للمريض',
            ],
        ],
    ];

    /**
     * @return list<array{value: string, label: string}>
     */
    public function availableStageKeys(): array
    {
        return array_map(
            fn (CaseStage $stage) => ['value' => $stage->value, 'label' => $stage->label()],
            CaseStage::cases(),
        );
    }

    /**
     * @return list<array{key: string, label: string, sort: int, stage_keys: list<string>, active: bool, description: ?string}>
     */
    public function steps(string $pathway, bool $activeOnly = false): array
    {
        $this->assertPathway($pathway);

        $query = PathwayStep::query()
            ->where('pathway', $pathway)
            ->orderBy('sort')
            ->orderBy('id');

        if ($activeOnly) {
            $query->where('active', true);
        }

        $stored = $query->get();

        if ($stored->isEmpty()) {
            return $this->normalizeDefaults(self::DEFAULTS[$pathway], $activeOnly);
        }

        return $stored->map(fn (PathwayStep $step) => $this->formatStep($step))->values()->all();
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function displaySteps(bool $isMilitary): array
    {
        $pathway = $isMilitary ? PathwayStep::PATHWAY_MILITARY : PathwayStep::PATHWAY_CIVILIAN;

        return array_map(
            fn (array $step) => ['key' => $step['key'], 'label' => $step['label']],
            $this->steps($pathway, activeOnly: true),
        );
    }

    public function resolveCurrentIndex(?CaseRecord $case, bool $isMilitary, bool $noCase = false, bool $awaitingEntityApproval = false): int
    {
        if ($noCase || ! $case) {
            return 0;
        }

        $pathway = $isMilitary ? PathwayStep::PATHWAY_MILITARY : PathwayStep::PATHWAY_CIVILIAN;
        $steps = $this->steps($pathway, activeOnly: true);

        if ($steps === []) {
            return 0;
        }

        if (! $isMilitary && $awaitingEntityApproval) {
            $approvalIndex = $this->indexOfKey($steps, 'operations')
                ?? $this->indexOfKey($steps, 'approval')
                ?? $this->indexOfKey($steps, 'costing');

            if ($approvalIndex !== null) {
                return $approvalIndex;
            }
        }

        $stageKey = $case->stage_key;
        if (! $stageKey) {
            return 0;
        }

        $matched = null;
        foreach ($steps as $index => $step) {
            if (in_array($stageKey, $step['stage_keys'], true)) {
                $matched = $index;
            }
        }

        return $matched ?? 0;
    }

    /**
     * @param  list<array{key: string, label: string, sort: int, stage_keys: list<string>, active: bool, description?: ?string}>  $steps
     */
    public function saveSteps(string $pathway, array $steps): void
    {
        $this->assertPathway($pathway);

        DB::transaction(function () use ($pathway, $steps) {
            PathwayStep::query()->where('pathway', $pathway)->delete();

            foreach ($steps as $row) {
                PathwayStep::create([
                    'pathway' => $pathway,
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'sort' => (int) $row['sort'],
                    'stage_keys' => array_values($row['stage_keys'] ?? []),
                    'active' => (bool) ($row['active'] ?? true),
                    'description' => $row['description'] ?? null,
                ]);
            }
        });
    }

    public function resetToDefaults(string $pathway): void
    {
        $this->assertPathway($pathway);
        $this->saveSteps($pathway, $this->normalizeDefaults(self::DEFAULTS[$pathway], false));
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function allForAdmin(): array
    {
        return [
            PathwayStep::PATHWAY_CIVILIAN => $this->steps(PathwayStep::PATHWAY_CIVILIAN),
            PathwayStep::PATHWAY_MILITARY => $this->steps(PathwayStep::PATHWAY_MILITARY),
            'stage_key_options' => $this->availableStageKeys(),
        ];
    }

    private function assertPathway(string $pathway): void
    {
        if (! isset(self::DEFAULTS[$pathway])) {
            throw new \InvalidArgumentException("مسار غير معروف: {$pathway}");
        }
    }

    /** @return array{key: string, label: string, sort: int, stage_keys: list<string>, active: bool, description: ?string} */
    private function formatStep(PathwayStep $step): array
    {
        return [
            'key' => $step->key,
            'label' => $step->label,
            'sort' => (int) $step->sort,
            'stage_keys' => array_values($step->stage_keys ?? []),
            'active' => (bool) $step->active,
            'description' => $step->description,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $defaults
     * @return list<array{key: string, label: string, sort: int, stage_keys: list<string>, active: bool, description: ?string}>
     */
    private function normalizeDefaults(array $defaults, bool $activeOnly): array
    {
        $rows = array_map(fn (array $row) => [
            'key' => $row['key'],
            'label' => $row['label'],
            'sort' => (int) $row['sort'],
            'stage_keys' => array_values($row['stage_keys']),
            'active' => true,
            'description' => $row['description'] ?? null,
        ], $defaults);

        if ($activeOnly) {
            return array_values(array_filter($rows, fn (array $row) => $row['active']));
        }

        return $rows;
    }

    /** @param  list<array{key: string}>  $steps */
    private function indexOfKey(array $steps, string $key): ?int
    {
        foreach ($steps as $index => $step) {
            if (($step['key'] ?? '') === $key) {
                return $index;
            }
        }

        return null;
    }
}
