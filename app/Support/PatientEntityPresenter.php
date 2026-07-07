<?php

namespace App\Support;

use App\Models\CaseRecord;
use App\Models\ContractCompany;
use App\Models\Patient;

/**
 * عرض موحّد لجهة المريض / الفوترة — نقدي، متعاقد، غير متعاقد، عسكري.
 */
final class PatientEntityPresenter
{
    public const KIND_CASH = 'cash';

    public const KIND_CONTRACTED = 'contracted';

    public const KIND_NON_CONTRACTED = 'non_contracted';

    public const KIND_MILITARY = 'military';

    public const CASH_LABEL = 'نقدي';

    /** @return array{label: string, kind: string, badge: string, badge_class: string} */
    public static function forPatient(Patient $patient): array
    {
        if ($patient->isMilitary()) {
            return self::military($patient->sovereign_entity ?: Patient::MILITARY_SOVEREIGN_ENTITY);
        }

        if (! $patient->contract_company_id) {
            return self::cash();
        }

        $company = $patient->relationLoaded('contractCompany')
            ? $patient->contractCompany
            : ContractCompany::query()->find($patient->contract_company_id);

        $name = $patient->company_name ?? $company?->name ?? '—';

        if ($company && ! $company->is_contracted) {
            return self::nonContracted($name);
        }

        return self::contracted($name);
    }

    /** @return array{label: string, kind: string, badge: string, badge_class: string} */
    public static function forCase(CaseRecord $case): array
    {
        if ($case->isMilitary()) {
            return self::military($case->sovereign_entity ?: Patient::MILITARY_SOVEREIGN_ENTITY);
        }

        $company = $case->relationLoaded('contractCompany')
            ? $case->contractCompany
            : ($case->contract_company_id ? ContractCompany::query()->find($case->contract_company_id) : null);

        $name = $case->company_name ?: $company?->name;

        // نقدي حقيقي: لا جهة تعاقد ولا اسم جهة على الحالة.
        if (! $case->contract_company_id && ($name === null || $name === '')) {
            return self::cash();
        }

        if ($company && ! $company->is_contracted) {
            return self::nonContracted($name ?? '—');
        }

        return self::contracted($name ?? '—');
    }

    /** @return array{label: string, kind: string, badge: string, badge_class: string} */
    public static function fromParts(
        ?string $patientType,
        ?int $contractCompanyId,
        ?string $companyName,
        ?string $sovereignEntity = null,
        ?bool $isContracted = null,
    ): array {
        if ($patientType === Patient::TYPE_MILITARY) {
            return self::military($sovereignEntity ?: Patient::MILITARY_SOVEREIGN_ENTITY);
        }

        if (! $contractCompanyId) {
            return self::cash();
        }

        $name = $companyName ?? '—';

        if ($isContracted === false) {
            return self::nonContracted($name);
        }

        return self::contracted($name);
    }

    /** هل تُرحَّل مديونية لجهة تعاقد؟ */
    public static function postsContractDebt(CaseRecord $case): bool
    {
        if ($case->isMilitary() || ! $case->contract_company_id) {
            return false;
        }

        $company = $case->relationLoaded('contractCompany')
            ? $case->contractCompany
            : ContractCompany::query()->find($case->contract_company_id);

        return (bool) ($company?->is_contracted);
    }

    /** عرض عمود «الجهة» — نقدي بدون جهة تعاقد يظهر كـ — */
    public static function forColumn(array $entity): array
    {
        if (($entity['kind'] ?? '') === self::KIND_CASH) {
            return [
                'label'       => '—',
                'kind'        => self::KIND_CASH,
                'badge'       => '',
                'badge_class' => '',
            ];
        }

        return $entity;
    }

    /** @return array{label: string, kind: string, badge: string, badge_class: string} */
    private static function cash(): array
    {
        return [
            'label'       => self::CASH_LABEL,
            'kind'        => self::KIND_CASH,
            'badge'       => '💵 نقدي',
            'badge_class' => 'entity-badge entity-badge--cash',
        ];
    }

    /** @return array{label: string, kind: string, badge: string, badge_class: string} */
    private static function contracted(string $name): array
    {
        return [
            'label'       => $name,
            'kind'        => self::KIND_CONTRACTED,
            'badge'       => '📑 متعاقد',
            'badge_class' => 'entity-badge entity-badge--contracted',
        ];
    }

    /** @return array{label: string, kind: string, badge: string, badge_class: string} */
    private static function nonContracted(string $name): array
    {
        return [
            'label'       => $name,
            'kind'        => self::KIND_NON_CONTRACTED,
            'badge'       => '🏷️ غير متعاقد',
            'badge_class' => 'entity-badge entity-badge--non-contracted',
        ];
    }

    /** @return array{label: string, kind: string, badge: string, badge_class: string} */
    private static function military(string $entity): array
    {
        return [
            'label'       => $entity,
            'kind'        => self::KIND_MILITARY,
            'badge'       => '🪖 عسكري',
            'badge_class' => 'entity-badge entity-badge--military',
        ];
    }
}
