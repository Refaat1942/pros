@php
    $row = $row ?? [];
    $status = $row['status'] ?? 'pending';
@endphp
<div class="spec-edit-detail-inner">
    <div class="spec-edit-detail-meta">
        <div><span>المريض</span><strong>{{ $row['patient_name'] ?? '—' }}</strong></div>
        <div><span>رقم الحالة</span><strong>{{ $row['case_no'] ?? '—' }}</strong></div>
        <div><span>مرجع الطلب</span><strong>{{ $row['order_ref'] ?? '—' }}</strong></div>
        <div><span>المصدر</span><strong>{{ $row['source_label'] ?? '—' }}</strong></div>
        <div><span>طلب بواسطة</span><strong>{{ $row['requested_by'] ?? '—' }}</strong></div>
        <div><span>التاريخ</span><strong>{{ $row['requested_at_label'] ?? '—' }}</strong></div>
    </div>

    <div class="spec-edit-detail-grid">
        <div>
            <p class="spec-edit-detail-label">📋 البنود الحالية</p>
            <table class="patient-track-table spec-edit-detail-table">
                <thead>
                    <tr>
                        <th>الكود</th>
                        <th>الصنف</th>
                        <th>الكمية</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (($row['original_items'] ?? []) as $item)
                        <tr>
                            <td style="font-family:monospace;font-size:12px;">{{ $item['stock_item_code'] ?? '—' }}</td>
                            <td>{{ $item['name'] ?? ($item['stock_item_code'] ?? '—') }}</td>
                            <td>{{ $item['qty'] ?? 0 }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" style="text-align:center;color:var(--text-muted);">لا توجد بنود</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>
            <p class="spec-edit-detail-label is-proposed">✏️ البنود المعدلة</p>
            <table class="patient-track-table spec-edit-detail-table is-proposed">
                <thead>
                    <tr>
                        <th>الكود</th>
                        <th>الصنف</th>
                        <th>الكمية</th>
                    </tr>
                </thead>
                <tbody>
                    @php $modifiedItems = $row['modified_items'] ?? []; @endphp
                    @if ($modifiedItems === [])
                        <tr><td colspan="3" style="text-align:center;color:var(--text-muted);">لا توجد بنود معدلة</td></tr>
                    @else
                        @include('admin.partials.spec-edit-modified-items-rows', ['items' => $modifiedItems])
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    @if (!empty($row['proposed_tech_notes']))
        <p class="spec-edit-detail-note is-muted">
            <strong>ملاحظات مقترحة:</strong> {{ $row['proposed_tech_notes'] }}
        </p>
    @endif

    @if ($status === 'rejected' && !empty($row['rejection_notes']))
        <p class="spec-edit-detail-note is-rejected">
            <strong>ملاحظة الرفض:</strong> {{ $row['rejection_notes'] }}
        </p>
    @endif

    @if ($status === 'approved' && !empty($row['reviewed_by']))
        <p class="spec-edit-detail-note is-muted">
            <strong>اعتُمد بواسطة:</strong> {{ $row['reviewed_by'] }}
            @if (!empty($row['reviewed_at_label']))
                — {{ $row['reviewed_at_label'] }}
            @endif
        </p>
    @endif
</div>
