@php
    $rows = collect($spec_edit_requests ?? []);
    $pending = (int) ($spec_edit_pending ?? 0);
    $reasons = $rejection_reasons ?? config('spec_edit.rejection_reasons', []);
@endphp
<div class="section-view" id="section-spec-edit-requests" data-server-rendered="1">
    <div id="analytics-spec-edit">@include('partials.dashboard-analytics-empty', [
        'hide_charts' => true,
        'stats' => [
            ['icon' => '✏️', 'label' => 'طلبات معلّقة', 'value' => (string) $pending, 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.12)'],
        ],
    ])</div>

    <div class="panel inventory-wrap">
        <div class="panel-header">
            <h3>✏️ طلبات تعديل التوصيف والمعدلات</h3>
            <span class="badge" id="specEditReqCount">{{ $rows->count() }} طلب</span>
        </div>

        <p style="padding:0 24px 12px;margin:0;color:var(--text-muted);font-size:13px;">
            طلبات تعديل من فني التوصيف (قبل التكاليف) أو من مكتب المعدلات (قبل تأكيد السعر) — راجع البنود ثم وافق أو ارفض.
        </p>

        <div class="data-toolbar" style="padding:0 24px 12px;flex-wrap:wrap;gap:8px;">
            <input type="search" id="specEditReqSearch" placeholder="🔍 بحث بالمريض أو رقم الحالة...">
            <select id="specEditReqStatus" style="padding:8px 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;">
                <option value="">كل الحالات</option>
                <option value="pending" selected>بانتظار الموافقة</option>
                <option value="approved">مُعتمد</option>
                <option value="rejected">مرفوض</option>
            </select>
            <button type="button" class="btn-action primary" id="specEditReqRefresh">🔄 تحديث</button>
            <span class="toolbar-count" id="specEditReqVisible">{{ $rows->count() }} طلب</span>
        </div>

        <div id="specEditReqList" class="spec-edit-req-list" style="padding:0 24px 24px;display:flex;flex-direction:column;gap:12px;">
            @forelse ($rows as $row)
                @include('admin.partials.spec-edit-request-card', ['row' => $row, 'reasons' => $reasons])
            @empty
                <p class="text-center text-muted py-10" id="specEditReqEmpty">لا توجد طلبات تعديل معلّقة.</p>
            @endforelse
        </div>
    </div>
</div>

<div class="catalog-modal-overlay" id="specEditCompareModal" hidden>
    <div class="catalog-modal" style="max-width:720px;">
        <div class="catalog-modal-header">
            <h4 id="specEditCompareTitle">مقارنة التعديل</h4>
            <button type="button" class="btn-action" id="specEditCompareClose">×</button>
        </div>
        <div class="catalog-modal-body" id="specEditCompareBody"></div>
    </div>
</div>

<script>
    window.__specEditRejectionReasons = @json($reasons);
</script>
