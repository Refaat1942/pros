{{--
    عرض جهة المريض / الفوترة مع شارة النوع.
    @param Patient|CaseRecord|Appointment|array|null $subject
    @param array|null $entity — presentation array من PatientEntityPresenter
--}}
@php
    if (! isset($entity)) {
        if ($subject instanceof \App\Models\Patient
            || $subject instanceof \App\Models\CaseRecord
            || $subject instanceof \App\Models\Appointment) {
            $entity = $subject->entityPresentation();
        } elseif (is_array($subject) && isset($subject['entity'])) {
            $entity = $subject['entity'];
        } elseif (is_array($subject)) {
            $entity = \App\Support\PatientEntityPresenter::fromParts(
                $subject['patient_type'] ?? null,
                $subject['contract_company_id'] ?? null,
                $subject['company_name'] ?? null,
                $subject['sovereign_entity'] ?? null,
                $subject['is_contracted'] ?? null,
            );
        } else {
            $entity = ['label' => '—', 'kind' => '', 'badge' => '', 'badge_class' => ''];
        }
    }

    if (! empty($column)) {
        $entity = \App\Support\PatientEntityPresenter::forColumn($entity);
    }
@endphp
<div class="entity-cell">
    @php
        $isCash = ($entity['kind'] ?? '') === \App\Support\PatientEntityPresenter::KIND_CASH;
        $showLabel = ! $isCash || ! empty($column);
    @endphp
    @if ($showLabel)
        <span class="entity-cell__label">{{ $entity['label'] ?? '—' }}</span>
    @endif
    @if (! empty($entity['badge']) && ! ($isCash && ! empty($column)))
        <span class="{{ $entity['badge_class'] ?? 'entity-badge' }}">{{ $entity['badge'] }}</span>
    @endif
</div>
