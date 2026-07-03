@php
    $rows = collect($spec_edit_requests ?? []);
    $stats = $spec_edit_stats ?? [];

    $exportRows = $rows->map(function ($row) {
        $orig = collect($row['original_items'] ?? [])
            ->map(fn ($i) => ($i['name'] ?? $i['stock_item_code'] ?? '—') . ' × ' . ($i['qty'] ?? 0))
            ->implode(' | ');
        $prop = $row['modified_summary']
            ?? collect($row['proposed_items'] ?? [])
                ->map(fn ($i) => ($i['name'] ?? $i['stock_item_code'] ?? '—') . ' × ' . ($i['qty'] ?? 0))
                ->implode(' | ');

        return [
            'patient'             => $row['patient_name'] ?? '—',
            'case_no'             => $row['case_no'] ?? '—',
            'order_ref'           => $row['order_ref'] ?? '—',
            'source_label'        => $row['source_label'] ?? '—',
            'requested_at_label'  => $row['requested_at_label'] ?? '—',
            'status_label'        => $row['status_label'] ?? ($row['status'] ?? '—'),
            'requested_by'        => $row['requested_by'] ?? '—',
            'original_summary'    => $orig ?: '—',
            'proposed_summary'    => $prop ?: '—',
            'proposed_tech_notes' => $row['proposed_tech_notes'] ?? '—',
            'search'              => strtolower(trim(implode(' ', [
                $row['patient_name'] ?? '',
                $row['case_no'] ?? '',
                $row['order_ref'] ?? '',
                $row['requested_by'] ?? '',
                $row['source_label'] ?? '',
                $row['status_label'] ?? '',
                $orig,
                $prop,
            ]))),
        ];
    })->values();
@endphp

@push('styles')
<style>
    .spec-edit-req-actions { display:flex; flex-wrap:wrap; gap:6px; justify-content:flex-end; }
    .spec-edit-detail-source { display:none !important; }
    .spec-edit-detail-meta {
        display:grid;
        grid-template-columns:repeat(auto-fit, minmax(140px, 1fr));
        gap:10px 16px;
        margin-bottom:18px;
        padding:14px 16px;
        background:#f8fafc;
        border-radius:12px;
        border:1px solid var(--border,#e2e8f0);
    }
    .spec-edit-detail-meta span {
        display:block;
        font-size:11px;
        font-weight:700;
        color:var(--text-muted);
        margin-bottom:2px;
    }
    .spec-edit-detail-meta strong { font-size:13px; color:var(--secondary,#1e293b); }
    .spec-edit-detail-grid {
        display:grid;
        grid-template-columns:1fr;
        gap:16px;
        margin-bottom:12px;
    }
    @media (min-width: 768px) { .spec-edit-detail-grid { grid-template-columns:1fr 1fr; } }
    .spec-edit-detail-label {
        margin:0 0 8px;
        font-size:12px;
        font-weight:800;
        color:var(--text-muted);
    }
    .spec-edit-detail-label.is-proposed { color:#6d28d9; }
    .spec-edit-detail-table.is-proposed thead { background:#f5f3ff; }
    .spec-edit-detail-note {
        margin:10px 0 0;
        padding:10px 12px;
        border-radius:10px;
        font-size:13px;
        line-height:1.6;
    }
    .spec-edit-detail-note.is-muted { background:#f1f5f9; color:#475569; }
    .spec-edit-detail-note.is-rejected { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }
    .spec-edit-item--removed td {
        background:#fef2f2;
        color:#991b1b;
    }
    .spec-edit-qty-was {
        font-size:11px;
        font-weight:600;
        color:var(--text-muted,#64748b);
        margin-right:4px;
    }
    .spec-edit-detail-footer {
        display:flex;
        flex-wrap:wrap;
        gap:8px;
        align-items:flex-end;
        padding:16px 20px;
        border-top:1px solid var(--border,#e2e8f0);
        background:#fafafa;
    }
    .spec-edit-detail-footer input {
        flex:1;
        min-width:200px;
        padding:8px 10px;
        border:1px solid var(--border,#e2e8f0);
        border-radius:8px;
        font-size:12px;
        font-family:inherit;
    }
</style>
@endpush

<div class="section-view" id="section-spec-edit-requests" data-server-rendered="1">
    <div id="analytics-spec-edit">
        @include('partials.dashboard-analytics-empty', [
            'hide_charts' => true,
            'stats' => $stats,
        ])
    </div>

    <div class="panel inventory-wrap">
        <div class="panel-header">
            <div>
                <h3>✏️ طلبات تعديل التوصيف والمعدلات</h3>
                <p style="margin:4px 0 0;font-size:12px;color:var(--text-muted);">
                    مراجعة طلبات تعديل البنود — الموافقة تُطبَّق على قائمة المواد مباشرة
                </p>
            </div>
            <span class="badge" id="specEditReqCount">{{ $rows->count() }} طلب</span>
        </div>

        <div class="data-toolbar spec-edit-requests-toolbar">
            <input type="search" id="specEditReqSearch" class="table-search-input"
                   placeholder="بحث بالمريض أو رقم الحالة..." autocomplete="off">
            <select id="specEditReqStatus" class="table-filter-select" aria-label="فلتر الحالة">
                <option value="">كل الحالات</option>
                <option value="pending" selected>بانتظار الموافقة</option>
                <option value="approved">مُعتمد</option>
                <option value="rejected">مرفوض</option>
            </select>
            <button type="button" class="btn-action primary" id="specEditReqRefresh">🔄 تحديث</button>
            <span class="toolbar-count" id="specEditReqVisible">{{ $rows->count() }} ظاهر</span>
            <div class="export-btns">
                <button type="button" class="btn-export excel" id="btnSpecEditReqExcel">📊 Excel</button>
                <button type="button" class="btn-export pdf" id="btnSpecEditReqPdf">📄 PDF</button>
            </div>
        </div>

        <div class="panel-body">
            <table data-paginate="10" class="patient-track-table" id="specEditReqTable">
                <thead>
                    <tr>
                        <th>المريض</th>
                        <th>رقم الحالة</th>
                        <th>مرجع الطلب</th>
                        <th>المصدر</th>
                        <th>تاريخ الطلب</th>
                        <th>الحالة</th>
                        <th class="col-actions">إجراء</th>
                    </tr>
                </thead>
                <tbody id="specEditReqTableBody">
                    @forelse ($rows as $row)
                        @include('admin.partials.spec-edit-request-table-row', compact('row'))
                    @empty
                        <tr class="pagination-empty-row">
                            <td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted);">لا توجد طلبات تعديل.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div id="specEditDetailSources">
        @foreach ($rows as $row)
            <div class="spec-edit-detail-source" id="spec-edit-detail-source-{{ $row['id'] }}" hidden>
                @include('admin.partials.spec-edit-request-detail', compact('row'))
            </div>
        @endforeach
    </div>
</div>

<div class="catalog-modal-overlay" id="specEditDetailModal" hidden>
    <div class="catalog-modal" style="max-width:920px;" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <h4 id="specEditDetailTitle">📋 تفاصيل طلب التعديل</h4>
            <button type="button" class="modal-close" id="specEditDetailClose" aria-label="إغلاق">&times;</button>
        </div>
        <div class="catalog-modal-body" id="specEditDetailBody"></div>
        <div class="spec-edit-detail-footer" id="specEditDetailFooter" style="display:none;">
            <a href="#" id="specEditDetailPrint" target="_blank" rel="noopener" class="btn-action" style="display:none;text-decoration:none;">🖨️ طباعة التوصيف</a>
            <input type="text" id="specEditDetailRejectNotes" placeholder="ملاحظة الرفض (اختياري)">
            <button type="button" class="btn-action danger" id="specEditDetailReject">❌ رفض</button>
            <button type="button" class="btn-action success" id="specEditDetailApprove">✅ موافقة</button>
        </div>
    </div>
</div>

<script>
    window.__SPEC_EDIT_REQ_EXPORT = @json($exportRows);
    window.__SPEC_EDIT_REQ_ROWS = @json($rows->values());
</script>
