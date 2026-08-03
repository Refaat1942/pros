<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Models\PathwayStep;
use App\Models\User;

/**
 * سياسات تخطي المراحل — تُقرأ من مصمم المسار الموحد (PathwayConfigService).
 */
class WorkflowPolicyService
{
    public function __construct(private readonly PathwayConfigService $pathway) {}

    /** @return list<array{value: string, label: string}> */
    public function availableSkipRoles(): array
    {
        return $this->pathway->availableSkipRoles();
    }

    public function pathwayForCase(CaseRecord $case): string
    {
        return $case->isMilitary()
            ? PathwayStep::PATHWAY_MILITARY
            : PathwayStep::PATHWAY_CIVILIAN;
    }

    public function isBusinessLocked(string $pathway, string $stageKey): bool
    {
        return $this->pathway->isBusinessLocked($pathway, $stageKey);
    }

    /** @return list<array<string, mixed>> */
    public function policies(string $pathway): array
    {
        return array_values(array_filter(
            array_map(function (array $step) {
                $stageKey = ($step['stage_keys'] ?? [])[0] ?? null;
                if (! $stageKey) {
                    return null;
                }

                return [
                    'stage_key' => $stageKey,
                    'label' => $step['label'],
                    'sort' => $step['sort'],
                    'required' => $step['required'],
                    'auto_skip' => $step['auto_skip'],
                    'skip_roles' => $step['skip_roles'],
                    'locked' => false,
                    'description' => $step['action_summary'],
                ];
            }, $this->pathway->steps($pathway)),
            fn ($row) => $row !== null,
        ));
    }

    public function shouldAutoSkip(CaseRecord $case): bool
    {
        $step = $this->pathway->currentStepForCase($case);

        return $step !== null
            && ! ($step['required'] ?? true)
            && ($step['auto_skip'] ?? false);
    }

    public function canManualSkip(CaseRecord $case, string $stageKey, ?User $user = null): bool
    {
        if (! $this->isAtSkippableStage($case, $stageKey)) {
            return false;
        }

        $step = $this->pathway->currentStepForCase($case);

        if ($step === null || ($step['required'] ?? true)) {
            return false;
        }

        return $this->roleMaySkipStep($step, $user, $stageKey);
    }

    public function canSkipStageForPathway(string $pathway, string $stageKey, ?User $user = null): bool
    {
        foreach ($this->pathway->steps($pathway) as $step) {
            if (! in_array($stageKey, $step['stage_keys'] ?? [], true)) {
                continue;
            }

            if ($step['required'] ?? true) {
                return false;
            }

            return $this->roleMaySkipStep($step, $user, $stageKey);
        }

        return false;
    }

    /** @return list<string> */
    public function skippableStageKeys(string $pathway): array
    {
        return array_values(array_filter(
            array_map(function (array $step) {
                $stageKey = ($step['stage_keys'] ?? [])[0] ?? null;

                return ($step['required'] ?? true) ? null : $stageKey;
            }, $this->pathway->steps($pathway)),
            fn ($stageKey) => is_string($stageKey) && $stageKey !== '',
        ));
    }

    /** @param  list<array<string, mixed>>  $policies */
    public function savePolicies(string $pathway, array $policies): void
    {
        $steps = $this->pathway->steps($pathway);

        foreach ($steps as &$step) {
            $stageKey = ($step['stage_keys'] ?? [])[0] ?? null;
            foreach ($policies as $policy) {
                if (($policy['stage_key'] ?? '') === $stageKey) {
                    $step['required'] = (bool) ($policy['required'] ?? true);
                    $step['auto_skip'] = (bool) ($policy['auto_skip'] ?? false);
                    $step['skip_roles'] = array_values($policy['skip_roles'] ?? []);
                }
            }
        }
        unset($step);

        $this->pathway->saveSteps($pathway, $steps);
    }

    public function resetToDefaults(string $pathway): void
    {
        $this->pathway->resetToDefaults($pathway);
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function allForAdmin(): array
    {
        return [
            PathwayStep::PATHWAY_CIVILIAN => $this->policies(PathwayStep::PATHWAY_CIVILIAN),
            PathwayStep::PATHWAY_MILITARY => $this->policies(PathwayStep::PATHWAY_MILITARY),
            'skip_role_options' => $this->availableSkipRoles(),
        ];
    }

    private function isAtSkippableStage(CaseRecord $case, string $stageKey): bool
    {
        if ($case->stage_key === $stageKey) {
            return true;
        }

        return $stageKey === CaseRecord::STAGE_EXAM
            && $case->stage_key === CaseRecord::STAGE_RECEPTION;
    }

    private function roleMaySkipStep(array $step, ?User $user, string $stageKey): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $roleSlug = $user->role?->slug;
        $skipRoles = $step['skip_roles'] ?? [];

        if ($roleSlug && in_array($roleSlug, $skipRoles, true)) {
            return true;
        }

        if ($stageKey === CaseRecord::STAGE_EXAM && $user->hasPermission('skip-diagnosis')) {
            return true;
        }

        return false;
    }
}
