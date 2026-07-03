@php
    $row = $row ?? [];
    $status = $row['status'] ?? 'pending';
    $searchHay = strtolower(trim(implode(' ', [
        $row['patient_name'] ?? '',
        $row['case_no'] ?? '',
        $row['order_ref'] ?? '',
        $row['requested_by'] ?? '',
        $row['source_label'] ?? '',
        $row['status_label'] ?? '',
    ])));
@endphp
<tr class="spec-edit-req-row patient-track-row"
    data-id="{{ $row['id'] ?? '' }}"
    data-status="{{ $status }}"
    data-search="{{ $searchHay }}"
    data-source="{{ $row['source'] ?? 'spec' }}"
    data-tech-spec-id="{{ $row['tech_order_spec_id'] ?? '' }}">
    <td>
        <strong>{{ $row['patient_name'] ?? '—' }}</strong>
        <div class="patient-track-cell-sub">{{ $row['requested_by'] ?? '—' }}</div>
    </td>
    <td>{{ $row['case_no'] ?? '—' }}</td>
    <td>{{ $row['order_ref'] ?? '—' }}</td>
    <td><span class="badge" style="font-size:11px;">{{ $row['source_label'] ?? '—' }}</span></td>
    <td>{{ $row['requested_at_label'] ?? '—' }}</td>
    <td><span class="badge {{ $row['status_badge_class'] ?? '' }}">{{ $row['status_label'] ?? $status }}</span></td>
    <td>
        <div class="spec-edit-req-actions">
            <button type="button" class="btn-action spec-edit-detail-btn" data-id="{{ $row['id'] }}">📋 التفاصيل</button>
            @if ($status === 'pending' && ($row['source'] ?? '') === 'spec' && !empty($row['tech_order_spec_id']))
                <a href="{{ route('spec.spec.print', ['spec' => $row['tech_order_spec_id']]) }}?embed=1"
                   target="_blank" rel="noopener" class="btn-action" title="طباعة التوصيف">🖨️</a>
            @endif
        </div>
    </td>
</tr>
