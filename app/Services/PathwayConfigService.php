<?php

namespace App\Services;

use App\Enums\CaseStage;
use App\Models\CaseRecord;
use App\Models\PathwayStep;
use App\Support\PathwayDepartments;
use Illuminate\Support\Facades\DB;

/**
 * مصمم مسار العمل — مصدر واحد للعرض + التدفق + القسم المسؤول.
 */
class PathwayConfigService
{
    public const NEXT_COMPLETED = '_completed';

    /** مراحل مقفلة — لا يمكن جعلها اختيارية (حماية المنطق التجاري). */
    public const BUSINESS_LOCKED = [
        PathwayStep::PATHWAY_CIVILIAN => [
            CaseRecord::STAGE_RECEPTION,
            CaseRecord::STAGE_TECHNICAL,
            CaseRecord::STAGE_COST_CALC,
            CaseRecord::STAGE_QUOTE,
            CaseRecord::STAGE_OPERATIONS,
            CaseRecord::STAGE_MANUFACTURING,
            CaseRecord::STAGE_READY_DELIVERY,
            CaseRecord::STAGE_DELIVERED,
        ],
        PathwayStep::PATHWAY_MILITARY => [
            CaseRecord::STAGE_RECEPTION,
            CaseRecord::STAGE_TECHNICAL,
            CaseRecord::STAGE_COST_CALC,
            CaseRecord::STAGE_OPERATIONS,
            CaseRecord::STAGE_MANUFACTURING,
            CaseRecord::STAGE_READY_DELIVERY,
            CaseRecord::STAGE_DELIVERED,
        ],
    ];

    /** @var array<string, string> */
    private const LOCK_REASONS = [
        CaseRecord::STAGE_RECEPTION => 'لا يمكن تخطي التسجيل — كل مريض يبدأ من الاستقبال.',
        CaseRecord::STAGE_TECHNICAL => 'التوصيف الفني إلزامي — بدون مواصفات لا يُصنع الطرف.',
        CaseRecord::STAGE_COST_CALC => 'حساب التكلفة إلزامي — أساس عرض السعر والفواتير.',
        CaseRecord::STAGE_QUOTE => 'عرض السعر إلزامي للمسار المدني — قبل التشغيل والتحصيل.',
        CaseRecord::STAGE_OPERATIONS => 'مكتب التشغيل إلزامي — نقطة اعتماد الحالة وإصدار أمر الشغل.',
        CaseRecord::STAGE_MANUFACTURING => 'المخزن والورشة إلزاميان — صرف بالباركود ثم تصنيع.',
        CaseRecord::STAGE_READY_DELIVERY => 'مرحلة التسليم إلزامية — مسح QR قبل إغلاق الحالة.',
        CaseRecord::STAGE_DELIVERED => 'إغلاق الحالة نهائي — لا يُتخطى.',
    ];

    /** @var array<string, list<array<string, mixed>>> */
    private const DEFAULTS = [
        PathwayStep::PATHWAY_CIVILIAN => [
            [
                'key' => 'reception',
                'label' => 'الاستقبال',
                'sort' => 1,
                'owner_department' => 'reception',
                'action_summary' => 'تسجيل المريض — فتح الملف — حجز الموعد — طباعة QR المتابعة',
                'on_complete' => 'ينتقل للكشف الطبي',
                'next_step_key' => 'exam',
                'stage_keys' => [CaseRecord::STAGE_RECEPTION],
                'required' => true,
                'auto_skip' => false,
                'skip_roles' => [],
                'handlers' => [],
            ],
            [
                'key' => 'exam',
                'label' => 'الكشف الطبي',
                'sort' => 2,
                'owner_department' => 'doctor',
                'action_summary' => 'فحص المريض — تقرير طبي — تحويل للتوصيف',
                'on_complete' => 'ينتقل للتوصيف الفني',
                'next_step_key' => 'technical',
                'stage_keys' => [CaseRecord::STAGE_EXAM],
                'required' => false,
                'auto_skip' => false,
                'skip_roles' => ['admin', 'doctor'],
                'handlers' => [],
            ],
            [
                'key' => 'technical',
                'label' => 'التوصيف الفني',
                'sort' => 3,
                'owner_department' => 'spec',
                'action_summary' => 'كتابة المواصفات الفنية واختيار الأصناف',
                'on_complete' => 'ينتقل للمعدلات',
                'next_step_key' => 'adjustments',
                'stage_keys' => [CaseRecord::STAGE_TECHNICAL],
                'required' => true,
                'auto_skip' => false,
                'skip_roles' => [],
                'handlers' => [],
            ],
            [
                'key' => 'adjustments',
                'label' => 'المعدلات',
                'sort' => 4,
                'owner_department' => 'adjustments',
                'action_summary' => 'مراجعة البنود — إضافة أو تعديل الكميات',
                'on_complete' => 'ينتقل لحساب التكاليف',
                'next_step_key' => 'cost_calc',
                'stage_keys' => [CaseRecord::STAGE_ADJUSTMENTS],
                'required' => false,
                'auto_skip' => false,
                'skip_roles' => ['admin', 'adjustments', 'operations'],
                'handlers' => [],
            ],
            [
                'key' => 'cost_calc',
                'label' => 'حساب التكاليف',
                'sort' => 5,
                'owner_department' => 'costing',
                'action_summary' => 'احتساب التكلفة — تجميد اللقطة — تأكيد السعر',
                'on_complete' => 'يُصدر عرض سعر ← ينتقل لمكتب التشغيل',
                'next_step_key' => 'quote',
                'stage_keys' => [CaseRecord::STAGE_COST_CALC],
                'required' => true,
                'auto_skip' => false,
                'skip_roles' => [],
                'handlers' => [],
            ],
            [
                'key' => 'quote',
                'label' => 'عرض السعر',
                'sort' => 6,
                'owner_department' => 'operations',
                'action_summary' => 'طباعة عرض السعر للمريض / الجهة المتعاقدة',
                'on_complete' => 'ينتقل لمكتب التشغيل لاعتماد الحالة',
                'next_step_key' => 'operations',
                'stage_keys' => [CaseRecord::STAGE_QUOTE],
                'required' => true,
                'auto_skip' => false,
                'skip_roles' => [],
                'handlers' => [],
            ],
            [
                'key' => 'operations',
                'label' => 'مكتب التشغيل',
                'sort' => 7,
                'owner_department' => 'operations',
                'action_summary' => 'مراجعة العرض — خطاب موافقة الجهة — اعتماد الحالة',
                'on_complete' => 'إصدار أمر الشغل ← الخزنة (كاش) أو المخزن مباشرة',
                'next_step_key' => 'cashier',
                'stage_keys' => [CaseRecord::STAGE_OPERATIONS],
                'required' => true,
                'auto_skip' => false,
                'skip_roles' => [],
                'handlers' => [
                    'work_order' => 'operations',
                    'entity_approval' => 'reception',
                ],
            ],
            [
                'key' => 'cashier',
                'label' => 'الخزنة',
                'sort' => 8,
                'owner_department' => 'cashier',
                'action_summary' => 'تحصيل المبلغ من المريض (نقدي فقط)',
                'on_complete' => 'يرجع للتشغيل لإصدار أمر الشغل',
                'next_step_key' => 'manufacturing',
                'stage_keys' => [CaseRecord::STAGE_CASHIER],
                'required' => false,
                'auto_skip' => false,
                'skip_roles' => ['admin'],
                'handlers' => ['collect_payment' => 'cashier'],
            ],
            [
                'key' => 'manufacturing',
                'label' => 'المخزن والورشة',
                'sort' => 9,
                'owner_department' => 'warehouse',
                'action_summary' => 'صرف بالباركود — تصنيع — إغلاق الجودة',
                'on_complete' => 'ينتقل للتسليمات',
                'next_step_key' => 'delivery',
                'stage_keys' => [CaseRecord::STAGE_MANUFACTURING],
                'required' => true,
                'auto_skip' => false,
                'skip_roles' => [],
                'handlers' => ['barcode_dispense' => 'warehouse', 'production' => 'workshop'],
            ],
            [
                'key' => 'delivery',
                'label' => 'التسليمات',
                'sort' => 10,
                'owner_department' => 'delivery',
                'action_summary' => 'مسح QR — تسليم للمريض — إغلاق الحالة',
                'on_complete' => 'الحالة مكتملة',
                'next_step_key' => self::NEXT_COMPLETED,
                'stage_keys' => [CaseRecord::STAGE_READY_DELIVERY, CaseRecord::STAGE_DELIVERED],
                'required' => true,
                'auto_skip' => false,
                'skip_roles' => [],
                'handlers' => [],
            ],
        ],
        PathwayStep::PATHWAY_MILITARY => [
            [
                'key' => 'reception',
                'label' => 'الاستقبال',
                'sort' => 1,
                'owner_department' => 'reception',
                'action_summary' => 'تسجيل عسكري — فتح ملف — موعد',
                'on_complete' => 'ينتقل للكشف',
                'next_step_key' => 'exam',
                'stage_keys' => [CaseRecord::STAGE_RECEPTION],
                'required' => true,
                'auto_skip' => false,
                'skip_roles' => [],
                'handlers' => [],
            ],
            [
                'key' => 'exam',
                'label' => 'الكشف الطبي',
                'sort' => 2,
                'owner_department' => 'doctor',
                'action_summary' => 'كشف طبي (اختياري)',
                'on_complete' => 'ينتقل للتوصيف',
                'next_step_key' => 'technical',
                'stage_keys' => [CaseRecord::STAGE_EXAM],
                'required' => false,
                'auto_skip' => false,
                'skip_roles' => ['admin', 'doctor'],
                'handlers' => [],
            ],
            [
                'key' => 'technical',
                'label' => 'التوصيف الفني',
                'sort' => 3,
                'owner_department' => 'spec',
                'action_summary' => 'مواصفات فنية للطرف',
                'on_complete' => 'ينتقل للمعدلات',
                'next_step_key' => 'adjustments',
                'stage_keys' => [CaseRecord::STAGE_TECHNICAL],
                'required' => true,
                'auto_skip' => false,
                'skip_roles' => [],
                'handlers' => [],
            ],
            [
                'key' => 'adjustments',
                'label' => 'المعدلات',
                'sort' => 4,
                'owner_department' => 'adjustments',
                'action_summary' => 'مراجعة البنود',
                'on_complete' => 'ينتقل للتكاليف',
                'next_step_key' => 'cost_calc',
                'stage_keys' => [CaseRecord::STAGE_ADJUSTMENTS],
                'required' => false,
                'auto_skip' => true,
                'skip_roles' => ['admin', 'adjustments'],
                'handlers' => [],
            ],
            [
                'key' => 'cost_calc',
                'label' => 'حساب التكاليف',
                'sort' => 5,
                'owner_department' => 'costing',
                'action_summary' => 'تسجيل التكلفة صامتاً (مديونية سيادية)',
                'on_complete' => 'اعتماد تلقائي ← أمر شغل ← المخزن',
                'next_step_key' => 'manufacturing',
                'stage_keys' => [CaseRecord::STAGE_COST_CALC, CaseRecord::STAGE_QUOTE, CaseRecord::STAGE_OPERATIONS],
                'required' => true,
                'auto_skip' => false,
                'skip_roles' => [],
                'handlers' => [],
            ],
            [
                'key' => 'manufacturing',
                'label' => 'المخزن والورشة',
                'sort' => 6,
                'owner_department' => 'warehouse',
                'action_summary' => 'صرف وتصنيع',
                'on_complete' => 'ينتقل للتسليمات',
                'next_step_key' => 'delivery',
                'stage_keys' => [CaseRecord::STAGE_MANUFACTURING],
                'required' => true,
                'auto_skip' => false,
                'skip_roles' => [],
                'handlers' => ['work_order' => 'operations', 'barcode_dispense' => 'warehouse'],
            ],
            [
                'key' => 'delivery',
                'label' => 'التسليمات',
                'sort' => 7,
                'owner_department' => 'delivery',
                'action_summary' => 'تسليم للمريض',
                'on_complete' => 'إغلاق الحالة',
                'next_step_key' => self::NEXT_COMPLETED,
                'stage_keys' => [CaseRecord::STAGE_READY_DELIVERY, CaseRecord::STAGE_DELIVERED],
                'required' => true,
                'auto_skip' => false,
                'skip_roles' => [],
                'handlers' => [],
            ],
        ],
    ];

    /** @return list<array{value: string, label: string, icon: string}> */
    public function departmentOptions(): array
    {
        return PathwayDepartments::options();
    }

    /** @return list<array{value: string, label: string}> */
    public function availableSkipRoles(): array
    {
        return [
            ['value' => 'admin', 'label' => 'الإدارة'],
            ['value' => 'doctor', 'label' => 'الطبيب'],
            ['value' => 'reception', 'label' => 'الاستقبال'],
            ['value' => 'spec', 'label' => 'التوصيف'],
            ['value' => 'adjustments', 'label' => 'المعدلات'],
            ['value' => 'operations', 'label' => 'التشغيل'],
            ['value' => 'cashier', 'label' => 'الخزنة'],
        ];
    }

    /** @return list<array{key: string, label: string}> */
    public function configurableHandlers(): array
    {
        return [
            ['key' => 'work_order', 'label' => 'من يصدر أمر الشغل (WO)'],
            ['key' => 'entity_approval', 'label' => 'من يستلم خطاب موافقة الجهة'],
            ['key' => 'collect_payment', 'label' => 'من يحصّل الدفع'],
        ];
    }

    public function isBusinessLocked(string $pathway, string $stageKey): bool
    {
        return in_array($stageKey, self::BUSINESS_LOCKED[$pathway] ?? [], true);
    }

    public function workOrderDepartment(bool $isMilitary): string
    {
        $pathway = $isMilitary ? PathwayStep::PATHWAY_MILITARY : PathwayStep::PATHWAY_CIVILIAN;

        foreach ($this->steps($pathway) as $step) {
            $handlers = $step['handlers'] ?? [];
            if (! empty($handlers['work_order'])) {
                return (string) $handlers['work_order'];
            }
        }

        return 'operations';
    }

    /**
     * @return list<array<string, mixed>>
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
            return $this->enrichStepsWithNext($pathway, $this->normalizeDefaults($pathway, self::DEFAULTS[$pathway], $activeOnly));
        }

        $rows = $stored->map(fn (PathwayStep $step) => $this->formatStep($pathway, $step))->values()->all();

        return $this->enrichStepsWithNext($pathway, $rows);
    }

    /** @return list<array{key: string, label: string}> */
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
                ?? $this->indexOfKey($steps, 'quote');

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

    /** @return ?array<string, mixed> */
    public function policyForStage(string $pathway, string $stageKey): ?array
    {
        foreach ($this->steps($pathway) as $step) {
            if (in_array($stageKey, $step['stage_keys'], true)) {
                return [
                    'stage_key' => $stageKey,
                    'label' => $step['label'],
                    'sort' => $step['sort'],
                    'required' => $step['required'],
                    'auto_skip' => $step['auto_skip'],
                    'skip_roles' => $step['skip_roles'],
                    'locked' => $step['locked'],
                    'description' => $step['action_summary'],
                ];
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     */
    public function saveSteps(string $pathway, array $steps): void
    {
        $this->assertPathway($pathway);

        DB::transaction(function () use ($pathway, $steps) {
            PathwayStep::query()->where('pathway', $pathway)->delete();

            $enriched = $this->enrichStepsWithNext($pathway, $steps);

            foreach ($enriched as $row) {
                $primaryStage = ($row['stage_keys'] ?? [])[0] ?? $row['key'];
                $locked = $this->isBusinessLocked($pathway, $primaryStage);

                PathwayStep::create([
                    'pathway' => $pathway,
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'sort' => (int) $row['sort'],
                    'stage_keys' => array_values($row['stage_keys'] ?? []),
                    'active' => (bool) ($row['active'] ?? true),
                    'description' => $row['description'] ?? null,
                    'owner_department' => $row['owner_department'] ?? null,
                    'action_summary' => $row['action_summary'] ?? null,
                    'on_complete' => $row['on_complete'] ?? null,
                    'next_step_key' => $row['next_step_key'] ?? null,
                    'required' => $locked ? true : (bool) ($row['required'] ?? true),
                    'auto_skip' => $locked ? false : (bool) ($row['auto_skip'] ?? false),
                    'skip_roles' => $locked ? [] : array_values($row['skip_roles'] ?? []),
                    'handlers' => $row['handlers'] ?? [],
                ]);
            }
        });
    }

    public function resetToDefaults(string $pathway): void
    {
        $this->assertPathway($pathway);
        $this->saveSteps($pathway, $this->normalizeDefaults($pathway, self::DEFAULTS[$pathway], false));
    }

    /** @return array<string, mixed> */
    public function designerPayload(): array
    {
        return [
            'civilian' => $this->steps(PathwayStep::PATHWAY_CIVILIAN),
            'military' => $this->steps(PathwayStep::PATHWAY_MILITARY),
            'departments' => $this->departmentOptions(),
            'skip_roles' => $this->availableSkipRoles(),
            'handlers' => $this->configurableHandlers(),
        ];
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function allForAdmin(): array
    {
        $payload = $this->designerPayload();

        return array_merge($payload, [
            'pathway_civilian_steps' => $payload['civilian'],
            'pathway_military_steps' => $payload['military'],
            'stage_key_options' => array_map(
                fn (CaseStage $stage) => ['value' => $stage->value, 'label' => $stage->label()],
                CaseStage::cases(),
            ),
        ]);
    }

    private function assertPathway(string $pathway): void
    {
        if (! isset(self::DEFAULTS[$pathway])) {
            throw new \InvalidArgumentException("مسار غير معروف: {$pathway}");
        }
    }

    /** @return array<string, mixed> */
    private function formatStep(string $pathway, PathwayStep $step): array
    {
        $default = $this->defaultRow($pathway, $step->key);
        $primaryStage = ($step->stage_keys ?? [])[0] ?? $step->key;
        $locked = $this->isBusinessLocked($pathway, $primaryStage);

        return [
            'key' => $step->key,
            'label' => $step->label ?: ($default['label'] ?? $step->key),
            'sort' => (int) $step->sort,
            'stage_keys' => array_values($step->stage_keys ?? []),
            'active' => (bool) $step->active,
            'description' => $step->description,
            'owner_department' => $step->owner_department ?: ($default['owner_department'] ?? 'reception'),
            'action_summary' => $step->action_summary ?: ($default['action_summary'] ?? ''),
            'on_complete' => $step->on_complete ?: ($default['on_complete'] ?? ''),
            'next_step_key' => $step->next_step_key ?: ($default['next_step_key'] ?? null),
            'required' => $locked ? true : (bool) ($step->required ?? $default['required'] ?? true),
            'auto_skip' => $locked ? false : (bool) ($step->auto_skip ?? $default['auto_skip'] ?? false),
            'skip_roles' => $locked ? [] : array_values($step->skip_roles ?? $default['skip_roles'] ?? []),
            'handlers' => array_merge($default['handlers'] ?? [], $step->handlers ?? []),
            'locked' => $locked,
            'lock_reason' => $locked ? $this->lockReason($primaryStage) : null,
            'can_skip' => ! $locked,
        ];
    }

    /** @return array<string, mixed> */
    private function defaultRow(string $pathway, string $key): array
    {
        foreach (self::DEFAULTS[$pathway] ?? [] as $row) {
            if (($row['key'] ?? '') === $key) {
                return $row;
            }
        }

        return [];
    }

    private function lockReason(string $stageKey): string
    {
        return self::LOCK_REASONS[$stageKey]
            ?? 'خطوة أساسية في المسار — يمكنك تعديل «ماذا يفعل» و«من ينفّذ» لكن لا يمكن تخطيها.';
    }

    /**
     * @param  list<array<string, mixed>>  $defaults
     * @return list<array<string, mixed>>
     */
    private function normalizeDefaults(string $pathway, array $defaults, bool $activeOnly): array
    {
        $rows = array_map(function (array $row) use ($pathway) {
            $primaryStage = ($row['stage_keys'] ?? [])[0] ?? $row['key'];
            $locked = $this->isBusinessLocked($pathway, $primaryStage);

            return [
                'key' => $row['key'],
                'label' => $row['label'],
                'sort' => (int) $row['sort'],
                'stage_keys' => array_values($row['stage_keys']),
                'active' => true,
                'description' => $row['description'] ?? null,
                'owner_department' => $row['owner_department'] ?? null,
                'action_summary' => $row['action_summary'] ?? null,
                'on_complete' => $row['on_complete'] ?? null,
                'next_step_key' => $row['next_step_key'] ?? null,
                'required' => $locked ? true : (bool) ($row['required'] ?? true),
                'auto_skip' => $locked ? false : (bool) ($row['auto_skip'] ?? false),
                'skip_roles' => array_values($row['skip_roles'] ?? []),
                'handlers' => $row['handlers'] ?? [],
                'locked' => $locked,
                'lock_reason' => $locked ? $this->lockReason($primaryStage) : null,
                'can_skip' => ! $locked,
            ];
        }, $defaults);

        if ($activeOnly) {
            return array_values(array_filter($rows, fn (array $row) => $row['active']));
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     * @return list<array<string, mixed>>
     */
    private function enrichStepsWithNext(string $pathway, array $steps): array
    {
        if ($steps === []) {
            return [];
        }

        $knownKeys = [];
        foreach ($steps as $step) {
            $knownKeys[$step['key']] = true;
        }

        return array_map(function (array $step, int $index) use ($pathway, $steps, $knownKeys) {
            $default = $this->defaultRow($pathway, $step['key']);
            $nextKey = $step['next_step_key'] ?? $default['next_step_key'] ?? null;

            if (! $nextKey) {
                $nextKey = $steps[$index + 1]['key'] ?? self::NEXT_COMPLETED;
            }

            if ($nextKey !== self::NEXT_COMPLETED && ! isset($knownKeys[$nextKey])) {
                $nextKey = $steps[$index + 1]['key'] ?? self::NEXT_COMPLETED;
            }

            $nextLabel = $this->labelForNextKey($steps, $nextKey);

            $step['next_step_key'] = $nextKey;
            $step['next_step_label'] = $nextLabel;
            $step['on_complete'] = $this->onCompleteText($nextLabel, $nextKey);

            return $step;
        }, $steps, array_keys($steps));
    }

    /** @param  list<array<string, mixed>>  $steps */
    private function labelForNextKey(array $steps, string $nextKey): string
    {
        if ($nextKey === self::NEXT_COMPLETED) {
            return 'إغلاق المسار';
        }

        foreach ($steps as $step) {
            if (($step['key'] ?? '') === $nextKey) {
                return (string) ($step['label'] ?? $nextKey);
            }
        }

        return PathwayDepartments::label($nextKey);
    }

    private function onCompleteText(string $nextLabel, string $nextKey): string
    {
        if ($nextKey === self::NEXT_COMPLETED) {
            return 'الحالة مكتملة';
        }

        return 'ينتقل إلى '.$nextLabel;
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
