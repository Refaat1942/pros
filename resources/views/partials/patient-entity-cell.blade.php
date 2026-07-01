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
@endphp
<div class="entity-cell">
    <span class="entity-cell__label">{{ $entity['label'] ?? '—' }}</span>
    @if (! empty($entity['badge']))
        <span class="{{ $entity['badge_class'] ?? 'entity-badge' }}">{{ $entity['badge'] }}</span>
    @endif
</div>
