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
                    'locked' => $step['locked'],
                    'description' => $step['action_summary'],
                ];
            }, $this->pathway->steps($pathway)),
            fn ($row) => $row !== null,
        ));
    }

    public function shouldAutoSkip(CaseRecord $case): bool
    {
        $pathway = $this->pathwayForCase($case);
        $policy = $this->pathway->policyForStage($pathway, (string) $case->stage_key);

        return $policy !== null
            && ! $policy['required']
            && $policy['auto_skip'];
    }

    public function canManualSkip(CaseRecord $case, string $stageKey, ?User $user = null): bool
    {
        if (! $this->isAtSkippableStage($case, $stageKey)) {
            return false;
        }

        return $this->roleMaySkipStage($this->pathwayForCase($case), $stageKey, $user);
    }

    public function canSkipStageForPathway(string $pathway, string $stageKey, ?User $user = null): bool
    {
        $policy = $this->pathway->policyForStage($pathway, $stageKey);

        if ($policy === null || $policy['required'] || $policy['locked']) {
            return false;
        }

        return $this->roleMaySkipStage($pathway, $stageKey, $user);
    }

    /** @return list<string> */
    public function skippableStageKeys(string $pathway): array
    {
        return array_values(array_map(
            fn (array $p) => $p['stage_key'],
            array_filter($this->policies($pathway), fn (array $p) => ! $p['required'] && ! $p['locked']),
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

    private function roleMaySkipStage(string $pathway, string $stageKey, ?User $user): bool
    {
        $policy = $this->pathway->policyForStage($pathway, $stageKey);

        if ($policy === null || $policy['required'] || $policy['locked']) {
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
}
