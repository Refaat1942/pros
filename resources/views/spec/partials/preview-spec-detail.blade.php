@php
    $proposedItems = $editRequest ? ($editRequest->proposed_items ?? []) : [];
    $requestDate = $editRequest?->created_at ? \App\Support\ClinicTime::format($editRequest->created_at) : null;
    $isRejectedRequest = $rejected && ! $pending;
@endphp
<div class="spec-preview-detail-inner">
    <div class="spec-preview-detail-grid{{ $editRequest ? ' spec-preview-detail-grid--dual' : '' }}">
        <div>
            @if ($editRequest)
                <p class="spec-preview-detail-label">📋 التوصيف الأساسي</p>
            @endif
            <div class="bom-table-wrap">
                <table class="bom-table bom-table--compact">
                    <thead>
                        <tr>
                            <th>الكود</th>
                            <th>الصنف</th>
                            <th>الكمية</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($spec->items as $item)
                            <tr>
                                <td class="font-mono">{{ $item->stock_item_code }}</td>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->qty }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty-cell">لا توجد بنود</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($editRequest)
            <div>
                <p class="spec-preview-detail-label {{ $isRejectedRequest ? 'is-rejected' : 'is-pending' }}">
                    ✏️ بنود طلب التعديل
                    <span>({{ $isRejectedRequest ? 'مرفوض' : 'معلّق' }}{{ $requestDate ? ' — ' . $requestDate : '' }})</span>
                </p>
                <div class="bom-table-wrap {{ $isRejectedRequest ? 'spec-preview-detail-wrap--rejected' : 'spec-preview-detail-wrap--pending' }}">
                    <table class="bom-table bom-table--compact">
                        <thead>
                            <tr>
                                <th>الكود</th>
                                <th>الصنف</th>
                                <th>الكمية</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($proposedItems as $item)
                                <tr>
                                    <td class="font-mono">{{ $item['stock_item_code'] ?? '—' }}</td>
                                    <td>{{ $item['name'] ?? ($item['stock_item_code'] ?? '—') }}</td>
                                    <td><strong>{{ $item['qty'] ?? 0 }}</strong></td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="empty-cell">لا توجد بنود مقترحة</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($editRequest->proposed_tech_notes)
                    <p class="spec-preview-note {{ $isRejectedRequest ? 'is-rejected' : 'is-pending' }}">
                        <strong>ملاحظات طلب التعديل:</strong> {{ $editRequest->proposed_tech_notes }}
                    </p>
                @endif
                @if ($isRejectedRequest && $editRequest->rejection_notes)
                    <p class="spec-preview-note is-rejected">
                        <strong>ملاحظة الإدارة:</strong> {{ $editRequest->rejection_notes }}
                    </p>
                @endif
            </div>
        @endif
    </div>
    @if ($spec->written_items)
        <p class="spec-preview-note is-muted" style="white-space: pre-line;"><strong>بنود مكتوبة:</strong> {{ $spec->written_items }}</p>
    @endif
    @if ($spec->tech_notes)
        <p class="spec-preview-note is-muted"><strong>ملاحظات التوصيف:</strong> {{ $spec->tech_notes }}</p>
    @endif
    @if ($rejected && ! $pending)
        <p class="spec-preview-note is-rejected">
            تم رفض طلب التعديل من الإدارة — لا يمكن إرسال طلب جديد على هذا التوصيف.
        </p>
    @endif
    @if ($stage && $stage !== 'adjustments')
        <p class="spec-preview-note is-muted">المرحلة الحالية: {{ $stage }} — التعديل غير متاح بعد تجاوز المعدلات.</p>
    @endif
</div>
