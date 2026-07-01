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


        <div class="data-toolbar spec-edit-requests-toolbar">
            <input type="text" id="specEditReqSearch" class="table-search-input"
                   placeholder="🔍 بحث بالمريض أو رقم الحالة..." autocomplete="off">
            <select id="specEditReqStatus" aria-label="فلتر الحالة">
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
