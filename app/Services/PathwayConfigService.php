<?php

namespace App\Services;

use App\Enums\CaseStage;
use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\Quote;
use App\Models\PathwayStep;
use App\Support\PathwayDefaultSteps;
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
        PathwayStep::PATHWAY_ENTITY => [
            CaseRecord::STAGE_RECEPTION,
            CaseRecord::STAGE_TECHNICAL,
            CaseRecord::STAGE_COST_CALC,
            CaseRecord::STAGE_QUOTE,
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

    /** @return array<string, list<array<string, mixed>>> */
    private static function defaults(): array
    {
        return PathwayDefaultSteps::all();
    }

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
            return $this->enrichStepsWithNext($pathway, $this->normalizeDefaults($pathway, self::defaults()[$pathway], $activeOnly));
        }

        $rows = $stored->map(fn (PathwayStep $step) => $this->formatStep($pathway, $step))->values()->all();

        return $this->enrichStepsWithNext($pathway, $rows);
    }

    public function resolvePathway(?Patient $patient, ?CaseRecord $case = null): string
    {
        if ($case) {
            if ($case->patient_type === Patient::TYPE_MILITARY || $case->path === CaseRecord::PATH_MILITARY) {
                return PathwayStep::PATHWAY_MILITARY;
            }
            if ($case->contract_company_id) {
                return PathwayStep::PATHWAY_ENTITY;
            }

            return PathwayStep::PATHWAY_CIVILIAN;
        }

        if ($patient?->isMilitary()) {
            return PathwayStep::PATHWAY_MILITARY;
        }
        if ($patient?->contract_company_id) {
            return PathwayStep::PATHWAY_ENTITY;
        }

        return PathwayStep::PATHWAY_CIVILIAN;
    }

    public function pathwayLabel(string $pathway): string
    {
        return match ($pathway) {
            PathwayStep::PATHWAY_MILITARY => 'عسكري',
            PathwayStep::PATHWAY_ENTITY => 'جهات',
            default => 'مدني',
        };
    }

    /** @return list<array{key: string, label: string}> */
    public function displayStepsForPathway(string $pathway): array
    {
        return array_map(
            fn (array $step) => ['key' => $step['key'], 'label' => $step['label']],
            $this->steps($pathway, activeOnly: true),
        );
    }

    /** @return list<array{key: string, label: string}> */
    public function displaySteps(bool $isMilitary): array
    {
        return $this->displayStepsForPathway(
            $isMilitary ? PathwayStep::PATHWAY_MILITARY : PathwayStep::PATHWAY_CIVILIAN
        );
    }

    public function resolveCurrentIndexForPathway(?CaseRecord $case, string $pathway, bool $noCase = false): int
    {
        if ($noCase || ! $case) {
            return 0;
        }

        $steps = $this->steps($pathway, activeOnly: true);
        if ($steps === []) {
            return 0;
        }

        return $this->resolveIndexForCase($case, $steps, $pathway);
    }

    public function resolveCurrentIndex(?CaseRecord $case, bool $isMilitary, bool $noCase = false, bool $awaitingEntityApproval = false): int
    {
        if ($noCase || ! $case) {
            return 0;
        }

        $pathway = $isMilitary
            ? PathwayStep::PATHWAY_MILITARY
            : ($awaitingEntityApproval ? PathwayStep::PATHWAY_ENTITY : PathwayStep::PATHWAY_CIVILIAN);

        return $this->resolveCurrentIndexForPathway($case, $pathway, false);
    }

    /** تسمية الخطوة الحالية حسب المسار المُخصَّص — للعرض في التتبع والإشعارات. */
    public function currentStepLabelForCase(CaseRecord $case): string
    {
        $case->loadMissing('patient');
        $pathway = $this->resolvePathway($case->patient, $case);
        $steps = $this->steps($pathway, activeOnly: true);

        if ($steps === []) {
            return CaseStage::labelFor($case->stage_key);
        }

        $index = $this->resolveIndexForCase($case, $steps, $pathway);

        return (string) ($steps[$index]['label'] ?? CaseStage::labelFor($case->stage_key));
    }

    /** تسمية خطوة المسار لمرحلة workflow محددة. */
    public function stepLabelForStage(CaseRecord $case, string $stageKey): string
    {
        $case->loadMissing(['patient', 'bom']);
        $pathway = $this->resolvePathway($case->patient, $case);
        $steps = $this->steps($pathway, activeOnly: true);

        if ($stageKey === CaseRecord::STAGE_READY_DELIVERY) {
            $delivery = $this->indexOfKey($steps, 'delivery');
            if ($delivery !== null) {
                return (string) $steps[$delivery]['label'];
            }
        }

        if ($stageKey === CaseRecord::STAGE_MANUFACTURING) {
            $bomStage = $case->bom?->stage;
            if ($bomStage === Bom::STAGE_FINISHED) {
                $delivery = $this->indexOfKey($steps, 'delivery');
                if ($delivery !== null) {
                    return (string) $steps[$delivery]['label'];
                }
            }
            if ($bomStage === Bom::STAGE_WIP) {
                $workshop = $this->indexOfKey($steps, 'workshop');
                if ($workshop !== null) {
                    return (string) $steps[$workshop]['label'];
                }
            }
            $warehouse = $this->indexOfKey($steps, 'warehouse');
            if ($warehouse !== null) {
                return (string) $steps[$warehouse]['label'];
            }
        }

        if ($stageKey === CaseRecord::STAGE_CASHIER) {
            $cashier = $this->indexOfKey($steps, 'cashier');
            if ($cashier !== null) {
                return (string) $steps[$cashier]['label'];
            }
        }

        if ($stageKey === CaseRecord::STAGE_OPERATIONS && $pathway === PathwayStep::PATHWAY_ENTITY) {
            if ($this->caseEntityQuotePendingAtOperations($case)) {
                $quote = $this->indexOfKey($steps, 'quote');
                if ($quote !== null) {
                    return (string) $steps[$quote]['label'];
                }
            }
            if ($this->caseAwaitingEntityApproval($case)) {
                $entityReturn = $this->indexOfKey($steps, 'entity_return');
                if ($entityReturn !== null) {
                    return (string) $steps[$entityReturn]['label'];
                }
            }
            $workOrder = $this->indexOfKey($steps, 'operations_wo');
            if ($workOrder !== null) {
                return (string) $steps[$workOrder]['label'];
            }
        }

        foreach ($steps as $step) {
            if (! in_array($stageKey, $step['stage_keys'] ?? [], true)) {
                continue;
            }

            if ($stageKey === CaseRecord::STAGE_READY_DELIVERY && ($step['key'] ?? '') === 'operations_release') {
                continue;
            }

            return (string) ($step['label'] ?? CaseStage::labelFor($stageKey));
        }

        return CaseStage::labelFor($stageKey);
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
        $this->saveSteps($pathway, $this->normalizeDefaults($pathway, self::defaults()[$pathway], false));
    }

    /** @return array<string, mixed> */
    public function designerPayload(): array
    {
        return [
            'civilian' => $this->steps(PathwayStep::PATHWAY_CIVILIAN),
            'military' => $this->steps(PathwayStep::PATHWAY_MILITARY),
            'entity' => $this->steps(PathwayStep::PATHWAY_ENTITY),
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
            'pathway_entity_steps' => $payload['entity'],
            'stage_key_options' => array_map(
                fn (CaseStage $stage) => ['value' => $stage->value, 'label' => $stage->label()],
                CaseStage::cases(),
            ),
        ]);
    }

    private function assertPathway(string $pathway): void
    {
        if (! isset(self::defaults()[$pathway])) {
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
        foreach (self::defaults()[$pathway] ?? [] as $row) {
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

    /** @param  list<array<string, mixed>>  $steps */
    private function resolveIndexForCase(CaseRecord $case, array $steps, string $pathway): int
    {
        $case->loadMissing(['bom', 'quotes']);

        if ($pathway === PathwayStep::PATHWAY_ENTITY && $this->caseAwaitingEntityApproval($case)) {
            $entityReturn = $this->indexOfKey($steps, 'entity_return');
            if ($entityReturn !== null) {
                return $entityReturn;
            }
        }

        $stageKey = $case->stage_key;
        $bomStage = $case->bom?->stage;

        if ($stageKey === CaseRecord::STAGE_DELIVERED) {
            $delivery = $this->indexOfKey($steps, 'delivery');

            return $delivery ?? count($steps) - 1;
        }

        if ($stageKey === CaseRecord::STAGE_CASHIER) {
            $cashier = $this->indexOfKey($steps, 'cashier');
            if ($cashier !== null) {
                return $cashier;
            }
        }

        if ($stageKey === CaseRecord::STAGE_READY_DELIVERY) {
            $delivery = $this->indexOfKey($steps, 'delivery');
            if ($delivery !== null) {
                return $delivery;
            }
            $release = $this->indexOfKey($steps, 'operations_release');
            if ($release !== null) {
                return $release;
            }
        }

        if ($stageKey === CaseRecord::STAGE_MANUFACTURING && $bomStage) {
            if ($bomStage === Bom::STAGE_FINISHED) {
                $delivery = $this->indexOfKey($steps, 'delivery');
                if ($delivery !== null) {
                    return $delivery;
                }
                $release = $this->indexOfKey($steps, 'operations_release');
                if ($release !== null) {
                    return $release;
                }
            }
            if ($bomStage === Bom::STAGE_WIP) {
                $workshop = $this->indexOfKey($steps, 'workshop');
                if ($workshop !== null) {
                    return $workshop;
                }
            }
            $warehouse = $this->indexOfKey($steps, 'warehouse');
            if ($warehouse !== null) {
                return $warehouse;
            }
        }

        if ($stageKey === CaseRecord::STAGE_QUOTE) {
            $quote = $this->indexOfKey($steps, 'quote');
            if ($quote !== null) {
                return $quote;
            }
        }

        if ($stageKey === CaseRecord::STAGE_OPERATIONS) {
            if ($pathway === PathwayStep::PATHWAY_ENTITY) {
                if ($this->caseEntityQuotePendingAtOperations($case)) {
                    $quote = $this->indexOfKey($steps, 'quote');
                    if ($quote !== null) {
                        return $quote;
                    }
                }
                if ($this->caseAwaitingEntityApproval($case)) {
                    $entityReturn = $this->indexOfKey($steps, 'entity_return');
                    if ($entityReturn !== null) {
                        return $entityReturn;
                    }
                }
            }
            $workOrder = $this->indexOfKey($steps, 'operations_wo');
            if ($workOrder !== null) {
                return $workOrder;
            }
        }

        $matched = 0;
        foreach ($steps as $index => $step) {
            if (in_array($stageKey, $step['stage_keys'] ?? [], true)) {
                $matched = $index;
            }
        }

        return $matched;
    }

    /** مفتاح خطوة مسار الجهات عند مرحلة التشغيل (عرض سعر / خطاب / أمر شغل). */
    public function entityOperationsStepKey(CaseRecord $case): string
    {
        if ($this->caseEntityQuotePendingAtOperations($case)) {
            return 'quote';
        }

        if ($this->caseAwaitingEntityApproval($case)) {
            return 'entity_return';
        }

        return 'operations_wo';
    }

    private function caseAwaitingEntityApproval(CaseRecord $case): bool
    {
        if ($case->patient_type === Patient::TYPE_MILITARY
            || $case->path === CaseRecord::PATH_MILITARY
            || ! $case->contract_company_id) {
            return false;
        }

        if (! $case->relationLoaded('quotes')) {
            $case->load('quotes:id,case_id,status,status_label');
        }

        return $case->quotes->contains(function (Quote $q) {
            if ($q->status !== Quote::STATUS_ISSUED) {
                return false;
            }

            $label = (string) ($q->status_label ?? '');

            return str_contains($label, 'موافقة') || str_contains($label, 'خطاب');
        });
    }

    /** عرض السعر أُنشئ ولا يزال بمكتب التشغيل — قبل إرساله للاستقبال. */
    private function caseEntityQuotePendingAtOperations(CaseRecord $case): bool
    {
        if ($case->stage_key !== CaseRecord::STAGE_OPERATIONS) {
            return false;
        }

        if (! $case->relationLoaded('quotes')) {
            $case->load('quotes:id,case_id,status,status_label');
        }

        $latest = $case->quotes->sortByDesc('id')->first();

        return $latest !== null && $latest->status === Quote::STATUS_PENDING;
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
