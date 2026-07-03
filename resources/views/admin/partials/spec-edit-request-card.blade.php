@php
    $row = $row ?? [];
    $status = $row['status'] ?? 'pending';
@endphp
<article class="spec-edit-req-card panel" style="margin:0;padding:16px;"
    data-id="{{ $row['id'] ?? '' }}"
    data-status="{{ $status }}"
    data-search="{{ ($row['patient_name'] ?? '') . ' ' . ($row['case_no'] ?? '') . ' ' . ($row['order_ref'] ?? '') }}">
    <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:12px;margin-bottom:12px;">
        <div>
            <strong style="font-size:15px;">{{ $row['patient_name'] ?? '—' }}</strong>
            <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">
                {{ $row['case_no'] ?? '—' }} · {{ $row['order_ref'] ?? '—' }}
                · {{ $row['requested_at_label'] ?? '—' }}
            </div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">
                طلب بواسطة: {{ $row['requested_by'] ?? '—' }}
                · <span class="badge" style="font-size:11px;">{{ $row['source_label'] ?? 'توصيف فني' }}</span>
            </div>
        </div>
        <span class="badge {{ $row['status_badge_class'] ?? '' }}">{{ $row['status_label'] ?? $status }}</span>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
        <div>
            <div style="font-size:11px;font-weight:800;color:var(--text-muted);margin-bottom:6px;">الحالي</div>
            <ul style="margin:0;padding-right:18px;font-size:13px;">
                @foreach (($row['original_items'] ?? []) as $item)
                    <li>{{ $item['name'] ?? $item['stock_item_code'] }} × {{ $item['qty'] ?? 0 }}</li>
                @endforeach
            </ul>
        </div>
        <div>
            <div style="font-size:11px;font-weight:800;color:var(--primary);margin-bottom:6px;">المقترح</div>
            <ul style="margin:0;padding-right:18px;font-size:13px;">
                @foreach (($row['proposed_items'] ?? []) as $item)
                    <li>{{ $item['name'] ?? $item['stock_item_code'] }} × {{ $item['qty'] ?? 0 }}</li>
                @endforeach
            </ul>
        </div>
    </div>

    @if (!empty($row['proposed_tech_notes']))
        <p style="font-size:12px;margin:0 0 12px;color:var(--text-muted);"><strong>ملاحظات مقترحة:</strong> {{ $row['proposed_tech_notes'] }}</p>
    @endif

    @if ($status === 'rejected' && !empty($row['rejection_notes']))
        <p style="font-size:12px;margin:0;color:#b91c1c;background:#fef2f2;padding:8px 10px;border-radius:8px;">
            <strong>ملاحظة الرفض:</strong> {{ $row['rejection_notes'] }}
        </p>
    @endif

    @if ($status === 'pending')
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;align-items:flex-end;">
            <a href="{{ route('spec.spec.print', ['spec' => $row['tech_order_spec_id'] ?? 0]) }}?embed=1"
               target="_blank"
               rel="noopener"
               class="btn-action"
               style="text-decoration:none;">
                🖨️ طباعة التوصيف
            </a>
            <button type="button" class="btn-action success spec-edit-approve-btn" data-id="{{ $row['id'] }}" data-source="{{ $row['source'] ?? 'spec' }}">✅ موافقة</button>
            <input type="text" class="spec-edit-reject-notes" data-id="{{ $row['id'] }}" placeholder="ملاحظة (اختياري)" style="padding:8px;border:1px solid var(--border);border-radius:8px;font-size:12px;flex:1;min-width:200px;">
            <button type="button" class="btn-action danger spec-edit-reject-btn" data-id="{{ $row['id'] }}">❌ رفض</button>
        </div>
    @endif
</article>
