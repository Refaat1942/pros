<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Models\User;
use App\Models\WorkflowStagePolicy;
use Illuminate\Support\Facades\DB;

/**
 * سياسات مراحل مسار العمل — ما يمكن تخطيه وما هو مقفل بحكم المنطق التجاري.
 */
class WorkflowPolicyService
{
    /** مراحل لا يمكن للإدارة جعلها اختيارية — حماية المنطق التجاري. */
    private const BUSINESS_LOCKED = [
        WorkflowStagePolicy::PATHWAY_CIVILIAN => [
            CaseRecord::STAGE_RECEPTION,
            CaseRecord::STAGE_TECHNICAL,
            CaseRecord::STAGE_COST_CALC,
            CaseRecord::STAGE_QUOTE,
            CaseRecord::STAGE_OPERATIONS,
            CaseRecord::STAGE_MANUFACTURING,
            CaseRecord::STAGE_READY_DELIVERY,
            CaseRecord::STAGE_DELIVERED,
        ],
        WorkflowStagePolicy::PATHWAY_MILITARY => [
            CaseRecord::STAGE_RECEPTION,
            CaseRecord::STAGE_TECHNICAL,
            CaseRecord::STAGE_COST_CALC,
            CaseRecord::STAGE_OPERATIONS,
            CaseRecord::STAGE_MANUFACTURING,
            CaseRecord::STAGE_READY_DELIVERY,
            CaseRecord::STAGE_DELIVERED,
        ],
    ];

    /** @var array<string, list<array<string, mixed>>> */
    private const DEFAULTS = [
        WorkflowStagePolicy::PATHWAY_CIVILIAN => [
            ['stage_key' => CaseRecord::STAGE_RECEPTION, 'label' => 'الاستقبال', 'sort' => 1, 'required' => true, 'auto_skip' => false, 'skip_roles' => [], 'description' => 'تسجيل المريض وإنشاء الحالة'],
            ['stage_key' => CaseRecord::STAGE_EXAM, 'label' => 'الكشف الطبي', 'sort' => 2, 'required' => false, 'auto_skip' => false, 'skip_roles' => ['admin', 'doctor'], 'description' => 'يمكن تخطيه للتحويل المباشر للتوصيف'],
            ['stage_key' => CaseRecord::STAGE_TECHNICAL, 'label' => 'التوصيف الفني', 'sort' => 3, 'required' => true, 'auto_skip' => false, 'skip_roles' => [], 'description' => 'مواصفات فنية إلزامية'],
            ['stage_key' => CaseRecord::STAGE_ADJUSTMENTS, 'label' => 'المعدلات', 'sort' => 4, 'required' => false, 'auto_skip' => false, 'skip_roles' => ['admin', 'adjustments', 'operations'], 'description' => 'مراجعة البنود — يمكن تخطيها بعد التوصيف'],
            ['stage_key' => CaseRecord::STAGE_COST_CALC, 'label' => 'حساب التكاليف', 'sort' => 5, 'required' => true, 'auto_skip' => false, 'skip_roles' => [], 'description' => 'احتساب التكلفة إلزامي'],
            ['stage_key' => CaseRecord::STAGE_QUOTE, 'label' => 'عرض السعر', 'sort' => 6, 'required' => true, 'auto_skip' => false, 'skip_roles' => [], 'description' => 'إلزامي للمسار المدني'],
            ['stage_key' => CaseRecord::STAGE_OPERATIONS, 'label' => 'مكتب التشغيل', 'sort' => 7, 'required' => true, 'auto_skip' => false, 'skip_roles' => [], 'description' => 'إصدار أمر الشغل من التشغيل فقط'],
            ['stage_key' => CaseRecord::STAGE_CASHIER, 'label' => 'الخزنة', 'sort' => 8, 'required' => false, 'auto_skip' => false, 'skip_roles' => ['admin'], 'description' => 'للكاش فقط — يُدار من التشغيل'],
            ['stage_key' => CaseRecord::STAGE_MANUFACTURING, 'label' => 'المخزن والورشة', 'sort' => 9, 'required' => true, 'auto_skip' => false, 'skip_roles' => [], 'description' => 'صرف بالباركود والتصنيع'],
            ['stage_key' => CaseRecord::STAGE_READY_DELIVERY, 'label' => 'جاهز للتسليم', 'sort' => 10, 'required' => true, 'auto_skip' => false, 'skip_roles' => [], 'description' => 'بانتظار مسح QR'],
            ['stage_key' => CaseRecord::STAGE_DELIVERED, 'label' => 'تم التسليم', 'sort' => 11, 'required' => true, 'auto_skip' => false, 'skip_roles' => [], 'description' => 'إغلاق الحالة'],
        ],
        WorkflowStagePolicy::PATHWAY_MILITARY => [
            ['stage_key' => CaseRecord::STAGE_RECEPTION, 'label' => 'الاستقبال', 'sort' => 1, 'required' => true, 'auto_skip' => false, 'skip_roles' => [], 'description' => 'تسجيل عسكري'],
            ['stage_key' => CaseRecord::STAGE_EXAM, 'label' => 'الكشف الطبي', 'sort' => 2, 'required' => false, 'auto_skip' => false, 'skip_roles' => ['admin', 'doctor'], 'description' => 'اختياري'],
            ['stage_key' => CaseRecord::STAGE_TECHNICAL, 'label' => 'التوصيف الفني', 'sort' => 3, 'required' => true, 'auto_skip' => false, 'skip_roles' => [], 'description' => 'مواصفات فنية'],
            ['stage_key' => CaseRecord::STAGE_ADJUSTMENTS, 'label' => 'المعدلات', 'sort' => 4, 'required' => false, 'auto_skip' => true, 'skip_roles' => ['admin', 'adjustments'], 'description' => 'تخطي تلقائي شائع للمسار العسكري'],
            ['stage_key' => CaseRecord::STAGE_COST_CALC, 'label' => 'حساب التكاليف', 'sort' => 5, 'required' => true, 'auto_skip' => false, 'skip_roles' => [], 'description' => 'تسجيل صامت للتكلفة'],
            ['stage_key' => CaseRecord::STAGE_OPERATIONS, 'label' => 'مكتب التشغيل', 'sort' => 6, 'required' => true, 'auto_skip' => false, 'skip_roles' => [], 'description' => 'اعتماد تلقائي + أمر شغل'],
            ['stage_key' => CaseRecord::STAGE_MANUFACTURING, 'label' => 'المخزن والورشة', 'sort' => 7, 'required' => true, 'auto_skip' => false, 'skip_roles' => [], 'description' => 'صرف وتصنيع'],
            ['stage_key' => CaseRecord::STAGE_READY_DELIVERY, 'label' => 'جاهز للتسليم', 'sort' => 8, 'required' => true, 'auto_skip' => false, 'skip_roles' => [], 'description' => 'بانتظار التسليم'],
            ['stage_key' => CaseRecord::STAGE_DELIVERED, 'label' => 'تم التسليم', 'sort' => 9, 'required' => true, 'auto_skip' => false, 'skip_roles' => [], 'description' => 'إغلاق'],
        ],
    ];

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

    public function pathwayForCase(CaseRecord $case): string
    {
        return $case->isMilitary()
            ? WorkflowStagePolicy::PATHWAY_MILITARY
            : WorkflowStagePolicy::PATHWAY_CIVILIAN;
    }

    public function isBusinessLocked(string $pathway, string $stageKey): bool
    {
        return in_array($stageKey, self::BUSINESS_LOCKED[$pathway] ?? [], true);
    }

    /**
     * @return list<array{stage_key: string, label: string, sort: int, required: bool, auto_skip: bool, skip_roles: list<string>, locked: bool, description: ?string}>
     */
    public function policies(string $pathway): array
    {
        $this->assertPathway($pathway);

        $stored = WorkflowStagePolicy::query()
            ->where('pathway', $pathway)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        if ($stored->isEmpty()) {
            return $this->normalizeDefaults(self::DEFAULTS[$pathway]);
        }

        return $stored->map(fn (WorkflowStagePolicy $row) => $this->formatPolicy($pathway, $row))->values()->all();
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function allForAdmin(): array
    {
        return [
            WorkflowStagePolicy::PATHWAY_CIVILIAN => $this->policies(WorkflowStagePolicy::PATHWAY_CIVILIAN),
            WorkflowStagePolicy::PATHWAY_MILITARY => $this->policies(WorkflowStagePolicy::PATHWAY_MILITARY),
            'skip_role_options' => $this->availableSkipRoles(),
        ];
    }

    public function shouldAutoSkip(CaseRecord $case): bool
    {
        $pathway = $this->pathwayForCase($case);
        $stageKey = $case->stage_key;

        if (! $stageKey || $this->isBusinessLocked($pathway, $stageKey)) {
            return false;
        }

        $policy = $this->findPolicy($pathway, $stageKey);

        return $policy !== null
            && ! $policy['required']
            && $policy['auto_skip'];
    }

    public function canManualSkip(CaseRecord $case, string $stageKey, ?User $user = null): bool
    {
        $pathway = $this->pathwayForCase($case);

        if (! $this->isAtSkippableStage($case, $stageKey)) {
            return false;
        }

        return $this->roleMaySkipStage($pathway, $stageKey, $user);
    }

    public function canSkipStageForPathway(string $pathway, string $stageKey, ?User $user = null): bool
    {
        if ($this->isBusinessLocked($pathway, $stageKey)) {
            return false;
        }

        $policy = $this->findPolicy($pathway, $stageKey);

        if ($policy === null || $policy['required']) {
            return false;
        }

        return $this->roleMaySkipStage($pathway, $stageKey, $user);
    }

    private function isAtSkippableStage(CaseRecord $case, string $stageKey): bool
    {
        if ($case->stage_key === $stageKey) {
            return true;
        }

        return $stageKey === CaseRecord::STAGE_EXAM
            && $case->stage_key === CaseRecord::STAGE_RECEPTION;
    }

    private function roleMaySkipStage(string $pathway, string $stageKey, ?User $user): bool
    {
        if ($this->isBusinessLocked($pathway, $stageKey)) {
            return false;
        }

        $policy = $this->findPolicy($pathway, $stageKey);

        if ($policy === null || $policy['required']) {
            return false;
        }

        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $roleSlug = $user->role?->slug;

        if ($roleSlug && in_array($roleSlug, $policy['skip_roles'], true)) {
            return true;
        }

        if ($stageKey === CaseRecord::STAGE_EXAM && $user->hasPermission('skip-diagnosis')) {
            return true;
        }

        return false;
    }

    /**
     * @param  list<array{stage_key: string, required: bool, auto_skip: bool, skip_roles?: list<string>, sort: int, label: string, description?: ?string}>  $policies
     */
    public function savePolicies(string $pathway, array $policies): void
    {
        $this->assertPathway($pathway);

        DB::transaction(function () use ($pathway, $policies) {
            WorkflowStagePolicy::query()->where('pathway', $pathway)->delete();

            foreach ($policies as $row) {
                $stageKey = $row['stage_key'];
                $locked = $this->isBusinessLocked($pathway, $stageKey);

                WorkflowStagePolicy::create([
                    'pathway' => $pathway,
                    'stage_key' => $stageKey,
                    'required' => $locked ? true : (bool) ($row['required'] ?? true),
                    'auto_skip' => $locked ? false : (bool) ($row['auto_skip'] ?? false),
                    'skip_roles' => $locked ? [] : array_values($row['skip_roles'] ?? []),
                    'sort' => (int) $row['sort'],
                    'label' => $row['label'],
                    'description' => $row['description'] ?? null,
                ]);
            }
        });
    }

    public function resetToDefaults(string $pathway): void
    {
        $this->assertPathway($pathway);
        $this->savePolicies($pathway, $this->normalizeDefaults(self::DEFAULTS[$pathway]));
    }

    /** @return list<string> */
    public function skippableStageKeys(string $pathway): array
    {
        return array_values(array_map(
            fn (array $p) => $p['stage_key'],
            array_filter($this->policies($pathway), fn (array $p) => ! $p['required'] && ! $p['locked']),
        ));
    }

    /** @return ?array{stage_key: string, label: string, sort: int, required: bool, auto_skip: bool, skip_roles: list<string>, locked: bool, description: ?string} */
    private function findPolicy(string $pathway, string $stageKey): ?array
    {
        foreach ($this->policies($pathway) as $policy) {
            if ($policy['stage_key'] === $stageKey) {
                return $policy;
            }
        }

        return null;
    }

    private function assertPathway(string $pathway): void
    {
        if (! isset(self::DEFAULTS[$pathway])) {
            throw new \InvalidArgumentException("مسار غير معروف: {$pathway}");
        }
    }

    /** @return array{stage_key: string, label: string, sort: int, required: bool, auto_skip: bool, skip_roles: list<string>, locked: bool, description: ?string} */
    private function formatPolicy(string $pathway, WorkflowStagePolicy $row): array
    {
        $locked = $this->isBusinessLocked($pathway, $row->stage_key);

        return [
            'stage_key' => $row->stage_key,
            'label' => $row->label,
            'sort' => (int) $row->sort,
            'required' => $locked ? true : (bool) $row->required,
            'auto_skip' => $locked ? false : (bool) $row->auto_skip,
            'skip_roles' => $locked ? [] : array_values($row->skip_roles ?? []),
            'locked' => $locked,
            'description' => $row->description,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $defaults
     * @return list<array{stage_key: string, label: string, sort: int, required: bool, auto_skip: bool, skip_roles: list<string>, locked: bool, description: ?string}>
     */
    private function normalizeDefaults(array $defaults): array
    {
        $pathway = null;
        foreach (self::DEFAULTS as $key => $rows) {
            if ($rows === $defaults) {
                $pathway = $key;
                break;
            }
        }

        return array_map(function (array $row) use ($pathway) {
            $locked = $pathway !== null && $this->isBusinessLocked($pathway, $row['stage_key']);

            return [
                'stage_key' => $row['stage_key'],
                'label' => $row['label'],
                'sort' => (int) $row['sort'],
                'required' => $locked ? true : (bool) $row['required'],
                'auto_skip' => $locked ? false : (bool) $row['auto_skip'],
                'skip_roles' => array_values($row['skip_roles'] ?? []),
                'locked' => $locked,
                'description' => $row['description'] ?? null,
            ];
        }, $defaults);
    }
}
